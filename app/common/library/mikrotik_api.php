<?php
/*
 *********************************************************************************************************
 * MikroTik RouterOS API client (minimal)
 *
 * This implementation provides the subset of API calls needed by daloRADIUS.
 *
 *********************************************************************************************************
 */

class MikrotikApi {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $timeout;
    private $socket = null;

    public function __construct($host, $port, $user, $pass, $timeout = 5) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->timeout = $timeout;
    }

    public function connect() {
        $errno = 0;
        $errstr = '';
        $this->socket = fsockopen($this->host, intval($this->port), $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        $this->writeWord('/login');
        $this->writeWord('=name=' . $this->user);
        $this->writeWord('=password=' . $this->pass);
        $this->writeWord('');

        $response = $this->readResponse();
        return $this->hasDone($response);
    }

    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function command($command, $params = array()) {
        if (!$this->socket) {
            return array();
        }

        $this->writeWord($command);
        foreach ($params as $key => $value) {
            $this->writeWord('=' . $key . '=' . $value);
        }
        $this->writeWord('');

        return $this->readResponse();
    }

    private function hasDone($response) {
        foreach ($response as $sentence) {
            if (isset($sentence['!done'])) {
                return true;
            }
        }
        return false;
    }

    private function writeWord($word) {
        $len = strlen($word);
        $this->writeLength($len);
        fwrite($this->socket, $word);
    }

    private function writeLength($length) {
        if ($length < 0x80) {
            fwrite($this->socket, chr($length));
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            fwrite($this->socket, chr(($length >> 16) & 0xFF));
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            fwrite($this->socket, chr(($length >> 24) & 0xFF));
            fwrite($this->socket, chr(($length >> 16) & 0xFF));
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0));
            fwrite($this->socket, chr(($length >> 24) & 0xFF));
            fwrite($this->socket, chr(($length >> 16) & 0xFF));
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        }
    }

    private function readResponse() {
        $response = array();
        $sentence = array();

        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                if (!empty($sentence)) {
                    $response[] = $sentence;
                    $sentence = array();
                }
                if (!empty($response) && isset($response[count($response) - 1]['!done'])) {
                    break;
                }
                continue;
            }

            if ($word[0] === '!') {
                $sentence[$word] = true;
            } elseif ($word[0] === '=') {
                $pair = explode('=', $word, 3);
                if (count($pair) === 3) {
                    $sentence[$pair[1]] = $pair[2];
                }
            }
        }

        return $response;
    }

    private function readWord() {
        $length = $this->readLength();
        if ($length === 0) {
            return '';
        }

        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function readLength() {
        $c = ord(fread($this->socket, 1));
        if ($c < 0x80) {
            return $c;
        }

        if (($c & 0xC0) == 0x80) {
            $c &= ~0xC0;
            $c = ($c << 8) + ord(fread($this->socket, 1));
            return $c;
        }

        if (($c & 0xE0) == 0xC0) {
            $c &= ~0xE0;
            $c = ($c << 8) + ord(fread($this->socket, 1));
            $c = ($c << 8) + ord(fread($this->socket, 1));
            return $c;
        }

        if (($c & 0xF0) == 0xE0) {
            $c &= ~0xF0;
            $c = ($c << 8) + ord(fread($this->socket, 1));
            $c = ($c << 8) + ord(fread($this->socket, 1));
            $c = ($c << 8) + ord(fread($this->socket, 1));
            return $c;
        }

        $c = ord(fread($this->socket, 1));
        $c = ($c << 8) + ord(fread($this->socket, 1));
        $c = ($c << 8) + ord(fread($this->socket, 1));
        $c = ($c << 8) + ord(fread($this->socket, 1));
        return $c;
    }
}
