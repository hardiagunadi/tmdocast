<?php
/*
 *********************************************************************************************************
 * WA Gateway helpers for daloRADIUS
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/common/includes/wa_gateway.php') !== false) {
    header("Location: ../index.php");
    exit;
}

function wa_gateway_get_settings($dbSocket, $configValues) {
    $sql = sprintf("SELECT id, is_enabled, base_url, api_key, session_name, masterkey, due_days, reminder_days_before, message_template
                    FROM %s WHERE id=1", $configValues['CONFIG_DB_TBL_DALOWAGATEWAY']);
    $row = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);
    return (DB::isError($row) || empty($row)) ? array() : $row;
}

function wa_gateway_send_text($settings, $to, $text) {
    if (empty($settings) || intval($settings['is_enabled']) !== 1) {
        return false;
    }
    if (empty($settings['base_url']) || empty($settings['session_name'])) {
        return false;
    }

    $url = rtrim($settings['base_url'], '/') . '/message/send-text';
    if (!empty($settings['api_key'])) {
        $url .= '?key=' . urlencode($settings['api_key']);
    }
    $payload = array(
        'session' => $settings['session_name'],
        'to' => $to,
        'text' => $text,
        'is_group' => false,
    );

    $headers = array('Content-Type: application/json');
    if (!empty($settings['api_key'])) {
        $headers[] = 'key: ' . $settings['api_key'];
    }
    if (!empty($settings['masterkey'])) {
        $headers[] = 'masterkey: ' . $settings['masterkey'];
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => json_encode($payload),
            'timeout' => 5,
        ),
    ));

    $result = @file_get_contents($url, false, $context);
    return ($result !== false);
}
