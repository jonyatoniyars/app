<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/helpers.php';
session_write_close();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../..');
$dotenv->load();

$user = require_auth();
$pdo  = db();

// Verify AI Permission
$s = $pdo->prepare("SELECT ai_enabled FROM users WHERE id = :id");
$s->execute([':id' => $user['id']]);
if (!(bool)$s->fetchColumn()) {
    json_error('FORBIDDEN', 'AI feature not enabled', 403);
}

$b = json_body();
$prompt = $b['prompt'] ?? '';
if (!$prompt) json_error('BAD_REQUEST', 'Prompt required');

$apiKey = $_ENV['GOOGLE_AI_API_KEY'];
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo $response;
} else {
    json_error('SERVER_ERROR', 'AI Service Error', $httpCode);
}
