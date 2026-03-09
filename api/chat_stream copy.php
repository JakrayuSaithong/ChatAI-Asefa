<?php
/**
 * ASEFA AI Chat Stream API
 * 
 * @version 2.1.0 - Fixed Multimodal Support
 * @author ASEFA Development Team
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
@ini_set('output_buffering', 0);
@ini_set('implicit_flush', 1);
ob_implicit_flush(true);

while (ob_get_level()) ob_end_clean();

// Padding สำหรับ Nginx buffering
echo ":" . str_repeat(" ", 2048) . "\n\n";
flush();

require_once 'config.php';
require_once 'db_connect.php';

set_time_limit(0);

// ============================================================
// MODEL CONFIGURATION
// ============================================================

/**
 * Models ที่รองรับ Web Search Tool (OpenRouter)
 */
$WEB_SEARCH_MODELS = [
    'anthropic/claude-sonnet-4',
    'anthropic/claude-3.5-sonnet',
    'anthropic/claude-3.5-sonnet:beta',
    'anthropic/claude-3-opus',
    'anthropic/claude-3-haiku',
    'google/gemini-2.5-pro',
    'google/gemini-2.5-flash',
    'google/gemini-3-pro',
    'google/gemini-3-flash-preview',
    'openai/gpt-4o',
    'openai/gpt-4o-mini',
];

/**
 * Models ที่มี Web Search ในตัว (ไม่ต้องเพิ่ม :online)
 */
$BUILT_IN_SEARCH_MODELS = [
    'perplexity/sonar-pro',
    'perplexity/sonar',
    'perplexity/sonar-reasoning',
    'perplexity/sonar-deep-research',
];

/**
 * Models ที่รองรับ Vision/Multimodal
 */
$VISION_MODELS = [
    'anthropic/claude-sonnet-4',
    'anthropic/claude-3.5-sonnet',
    'anthropic/claude-3.5-sonnet:beta',
    'anthropic/claude-3-opus',
    'anthropic/claude-3-haiku',
    'google/gemini-2.5-pro',
    'google/gemini-2.5-flash',
    'google/gemini-3-pro',
    'google/gemini-3-flash-preview',
    'perplexity/sonar-pro',
    'openai/gpt-4o',
    'openai/gpt-4o-mini',
];

/**
 * Fallback models เมื่อ primary model ถูก rate limit
 */
$FALLBACK_MODELS = [
    'google/gemini-2.0-flash-exp:free' => 'google/gemini-exp-1206:free',
    'anthropic/claude-sonnet-4' => 'anthropic/claude-3.5-sonnet',
];

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function sendSSE($type, $data) {
    echo "data: " . json_encode(['type' => $type, 'data' => $data], JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function sendError($msg) {
    sendSSE('error', $msg);
}

function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/debug_multimodal.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }
    $logMessage .= "\n---\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function supportsWebSearchTool($model, $webSearchModels) {
    return in_array($model, $webSearchModels);
}

function hasBuiltInSearch($model, $builtInModels) {
    return in_array($model, $builtInModels);
}

function supportsVision($model, $visionModels) {
    return in_array($model, $visionModels);
}

/**
 * ตรวจสอบว่า messages มี multimodal content หรือไม่
 */
