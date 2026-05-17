<?php
// ============================================
// DocuGuard - AI Helper Functions
// Author: Sujal Patel
// Module: Gemini Vision API integration, fuzzy matching, OTP email
// ============================================

function geminiAnalyze($imageBase64, $mimeType, $prompt, $schema) {
    $apiKey = GEMINI_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]]
            ]
        ]],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'response_schema' => $schema
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("Gemini API curl error: " . $err);

    $data = json_decode($response, true);
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Gemini API returned unexpected response: " . substr($response, 0, 500));
    }

    return json_decode($data['candidates'][0]['content']['parts'][0]['text'], true);
}

function geminiAnalyzeMulti($parts, $prompt, $schema) {
    $apiKey = GEMINI_API_KEY;
    $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.0-flash-lite'];

    foreach ($models as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $contentParts = [['text' => $prompt]];
        foreach ($parts as $part) {
            $contentParts[] = ['inline_data' => ['mime_type' => $part['mime'], 'data' => $part['base64']]];
        }

        $payload = [
            'contents' => [['parts' => $contentParts]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema' => $schema
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) continue;

        $data = json_decode($response, true);

        // Skip to next model if quota exceeded
        if (isset($data['error']['code']) && $data['error']['code'] === 429) continue;

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Gemini API returned unexpected response: " . substr($response, 0, 500));
        }

        return json_decode($data['candidates'][0]['content']['parts'][0]['text'], true);
    }

    throw new Exception("All Gemini models are quota-limited. Please wait a minute and try again.");
}

function fuzzyScore($a, $b) {
    $a = strtolower(trim($a));
    $b = strtolower(trim($b));
    if ($a === $b) return 100;
    if (empty($a) || empty($b)) return 0;
    $maxLen = max(strlen($a), strlen($b));
    $distance = levenshtein($a, $b);
    return round((1 - $distance / $maxLen) * 100);
}

function sendOtpEmail($toEmail, $otp) {
    $emailUser = EMAIL_USER;
    $emailPass = EMAIL_APP_PASSWORD;

    if (empty($emailUser) || empty($emailPass)) {
        return ['simulated' => true, 'otp' => $otp];
    }

    $subject = "DocuGuard - Your OTP Code";
    $body = "Your OTP for DocuGuard is: $otp\n\nIt expires in 5 minutes. Do not share it with anyone.";

    $headers = "From: $emailUser\r\nReply-To: $emailUser\r\nX-Mailer: PHP/" . phpversion();

    // Using PHP mail() — for Gmail SMTP use PHPMailer
    if (mail($toEmail, $subject, $body, $headers)) {
        return ['sent' => true];
    }
    return ['error' => 'Failed to send email'];
}

