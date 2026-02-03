<?php
/*
 * daloRADIUS API
 * This file handles invalid API routes
 */

header('Content-Type: application/json');
http_response_code(404);

echo json_encode([
    'status' => 'error',
    'code' => 404,
    'message' => 'API endpoint not found',
    'documentation' => '/docs/api'
]);
