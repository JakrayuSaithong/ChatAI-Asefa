<?php
// api/load_chat.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$sessionId = $_GET['session_id'] ?? '';

if (!$sessionId) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT Role, Content, CreatedAt, Attachments FROM ChatMessages WHERE SessionID = ? ORDER BY CreatedAt ASC, CASE WHEN Role = 'user' THEN 0 ELSE 1 END ASC";
$params = array($sessionId);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(['error' => sqlsrv_errors()]);
    exit;
}

$messages = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $attachments = [];
    if (!empty($row['Attachments'])) {
        $files = json_decode($row['Attachments'], true);
        if (is_array($files)) {
            foreach ($files as &$file) {
                // Check if file exists
                $filePath = '../uploads/' . $file['stored_name']; // Relative to api/ folder
                if (file_exists($filePath)) {
                    $file['url'] = 'uploads/' . $file['stored_name']; // Public URL relative to index.php
                    $file['exists'] = true;
                } else {
                    $file['exists'] = false;
                }
            }
            $attachments = $files;
        }
    }

    $messages[] = [
        'role' => $row['Role'],
        'content' => $row['Content'],
        'time' => $row['CreatedAt'],
        'attachments' => $attachments
    ];
}

echo json_encode($messages);
