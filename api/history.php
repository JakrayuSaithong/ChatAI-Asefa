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

    $sql = "UPDATE ChatSessions SET IsActive = 0 WHERE SessionID = ? AND Username = ?";
    $params = array($sessionId, $username);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }

    // เช็กว่ามีแถวถูกอัปเดตจริงไหม (ช่วยบอกกรณี session ไม่ตรง user)
    $rows = sqlsrv_rows_affected($stmt);
    echo json_encode(['success' => ($rows > 0), 'affected' => $rows]);
}