function hasMultimodalContent($messages) {
    foreach ($messages as $msg) {
        if (isset($msg['files']) && !empty($msg['files'])) {
            return true;
        }
        if (isset($msg['attachments']) && !empty($msg['attachments'])) {
            return true;
        }
        // ตรวจสอบว่า content เป็น array ที่มี image_url
        if (is_array($msg['content'] ?? null)) {
            foreach ($msg['content'] as $item) {
                if (isset($item['type']) && $item['type'] === 'image_url') {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * แปลง Base64 image ให้อยู่ในรูปแบบที่ถูกต้อง
 */
function formatImageUrl($base64Data) {
    // ถ้าเป็น data URL อยู่แล้ว ใช้ได้เลย
    if (strpos($base64Data, 'data:') === 0) {
        return $base64Data;
    }
    // ถ้าเป็น raw base64 ให้เพิ่ม prefix
    return 'data:image/jpeg;base64,' . $base64Data;
}

/**
 * แก้ไข encoding issues ใน array
 */
function fixEncoding($data) {
    if (is_array($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = fixEncoding($value);
        }
        return $result;
    } elseif (is_string($data)) {
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8', 'auto');
        }
        // Remove invalid UTF-8 sequences
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }
    return $data;
}

// ============================================================
// INPUT PARSING
// ============================================================

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$messages = $input['messages'] ?? [];
$model = $input['model'] ?? 'anthropic/claude-sonnet-4';
$username = $input['username'] ?? '';
$sessionId = $input['session_id'] ?? null;
$enableWebSearch = $input['web_search'] ?? true;

$lastUserMsg = '';
foreach ($messages as $msg) {
    if (($msg['role'] ?? '') === 'user') {
        $content = $msg['content'] ?? '';
        if (is_string($content)) {
            $lastUserMsg = $content;
        } elseif (is_array($content)) {
            foreach ($content as $item) {
                if (isset($item['type']) && $item['type'] === 'text') {
                    $lastUserMsg = $item['text'] ?? '';
                    break;
                }
            }
        }
    }
}
// ถ้ายังไม่ได้ ลอง get จาก message สุดท้าย
if (empty($lastUserMsg) && !empty($messages)) {
    $lastMsg = end($messages);
    $content = $lastMsg['content'] ?? '';
    if (is_string($content)) {
        $lastUserMsg = $content;
    }
}

if (!$username) {
    sendError("Username is required.");
    exit;
}

// ============================================================
// FILE HANDLING - ตรวจจับไฟล์แนบ
// ============================================================

$lastMsgIndex = count($messages) - 1;
$filesData = [];

// หาไฟล์จาก message สุดท้าย (user message)
for ($i = count($messages) - 1; $i >= 0; $i--) {
    $msg = $messages[$i];
    if (($msg['role'] ?? '') === 'user') {
        if (isset($msg['files']) && !empty($msg['files'])) {
            $filesData = $msg['files'];
        } elseif (isset($msg['file'])) {
            $filesData[] = $msg['file'];
        }
        break;
    }
}

debugLog("Files detected", ['count' => count($filesData), 'model' => $model]);

$dbContent = $lastUserMsg;
if (!empty($filesData)) {
    $fileNames = array_column($filesData, 'name');
    $dbContent .= "\n[Attached: " . implode(', ', $fileNames) . "]";
    sendSSE('status', '📎 กำลังประมวลผลไฟล์แนบ...');
}

// Save files to disk
$attachments = [];
if (!empty($filesData)) {
    if (!is_dir('../uploads')) mkdir('../uploads', 0777, true);

    foreach ($filesData as $file) {
        $originalName = $file['name'] ?? 'unnamed';
        $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
        $storedName = date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $targetPath = '../uploads/' . $storedName;

        $base64Val = $file['data'] ?? '';
        if (strpos($base64Val, 'base64,') !== false) {
            $base64Val = explode('base64,', $base64Val)[1];
        }

        if (!empty($base64Val)) {
            file_put_contents($targetPath, base64_decode($base64Val));
            $attachments[] = [
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'upload_date' => date('Y-m-d H:i:s')
            ];
        }
    }
}
$attachmentsJSON = !empty($attachments) ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : null;

// ============================================================
// SESSION MANAGEMENT
// ============================================================

if (!$sessionId) {
    $title = mb_substr($dbContent, 0, 15, "UTF-8");
    if (mb_strlen($dbContent, "UTF-8") > 15) $title .= '...';

    $sql = "INSERT INTO ChatSessions (Username, Title) OUTPUT INSERTED.SessionID VALUES (?, ?)";
    $params = [$username, $title];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        sendError("Failed to create session.");
        exit;
    }
    $sessionId = $row['SessionID'];
} else {
    $newTitle = mb_substr($dbContent, 0, 15, "UTF-8");
    if (mb_strlen($dbContent, "UTF-8") > 15) $newTitle .= '...';

    $sqlUpdate = "UPDATE ChatSessions SET Title = ?, CreatedAt = GETDATE() WHERE SessionID = ?";
    sqlsrv_query($conn, $sqlUpdate, [$newTitle, $sessionId]);
}

sendSSE('session_id', $sessionId);

// ============================================================
// MESSAGE TRANSFORMATION (Multimodal Support) - FIXED
// ============================================================

$hasImages = false;

foreach ($messages as $index => &$msg) {
    // ข้าม system message
    if (($msg['role'] ?? '') === 'system') {
        continue;
    }
    
    $contentParts = [];
    $originalText = '';
    
    // ดึง text เดิมออกมา
    if (isset($msg['content'])) {
        if (is_string($msg['content'])) {
            $originalText = $msg['content'];
        } elseif (is_array($msg['content'])) {
            // content เป็น array อยู่แล้ว (multimodal format)
            foreach ($msg['content'] as $part) {
                if (isset($part['type'])) {
                    $contentParts[] = $part;
                    if ($part['type'] === 'image_url') {
                        $hasImages = true;
                    }
                }
            }
        }
    }
    
    // ถ้ามี text เดิม ให้เพิ่มเป็น part แรก
    if (!empty($originalText) && empty($contentParts)) {
        $contentParts[] = [
            'type' => 'text',
            'text' => $originalText
        ];
    }

    // ============================================================
    // Handle new file uploads (Base64 from frontend)
    // ============================================================
    if (isset($msg['files']) && !empty($msg['files'])) {
        debugLog("Processing files for message $index", ['fileCount' => count($msg['files'])]);
        
        foreach ($msg['files'] as $file) {
            $fileType = $file['type'] ?? '';
            $fileName = $file['name'] ?? 'file';
            $fileData = $file['data'] ?? '';
            
            debugLog("File info", ['name' => $fileName, 'type' => $fileType]);
            
            // รูปภาพ
            if (strpos($fileType, 'image') === 0) {
                $hasImages = true;
                $imageUrl = formatImageUrl($fileData);
                
                $contentParts[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $imageUrl
                    ]
                ];
                
                debugLog("Added image_url", ['urlPrefix' => substr($imageUrl, 0, 50) . '...']);
            } 
            // ไฟล์ Text-based (txt, csv, json, etc.)
            else {
                $decoded = '';
                if (strpos($fileData, 'base64,') !== false) {
                    $decoded = base64_decode(explode('base64,', $fileData)[1]);
                } elseif (!empty($fileData)) {
                    $decoded = base64_decode($fileData);
                }
                
                if (!empty($decoded)) {
                    $contentParts[] = [
                        'type' => 'text',
                        'text' => "\n\n📄 **ไฟล์: {$fileName}**\n```\n" . $decoded . "\n```"
                    ];
                }
            }
        }
        
        // ลบ files key ออก
        unset($msg['files']);
    }
    
    // ============================================================
    // Handle history attachments (Read from disk)
    // ============================================================
    if (isset($msg['attachments']) && !empty($msg['attachments'])) {
        foreach ($msg['attachments'] as $att) {
            $storedName = $att['stored_name'] ?? '';
            $originalName = $att['original_name'] ?? 'file';
            $path = '../uploads/' . $storedName;
            
            if (file_exists($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                $textExts = ['txt', 'md', 'csv', 'json', 'js', 'php', 'html', 'css', 'py', 'c', 'cpp', 'java', 'xml', 'sql'];
                
                if (in_array($ext, $imageExts)) {
                    $hasImages = true;
                    $mimeTypes = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'bmp' => 'image/bmp'
                    ];
                    $mime = $mimeTypes[$ext] ?? 'image/jpeg';
                    $b64 = base64_encode(file_get_contents($path));
                    
                    $contentParts[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:$mime;base64,$b64"
                        ]
                    ];
                } elseif (in_array($ext, $textExts)) {
                    $textContent = file_get_contents($path);
                    $contentParts[] = [
                        'type' => 'text',
                        'text' => "\n\n📄 **ไฟล์: {$originalName}**\n```\n" . $textContent . "\n```"
                    ];
                }
            }
        }
        
        unset($msg['attachments']);
    }
    
    // ============================================================
    // Apply transformed content
    // ============================================================
    if (!empty($contentParts)) {
        // ถ้ามีแค่ text part เดียว และไม่มี image ให้ใช้ string แทน array
        if (count($contentParts) === 1 && $contentParts[0]['type'] === 'text' && !$hasImages) {
            $msg['content'] = $contentParts[0]['text'];
        } else {
            $msg['content'] = $contentParts;
        }
    }
}
unset($msg);

debugLog("Final messages structure", [
    'hasImages' => $hasImages,
    'messageCount' => count($messages),
    'firstMsgRole' => $messages[0]['role'] ?? 'none',
    'lastMsgContentType' => is_array(end($messages)['content'] ?? '') ? 'array' : 'string'
]);

// ============================================================
// DETERMINE API MODEL (Handle Web Search vs Multimodal)
// ============================================================

$modelForAPI = $model;

/**
 * IMPORTANT: เมื่อมี multimodal content (รูปภาพ) 
 * ไม่ควรใช้ :online suffix เพราะอาจทำให้ไม่ทำงาน
 */
if ($hasImages) {
    // มีรูปภาพ - ใช้ model ปกติ ไม่ต่อ :online
    $modelForAPI = $model;
    sendSSE('status', '🖼️ กำลังวิเคราะห์รูปภาพ...');
    
    // ตรวจสอบว่า model รองรับ vision หรือไม่
    if (!supportsVision($model, $VISION_MODELS)) {
        sendError("Model $model ไม่รองรับการอ่านรูปภาพ กรุณาเลือก model อื่น");
        exit;
    }
    
    debugLog("Using model without :online for images", ['model' => $modelForAPI]);
} else {
    // ไม่มีรูปภาพ - ใช้ web search ได้
    if ($enableWebSearch && !hasBuiltInSearch($model, $BUILT_IN_SEARCH_MODELS)) {
        $modelForAPI = $model . ':online';
    }
}

// Perplexity models ใช้ชื่อปกติ
if (hasBuiltInSearch($model, $BUILT_IN_SEARCH_MODELS)) {
    $modelForAPI = $model;
}

debugLog("Final model for API", ['model' => $modelForAPI, 'hasImages' => $hasImages]);

// ============================================================
// API REQUEST
// ============================================================

$maxRetries = 3;
$retryCount = 0;
$responseContent = "";

while ($retryCount < $maxRetries) {
    $ch = curl_init();
    
    // Prepare payload
    $data = [
        'model' => $modelForAPI,
        'temperature' => 0.7,
        'top_p' => 0.9,
        'max_tokens' => 4096,
        'messages' => $messages,
        'stream' => true
    ];

    // Debug: Log messages structure (truncate long content)
    $debugMessages = array_map(function($msg) {
        $debugMsg = [
            'role' => $msg['role'] ?? 'unknown',
            'content_type' => is_array($msg['content'] ?? '') ? 'array' : 'string'
        ];
        if (is_array($msg['content'] ?? null)) {
            $debugMsg['content_parts'] = count($msg['content']);
            $debugMsg['part_types'] = array_map(function($p) { 
                return $p['type'] ?? 'unknown'; 
            }, $msg['content']);
        } else {
            $debugMsg['content_length'] = strlen($msg['content'] ?? '');
        }
        return $debugMsg;
    }, $messages);
    
    debugLog("API Request - Messages Structure", $debugMessages);

    // Debug: Log the request
    debugLog("API Request", [
        'model' => $modelForAPI,
        'messagesCount' => count($messages),
        'hasImages' => $hasImages
    ]);

    curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 128);

    if (defined('HTTP_PROXY') && HTTP_PROXY) {
        curl_setopt($ch, CURLOPT_PROXY, HTTP_PROXY);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . OPENROUTER_API_KEY,
        'Content-Type: application/json',
        'HTTP-Referer: ' . (defined('SITE_URL') ? SITE_URL : 'http://localhost'),
        'X-Title: ASEFA AI Assistant'
    ]);
    
    // Ensure proper encoding before json_encode
    $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    
    // Check for json_encode errors
    if ($jsonPayload === false) {
        $jsonError = json_last_error_msg();
        debugLog("JSON Encode Error", ['error' => $jsonError]);
        
        // Try to fix encoding issues
        $data = fixEncoding($data);
        $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        
        if ($jsonPayload === false) {
            sendError("ไม่สามารถประมวลผลข้อมูลได้: " . json_last_error_msg());
            exit;
        }
    }
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    
    // Debug: Log payload size
    debugLog("Payload size", ['bytes' => strlen($jsonPayload), 'jsonError' => json_last_error_msg()]);

    // ============================================================
    // STREAMING RESPONSE HANDLER
    // ============================================================
    
    $buffer = "";
    $isSearching = false;
    $errorResponse = "";
    
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$responseContent, &$buffer, &$isSearching, &$errorResponse) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode === 429) {
            $errorResponse = $data;
            return 0;
        }
        
        if ($httpCode !== 200 && $httpCode !== 0) {
            $errorResponse .= $data;
            return strlen($data);
        }

        $buffer .= $data;

        while (($newlinePos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $newlinePos);
            $buffer = substr($buffer, $newlinePos + 1);
            $line = trim($line);

            if (strpos($line, 'data: ') === 0) {
                $jsonStr = substr($line, 6);
                if ($jsonStr === '[DONE]') continue;

                $json = json_decode($jsonStr, true);
                if (!$json) continue;

                // Handle content delta
                if (isset($json['choices'][0]['delta']['content'])) {
                    $chunk = $json['choices'][0]['delta']['content'];
                    if ($chunk !== null) {
                        $responseContent .= $chunk;
                        sendSSE('content', $chunk);
                    }
                }
                
                // Handle Web Search status
                if (isset($json['choices'][0]['delta']['tool_calls'])) {
                    $toolCalls = $json['choices'][0]['delta']['tool_calls'];
                    foreach ($toolCalls as $toolCall) {
                        if (isset($toolCall['function']['name']) && $toolCall['function']['name'] === 'web_search') {
                            if (!$isSearching) {
                                $isSearching = true;
                                sendSSE('status', '🔍 กำลังค้นหาข้อมูลจากเว็บ...');
                            }
                        }
                    }
                }
                
                // Handle finish reason
                if (isset($json['choices'][0]['finish_reason'])) {
                    $finishReason = $json['choices'][0]['finish_reason'];
                    if ($finishReason === 'tool_calls') {
                        sendSSE('status', '🔄 กำลังประมวลผลข้อมูล...');
                    }
                }
            }
        }

        return strlen($data);
    });

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    debugLog("API Response", [
        'httpCode' => $httpCode,
        'success' => $success,
        'curlError' => $curlError,
        'responseLength' => strlen($responseContent)
    ]);

    // ============================================================
    // ERROR HANDLING & RETRY
    // ============================================================
    
    if ($httpCode === 429) {
        $retryCount++;
        if ($retryCount < $maxRetries) {
            $waitTime = pow(2, $retryCount) + rand(1, 3);
            sendSSE('status', "⏳ Server busy ($retryCount/$maxRetries). Retrying in $waitTime s...");
            sleep($waitTime);
            continue;
        } else {
            if (isset($FALLBACK_MODELS[$model])) {
                $model = $FALLBACK_MODELS[$model];
                $modelForAPI = $hasImages ? $model : $model . ':online';
                $retryCount = 0;
                $maxRetries = 2;
                sendSSE('status', "🔄 Switching to backup model...");
                continue;
            }
            sendError("Server busy. Please try again later.");
            exit;
        }
    }

    if (!$success || ($httpCode !== 200 && $httpCode !== 0)) {
        $errMsg = "API Error ($httpCode)";
        if ($curlError) $errMsg .= ": $curlError";
        
        // Try to parse error response
        if (!empty($errorResponse)) {
            $json = json_decode($errorResponse, true);
            if (isset($json['error']['message'])) {
                $errMsg = $json['error']['message'];
            }
        }
        
        debugLog("API Error", ['error' => $errMsg, 'response' => $errorResponse]);
        sendError($errMsg);
        exit;
    }

    break; // Success
}

// ============================================================
// SAVE TO DATABASE
// ============================================================

// Save User Message
$sql = "INSERT INTO ChatMessages (SessionID, Role, Content, Attachments) VALUES (?, ?, ?, ?)";
$params = [$sessionId, 'user', $dbContent, $attachmentsJSON];
sqlsrv_query($conn, $sql, $params);

// Save AI Response
if ($responseContent) {
    $sql = "INSERT INTO ChatMessages (SessionID, Role, Content) VALUES (?, ?, ?)";
    $params = [$sessionId, 'model', $responseContent];
    sqlsrv_query($conn, $sql, $params);
}

sendSSE('done', '[DONE]');