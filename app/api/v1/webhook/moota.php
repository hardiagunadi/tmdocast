<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Moota Bank Mutation Webhook Handler
 *
 * This endpoint receives bank mutation notifications from Moota
 * URL: /app/api/v1/webhook/moota.php
 *
 *********************************************************************************************************
 */

// Set JSON response header
header('Content-Type: application/json');

// Log all requests
$logFile = __DIR__ . '/../../../../var/log/moota-webhook.log';
$timestamp = date('Y-m-d H:i:s');
$requestBody = file_get_contents('php://input');
$logEntry = "[{$timestamp}] " . $_SERVER['REQUEST_METHOD'] . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
$logEntry .= "Body: " . substr($requestBody, 0, 2000) . "\n";
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
require_once(__DIR__ . '/../../../common/library/payment/MootaWebhook.php');

// Parse payload
$payload = json_decode($requestBody, true);

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

try {
    // Initialize Moota handler
    $moota = new MootaWebhook(
        $configValues['MOOTA_API_KEY'] ?? '',
        $configValues['MOOTA_SECRET_KEY'] ?? ''
    );

    // Verify signature if secret is configured
    $signature = $_SERVER['HTTP_X_MOOTA_SIGNATURE'] ?? $_SERVER['HTTP_SIGNATURE'] ?? '';
    if (!empty($configValues['MOOTA_SECRET_KEY']) && !empty($signature)) {
        if (!$moota->verifySignature($requestBody, $signature)) {
            file_put_contents($logFile, "[{$timestamp}] INVALID SIGNATURE\n", FILE_APPEND);
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
            exit;
        }
    }

    // Process mutations
    $results = $moota->handleMutationWebhook($payload);

    // Log results
    $matched = 0;
    $unmatched = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($results as $result) {
        switch ($result['status']) {
            case 'matched':
                $matched++;
                file_put_contents($logFile, "[{$timestamp}] MATCHED: mutation_id={$result['mutation_id']}, payment_id={$result['payment_id']}, amount={$result['amount']}\n", FILE_APPEND);
                break;
            case 'unmatched':
                $unmatched++;
                file_put_contents($logFile, "[{$timestamp}] UNMATCHED: mutation_id={$result['mutation_id']}, amount={$result['amount']}, desc={$result['description']}\n", FILE_APPEND);
                break;
            case 'skipped':
                $skipped++;
                break;
            case 'error':
                $errors++;
                file_put_contents($logFile, "[{$timestamp}] ERROR: mutation_id={$result['mutation_id']}, message={$result['message']}\n", FILE_APPEND);
                break;
        }
    }

    file_put_contents($logFile, "[{$timestamp}] SUMMARY: matched={$matched}, unmatched={$unmatched}, skipped={$skipped}, errors={$errors}\n", FILE_APPEND);

    // Return success
    echo json_encode([
        'status' => 'ok',
        'message' => 'Mutations processed',
        'summary' => [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'skipped' => $skipped,
            'errors' => $errors
        ]
    ]);

} catch (Exception $e) {
    file_put_contents($logFile, "[{$timestamp}] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

require_once(__DIR__ . '/../../../common/includes/db_close.php');
