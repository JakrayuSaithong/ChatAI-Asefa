<?php
// api/load_chat.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$sessionId = $_GET['session_id'] ?? '';

if (!$sessionId) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT Role, Content, CreatedAt FROM ChatMessages WHERE SessionID = ? ORDER BY CreatedAt ASC";
$params = array($sessionId);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(['error' => sqlsrv_errors()]);
    exit;
}

$messages = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $messages[] = [
        'role' => $row['Role'],
        'content' => $row['Content'],
        'time' => $row['CreatedAt']
    ];
}

echo json_encode($messages);
