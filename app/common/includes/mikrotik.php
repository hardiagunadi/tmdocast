<?php
/*
 *********************************************************************************************************
 * MikroTik helpers for daloRADIUS
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/common/includes/mikrotik.php') !== false) {
    header("Location: ../index.php");
    exit;
}

require_once(__DIR__ . '/../library/mikrotik_api.php');

function mikrotik_list_nas($dbSocket, $configValues) {
    $sql = sprintf("SELECT id, name, host, port, api_username, api_password, isolir_profile, redirect_url, is_active,
                    pppoe_pool_network, pppoe_pool_cidr
                    FROM %s ORDER BY name ASC", $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS']);
    $res = $dbSocket->query($sql);
    if (DB::isError($res)) {
        return array();
    }

    $items = array();
    while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $items[] = $row;
    }
    return $items;
}

function mikrotik_connect($nas) {
    $api = new MikrotikApi($nas['host'], $nas['port'], $nas['api_username'], $nas['api_password']);
    if (!$api->connect()) {
        return null;
    }
    return $api;
}

function mikrotik_find($api, $path, $name) {
    $response = $api->command($path . '/print', array('?name' => $name));
    foreach ($response as $sentence) {
        if (isset($sentence['.id'])) {
            return $sentence['.id'];
        }
    }
    return null;
}

function mikrotik_rate_limit($down, $up) {
    $down = trim($down);
    $up = trim($up);
    if ($down === '' && $up === '') {
        return '';
    }
    $down = ($down === '') ? '0' : $down;
    $up = ($up === '') ? '0' : $up;
    return sprintf('%sk/%sk', $down, $up);
}

function mikrotik_ip_to_long($ip) {
    $long = ip2long($ip);
    if ($long === false) {
        return null;
    }
    return (int)sprintf('%u', $long);
}

function mikrotik_long_to_ip($long) {
    if ($long > 0x7FFFFFFF) {
        $long -= 0x100000000;
    }
    return long2ip($long);
}

function mikrotik_next_pppoe_ip($dbSocket, $configValues, $username, $nasId, $network, $cidr) {
    $network = trim($network);
    $cidr = intval($cidr);
    if ($cidr < 8 || $cidr > 30) {
        return null;
    }
    $networkLong = mikrotik_ip_to_long($network);
    if ($networkLong === null) {
        return null;
    }

    $existing_sql = sprintf("SELECT ip_address FROM %s WHERE username='%s' AND service_type='pppoe'
                             AND nas_id=%d AND ip_address IS NOT NULL LIMIT 1",
                             $configValues['CONFIG_DB_TBL_DALOMIKROTIKUSERS'],
                             $dbSocket->escapeSimple($username),
                             intval($nasId));
    $existing = $dbSocket->getRow($existing_sql, array(), DB_FETCHMODE_ASSOC);
    if (!DB::isError($existing) && !empty($existing['ip_address'])) {
        return $existing['ip_address'];
    }

    $mask = ($cidr === 0) ? 0 : (~((1 << (32 - $cidr)) - 1) & 0xFFFFFFFF);
    $netBase = $networkLong & $mask;
    $broadcast = $netBase | (~$mask & 0xFFFFFFFF);
    $start = $netBase + 1;
    $end = $broadcast - 1;

    $used_sql = sprintf("SELECT ip_address FROM %s WHERE nas_id=%d AND service_type='pppoe' AND ip_address IS NOT NULL",
                        $configValues['CONFIG_DB_TBL_DALOMIKROTIKUSERS'],
                        intval($nasId));
    $used_res = $dbSocket->query($used_sql);
    $used = array();
    if (!DB::isError($used_res)) {
        while ($row = $used_res->fetchRow(DB_FETCHMODE_ASSOC)) {
            if (!empty($row['ip_address'])) {
                $used[$row['ip_address']] = true;
            }
        }
    }

    for ($ipLong = $start; $ipLong <= $end; $ipLong++) {
        $ip = mikrotik_long_to_ip($ipLong);
        if (!isset($used[$ip])) {
            return $ip;
        }
    }

    return null;
}

function mikrotik_sync_profile($api, $serviceType, $profileName, $rateLimit, $isolirProfile, $redirectUrl) {
    if ($serviceType === 'pppoe') {
        $path = '/ppp/profile';
        $params = array(
            'name' => $profileName,
        );
        if (!empty($rateLimit)) {
            $params['rate-limit'] = $rateLimit;
        }
    } else {
        $path = '/ip/hotspot/user/profile';
        $params = array(
            'name' => $profileName,
        );
        if (!empty($rateLimit)) {
            $params['rate-limit'] = $rateLimit;
        }
        if (!empty($redirectUrl) && $profileName === $isolirProfile) {
            $params['open-status-page'] = $redirectUrl;
        }
    }

    $id = mikrotik_find($api, $path, $profileName);
    if ($id) {
        $api->command($path . '/set', array_merge(array('.id' => $id), $params));
    } else {
        $api->command($path . '/add', $params);
    }
}

function mikrotik_sync_user($api, $serviceType, $username, $password, $profileName, $remoteAddress = '') {
    if ($serviceType === 'pppoe') {
        $path = '/ppp/secret';
        $params = array(
            'name' => $username,
            'password' => $password,
            'service' => 'pppoe',
            'profile' => $profileName,
        );
        if (!empty($remoteAddress)) {
            $params['remote-address'] = $remoteAddress;
        }
    } else {
        $path = '/ip/hotspot/user';
        $params = array(
            'name' => $username,
            'password' => $password,
            'profile' => $profileName,
        );
    }

    $id = mikrotik_find($api, $path, $username);
    if ($id) {
        $api->command($path . '/set', array_merge(array('.id' => $id), $params));
    } else {
        $api->command($path . '/add', $params);
    }
}
