<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * Midtrans Payment Gateway Integration
 * Supports QRIS, Virtual Account, and other payment methods
 *
 * Documentation: https://docs.midtrans.com/
 *
 *********************************************************************************************************
 */

class MidtransGateway {
    private $serverKey;
    private $clientKey;
    private $isProduction;
    private $baseUrl;
    private $snapUrl;

    // Supported banks for Virtual Account
    const VA_BANKS = ['bca', 'bni', 'bri', 'mandiri', 'permata', 'cimb'];

    // QRIS acquirers
    const QRIS_ACQUIRERS = ['gopay', 'airpay shopee'];

    public function __construct($serverKey, $clientKey, $isProduction = false) {
        $this->serverKey = $serverKey;
        $this->clientKey = $clientKey;
        $this->isProduction = $isProduction;

        if ($isProduction) {
            $this->baseUrl = 'https://api.midtrans.com';
            $this->snapUrl = 'https://app.midtrans.com/snap/v1';
        } else {
            $this->baseUrl = 'https://api.sandbox.midtrans.com';
            $this->snapUrl = 'https://app.sandbox.midtrans.com/snap/v1';
        }
    }

    /**
     * Create QRIS Payment
     *
     * @param string $orderId Unique order ID
     * @param int $amount Amount in IDR
     * @param array $itemDetails Array of items
     * @param array $customerDetails Customer information
     * @param string $acquirer QRIS acquirer (gopay, airpay shopee)
     * @return array Response from Midtrans
     */
    public function createQrisPayment($orderId, $amount, $itemDetails, $customerDetails, $acquirer = 'gopay') {
        $params = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => intval($amount)
            ],
            'qris' => [
                'acquirer' => $acquirer
            ],
            'item_details' => $itemDetails,
            'customer_details' => $customerDetails
        ];

        return $this->chargeApi($params);
    }

    /**
     * Create Virtual Account Payment
     *
     * @param string $orderId Unique order ID
     * @param int $amount Amount in IDR
     * @param string $bank Bank code (bca, bni, bri, mandiri, permata, cimb)
     * @param array $customerDetails Customer information
     * @param int $expiryMinutes Expiry time in minutes (default 24 hours)
     * @return array Response from Midtrans
     */
    public function createVirtualAccountPayment($orderId, $amount, $bank, $customerDetails, $expiryMinutes = 1440) {
        if (!in_array(strtolower($bank), self::VA_BANKS)) {
            throw new Exception("Bank tidak didukung: {$bank}");
        }

        $params = [
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => intval($amount)
            ],
            'bank_transfer' => [
                'bank' => strtolower($bank)
            ],
            'customer_details' => $customerDetails,
            'custom_expiry' => [
                'expiry_duration' => $expiryMinutes,
                'unit' => 'minute'
            ]
        ];

        // BCA specific: VA number can be customized
        if ($bank === 'bca' && isset($customerDetails['va_number'])) {
            $params['bank_transfer']['va_number'] = $customerDetails['va_number'];
        }

        // Mandiri Bill specific
        if ($bank === 'mandiri') {
            $params['payment_type'] = 'echannel';
            unset($params['bank_transfer']);
            $params['echannel'] = [
                'bill_info1' => 'Pembayaran:',
                'bill_info2' => 'ISP Manager'
            ];
        }

        return $this->chargeApi($params);
    }

    /**
     * Create GoPay Payment
     *
     * @param string $orderId Unique order ID
     * @param int $amount Amount in IDR
     * @param array $customerDetails Customer information
     * @param string $callbackUrl Callback URL after payment
     * @return array Response from Midtrans
     */
    public function createGopayPayment($orderId, $amount, $customerDetails, $callbackUrl = null) {
        $params = [
            'payment_type' => 'gopay',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => intval($amount)
            ],
            'gopay' => [
                'enable_callback' => true,
                'callback_url' => $callbackUrl ?? ''
            ],
            'customer_details' => $customerDetails
        ];

        return $this->chargeApi($params);
    }

    /**
     * Create Snap Token for Snap payment page
     *
     * @param string $orderId Unique order ID
     * @param int $amount Amount in IDR
     * @param array $itemDetails Array of items
     * @param array $customerDetails Customer information
     * @param array $enabledPayments List of enabled payment methods
     * @return array Response with snap token and redirect URL
     */
    public function createSnapToken($orderId, $amount, $itemDetails, $customerDetails, $enabledPayments = []) {
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => intval($amount)
            ],
            'item_details' => $itemDetails,
            'customer_details' => $customerDetails
        ];

        if (!empty($enabledPayments)) {
            $params['enabled_payments'] = $enabledPayments;
        }

        return $this->snapApi($params);
    }

    /**
     * Get transaction status
     *
     * @param string $orderId Order ID to check
     * @return array Transaction status
     */
    public function getTransactionStatus($orderId) {
        $url = $this->baseUrl . '/v2/' . urlencode($orderId) . '/status';
        return $this->sendRequest('GET', $url);
    }

    /**
     * Cancel transaction
     *
     * @param string $orderId Order ID to cancel
     * @return array Response
     */
    public function cancelTransaction($orderId) {
        $url = $this->baseUrl . '/v2/' . urlencode($orderId) . '/cancel';
        return $this->sendRequest('POST', $url);
    }

    /**
     * Expire transaction (for pending VA/QRIS)
     *
     * @param string $orderId Order ID to expire
     * @return array Response
     */
    public function expireTransaction($orderId) {
        $url = $this->baseUrl . '/v2/' . urlencode($orderId) . '/expire';
        return $this->sendRequest('POST', $url);
    }

    /**
     * Refund transaction
     *
     * @param string $orderId Order ID to refund
     * @param int $amount Amount to refund (null for full refund)
     * @param string $reason Refund reason
     * @return array Response
     */
    public function refundTransaction($orderId, $amount = null, $reason = 'Customer request') {
        $url = $this->baseUrl . '/v2/' . urlencode($orderId) . '/refund';

        $params = ['reason' => $reason];
        if ($amount !== null) {
            $params['amount'] = intval($amount);
        }

        return $this->sendRequest('POST', $url, $params);
    }

    /**
     * Verify webhook notification signature
     *
     * @param string $orderId Order ID from notification
     * @param string $statusCode Status code from notification
     * @param string $grossAmount Gross amount from notification
     * @param string $signatureKey Signature key from notification
     * @return bool True if signature is valid
     */
    public function verifySignature($orderId, $statusCode, $grossAmount, $signatureKey) {
        $input = $orderId . $statusCode . $grossAmount . $this->serverKey;
        $calculatedSignature = hash('sha512', $input);
        return hash_equals($calculatedSignature, $signatureKey);
    }

    /**
     * Parse notification and verify
     *
     * @param array|string $notification Notification data (array or JSON string)
     * @return array Parsed and verified notification with 'verified' flag
     */
    public function parseNotification($notification) {
        if (is_string($notification)) {
            $notification = json_decode($notification, true);
        }

        if (!is_array($notification)) {
            throw new Exception('Invalid notification format');
        }

        // Required fields
        $required = ['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'];
        foreach ($required as $field) {
            if (!isset($notification[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Verify signature
        $isValid = $this->verifySignature(
            $notification['order_id'],
            $notification['status_code'],
            $notification['gross_amount'],
            $notification['signature_key']
        );

        $notification['verified'] = $isValid;
        $notification['is_success'] = in_array($notification['transaction_status'], ['capture', 'settlement']);
        $notification['is_pending'] = $notification['transaction_status'] === 'pending';
        $notification['is_failed'] = in_array($notification['transaction_status'], ['deny', 'expire', 'cancel']);

        return $notification;
    }

    /**
     * Get QRIS image URL from response
     *
     * @param array $response Response from createQrisPayment
     * @return string|null QRIS image URL
     */
    public function getQrisImageUrl($response) {
        if (isset($response['actions'])) {
            foreach ($response['actions'] as $action) {
                if ($action['name'] === 'generate-qr-code') {
                    return $action['url'];
                }
            }
        }
        return null;
    }

    /**
     * Get VA number from response
     *
     * @param array $response Response from createVirtualAccountPayment
     * @return string|null VA number
     */
    public function getVaNumber($response) {
        if (isset($response['va_numbers']) && !empty($response['va_numbers'])) {
            return $response['va_numbers'][0]['va_number'];
        }
        if (isset($response['permata_va_number'])) {
            return $response['permata_va_number'];
        }
        if (isset($response['bill_key'])) {
            return $response['biller_code'] . '-' . $response['bill_key'];
        }
        return null;
    }

    /**
     * Make charge API request
     */
    private function chargeApi($params) {
        $url = $this->baseUrl . '/v2/charge';
        return $this->sendRequest('POST', $url, $params);
    }

    /**
     * Make Snap API request
     */
    private function snapApi($params) {
        $url = $this->snapUrl . '/transactions';
        return $this->sendRequest('POST', $url, $params);
    }

    /**
     * Send HTTP request to Midtrans
     */
    private function sendRequest($method, $url, $params = null) {
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->serverKey . ':')
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ];

        if ($method === 'POST' && $params !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($params);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("Midtrans API Error: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = isset($result['status_message']) ? $result['status_message'] : 'Unknown error';
            throw new Exception("Midtrans API Error ({$httpCode}): {$errorMessage}");
        }

        return $result;
    }

    /**
     * Generate unique order ID
     *
     * @param string $prefix Prefix for order ID
     * @param int $tenantId Tenant ID
     * @return string Unique order ID
     */
    public static function generateOrderId($prefix = 'TXN', $tenantId = null) {
        $timestamp = date('YmdHis');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $tenantPart = $tenantId ? "-T{$tenantId}" : '';
        return "{$prefix}{$tenantPart}-{$timestamp}-{$random}";
    }

    /**
     * Format amount for display
     *
     * @param int $amount Amount in IDR
     * @return string Formatted amount
     */
    public static function formatAmount($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * Get client key (for frontend)
     */
    public function getClientKey() {
        return $this->clientKey;
    }

    /**
     * Check if production mode
     */
    public function isProduction() {
        return $this->isProduction;
    }
}

/**
 * Helper function to create Midtrans instance from config
 */
function midtrans_create_gateway() {
    global $configValues;

    return new MidtransGateway(
        $configValues['MIDTRANS_SERVER_KEY'] ?? '',
        $configValues['MIDTRANS_CLIENT_KEY'] ?? '',
        ($configValues['MIDTRANS_IS_PRODUCTION'] ?? false) === true
    );
}
