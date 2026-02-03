<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * Telnet Connection Handler
 * Provides reliable telnet connection for OLT device management
 *
 *********************************************************************************************************
 */

class TelnetConnection {
    private $socket = null;
    private $host;
    private $port;
    private $timeout;
    private $buffer = '';
    private $lastResponse = '';
    private $debug = false;
    private $debugLog = [];

    // Telnet protocol constants
    const IAC  = "\xFF"; // 255 - Interpret As Command
    const DONT = "\xFE"; // 254
    const DO   = "\xFD"; // 253
    const WONT = "\xFC"; // 252
    const WILL = "\xFB"; // 251
    const SB   = "\xFA"; // 250 - Sub-negotiation Begin
    const GA   = "\xF9"; // 249 - Go Ahead
    const EL   = "\xF8"; // 248 - Erase Line
    const EC   = "\xF7"; // 247 - Erase Character
    const AYT  = "\xF6"; // 246 - Are You There
    const AO   = "\xF5"; // 245 - Abort Output
    const IP   = "\xF4"; // 244 - Interrupt Process
    const BRK  = "\xF3"; // 243 - Break
    const DM   = "\xF2"; // 242 - Data Mark
    const NOP  = "\xF1"; // 241 - No Operation
    const SE   = "\xF0"; // 240 - Sub-negotiation End

    /**
     * Constructor
     *
     * @param string $host Host to connect to
     * @param int $port Port number (default 23)
     * @param int $timeout Connection timeout in seconds
     * @param bool $debug Enable debug logging
     */
    public function __construct($host, $port = 23, $timeout = 10, $debug = false) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->debug = $debug;

