<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Midtrans Payment Notification Webhook Handler
 *
 * This endpoint receives payment notifications from Midtrans
 * URL: /app/api/v1/webhook/midtrans.php
 *
 *********************************************************************************************************
 */

// Set JSON response header
header('Content-Type: application/json');

// Log all requests
$logFile = __DIR__ . '/../../../../var/log/midtrans-webhook.log';
$timestamp = date('Y-m-d H:i:s');
$requestBody = file_get_contents('php://input');
$logEntry = "[{$timestamp}] " . $_SERVER['REQUEST_METHOD'] . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
$logEntry .= "Body: {$requestBody}\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Load configuration and database
define('DALO_INCLUDE', true);
require_once(__DIR__ . '/../../../common/includes/config_read.php');
require_once(__DIR__ . '/../../../common/includes/db_open.php');
require_once(__DIR__ . '/../../../common/library/payment/MidtransGateway.php');

// Parse notification
$notification = json_decode($requestBody, true);

if (empty($notification)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

try {
    // Initialize Midtrans
    $midtrans = new MidtransGateway(
        $configValues['MIDTRANS_SERVER_KEY'] ?? '',
        $configValues['MIDTRANS_CLIENT_KEY'] ?? '',
        ($configValues['MIDTRANS_IS_PRODUCTION'] ?? false) === true
    );

    // Parse and verify notification
    $parsed = $midtrans->parseNotification($notification);

    if (!$parsed['verified']) {
        file_put_contents($logFile, "[{$timestamp}] INVALID SIGNATURE\n", FILE_APPEND);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }

    $orderId = $parsed['order_id'];
    $transactionStatus = $parsed['transaction_status'];
    $fraudStatus = $parsed['fraud_status'] ?? 'accept';
    $transactionId = $parsed['transaction_id'] ?? '';
    $grossAmount = $parsed['gross_amount'] ?? 0;
    $paymentType = $parsed['payment_type'] ?? '';

    // Log parsed notification
    file_put_contents($logFile, "[{$timestamp}] Order: {$orderId}, Status: {$transactionStatus}, Fraud: {$fraudStatus}\n", FILE_APPEND);

    // Get transaction from database
    $sql = sprintf("SELECT * FROM payment_transactions WHERE transaction_id = '%s'",
        $dbSocket->escapeSimple($orderId)
    );
    $transaction = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);

    if (empty($transaction)) {
        file_put_contents($logFile, "[{$timestamp}] Transaction not found: {$orderId}\n", FILE_APPEND);
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }

    // Process based on transaction status
    $newStatus = 'pending';
    $shouldActivate = false;

    if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
        if ($fraudStatus === 'accept') {
            $newStatus = 'success';
            $shouldActivate = true;
        } elseif ($fraudStatus === 'challenge') {
            $newStatus = 'processing'; // Manual review needed
        } else {
            $newStatus = 'failed';
        }
    } elseif ($transactionStatus === 'pending') {
        $newStatus = 'pending';
    } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
        $newStatus = 'failed';
    } elseif ($transactionStatus === 'refund' || $transactionStatus === 'partial_refund') {
        $newStatus = 'refunded';
    }

    // Update transaction status
    $sql = sprintf("UPDATE payment_transactions SET
            status = '%s',
            gateway_transaction_id = '%s',
            gateway_response = '%s',
            callback_received_at = NOW(),
            paid_at = %s,
            updated_at = NOW()
        WHERE transaction_id = '%s'",
        $dbSocket->escapeSimple($newStatus),
        $dbSocket->escapeSimple($transactionId),
        $dbSocket->escapeSimple(json_encode($parsed)),
        $shouldActivate ? 'NOW()' : 'paid_at',
        $dbSocket->escapeSimple($orderId)
    );
    $dbSocket->query($sql);

    // Process based on transaction type
    if ($shouldActivate) {
        $transactionType = $transaction['transaction_type'];
        $referenceType = $transaction['reference_type'];
        $referenceId = $transaction['reference_id'];
        $tenantId = $transaction['tenant_id'];

        if ($transactionType === 'app_subscription') {
            // Activate tenant subscription
            require_once(__DIR__ . '/../../../common/includes/subscription_check.php');

            // Get invoice details
            $sql = sprintf("SELECT * FROM app_subscription_invoices WHERE id = %d", $referenceId);
            $invoice = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);

            if (!empty($invoice)) {
                // Update invoice
                $sql = sprintf("UPDATE app_subscription_invoices SET
                        status = 'paid',
                        payment_method = '%s',
                        payment_reference = '%s',
                        payment_date = NOW(),
                        updated_at = NOW()
                    WHERE id = %d",
                    $dbSocket->escapeSimple($paymentType),
                    $dbSocket->escapeSimple($transactionId),
                    $referenceId
                );
                $dbSocket->query($sql);

                // Activate subscription
                dalo_activate_subscription($tenantId, $invoice['plan_id'], 'monthly');

                file_put_contents($logFile, "[{$timestamp}] Subscription activated for tenant {$tenantId}\n", FILE_APPEND);
            }

        } elseif ($transactionType === 'customer_payment') {
            // Process customer internet payment
            $sql = sprintf("SELECT * FROM customer_payments WHERE id = %d", $referenceId);
            $payment = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);

            if (!empty($payment)) {
                // Update payment
                $sql = sprintf("UPDATE customer_payments SET
                        status = 'confirmed',
                        reference_number = '%s',
                        confirmed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = %d",
                    $dbSocket->escapeSimple($transactionId),
                    $referenceId
                );
                $dbSocket->query($sql);

                // Update invoice
                $sql = sprintf("UPDATE customer_invoices SET
                        status = 'paid',
                        paid_amount = total_amount,
                        paid_at = NOW(),
                        updated_at = NOW()
                    WHERE id = %d",
                    $payment['invoice_id']
                );
                $dbSocket->query($sql);

                // Reactivate customer if isolated
                $sql = sprintf("UPDATE customer_accounts SET
                        status = 'active',
                        isolation_reason = NULL,
                        updated_at = NOW()
                    WHERE id = %d AND status = 'isolated'",
                    $payment['customer_id']
                );
                $dbSocket->query($sql);

                file_put_contents($logFile, "[{$timestamp}] Customer payment confirmed: {$referenceId}\n", FILE_APPEND);
            }
        }
    }

    // Log activity
    $sql = sprintf("INSERT INTO activity_logs
            (tenant_id, action, entity_type, entity_id, new_values, ip_address, created_at)
        VALUES (%s, 'payment_webhook', 'payment_transaction', %d, '%s', '%s', NOW())",
        $transaction['tenant_id'] ? $transaction['tenant_id'] : 'NULL',
        $transaction['id'],
        $dbSocket->escapeSimple(json_encode([
            'status' => $newStatus,
            'transaction_status' => $transactionStatus,
            'gateway' => 'midtrans'
        ])),
        $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR'] ?? '')
    );
    $dbSocket->query($sql);

    // Success response
    file_put_contents($logFile, "[{$timestamp}] Processed successfully: {$orderId} -> {$newStatus}\n", FILE_APPEND);
    echo json_encode(['status' => 'ok', 'message' => 'Notification processed']);

} catch (Exception $e) {
    file_put_contents($logFile, "[{$timestamp}] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

require_once(__DIR__ . '/../../../common/includes/db_close.php');
