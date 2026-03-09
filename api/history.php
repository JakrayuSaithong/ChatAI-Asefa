<?php
// api/history.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List Sessions
    $username = $_GET['username'] ?? '';
    if (!$username) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT 
            s.SessionID, 
            s.Title, 
            s.CreatedAt, 
            MAX(m.CreatedAt) as LastMessageTime
        FROM ChatSessions s
        LEFT JOIN ChatMessages m ON s.SessionID = m.SessionID
        WHERE s.Username = ? AND s.IsActive = 1
        GROUP BY s.SessionID, s.Title, s.CreatedAt
        ORDER BY LastMessageTime DESC
    ";
    $params = array($username);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(['error' => sqlsrv_errors()]);
        exit;
    }

    $history = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $history[] = [
            'id' => $row['SessionID'], // SQLSRV might return object, check GUID string
            'title' => $row['Title'],
            'date' => $row['LastMessageTime'] ?? $row['CreatedAt']
        ];
    }
    echo json_encode($history);
} elseif ($method === 'DELETE') {

    // 1) รับจาก query string ก่อน (ง่ายสุดสำหรับ DELETE)
    $sessionId = $_GET['session_id'] ?? '';
    $username  = $_GET['username'] ?? '';

    // 2) ถ้ายังไม่มา ลองอ่าน body
    if (!$sessionId || !$username) {
        $raw = file_get_contents("php://input");

        // 2.1) ถ้าเป็น JSON
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $sessionId = $sessionId ?: ($json['session_id'] ?? $json['sessionId'] ?? '');
            $username  = $username  ?: ($json['username'] ?? '');
        }

        // 2.2) ถ้าเป็น form-urlencoded
        if (!$sessionId || !$username) {
            parse_str($raw, $form);
            $sessionId = $sessionId ?: ($form['session_id'] ?? $form['sessionId'] ?? '');
            $username  = $username  ?: ($form['username'] ?? '');
        }
    }

    if (!$sessionId || !$username) {
        echo json_encode(['success' => false, 'error' => 'Missing ID']);
        exit;
    }

    // Verify Ownership
    $checkSql = "SELECT Username FROM ChatSessions WHERE SessionID = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, array($sessionId));
    if ($checkStmt === false || !($row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC))) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        exit;
    }
    if ($row['Username'] !== $username) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // 1. Fetch & Delete Files
    $sqlFiles = "SELECT Attachments FROM ChatMessages WHERE SessionID = ?";
    $stmtFiles = sqlsrv_query($conn, $sqlFiles, array($sessionId));

    if ($stmtFiles !== false) {
        while ($row = sqlsrv_fetch_array($stmtFiles, SQLSRV_FETCH_ASSOC)) {
            if (!empty($row['Attachments'])) {
                $files = json_decode($row['Attachments'], true);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $path = '../uploads/' . $file['stored_name'];
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                }
            }
        }
    }

    // 2. Hard Delete Messages
    $delMsgSql = "DELETE FROM ChatMessages WHERE SessionID = ?";
    sqlsrv_query($conn, $delMsgSql, array($sessionId));

    // 3. Hard Delete Session
    $delSessSql = "DELETE FROM ChatSessions WHERE SessionID = ?";
    $stmt = sqlsrv_query($conn, $delSessSql, array($sessionId));

    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }

    echo json_encode(['success' => true]);
}