        $this->connect();
    }

    /**
     * Connect to telnet server
     *
     * @throws Exception on connection failure
     */
    private function connect() {
        $this->debug("Connecting to {$this->host}:{$this->port}");

        $errno = 0;
        $errstr = '';
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("Failed to connect to {$this->host}:{$this->port} - Error {$errno}: {$errstr}");
        }

        stream_set_timeout($this->socket, $this->timeout);
        stream_set_blocking($this->socket, true);

        $this->debug("Connected successfully");
    }

    /**
     * Write data to telnet
     *
     * @param string $data Data to write
     * @param bool $addNewline Add newline at end
     * @return int Bytes written
     */
    public function write($data, $addNewline = true) {
        if (!$this->socket) {
            throw new Exception("Not connected");
        }

        if ($addNewline) {
            $data .= "\r\n";
        }

        $this->debug("SEND: " . trim($data));

        $written = fwrite($this->socket, $data);
        fflush($this->socket);

        // Small delay to let device process
        usleep(100000); // 100ms

        return $written;
    }

    /**
     * Read until specific pattern(s) found
     *
     * @param string|array $patterns Pattern(s) to wait for
     * @param int $timeout Timeout in seconds (null for default)
     * @return string Data read before pattern
     * @throws Exception on timeout
     */
    public function waitFor($patterns, $timeout = null) {
        if (!$this->socket) {
            throw new Exception("Not connected");
        }

        if (!is_array($patterns)) {
            $patterns = [$patterns];
        }

        $timeout = $timeout ?? $this->timeout;
        $startTime = time();
        $buffer = '';

        $this->debug("Waiting for: " . implode(', ', $patterns));

        while (true) {
            // Check timeout
            if (time() - $startTime > $timeout) {
                $this->debug("Timeout! Buffer so far: " . substr($buffer, -500));
                throw new Exception("Timeout waiting for: " . implode(', ', $patterns));
            }

            // Read available data
            $char = fgetc($this->socket);

            if ($char === false) {
                // Check for stream timeout
                $info = stream_get_meta_data($this->socket);
                if ($info['timed_out']) {
                    continue; // Try again
                }
                usleep(10000); // 10ms delay
                continue;
            }

            // Handle telnet protocol
            if ($char === self::IAC) {
                $this->handleTelnetCommand();
                continue;
            }

            $buffer .= $char;

            // Check for patterns
            foreach ($patterns as $pattern) {
                if (strpos($buffer, $pattern) !== false) {
                    $this->lastResponse = $buffer;
                    $this->debug("RECV: " . $this->sanitizeOutput($buffer));
                    return $buffer;
                }
            }
        }
    }

    /**
     * Read data with timeout
     *
     * @param int $bytes Maximum bytes to read
     * @param int $timeout Timeout in seconds
     * @return string Data read
     */
    public function read($bytes = 4096, $timeout = null) {
        if (!$this->socket) {
            throw new Exception("Not connected");
        }

        $timeout = $timeout ?? $this->timeout;
        stream_set_timeout($this->socket, $timeout);

        $data = fread($this->socket, $bytes);

        // Handle telnet commands in data
        $data = $this->stripTelnetCommands($data);

        $this->lastResponse = $data;
        return $data;
    }

    /**
     * Read all available data
     *
     * @param int $timeout Timeout in seconds
     * @return string All available data
     */
    public function readAll($timeout = 2) {
        if (!$this->socket) {
            throw new Exception("Not connected");
        }

        $buffer = '';
        $startTime = time();

        stream_set_blocking($this->socket, false);

        while (time() - $startTime < $timeout) {
            $data = fread($this->socket, 4096);
            if ($data === false || $data === '') {
                usleep(50000); // 50ms
                continue;
            }
            $buffer .= $data;
        }

        stream_set_blocking($this->socket, true);

        $buffer = $this->stripTelnetCommands($buffer);
        $this->lastResponse = $buffer;

        return $buffer;
    }

    /**
     * Handle telnet protocol commands
     */
    private function handleTelnetCommand() {
        $cmd = fgetc($this->socket);

        if ($cmd === self::DO || $cmd === self::DONT ||
            $cmd === self::WILL || $cmd === self::WONT) {
            $option = fgetc($this->socket);

            // Refuse all options
            if ($cmd === self::DO) {
                fwrite($this->socket, self::IAC . self::WONT . $option);
            } elseif ($cmd === self::WILL) {
                fwrite($this->socket, self::IAC . self::DONT . $option);
            }
        } elseif ($cmd === self::SB) {
            // Skip sub-negotiation
            while (true) {
                $c = fgetc($this->socket);
                if ($c === self::IAC) {
                    $c2 = fgetc($this->socket);
                    if ($c2 === self::SE) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Strip telnet commands from data
     *
     * @param string $data Data to clean
     * @return string Cleaned data
     */
    private function stripTelnetCommands($data) {
        // Remove IAC sequences
        $data = preg_replace('/\xFF[\xFB-\xFE]./s', '', $data);
        $data = preg_replace('/\xFF\xFA.*?\xFF\xF0/s', '', $data);
        return $data;
    }

    /**
     * Execute command and wait for prompt
     *
     * @param string $command Command to execute
     * @param string|array $prompts Expected prompts
     * @param int $timeout Timeout in seconds
     * @return string Command output
     */
    public function execute($command, $prompts = ['#', '>'], $timeout = null) {
        $this->write($command);
        $response = $this->waitFor($prompts, $timeout);

        // Remove the command echo from response
        $response = str_replace($command, '', $response);

        return trim($response);
    }

    /**
     * Login to device
     *
     * @param string $username Username
     * @param string $password Password
     * @param string $userPrompt Username prompt
     * @param string $passPrompt Password prompt
     * @param string|array $successPrompt Expected prompt after login
     * @return bool True if login successful
     */
    public function login($username, $password, $userPrompt = 'Username:', $passPrompt = 'Password:', $successPrompt = ['#', '>']) {
        try {
            // Wait for username prompt
            $this->waitFor($userPrompt);
            $this->write($username);

            // Wait for password prompt
            $this->waitFor($passPrompt);
            $this->write($password);

            // Wait for success prompt
            $this->waitFor($successPrompt);

            $this->debug("Login successful");
            return true;

        } catch (Exception $e) {
            $this->debug("Login failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Close connection
     */
    public function close() {
        if ($this->socket) {
            $this->debug("Closing connection");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Check if connected
     *
     * @return bool True if connected
     */
    public function isConnected() {
        if (!$this->socket) {
            return false;
        }

        $info = stream_get_meta_data($this->socket);
        return !$info['eof'];
    }

    /**
     * Get last response
     *
     * @return string Last response
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }

    /**
     * Get debug log
     *
     * @return array Debug log entries
     */
    public function getDebugLog() {
        return $this->debugLog;
    }

    /**
     * Enable/disable debug mode
     *
     * @param bool $enabled Enable debug
     */
    public function setDebug($enabled) {
        $this->debug = $enabled;
    }

    /**
     * Debug logging
     *
     * @param string $message Message to log
     */
    private function debug($message) {
        if ($this->debug) {
            $timestamp = date('Y-m-d H:i:s');
            $entry = "[{$timestamp}] {$message}";
            $this->debugLog[] = $entry;
            error_log("Telnet Debug: {$message}");
        }
    }

    /**
     * Sanitize output for logging (remove control characters)
     *
     * @param string $output Output to sanitize
     * @return string Sanitized output
     */
    private function sanitizeOutput($output) {
        // Remove control characters but keep newlines and tabs
        $output = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $output);
        return substr($output, 0, 1000); // Limit length
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct() {
        $this->close();
    }
}
