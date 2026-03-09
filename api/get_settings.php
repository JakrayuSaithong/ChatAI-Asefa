<?php
// api/get_settings.php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'db_connect.php';

$username = $_GET['username'] ?? '';

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'Username required']);
    exit;
}

$sql = "SELECT * FROM UserSettings WHERE Username = ?";
$params = array($username);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
    exit;
}

$settings = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$settings) {
    echo json_encode(['success' => true, 'data' => null]);
} else {
    echo json_encode(['success' => true, 'data' => $settings]);
}
