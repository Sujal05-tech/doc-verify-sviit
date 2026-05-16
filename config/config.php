<?php
// ============================================
// DocuGuard - Application Configuration
// Author: Sujal Patidar
// Module: Database connection, API keys, environment setup
// ============================================

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'docuguard';

// Get free API key: https://aistudio.google.com
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');

define('EMAIL_USER', '');
define('EMAIL_APP_PASSWORD', '');

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!is_dir(__DIR__ . '/../uploads')) {
        mkdir(__DIR__ . '/../uploads', 0777, true);
    }
} catch (PDOException $e) {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'DB failed: ' . $e->getMessage()]));
    }
}
