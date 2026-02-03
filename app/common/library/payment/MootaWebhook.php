<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * Moota Bank Mutation Webhook Integration
 * Auto-confirm bank transfer payments by monitoring bank mutations
 *
 * Documentation: https://moota.co/developer
 *
 *********************************************************************************************************
 */

class MootaWebhook {
    private $apiKey;
    private $secretKey;
    private $baseUrl = 'https://app.moota.co/api/v2';

    // Supported banks
    const SUPPORTED_BANKS = [
        'bca' => 'Bank Central Asia',
        'mandiri' => 'Bank Mandiri',
        'bni' => 'Bank Negara Indonesia',
        'bri' => 'Bank Rakyat Indonesia',
        'cimb' => 'CIMB Niaga',
        'muamalat' => 'Bank Muamalat',
        'danamon' => 'Bank Danamon',
        'permata' => 'Bank Permata',
        'bsi' => 'Bank Syariah Indonesia'
    ];

    public function __construct($apiKey, $secretKey = null) {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
    }

    /**
     * Handle incoming mutation webhook from Moota
     *
     * @param array|string $payload Webhook payload
     * @return array Processing results
     */
    public function handleMutationWebhook($payload) {
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (!is_array($payload)) {
            throw new Exception('Invalid webhook payload');
        }

        $results = [];

        // Moota sends mutations array
        $mutations = $payload['data'] ?? $payload;
        if (!is_array($mutations)) {
            $mutations = [$mutations];
        }

        foreach ($mutations as $mutation) {
            try {
                $result = $this->processMutation($mutation);
                $results[] = $result;
            } catch (Exception $e) {
                $results[] = [
                    'status' => 'error',
                    'mutation_id' => $mutation['mutation_id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Process a single mutation
     *
     * @param array $mutation Mutation data
     * @return array Processing result
     */
    private function processMutation($mutation) {
        // Only process credit (incoming) mutations
        if (($mutation['type'] ?? '') !== 'CR') {
            return [
                'status' => 'skipped',
                'reason' => 'Not a credit mutation',
                'mutation_id' => $mutation['mutation_id'] ?? 'unknown'
            ];
        }

        $amount = floatval($mutation['amount'] ?? 0);
        $description = $mutation['description'] ?? '';
        $bankId = $mutation['bank_id'] ?? '';
        $mutationId = $mutation['mutation_id'] ?? '';
        $mutationDate = $mutation['date'] ?? date('Y-m-d');

        if ($amount <= 0) {
            return [
                'status' => 'skipped',
                'reason' => 'Invalid amount',
                'mutation_id' => $mutationId
            ];
        }

        // Try to match payment
        $matchResult = $this->matchPayment($amount, $description, $bankId, $mutationId, $mutationDate);

        return $matchResult;
    }

    /**
     * Match mutation to pending payment
     *
     * @param float $amount Mutation amount
     * @param string $description Mutation description
     * @param string $bankId Moota bank ID
     * @param string $mutationId Moota mutation ID
     * @param string $mutationDate Mutation date
     * @return array Match result
     */
    private function matchPayment($amount, $description, $bankId, $mutationId, $mutationDate) {
        global $dbSocket;

        // Strategy 1: Match by exact amount with unique code
        // Invoice amount + unique code (last 3 digits)
        // e.g., Invoice Rp 150.000 -> Payment Rp 150.123 (123 is unique code)

        $uniqueCode = $amount % 1000; // Last 3 digits
        $baseAmount = floor($amount / 1000) * 1000;

        // Try exact amount match first
        $sql = sprintf("
            SELECT cp.id, cp.tenant_id, cp.customer_id, cp.invoice_id,
                   cp.amount, cp.payment_number, ci.total_amount, ci.invoice_number,
                   ca.fullname AS customer_name, ca.phone AS customer_phone
            FROM customer_payments cp
            JOIN customer_invoices ci ON cp.invoice_id = ci.id
            JOIN customer_accounts ca ON cp.customer_id = ca.id
            WHERE cp.status = 'pending'
            AND cp.payment_method = 'bank_transfer'
            AND ABS(cp.amount - %f) < 10
            AND cp.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY ABS(cp.amount - %f) ASC
            LIMIT 1",
            $amount, $amount
        );

        $res = $dbSocket->query($sql);

        if ($res && $res->numRows() > 0) {
            $payment = $res->fetchRow(DB_FETCHMODE_ASSOC);
            return $this->confirmPayment($payment, $mutationId, $amount, $description);
        }

        // Strategy 2: Match by unique code in description
        // Look for invoice number or payment reference in description
        if (preg_match('/INV[\/-]?(\d{4}[\/-]\d{2}[\/-]\d{4})/i', $description, $matches)) {
            $invoiceNumber = $matches[0];
            $sql = sprintf("
                SELECT cp.id, cp.tenant_id, cp.customer_id, cp.invoice_id,
                       cp.amount, cp.payment_number, ci.total_amount, ci.invoice_number,
                       ca.fullname AS customer_name, ca.phone AS customer_phone
                FROM customer_payments cp
                JOIN customer_invoices ci ON cp.invoice_id = ci.id
                JOIN customer_accounts ca ON cp.customer_id = ca.id
                WHERE cp.status = 'pending'
                AND ci.invoice_number LIKE '%%%s%%'
                AND cp.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                LIMIT 1",
                $dbSocket->escapeSimple($invoiceNumber)
            );

            $res = $dbSocket->query($sql);

            if ($res && $res->numRows() > 0) {
                $payment = $res->fetchRow(DB_FETCHMODE_ASSOC);
                // Verify amount is close enough (within 10% tolerance for fees/rounding)
                if (abs($payment['amount'] - $amount) <= ($payment['amount'] * 0.1)) {
                    return $this->confirmPayment($payment, $mutationId, $amount, $description);
                }
            }
        }

        // Strategy 3: Match by base amount (ignoring unique code)
        $sql = sprintf("
            SELECT cp.id, cp.tenant_id, cp.customer_id, cp.invoice_id,
                   cp.amount, cp.payment_number, ci.total_amount, ci.invoice_number,
                   ca.fullname AS customer_name, ca.phone AS customer_phone
            FROM customer_payments cp
            JOIN customer_invoices ci ON cp.invoice_id = ci.id
            JOIN customer_accounts ca ON cp.customer_id = ca.id
            WHERE cp.status = 'pending'
            AND cp.payment_method = 'bank_transfer'
            AND (
                ABS(cp.amount - %f) < 1000
                OR ABS(ci.total_amount - %f) < 1000
            )
            AND cp.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY ABS(cp.amount - %f) ASC
            LIMIT 1",
            $baseAmount, $baseAmount, $amount
        );

        $res = $dbSocket->query($sql);

        if ($res && $res->numRows() > 0) {
            $payment = $res->fetchRow(DB_FETCHMODE_ASSOC);
            return $this->confirmPayment($payment, $mutationId, $amount, $description);
        }

        // No match found - log for manual review
        $this->logUnmatchedMutation($amount, $description, $bankId, $mutationId, $mutationDate);

        return [
            'status' => 'unmatched',
            'mutation_id' => $mutationId,
            'amount' => $amount,
            'description' => $description
        ];
    }

    /**
     * Confirm payment and update related records
     *
     * @param array $payment Payment record
     * @param string $mutationId Moota mutation ID
     * @param float $actualAmount Actual amount received
     * @param string $description Mutation description
     * @return array Confirmation result
     */
    private function confirmPayment($payment, $mutationId, $actualAmount, $description) {
        global $dbSocket;

        // Start transaction
        $dbSocket->query('START TRANSACTION');

        try {
            // Update payment record
            $sql = sprintf("
                UPDATE customer_payments SET
                    status = 'confirmed',
                    reference_number = '%s',
                    amount = %f,
                    notes = CONCAT(IFNULL(notes, ''), '\n[Moota] ', '%s'),
                    confirmed_at = NOW(),
                    updated_at = NOW()
                WHERE id = %d",
                $dbSocket->escapeSimple($mutationId),
                $actualAmount,
                $dbSocket->escapeSimple($description),
                $payment['id']
            );
            $dbSocket->query($sql);

            // Update invoice status
            $sql = sprintf("
                UPDATE customer_invoices SET
                    status = 'paid',
                    paid_amount = paid_amount + %f,
                    paid_at = NOW(),
                    updated_at = NOW()
                WHERE id = %d",
                $actualAmount,
                $payment['invoice_id']
            );
            $dbSocket->query($sql);

            // Reactivate customer if isolated
            $sql = sprintf("
                UPDATE customer_accounts SET
                    status = 'active',
                    isolation_reason = NULL,
                    updated_at = NOW()
                WHERE id = %d AND status = 'isolated'",
                $payment['customer_id']
            );
            $dbSocket->query($sql);

            // Log activity
            $sql = sprintf("
                INSERT INTO activity_logs
                (tenant_id, action, entity_type, entity_id, new_values, created_at)
                VALUES (%d, 'payment_auto_confirmed', 'customer_payment', %d, '%s', NOW())",
                $payment['tenant_id'],
                $payment['id'],
                $dbSocket->escapeSimple(json_encode([
                    'moota_mutation_id' => $mutationId,
                    'amount' => $actualAmount,
                    'invoice_number' => $payment['invoice_number']
                ]))
            );
            $dbSocket->query($sql);

            // Create payment transaction record
            $transactionId = 'MOOTA-' . $mutationId;
            $sql = sprintf("
                INSERT INTO payment_transactions
                (transaction_id, tenant_id, transaction_type, reference_type, reference_id,
                 amount, payment_method, status, gateway_transaction_id, paid_at, created_at)
                VALUES ('%s', %d, 'customer_payment', 'customer_payment', %d,
                        %f, 'bank_transfer', 'success', '%s', NOW(), NOW())",
                $dbSocket->escapeSimple($transactionId),
                $payment['tenant_id'],
                $payment['id'],
                $actualAmount,
                $dbSocket->escapeSimple($mutationId)
            );
            $dbSocket->query($sql);

            $dbSocket->query('COMMIT');

            // Send notification to customer
            $this->sendPaymentNotification($payment, $actualAmount);

            return [
                'status' => 'matched',
                'payment_id' => $payment['id'],
                'invoice_number' => $payment['invoice_number'],
                'customer_name' => $payment['customer_name'],
                'expected_amount' => $payment['amount'],
                'actual_amount' => $actualAmount,
                'mutation_id' => $mutationId
            ];

        } catch (Exception $e) {
            $dbSocket->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Log unmatched mutation for manual review
     */
    private function logUnmatchedMutation($amount, $description, $bankId, $mutationId, $mutationDate) {
        global $dbSocket;

        $sql = sprintf("
            INSERT INTO activity_logs
            (action, entity_type, new_values, created_at)
            VALUES ('moota_unmatched_mutation', 'moota_webhook', '%s', NOW())",
            $dbSocket->escapeSimple(json_encode([
                'mutation_id' => $mutationId,
                'amount' => $amount,
                'description' => $description,
                'bank_id' => $bankId,
                'date' => $mutationDate
            ]))
        );
        $dbSocket->query($sql);
    }

    /**
     * Send payment confirmation notification
     */
    private function sendPaymentNotification($payment, $amount) {
        // This would integrate with WhatsApp gateway or email
        // Implementation depends on notification system setup

        global $dbSocket;

        // Log notification attempt
        $sql = sprintf("
            INSERT INTO notification_logs
            (tenant_id, notification_type, recipient, subject, body, status, created_at)
            VALUES (%d, 'whatsapp', '%s', 'Pembayaran Dikonfirmasi',
                    'Pembayaran untuk invoice %s sebesar Rp %s telah dikonfirmasi. Terima kasih.',
                    'pending', NOW())",
            $payment['tenant_id'],
            $dbSocket->escapeSimple($payment['customer_phone'] ?? ''),
            $dbSocket->escapeSimple($payment['invoice_number']),
            number_format($amount, 0, ',', '.')
        );
        $dbSocket->query($sql);
    }

    /**
     * Get bank list from Moota
     */
    public function getBankList() {
        $url = $this->baseUrl . '/bank';
        return $this->sendRequest('GET', $url);
    }

    /**
     * Get mutations for a specific bank
     *
     * @param string $bankId Moota bank ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Mutations
     */
    public function getMutations($bankId, $startDate = null, $endDate = null) {
        $url = $this->baseUrl . '/mutation/' . $bankId;

        $params = [];
        if ($startDate) {
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $params['end_date'] = $endDate;
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->sendRequest('GET', $url);
    }

    /**
     * Verify webhook signature (if using secret key)
     *
     * @param string $payload Raw payload
     * @param string $signature Signature from header
     * @return bool True if valid
     */
    public function verifyWebhookSignature($payload, $signature) {
        if (empty($this->secretKey)) {
            return true; // No secret key configured
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate unique payment code for invoice
     * This code is added to the invoice amount for matching
     *
     * @param int $invoiceId Invoice ID
     * @return int Unique code (3 digits)
     */
    public static function generateUniqueCode($invoiceId) {
        // Generate a unique 3-digit code based on invoice ID
        // This ensures the code is consistent for the same invoice
        $hash = crc32(strval($invoiceId) . date('Ymd'));
        return ($hash % 900) + 100; // Range: 100-999
    }

    /**
     * Calculate total amount with unique code
     *
     * @param float $baseAmount Invoice amount
     * @param int $invoiceId Invoice ID
     * @return float Total amount with unique code
     */
    public static function calculatePaymentAmount($baseAmount, $invoiceId) {
        $uniqueCode = self::generateUniqueCode($invoiceId);
        return $baseAmount + $uniqueCode;
    }

    /**
     * Send HTTP request to Moota API
     */
    private function sendRequest($method, $url, $params = null) {
        $ch = curl_init($url);

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ];

        if ($method === 'POST' && $params !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($params);
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("Moota API Error: {$error}");
        }

        return json_decode($response, true);
    }
}

/**
 * Helper function to create Moota instance from config
 */
function moota_create_webhook() {
    global $configValues;

    return new MootaWebhook(
        $configValues['MOOTA_API_KEY'] ?? '',
        $configValues['MOOTA_SECRET_KEY'] ?? null
    );
}
