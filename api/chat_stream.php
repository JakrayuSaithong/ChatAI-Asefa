<?php
// api/chat_stream.php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx
@ini_set('output_buffering', 0);
@ini_set('implicit_flush', 1);
ob_implicit_flush(true);

require_once 'config.php';
require_once 'db_connect.php';

set_time_limit(0);

// --- Input Parsing ---
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$messages = $input['messages'] ?? [];
$model = $input['model'] ?? 'google/gemini-2.0-flash-exp:free';
$username = $input['username'] ?? '';
$sessionId = $input['session_id'] ?? null;
$lastUserMsg = empty($messages) ? '' : end($messages)['content'];

if (!$username) {
    sendError("Username is required.");
    exit;
}

// --- 1. Session & User Message Saving ---
if (!$sessionId) {
    $title = mb_substr($lastUserMsg, 0, 15, "UTF-8");
    if (mb_strlen($lastUserMsg, "UTF-8") > 15) $title .= '...';
    $sql = "INSERT INTO ChatSessions (Username, Title) OUTPUT INSERTED.SessionID VALUES (?, ?)";
    $params = array($username, $title);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        sendError("Failed to create session.");
        exit;
    }
    $sessionId = $row['SessionID'];
} else {
    // Session exists, update Title to match new topic (optional, per user request)
    $title = mb_substr($lastUserMsg, 0, 15, "UTF-8");
    if (mb_strlen($lastUserMsg, "UTF-8") > 15) $title .= '...';
    $sql = "UPDATE ChatSessions SET Title = ? WHERE SessionID = ?";
    $params = array($title, $sessionId);
    sqlsrv_query($conn, $sql, $params);
}
$sql = "INSERT INTO ChatMessages (SessionID, Role, Content) VALUES (?, ?, ?)";
$params = array($sessionId, 'user', $lastUserMsg);
if (!sqlsrv_query($conn, $sql, $params)) {
    sendError("Failed to save message.");
    exit;
}
sendSSE('session_id', $sessionId);

// --- 2. OpenRouter Call ---
$maxRetries = 5;
$retryCount = 0;
$responseContent = "";

while ($retryCount < $maxRetries) {
    $ch = curl_init();
    $data = ['model' => $model, 'messages' => $messages, 'stream' => true];

    curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if (defined('HTTP_PROXY') && HTTP_PROXY) curl_setopt($ch, CURLOPT_PROXY, HTTP_PROXY);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . OPENROUTER_API_KEY,
        'Content-Type: application/json',
        'HTTP-Referer: http://localhost',
        'X-Title: AsefaChatAI'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // WRITE FUNCTION
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$responseContent) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 429) return 0; // Abort

        if ($httpCode !== 200) {
            $responseContent .= $data; // Capture error
            return strlen($data);
        }

        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $jsonStr = substr($line, 6);
                if ($jsonStr === '[DONE]') continue;
                $json = json_decode($jsonStr, true);
                if (isset($json['choices'][0]['delta']['content'])) {
                    $chunk = $json['choices'][0]['delta']['content'];
                    if ($chunk !== null) {
                        $responseContent .= $chunk;
                        sendSSE('content', $chunk);
                    }
                }
            }
        }
        ob_flush();
        flush(); // CRITICAL for streaming
        return strlen($data);
    });

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 429) {
        $retryCount++;
        if ($retryCount < $maxRetries) {
            $waitTime = pow(2, $retryCount) + rand(1, 3);
            sendSSE('status', "Server busy ($retryCount/$maxRetries). Retrying in $waitTime s...");
            ob_flush();
            flush();
            sleep($waitTime);
            continue;
        } else {
            if ($model === 'google/gemini-2.0-flash-exp:free') {
                $model = 'google/gemini-exp-1206:free';
                $retryCount = 0;
                $maxRetries = 2;
                sendSSE('status', "Switching to backup model...");
                ob_flush();
                flush();
                continue;
            }
            sendError("Server busy. Please try again later.");
            exit;
        }
    }

    if (!$success || $httpCode !== 200) {
        // Parse error
        $errMsg = "API Error ($httpCode)";
        $json = json_decode($responseContent, true);
        if (isset($json['error']['message'])) $errMsg .= ": " . $json['error']['message'];
        sendError($errMsg);
        exit;
    }

    break; // Success
}

// --- 3. Save AI Message ---
if ($responseContent) {
    $sql = "INSERT INTO ChatMessages (SessionID, Role, Content) VALUES (?, ?, ?)";
    $params = array($sessionId, 'model', $responseContent);
    sqlsrv_query($conn, $sql, $params);
}
sendSSE('done', '[DONE]');

// --- Helper ---
function sendSSE($type, $data)
{
    echo "data: " . json_encode(['type' => $type, 'data' => $data]) . "\n\n";
    ob_flush();
    flush();
}
function sendError($msg)
{
    sendSSE('error', $msg);
}
