<?php
// api/save_settings.php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$personality = $input['personality'] ?? '';
$tone = $input['tone'] ?? '';
$topics = $input['topics'] ?? '';
$emojiLevel = $input['emoji_level'] ?? '';
$customInstructions = $input['custom_instructions'] ?? '';

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'Username required']);
    exit;
}

// Check if exists
$checkSql = "SELECT COUNT(*) as count FROM UserSettings WHERE Username = ?";
$checkStmt = sqlsrv_query($conn, $checkSql, array($username));
$row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);

if ($row['count'] > 0) {
    // Update
    $sql = "UPDATE UserSettings SET Personality = ?, Tone = ?, Topics = ?, EmojiLevel = ?, CustomInstructions = ?, UpdatedAt = GETDATE() WHERE Username = ?";
    $params = array($personality, $tone, $topics, $emojiLevel, $customInstructions, $username);
} else {
    // Insert
    $sql = "INSERT INTO UserSettings (Personality, Tone, Topics, EmojiLevel, CustomInstructions, Username) VALUES (?, ?, ?, ?, ?, ?)";
    $params = array($personality, $tone, $topics, $emojiLevel, $customInstructions, $username);
}

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
} else {
    echo json_encode(['success' => true]);
}
