<?php
// Response ko JSON format mein set karna
header('Content-Type: application/json; charset=utf-8');

// --- CONFIGURATION ---
$AES_KEY = "ai-enhancer-web__aes-key"; // 24 bytes for AES-192
$AES_IV  = "aienhancer-aesiv";       // 16 bytes

$imageUrl = $_GET['img'] ?? null;
$prompt   = $_GET['prompt'] ?? null;

// Agar parameters missing hain
if (!$imageUrl || !$prompt) {
    echo json_encode([
        "creator" => "@BJ_Devs on Telegram",
        "ok" => false,
        "message" => "Missing 'img' or 'prompt' parameter."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Encrypt settings to match CryptoJS.AES.encrypt
 */
function encryptData($data, $key, $iv) {
    $json = json_encode($data);
    $encrypted = openssl_encrypt(
        $json, 
        'aes-192-cbc', 
        $key, 
        OPENSSL_RAW_DATA, 
        $iv
    );
    return base64_encode($encrypted);
}

// 1. Image download karna (CURL is better for free hosting)
$ch = curl_init($imageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$imageData = curl_exec($ch);
curl_close($ch);

if (!$imageData) {
    echo json_encode([
        "creator" => "@BJ_Devs on Telegram",
        "ok" => false,
        "message" => "Could not download image from the provided URL."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$base64Image = base64_encode($imageData);

// 2. Settings prepare aur encrypt karna
$settingsObj = [
    "prompt" => $prompt,
    "size" => "2K",
    "aspect_ratio" => "match_input_image",
    "output_format" => "jpeg",
    "max_images" => 1
];

$encryptedSettings = encryptData($settingsObj, $AES_KEY, $AES_IV);

// 3. API Request Headers
$apiHeaders = [
    "Content-Type: application/json",
    "Origin: https://aienhancer.ai",
    "Referer: https://aienhancer.ai/ai-image-editor",
    "User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G960F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
];

// 4. Task Create Request
$createPayload = [
    "model" => 2,
    "image" => "data:image/jpeg;base64," . $base64Image,
    "settings" => $encryptedSettings
];

$ch = curl_init("https://aienhancer.ai/api/v1/k/image-enhance/create");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($createPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $apiHeaders);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$createRes = curl_exec($ch);
$createData = json_decode($createRes, true);
curl_close($ch);

$taskId = $createData['data']['id'] ?? null;

if (!$taskId) {
    echo json_encode([
        "creator" => "@BJ_Devs on Telegram",
        "ok" => false,
        "message" => "Task creation failed.",
        "server_response" => $createData
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 5. Polling Result (Max 10 attempts)
$finalOutput = null;
for ($i = 0; $i < 10; $i++) {
    sleep(4); // Waiting for AI
    
    $ch = curl_init("https://aienhancer.ai/api/v1/k/image-enhance/result");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["task_id" => $taskId]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $apiHeaders);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $resData = json_decode($res, true);
    curl_close($ch);

    $status = $resData['data']['status'] ?? '';

    if ($status === 'success') {
        $finalOutput = $resData['data']['output'];
        break;
    } elseif ($status === 'failed') {
        echo json_encode([
            "creator" => "@BJ_Devs on Telegram",
            "ok" => false,
            "message" => "AI processing failed on server."
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Final Response Delivery
if ($finalOutput) {
    echo json_encode([
        "creator" => "@BJ_Devs on Telegram",
        "ok" => true,
        "result_url" => $finalOutput
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        "creator" => "@BJ_Devs on Telegram",
        "ok" => false,
        "message" => "Processing Timeout. Please try again."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>
