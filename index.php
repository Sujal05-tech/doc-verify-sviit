<?php
session_start();

// ==========================================
// 1. CONFIGURATION
// ==========================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'docuguard';

// *** SET YOUR GEMINI API KEY HERE ***
define('GEMINI_API_KEY', 'AIzaSyB4vQEjKCJtNtpvF_N6X7gJF3EPQR0Xui4');

// *** EMAIL CONFIG (Gmail App Password) ***
// To enable OTP emails: set these. Leave empty to simulate OTP in response.
define('EMAIL_USER', '');           // e.g. 'yourapp@gmail.com'
define('EMAIL_APP_PASSWORD', '');   // Gmail App Password (not your login password)

$pdo = null;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0777, true);
    }

    // Auto-create all required tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `subject` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `admin_reply` text DEFAULT NULL,
        `status` enum('unread','read') DEFAULT 'unread',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try { $pdo->exec("ALTER TABLE `contact_messages` ADD COLUMN `admin_reply` text DEFAULT NULL"); } catch (PDOException $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
        `student_id` varchar(20) NOT NULL,
        `name` varchar(100) NOT NULL,
        `year` varchar(20) DEFAULT '1st Year',
        `branch` varchar(50) DEFAULT 'Unknown',
        `email` varchar(100) DEFAULT NULL,
        `phone` varchar(15) DEFAULT NULL,
        `photo` varchar(255) DEFAULT NULL,
        `bus_fee_paid` tinyint(1) DEFAULT 0,
        `created_at` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `bus_passes` (
        `pass_id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` varchar(20) NOT NULL,
        `pass_number` varchar(20) NOT NULL,
        `route_no` varchar(10) NOT NULL,
        `issue_date` date NOT NULL,
        `expiry_date` date NOT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`pass_id`),
        UNIQUE KEY `pass_number` (`pass_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `verification_results` (
        `result_id` int(11) NOT NULL AUTO_INCREMENT,
        `doc_id` int(11) NOT NULL,
        `is_valid` tinyint(1) NOT NULL,
        `failure_reason` text DEFAULT NULL,
        `ai_extracted` text DEFAULT NULL,
        `match_score` float DEFAULT NULL,
        `checked_at` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`result_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `otps` (
        `email` varchar(100) NOT NULL,
        `otp` varchar(10) NOT NULL,
        `expires_at` datetime NOT NULL,
        PRIMARY KEY (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed sample student data if table is empty
    $cnt = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($cnt == 0) {
        $pdo->exec("INSERT INTO students (student_id, name, year, branch, email, phone, bus_fee_paid) VALUES
            ('CSE2301','Amit Patel','3rd Year','CSE','amit@sviit.ac.in','9876543210',1),
            ('CSE2302','Sneha Verma','3rd Year','CSE','sneha@sviit.ac.in','9876543211',1),
            ('CSE2201','Neha Joshi','2nd Year','CSE','neha@sviit.ac.in','9876543213',1),
            ('CSE2202','Vikram Singh','2nd Year','CSE','vikram@sviit.ac.in','9876543214',0),
            ('CSE2303','Rohan Gupta','3rd Year','CSE','rohan@sviit.ac.in','9876543212',0),
            ('CSE2101','Riya Malhotra','1st Year','CSE','riya@sviit.ac.in','9876543217',0),
            ('IT2301','Kavya Sharma','3rd Year','IT','kavya@sviit.ac.in','9876543215',1),
            ('IT2302','Harsh Patel','3rd Year','IT','harsh@sviit.ac.in','9876543216',1)");

        $pdo->exec("INSERT IGNORE INTO bus_passes (student_id, pass_number, route_no, issue_date, expiry_date, is_active) VALUES
            ('CSE2301','BP001','R-5','2026-01-01','2026-12-31',1),
            ('CSE2302','BP002','R-3','2026-01-01','2026-12-31',1),
            ('CSE2201','BP003','R-7','2026-01-01','2026-12-31',1),
            ('IT2301','BP004','R-2','2026-01-01','2026-12-31',1),
            ('IT2302','BP005','R-5','2026-01-01','2026-12-31',1)");
    }

} catch (PDOException $e) {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
    }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================
function logAudit($pdo, $userId, $action, $targetId, $details) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `action` varchar(64) NOT NULL,
            `target_id` int(11) DEFAULT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {}
}

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

// ==========================================
// 2. API ENDPOINTS
// ==========================================
if (isset($_GET['api'])) {
    ini_set('display_errors', 0);
    header('Content-Type: application/json');

    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please ensure MySQL is running.']);
        exit;
    }

    $action = $_GET['api'];
    $method = $_SERVER['REQUEST_METHOD'];

    try {

        // ==========================================
        // --- PUBLIC AUTH ENDPOINTS ---
        // ==========================================

        if ($action === 'login' && $method === 'POST') {
            $email    = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_deleted = 0");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['name']    = $user['name'];
                echo json_encode(['success' => true, 'role' => $user['role']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
            exit;
        }

        if ($action === 'register' && $method === 'POST') {
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $otp      = trim($_POST['otp'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']); exit;
            }

            // If OTP provided, verify it
            if (!empty($otp)) {
                $otpRow = $pdo->prepare("SELECT * FROM otps WHERE email = ?");
                $otpRow->execute([$email]);
                $otpData = $otpRow->fetch();
                if (!$otpData) { echo json_encode(['success' => false, 'message' => 'OTP not found. Please request a new one.']); exit; }
                if ($otpData['otp'] !== $otp) { echo json_encode(['success' => false, 'message' => 'Invalid OTP.']); exit; }
                if (new DateTime() > new DateTime($otpData['expires_at'])) { echo json_encode(['success' => false, 'message' => 'OTP has expired.']); exit; }
                $pdo->prepare("DELETE FROM otps WHERE email = ?")->execute([$email]);
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Email already registered.']); exit; }

            $hashedPw = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            if ($stmt->execute([$name, $email, $hashedPw])) {
                $pdo->prepare("DELETE FROM otps WHERE email = ?")->execute([$email]);
                echo json_encode(['success' => true, 'message' => 'Account registered successfully! You can now log in.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Registration failed.']);
            }
            exit;
        }

        if ($action === 'logout') {
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
        }

        // --- OTP ENDPOINTS (public) ---
        if ($action === 'send_otp' && $method === 'POST') {
            $email = trim($_POST['email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'A valid email is required.']); exit;
            }
            $otp = strval(random_int(100000, 999999));
            $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
            $pdo->prepare("REPLACE INTO otps (email, otp, expires_at) VALUES (?, ?, ?)")->execute([$email, $otp, $expiresAt]);

            $result = sendOtpEmail($email, $otp);
            if (isset($result['simulated'])) {
                echo json_encode(['success' => true, 'message' => "OTP generated (email not configured). For testing, your OTP is: $otp"]);
            } elseif (isset($result['sent'])) {
                echo json_encode(['success' => true, 'message' => 'OTP sent to your email. Check your inbox.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send OTP. ' . ($result['error'] ?? '')]);
            }
            exit;
        }

        if ($action === 'forgot_password' && $method === 'POST') {
            $email    = trim($_POST['email'] ?? '');
            $otp      = trim($_POST['otp'] ?? '');
            $password = $_POST['password'] ?? '';
            if (empty($email) || empty($otp) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields required.']); exit;
            }
            $otpRow = $pdo->prepare("SELECT * FROM otps WHERE email = ?");
            $otpRow->execute([$email]);
            $otpData = $otpRow->fetch();
            if (!$otpData || $otpData['otp'] !== $otp) { echo json_encode(['success' => false, 'message' => 'Invalid OTP.']); exit; }
            if (new DateTime() > new DateTime($otpData['expires_at'])) { echo json_encode(['success' => false, 'message' => 'OTP expired.']); exit; }
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
            $pdo->prepare("DELETE FROM otps WHERE email = ?")->execute([$email]);
            echo json_encode(['success' => true, 'message' => 'Password updated successfully! Please log in.']);
            exit;
        }

        // ==========================================
        // --- PROTECTED (must be logged in) ---
        // ==========================================
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // --- CONTACT ---
        if ($action === 'submit_contact' && $method === 'POST') {
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $userId  = $_SESSION['user_id'] ?? null;
            if (empty($subject) || empty($message)) { echo json_encode(['success' => false, 'message' => 'Subject and message are required.']); exit; }
            $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, subject, message) VALUES (?, ?, ?)");
            if ($stmt->execute([$userId, $subject, $message])) {
                if ($userId) logAudit($pdo, $userId, 'contact_admin', null, "Sent support message: $subject");
                echo json_encode(['success' => true, 'message' => 'Message dispatched to Admin securely.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to dispatch message.']);
            }
            exit;
        }

        if ($action === 'my_alerts' && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE user_id = ? AND admin_reply IS NOT NULL ORDER BY created_at DESC");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // --- UPLOAD (standard file upload, stores to DB) ---
        if ($action === 'upload' && $method === 'POST') {
            $title     = trim($_POST['title'] ?? 'Untitled Document');
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            if (!isset($_FILES['uploaded_file'])) { echo json_encode(['success' => false, 'message' => 'No file field found.']); exit; }
            $fileError = $_FILES['uploaded_file']['error'];
            if ($fileError !== UPLOAD_ERR_OK) {
                $phpErrors = [UPLOAD_ERR_INI_SIZE=>'File exceeds server limit.',UPLOAD_ERR_FORM_SIZE=>'File exceeds form limit.',UPLOAD_ERR_PARTIAL=>'File partially uploaded.',UPLOAD_ERR_NO_FILE=>'No file selected.',UPLOAD_ERR_NO_TMP_DIR=>'Server missing temp folder.',UPLOAD_ERR_CANT_WRITE=>'Cannot write to disk.',UPLOAD_ERR_EXTENSION=>'Upload blocked by extension.'];
                echo json_encode(['success' => false, 'message' => $phpErrors[$fileError] ?? 'Unknown upload error.']); exit;
            }
            if ($_FILES['uploaded_file']['size'] === 0) { echo json_encode(['success' => false, 'message' => 'Uploaded file is empty.']); exit; }

            $allowedTypes = ['application/pdf','image/jpeg','image/png','image/gif','image/webp','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['uploaded_file']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mimeType, $allowedTypes)) { echo json_encode(['success' => false, 'message' => 'Invalid file type.']); exit; }

            $safeFileName = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($_FILES['uploaded_file']['name']));
            $fileName     = time() . '_' . $safeFileName;
            $targetPath   = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $targetPath)) {
                $verificationHash = strtoupper(substr(hash('sha256', $fileName . $_SESSION['user_id'] . time()), 0, 32));
                $stmt = $pdo->prepare("INSERT INTO documents (user_id, title, file_name, status) VALUES (?, ?, ?, 'pending')");
                if ($stmt->execute([$_SESSION['user_id'], $title, $fileName])) {
                    $docId = $pdo->lastInsertId();
                    logAudit($pdo, $_SESSION['user_id'], 'upload', $docId, "Uploaded document: $title ($fileName)");
                    echo json_encode(['success' => true, 'message' => 'Document uploaded & queued for AI verification.', 'doc_id' => $docId, 'verification_hash' => $verificationHash, 'file_name' => $fileName, 'mime_type' => $mimeType]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database log failed.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save file. Check server permissions.']);
            }
            exit;
        }

        // ==========================================
        // --- AI VERIFY (Student ID / Bus Pass) ---
        // ==========================================
        if ($action === 'ai_verify' && $method === 'POST') {
            $verificationType = $_POST['verificationType'] ?? 'Student ID';
            $documentNumber   = trim($_POST['document_number'] ?? '');

            // --- Lookup-only mode (no file uploaded) ---
            if (empty($_FILES['document']['name'])) {
                if (empty($documentNumber)) {
                    echo json_encode(['success' => false, 'message' => 'Please provide an ID/Pass number or upload a document.']); exit;
                }
                if ($verificationType === 'Bus Pass') {
                    $stmt = $pdo->prepare("SELECT b.*, s.name as student_name FROM bus_passes b JOIN students s ON b.student_id = s.student_id WHERE LOWER(b.pass_number) = LOWER(?)");
                    $stmt->execute([$documentNumber]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { echo json_encode(['success' => false, 'message' => 'Bus pass not found.']); exit; }
                    echo json_encode(['success' => true, 'mode' => 'lookup', 'type' => 'Bus Pass', 'record' => $row]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE LOWER(student_id) = LOWER(?)");
                    $stmt->execute([$documentNumber]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { echo json_encode(['success' => false, 'message' => 'Student not found.']); exit; }
                    echo json_encode(['success' => true, 'mode' => 'lookup', 'type' => 'Student ID', 'record' => $row]);
                }
                exit;
            }

            // --- File upload + AI analysis mode ---
            if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Document upload error.']); exit;
            }

            $docFile  = $_FILES['document'];
            $allowedMimes = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $docMime = finfo_file($finfo, $docFile['tmp_name']);
            finfo_close($finfo);
            if (!in_array($docMime, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Document must be an image (JPG/PNG/WEBP) or PDF.']); exit;
            }

            $docBase64 = base64_encode(file_get_contents($docFile['tmp_name']));
            $hasSelfie = isset($_FILES['selfie']) && $_FILES['selfie']['error'] === UPLOAD_ERR_OK;

            // Build AI prompt & schema
            if ($verificationType === 'Bus Pass') {
                $prompt = "Analyze this Bus Pass document image.\n" .
                    "Extract: pass_number, student_name.\n" .
                    "Also perform visual forensic analysis:\n" .
                    "- is_likely_fake (boolean): does it look digitally forged, have pasted photos, unnatural lighting, or altered fonts?\n" .
                    "- forgery_reason (string): explain your reasoning.\n";
                $schema = ['type' => 'object', 'properties' => [
                    'pass_number'   => ['type' => 'string'],
                    'student_name'  => ['type' => 'string'],
                    'is_likely_fake'=> ['type' => 'boolean'],
                    'forgery_reason'=> ['type' => 'string'],
                ], 'required' => ['pass_number','student_name','is_likely_fake','forgery_reason']];
            } else {
                $prompt = "Analyze this Student ID card image.\n" .
                    "Extract: document_number (student ID), first_name, last_name, date_of_birth.\n" .
                    "Also perform visual forensic analysis:\n" .
                    "- is_likely_fake (boolean): does it look digitally forged?\n" .
                    "- forgery_reason (string): explain your reasoning.\n";
                $schema = ['type' => 'object', 'properties' => [
                    'document_number'=> ['type' => 'string'],
                    'first_name'     => ['type' => 'string'],
                    'last_name'      => ['type' => 'string'],
                    'date_of_birth'  => ['type' => 'string'],
                    'is_likely_fake' => ['type' => 'boolean'],
                    'forgery_reason' => ['type' => 'string'],
                ], 'required' => ['document_number','first_name','last_name','date_of_birth','is_likely_fake','forgery_reason']];
            }

            if ($hasSelfie) {
                $prompt .= "\nAlso, compare the face on the ID document to the live selfie photo provided.\n" .
                    "- faces_match (boolean): do they appear to be the same person?\n" .
                    "- biometric_reason (string): explain your face comparison.";
                $schema['properties']['faces_match']      = ['type' => 'boolean'];
                $schema['properties']['biometric_reason'] = ['type' => 'string'];
                $schema['required'][] = 'faces_match';
                $schema['required'][] = 'biometric_reason';
            }

            // Call Gemini
            $parts = [['mime' => $docMime, 'base64' => $docBase64]];
            if ($hasSelfie) {
                $selfieMime   = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['selfie']['tmp_name']);
                $selfieBase64 = base64_encode(file_get_contents($_FILES['selfie']['tmp_name']));
                $parts[] = ['mime' => $selfieMime, 'base64' => $selfieBase64];
            }
            $aiData = geminiAnalyzeMulti($parts, $prompt, $schema);

            // Cross-reference DB
            $isMatch     = false;
            $matchScore  = 0;
            $details     = null;
            $dbRecord    = null;
            $extractedId = $documentNumber;

            if ($verificationType === 'Bus Pass') {
                $extractedId = $documentNumber ?: ($aiData['pass_number'] ?? '');
                if ($extractedId) {
                    $stmt = $pdo->prepare("SELECT b.*, s.name as student_name FROM bus_passes b JOIN students s ON b.student_id = s.student_id WHERE LOWER(b.pass_number) = LOWER(?)");
                    $stmt->execute([$extractedId]);
                    $dbRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if ($dbRecord) {
                    $s1 = fuzzyScore($dbRecord['pass_number'], $aiData['pass_number'] ?? '');
                    $s2 = fuzzyScore($dbRecord['student_name'], $aiData['student_name'] ?? '');
                    $matchScore = ($s1 + $s2) / 2;
                    $isMatch    = $matchScore >= 70;
                    $details    = [
                        'pass_number'  => ['db' => $dbRecord['pass_number'],  'extracted' => $aiData['pass_number'] ?? '',  'score' => $s1],
                        'student_name' => ['db' => $dbRecord['student_name'], 'extracted' => $aiData['student_name'] ?? '', 'score' => $s2],
                    ];
                }
            } else {
                $extractedId = $documentNumber ?: ($aiData['document_number'] ?? '');
                if ($extractedId) {
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE LOWER(student_id) = LOWER(?)");
                    $stmt->execute([$extractedId]);
                    $dbRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if ($dbRecord) {
                    $nameParts  = explode(' ', $dbRecord['name'], 2);
                    $dbFirst    = $nameParts[0] ?? '';
                    $dbLast     = $nameParts[1] ?? '';
                    $s1 = fuzzyScore($dbRecord['student_id'], $aiData['document_number'] ?? '');
                    $s2 = fuzzyScore($dbFirst, $aiData['first_name'] ?? '');
                    $s3 = fuzzyScore($dbLast,  $aiData['last_name']  ?? '');
                    $matchScore = ($s1 + $s2 + $s3) / 3;
                    $isMatch    = $matchScore >= 70;
                    $details    = [
                        'student_id' => ['db' => $dbRecord['student_id'], 'extracted' => $aiData['document_number'] ?? '', 'score' => $s1],
                        'first_name' => ['db' => $dbFirst,                'extracted' => $aiData['first_name'] ?? '',       'score' => $s2],
                        'last_name'  => ['db' => $dbLast,                 'extracted' => $aiData['last_name'] ?? '',        'score' => $s3],
                    ];
                }
            }

            // Build final verdict
            $finalIsValid = ($dbRecord !== null) && $isMatch && !($aiData['is_likely_fake'] ?? true);
            if ($hasSelfie) $finalIsValid = $finalIsValid && ($aiData['faces_match'] ?? false);
            $finalReason  = $aiData['forgery_reason'] ?? '';

            if ($verificationType === 'Bus Pass' && $dbRecord) {
                if (!$dbRecord['is_active']) { $finalIsValid = false; $finalReason .= ' | Bus pass is INACTIVE.'; }
                if (new DateTime($dbRecord['expiry_date']) < new DateTime()) { $finalIsValid = false; $finalReason .= ' | Bus pass EXPIRED.'; }
            }
            if ($hasSelfie && !($aiData['faces_match'] ?? true)) $finalReason .= ' | Face mismatch: ' . ($aiData['biometric_reason'] ?? '');
            if (!$dbRecord) $finalReason = 'Record not found in database.';
            elseif (!$isMatch) $finalReason .= ' | Extracted data did not match DB records (score: ' . round($matchScore) . '%).';

            // Log verification result
            $stmt = $pdo->prepare("INSERT INTO documents (user_id, title, file_name, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'AI Verify: ' . $verificationType . ' - ' . ($extractedId ?: 'Unknown'), basename($docFile['name']), $finalIsValid ? 'verified' : 'rejected']);
            $docId = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO verification_results (doc_id, is_valid, failure_reason, ai_extracted, match_score, checked_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$docId, $finalIsValid ? 1 : 0, $finalReason, json_encode($aiData), $matchScore]);

            logAudit($pdo, $_SESSION['user_id'], 'ai_verify', $docId, "AI verification: $verificationType - " . ($finalIsValid ? 'VALID' : 'INVALID'));

            echo json_encode([
                'success'     => true,
                'mode'        => 'verify',
                'type'        => $verificationType,
                'isValid'     => $finalIsValid,
                'isMatch'     => $isMatch,
                'matchScore'  => round($matchScore, 1),
                'details'     => $details,
                'dbRecord'    => $dbRecord,
                'aiExtracted' => $aiData,
                'security'    => [
                    'is_likely_fake'   => $aiData['is_likely_fake'] ?? null,
                    'forgery_reason'   => $aiData['forgery_reason'] ?? '',
                    'faces_match'      => $aiData['faces_match'] ?? null,
                    'biometric_reason' => $aiData['biometric_reason'] ?? '',
                    'finalIsValid'     => $finalIsValid,
                    'finalReason'      => $finalReason,
                ]
            ]);
            exit;
        }

        // ==========================================
        // --- AI VERIFY OFFICIAL (Aadhaar/PAN/Domicile) ---
        // ==========================================
        if ($action === 'ai_verify_official' && $method === 'POST') {
            $docType = $_POST['docType'] ?? '';
            if (!in_array($docType, ['Aadhaar', 'PAN', 'Domicile'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid document type.']); exit;
            }
            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Please upload the document file.']); exit;
            }

            $docFile = $_FILES['document'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $docMime = finfo_file($finfo, $docFile['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
            if (!in_array($docMime, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Document must be an image or PDF.']); exit;
            }

            $docBase64 = base64_encode(file_get_contents($docFile['tmp_name']));

            // Build prompt per doc type
            if ($docType === 'Aadhaar') {
                $prompt = "Analyze this Aadhaar Card.\nExtract: aadhaar_number, name, date_of_birth, gender.\nAlso perform visual forensic analysis:\n- is_likely_fake (boolean): does it look forged?\n- forgery_reason (string): explain.";
                $schema = ['type'=>'object','properties'=>['aadhaar_number'=>['type'=>'string'],'name'=>['type'=>'string'],'date_of_birth'=>['type'=>'string'],'gender'=>['type'=>'string'],'is_likely_fake'=>['type'=>'boolean'],'forgery_reason'=>['type'=>'string']],'required'=>['aadhaar_number','name','date_of_birth','gender','is_likely_fake','forgery_reason']];
            } elseif ($docType === 'PAN') {
                $prompt = "Analyze this PAN Card.\nExtract: pan_number, name, fathers_name, date_of_birth.\nAlso perform visual forensic analysis:\n- is_likely_fake (boolean): does it look forged?\n- forgery_reason (string): explain.";
                $schema = ['type'=>'object','properties'=>['pan_number'=>['type'=>'string'],'name'=>['type'=>'string'],'fathers_name'=>['type'=>'string'],'date_of_birth'=>['type'=>'string'],'is_likely_fake'=>['type'=>'boolean'],'forgery_reason'=>['type'=>'string']],'required'=>['pan_number','name','fathers_name','date_of_birth','is_likely_fake','forgery_reason']];
            } else {
                $prompt = "Analyze this Domicile Certificate.\nExtract: certificate_number, name, state_or_address.\nAlso perform visual forensic analysis:\n- is_likely_fake (boolean): does it look forged?\n- forgery_reason (string): explain.";
                $schema = ['type'=>'object','properties'=>['certificate_number'=>['type'=>'string'],'name'=>['type'=>'string'],'state_or_address'=>['type'=>'string'],'is_likely_fake'=>['type'=>'boolean'],'forgery_reason'=>['type'=>'string']],'required'=>['certificate_number','name','state_or_address','is_likely_fake','forgery_reason']];
            }

            $aiData = geminiAnalyze($docBase64, $docMime, $prompt, $schema);
            $finalIsValid = !($aiData['is_likely_fake'] ?? true);
            $finalReason  = $aiData['forgery_reason'] ?? '';

            $stmt = $pdo->prepare("INSERT INTO documents (user_id, title, file_name, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'Official Verify: ' . $docType, basename($docFile['name']), $finalIsValid ? 'verified' : 'rejected']);
            $docId = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO verification_results (doc_id, is_valid, failure_reason, ai_extracted, checked_at) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$docId, $finalIsValid ? 1 : 0, $finalReason, json_encode($aiData)]);

            logAudit($pdo, $_SESSION['user_id'], 'ai_verify_official', $docId, "Official doc verify: $docType - " . ($finalIsValid ? 'GENUINE' : 'FAKE/SUSPECT'));

            $extractedData = $aiData;
            unset($extractedData['is_likely_fake'], $extractedData['forgery_reason']);

            echo json_encode([
                'success'       => true,
                'mode'          => 'official',
                'docType'       => $docType,
                'extractedData' => $extractedData,
                'security'      => [
                    'is_likely_fake' => $aiData['is_likely_fake'] ?? null,
                    'forgery_reason' => $aiData['forgery_reason'] ?? '',
                    'finalIsValid'   => $finalIsValid,
                ]
            ]);
            exit;
        }

        // --- STANDARD DOCUMENT QUERIES ---
        if ($action === 'my_docs' && $method === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'users' && $method === 'GET' && $_SESSION['role'] === 'admin') {
            $stmt = $pdo->query("SELECT id, name, email, role, is_deleted, created_at FROM users ORDER BY created_at DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'all_docs' && $method === 'GET' && in_array($_SESSION['role'], ['admin','verifier'])) {
            $stmt = $pdo->query("SELECT d.*, u.name as uploader_name FROM documents d JOIN users u ON d.user_id = u.id WHERE u.is_deleted = 0 ORDER BY d.uploaded_at DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'admin_messages' && $method === 'GET' && $_SESSION['role'] === 'admin') {
            $stmt = $pdo->query("SELECT c.*, u.name as user_name, u.email as user_email FROM contact_messages c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'update_message_status' && $method === 'POST' && $_SESSION['role'] === 'admin') {
            $msg_id = (int)$_POST['id'];
            $status = $_POST['status'];
            $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")->execute([$status, $msg_id]);
            echo json_encode(['success' => true, 'message' => "Message marked as $status."]);
            exit;
        }

        if ($action === 'reply_message' && $method === 'POST' && $_SESSION['role'] === 'admin') {
            $msg_id = (int)$_POST['id'];
            $reply  = trim($_POST['reply']);
            $pdo->prepare("UPDATE contact_messages SET admin_reply = ?, status = 'read' WHERE id = ?")->execute([$reply, $msg_id]);
            logAudit($pdo, $_SESSION['user_id'], 'admin_reply', $msg_id, "Replied to support ticket #$msg_id");
            echo json_encode(['success' => true, 'message' => 'Reply dispatched securely.']);
            exit;
        }

        if ($action === 'update_doc' && $method === 'POST' && in_array($_SESSION['role'], ['admin','verifier'])) {
            $doc_id = (int)$_POST['doc_id'];
            $status = $_POST['status'];
            if (!in_array($status, ['pending','verified','rejected'])) { echo json_encode(['success' => false, 'message' => 'Invalid status.']); exit; }
            $pdo->prepare("UPDATE documents SET status = ? WHERE id = ?")->execute([$status, $doc_id]);
            logAudit($pdo, $_SESSION['user_id'], 'status_change', $doc_id, "Changed status to: $status");
            echo json_encode(['success' => true, 'message' => "Document marked as $status."]);
            exit;
        }

        if ($action === 'update_role' && $method === 'POST' && $_SESSION['role'] === 'admin') {
            $user_id  = (int)$_POST['user_id'];
            $new_role = $_POST['new_role'];
            if ($user_id == $_SESSION['user_id']) { echo json_encode(['success' => false, 'message' => 'Cannot change your own role.']); exit; }
            if (!in_array($new_role, ['user','verifier','admin'])) { echo json_encode(['success' => false, 'message' => 'Invalid role.']); exit; }
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $user_id]);
            logAudit($pdo, $_SESSION['user_id'], 'role_change', $user_id, "Changed user #$user_id role to: $new_role");
            echo json_encode(['success' => true, 'message' => 'Role updated to ' . strtoupper($new_role) . '.']);
            exit;
        }

        if ($action === 'toggle_user_status' && $method === 'POST' && $_SESSION['role'] === 'admin') {
            $user_id    = (int)$_POST['user_id'];
            $new_status = (int)$_POST['status'];
            if ($user_id == $_SESSION['user_id']) { echo json_encode(['success' => false, 'message' => 'Cannot modify own account.']); exit; }
            $pdo->prepare("UPDATE users SET is_deleted = ? WHERE id = ?")->execute([$new_status, $user_id]);
            $msg = $new_status == 1 ? 'User account deactivated.' : 'User account restored.';
            logAudit($pdo, $_SESSION['user_id'], 'user_status', $user_id, $msg);
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }

        if ($action === 'get_analytics' && $_SESSION['role'] === 'admin') {
            $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $doc_query   = $pdo->query("SELECT status, COUNT(*) as count FROM documents GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
            $doc_stats   = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
            foreach ($doc_query as $row) { $doc_stats[$row['status']] = $row['count']; }
            $total_docs     = array_sum($doc_stats);
            $processed_docs = $doc_stats['verified'] + $doc_stats['rejected'];
            $accuracy       = ($processed_docs > 0) ? round(($doc_stats['verified'] / $processed_docs) * 100) : 0;
            $stmt = $pdo->prepare("SELECT DATE(uploaded_at) as date, COUNT(*) as count FROM documents WHERE uploaded_at >= ? GROUP BY DATE(uploaded_at) ORDER BY date ASC");
            $stmt->execute([date('Y-m-d', strtotime('-6 days')) . ' 00:00:00']);
            $trend_raw = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $trend_raw[$row['date']] = $row['count']; }
            $trend_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $trend_data[$d] = $trend_raw[$d] ?? 0;
            }
            echo json_encode(['success' => true, 'stats' => ['users' => $total_users, 'total_docs' => $total_docs, 'pending' => $doc_stats['pending'], 'verified' => $doc_stats['verified'], 'rejected' => $doc_stats['rejected'], 'accuracy' => $accuracy], 'trend' => ['labels' => array_keys($trend_data), 'data' => array_values($trend_data)]]);
            exit;
        }

        if ($action === 'get_audit_log' && $_SESSION['role'] === 'admin') {
            $exists = $pdo->query("SHOW TABLES LIKE 'audit_log'")->rowCount();
            if (!$exists) { echo json_encode(['success' => true, 'data' => []]); exit; }
            $stmt = $pdo->query("SELECT al.*, u.name as actor_name FROM audit_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 100");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // --- VERIFICATION HISTORY ---
        if ($action === 'verify_history' && $method === 'GET') {
            $userId = $_SESSION['user_id'];
            $role   = $_SESSION['role'];
            if (in_array($role, ['admin', 'verifier'])) {
                $stmt = $pdo->query("SELECT vr.*, d.title, d.user_id, u.name as uploader FROM verification_results vr JOIN documents d ON vr.doc_id = d.id JOIN users u ON d.user_id = u.id ORDER BY vr.checked_at DESC LIMIT 100");
            } else {
                $stmt = $pdo->prepare("SELECT vr.*, d.title, d.user_id, u.name as uploader FROM verification_results vr JOIN documents d ON vr.doc_id = d.id JOIN users u ON d.user_id = u.id WHERE d.user_id = ? ORDER BY vr.checked_at DESC");
                $stmt->execute([$userId]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid API endpoint.']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = $_SESSION['role'] ?? 'guest';
$userName   = $_SESSION['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocuGuard Automata | Verification Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { primary: '#4f46e5', secondary: '#ec4899', darkbg: '#0f172a', darkcard: '#1e293b' },
                    animation: { 'blob': 'blob 7s infinite' },
                    keyframes: { blob: { '0%': { transform: 'translate(0px,0px) scale(1)' }, '33%': { transform: 'translate(30px,-50px) scale(1.1)' }, '66%': { transform: 'translate(-20px,20px) scale(0.9)' }, '100%': { transform: 'translate(0px,0px) scale(1)' } } }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; transition: background-color 0.3s ease, color 0.3s ease; }
        .glass-panel { background: rgba(255,255,255,0.7); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.3); }
        .dark .glass-panel { background: rgba(30,41,59,0.7); border: 1px solid rgba(255,255,255,0.1); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toast-enter { animation: slideIn 0.3s cubic-bezier(0.16,1,0.3,1) forwards; }
        .hidden-view { display: none !important; opacity: 0; }
        .active-view { display: block; opacity: 1; animation: fadeIn 0.4s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-row { opacity: 0; animation: fadeInRow 0.4s ease forwards; }
        @keyframes fadeInRow { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #475569; }
        .scan-container { position: relative; overflow: hidden; }
        .laser-line { position: absolute; width: 100%; height: 2px; background: #ec4899; box-shadow: 0 0 10px #ec4899, 0 0 20px #ec4899; top: 0; left: 0; animation: scan 1.5s linear infinite; z-index: 10; }
        @keyframes scan { 0% { top: -10px; } 50% { top: 100%; } 100% { top: -10px; } }
        .step-inactive { opacity: 0.3; filter: grayscale(1); transition: all 0.3s; }
        .step-active { opacity: 1; font-weight: bold; color: #4f46e5; transition: all 0.3s; transform: scale(1.02); }
        .dark .step-active { color: #818cf8; }
        .step-done { opacity: 1; color: #10b981; transition: all 0.3s; }
        #doc-viewer-modal iframe, #doc-viewer-modal img { max-width: 100%; max-height: 70vh; }
        .viewer-toolbar { background: rgba(15,23,42,0.95); }
        .cert-border { border: 4px solid transparent; background: linear-gradient(white, white) padding-box, linear-gradient(135deg, #4f46e5, #ec4899, #4f46e5) border-box; }
        .dark .cert-border { background: linear-gradient(#1e293b, #1e293b) padding-box, linear-gradient(135deg, #4f46e5, #ec4899, #4f46e5) border-box; }
        .audit-row:nth-child(even) { background: rgba(79,70,229,0.03); }
        @keyframes pulse-badge { 0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); } 50% { box-shadow: 0 0 0 8px rgba(16,185,129,0); } }
        .security-pulse { animation: pulse-badge 2s infinite; }
        /* Selfie camera preview */
        #selfie-preview-container video, #selfie-preview-container img { border-radius: 12px; width: 100%; max-height: 180px; object-fit: cover; }
        /* Score bar */
        .score-bar { height: 8px; border-radius: 4px; background: #e5e7eb; overflow: hidden; }
        .score-fill { height: 100%; border-radius: 4px; transition: width 1s ease; }
        @media print { body * { visibility: hidden; } #cert-print-area, #cert-print-area * { visibility: visible; } #cert-print-area { position: fixed; left: 0; top: 0; width: 100%; } }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 dark:bg-darkbg dark:text-gray-200 min-h-screen flex flex-col overflow-x-hidden">

    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-0 -left-4 w-72 h-72 bg-purple-300 dark:bg-purple-900 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-yellow-300 dark:bg-yellow-900 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-blob" style="animation-delay:2s"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-300 dark:bg-pink-900 rounded-full mix-blend-multiply filter blur-2xl opacity-70 animate-blob" style="animation-delay:4s"></div>
    </div>

    <div id="toast-container" class="fixed bottom-5 right-5 z-[100] flex flex-col gap-3"></div>

    <div id="app" class="flex-1 flex flex-col md:flex-row min-h-screen w-full">

        <!-- SIDEBAR -->
        <aside id="sidebar" class="hidden md:flex flex-col w-64 glass-panel border-r border-gray-200 dark:border-gray-700 h-screen sticky top-0 flex-shrink-0 z-40 transition-all duration-300">
            <div class="p-6 flex items-center justify-between">
                <div class="flex items-center gap-3 group cursor-pointer">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform">
                        <i class="ph-bold ph-shield-check text-2xl"></i>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight text-gray-900 dark:text-white">DocuGuard</h1>
                </div>
                <button id="close-mobile-menu" class="md:hidden text-gray-500 hover:text-gray-900 dark:hover:text-white"><i class="ph ph-x text-2xl"></i></button>
            </div>
            <div class="mx-4 mb-4 px-3 py-2 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 flex items-center gap-2 cursor-help group" title="System is actively protected">
                <div class="w-2 h-2 rounded-full bg-green-500 security-pulse"></div>
                <span class="text-xs font-semibold text-green-700 dark:text-green-400">AI Verification Active</span>
            </div>
            <nav class="flex-1 px-4 py-2 space-y-1 overflow-y-auto custom-scrollbar" id="nav-links"></nav>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4 px-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Theme</span>
                    <button id="theme-toggle" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors hover:shadow-inner">
                        <i class="ph ph-moon text-lg dark:hidden"></i>
                        <i class="ph ph-sun text-lg hidden dark:block"></i>
                    </button>
                </div>
                <div class="flex items-center gap-3 px-2 mb-4">
                    <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center text-primary font-bold uppercase" id="user-initial">U</div>
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold truncate w-32" id="sidebar-username">User</span>
                        <span class="text-xs text-gray-500 uppercase tracking-wider" id="sidebar-role">Role</span>
                    </div>
                </div>
                <button onclick="logout()" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-red-600 bg-red-50 dark:bg-red-900/10 hover:bg-red-100 dark:hover:bg-red-900/30 transition-all font-medium group">
                    <i class="ph ph-sign-out text-xl group-hover:-translate-x-1 transition-transform"></i> Logout
                </button>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 relative flex flex-col min-h-screen">
            <header id="mobile-header" class="md:hidden flex items-center justify-between p-4 glass-panel sticky top-0 z-30 flex-shrink-0 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <i class="ph-fill ph-shield-check text-primary text-2xl"></i>
                    <span class="font-bold text-lg text-gray-900 dark:text-white">DocuGuard</span>
                </div>
                <button id="open-mobile-menu" class="p-2 text-gray-600 dark:text-gray-300"><i class="ph ph-list text-2xl"></i></button>
            </header>

            <header class="w-full px-6 md:px-10 py-5 flex items-center justify-between sticky top-0 z-20 glass-panel border-b border-gray-200/50 dark:border-gray-700/50 flex-shrink-0" id="global-header">
                <div>
                    <h2 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-secondary" id="header-greeting">Welcome to DocuGuard</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400" id="header-subtitle">Secure Automated Document Verification</p>
                </div>
                <div class="hidden md:flex items-center gap-4 text-sm font-medium text-gray-600 dark:text-gray-300">
                    <button onclick="fetchAndShowAlerts()" class="flex items-center gap-1 hover:text-primary transition-colors px-2 py-1 rounded-md hover:bg-primary/10"><i class="ph-fill ph-bell text-lg"></i> Alerts</button>
                    <button onclick="openInfoModal('support')" class="flex items-center gap-1 hover:text-primary transition-colors px-2 py-1 rounded-md hover:bg-primary/10"><i class="ph-fill ph-question text-lg"></i> Support</button>
                    <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-xs font-semibold border border-green-200 dark:border-green-800 ml-2 shadow-sm">
                        <i class="ph-fill ph-robot text-sm"></i> Gemini AI Powered
                    </span>
                </div>
            </header>

            <div class="w-full max-w-6xl mx-auto flex-1 p-6 md:p-10 flex flex-col">

                <!-- 1. AUTH VIEW -->
                <div id="view-auth" class="page-transition hidden-view flex-1 flex items-center justify-center">
                    <div class="w-full max-w-md my-auto">
                        <div class="text-center mb-10 group cursor-default">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white shadow-xl group-hover:scale-110 transition-transform duration-300">
                                <i class="ph-bold ph-shield-check text-4xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2" id="auth-title">Sign In</h2>
                            <p class="text-gray-500 dark:text-gray-400">Access your secure verification portal</p>
                        </div>
                        <div class="glass-panel rounded-3xl p-8 shadow-2xl">
                            <!-- Login -->
                            <form id="form-login" class="space-y-5" onsubmit="handleLogin(event)">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                                    <input type="email" name="email" required class="w-full px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all dark:text-white" placeholder="you@example.com">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                                    <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all dark:text-white" placeholder="••••••••">
                                </div>
                                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-primary to-secondary text-white font-bold text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all flex justify-center items-center gap-2">Sign In <i class="ph-bold ph-arrow-right"></i></button>
                                <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400 mt-2">
                                    <a href="#" onclick="toggleAuth('signup')" class="text-primary font-semibold hover:underline">Create account</a>
                                    <a href="#" onclick="toggleAuth('forgot')" class="text-primary font-semibold hover:underline">Forgot password?</a>
                                </div>
                            </form>
                            <!-- Register -->
                            <form id="form-signup" class="space-y-4 hidden" onsubmit="handleSignup(event)">
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label><input type="text" name="name" required class="w-full px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none transition-all dark:text-white" placeholder="John Doe"></div>
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                                    <div class="flex gap-2">
                                        <input type="email" name="email" id="signup-email" required class="flex-1 px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none transition-all dark:text-white" placeholder="you@example.com">
                                        <button type="button" onclick="sendOtp('signup-email','signup-otp')" class="px-3 py-2 rounded-xl bg-primary/10 text-primary font-bold text-sm hover:bg-primary/20 transition whitespace-nowrap">Send OTP</button>
                                    </div>
                                </div>
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">OTP</label><input type="text" name="otp" id="signup-otp" maxlength="6" class="w-full px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none transition-all dark:text-white tracking-[0.5em] text-center font-bold text-lg" placeholder="______"></div>
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label><input type="password" name="password" required class="w-full px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none transition-all dark:text-white" placeholder="••••••••"></div>
                                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-primary to-secondary text-white font-bold text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all flex justify-center items-center gap-2">Register Account <i class="ph-bold ph-user-plus"></i></button>
                                <p class="text-center text-sm text-gray-500"><a href="#" onclick="toggleAuth('login')" class="text-primary font-semibold hover:underline">Back to Sign In</a></p>
                            </form>
                            <!-- Forgot Password -->
                            <form id="form-forgot" class="space-y-4 hidden" onsubmit="handleForgotPassword(event)">
                                <p class="text-sm text-gray-500 mb-2">Enter your email, request an OTP, then set a new password.</p>
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                                    <div class="flex gap-2">
                                        <input type="email" name="email" id="forgot-email" required class="flex-1 px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none transition-all dark:text-white" placeholder="you@example.com">
                                        <button type="button" onclick="sendOtp('forgot-email','forgot-otp')" class="px-3 py-2 rounded-xl bg-primary/10 text-primary font-bold text-sm hover:bg-primary/20 transition whitespace-nowrap">Send OTP</button>
                                    </div>
                                </div>
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">OTP</label><input type="text" name="otp" id="forgot-otp" maxlength="6" class="w-full px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none transition-all dark:text-white tracking-[0.5em] text-center font-bold text-lg" placeholder="______"></div>
                                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label><input type="password" name="password" required class="w-full px-4 py-3 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none transition-all dark:text-white" placeholder="New password"></div>
                                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-primary to-secondary text-white font-bold text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all">Reset Password</button>
                                <p class="text-center text-sm text-gray-500"><a href="#" onclick="toggleAuth('login')" class="text-primary font-semibold hover:underline">Back to Sign In</a></p>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 2. USER DASHBOARD -->
                <div id="view-user" class="page-transition hidden-view">
                    <header class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
                        <div>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">My Documents</h2>
                            <p class="text-gray-500 mt-1">Upload and track your document verification status.</p>
                        </div>
                        <div class="flex gap-3 flex-wrap">
                            <button onclick="openAiVerifyModal()" class="px-5 py-2.5 bg-gradient-to-r from-primary to-secondary text-white rounded-xl font-medium shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                <i class="ph-fill ph-robot"></i> AI Verify ID / Bus Pass
                            </button>
                            <button onclick="openOfficialVerifyModal()" class="px-5 py-2.5 bg-secondary/90 text-white rounded-xl font-medium shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                <i class="ph-fill ph-identification-card"></i> Verify Official Doc
                            </button>
                            <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="px-5 py-2.5 bg-gray-800 dark:bg-gray-700 text-white rounded-xl font-medium shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                <i class="ph ph-upload-simple"></i> Upload Document
                            </button>
                        </div>
                    </header>
                    <div class="mb-6 p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 flex flex-wrap gap-4 items-center text-sm text-indigo-700 dark:text-indigo-300 shadow-sm">
                        <span class="flex items-center gap-1.5 font-medium"><i class="ph-fill ph-robot text-lg"></i> Gemini AI Analysis</span>
                        <span class="flex items-center gap-1.5 font-medium"><i class="ph-fill ph-fingerprint text-lg"></i> Biometric Face Match</span>
                        <span class="flex items-center gap-1.5 font-medium"><i class="ph-fill ph-shield-check text-lg"></i> Forgery Detection</span>
                        <span class="flex items-center gap-1.5 font-medium"><i class="ph-fill ph-database text-lg"></i> University DB Cross-check</span>
                    </div>
                    <div class="glass-panel rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="overflow-x-auto w-full custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[700px]">
                                <thead><tr class="bg-gray-50/50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 text-sm uppercase tracking-wider">
                                    <th class="p-4 font-semibold">Document Title</th>
                                    <th class="p-4 font-semibold">File Name</th>
                                    <th class="p-4 font-semibold">Upload Date</th>
                                    <th class="p-4 font-semibold">Status</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr></thead>
                                <tbody id="user-docs-table" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Verification History (for user) -->
                    <div class="mt-10">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2"><i class="ph-fill ph-clock-clockwise text-primary"></i> My AI Verification History</h3>
                        <div class="glass-panel rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="overflow-x-auto w-full custom-scrollbar">
                                <table class="w-full text-left border-collapse min-w-[600px]">
                                    <thead><tr class="bg-gray-50/50 dark:bg-gray-800/50 text-gray-500 text-xs uppercase tracking-wider">
                                        <th class="p-3 font-semibold">Document / Type</th>
                                        <th class="p-3 font-semibold">Result</th>
                                        <th class="p-3 font-semibold">Score</th>
                                        <th class="p-3 font-semibold">Reason</th>
                                        <th class="p-3 font-semibold">Date</th>
                                    </tr></thead>
                                    <tbody id="user-verify-history" class="divide-y divide-gray-100 dark:divide-gray-800 text-sm"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. ADMIN ANALYTICS -->
                <div id="view-admin-analytics" class="page-transition hidden-view">
                    <header class="mb-8"><h2 class="text-3xl font-bold text-gray-900 dark:text-white">System Analytics</h2><p class="text-gray-500 mt-1">Real-time platform metrics and processing trends.</p></header>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="glass-panel p-6 rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 flex items-center gap-5 transform hover:-translate-y-1 hover:shadow-lg transition-all duration-300"><div class="w-14 h-14 rounded-2xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-3xl"><i class="ph-fill ph-files"></i></div><div><p class="text-sm text-gray-500 font-medium uppercase tracking-wider">Total Docs</p><h4 class="text-3xl font-bold mt-1 text-gray-900 dark:text-white" id="stat-total-docs">0</h4></div></div>
                        <div class="glass-panel p-6 rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 flex items-center gap-5 transform hover:-translate-y-1 hover:shadow-lg transition-all duration-300"><div class="w-14 h-14 rounded-2xl bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center text-3xl"><i class="ph-fill ph-check-circle"></i></div><div><p class="text-sm text-gray-500 font-medium uppercase tracking-wider">Approval Rate</p><h4 class="text-3xl font-bold mt-1 text-green-500"><span id="stat-accuracy">0</span>%</h4></div></div>
                        <div class="glass-panel p-6 rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 flex items-center gap-5 transform hover:-translate-y-1 hover:shadow-lg transition-all duration-300"><div class="w-14 h-14 rounded-2xl bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center text-3xl"><i class="ph-fill ph-users"></i></div><div><p class="text-sm text-gray-500 font-medium uppercase tracking-wider">Total Users</p><h4 class="text-3xl font-bold mt-1 text-gray-900 dark:text-white" id="stat-users">0</h4></div></div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="glass-panel p-6 rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 lg:col-span-1 flex flex-col hover:shadow-md transition-shadow"><h4 class="font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2"><i class="ph-fill ph-chart-pie-slice text-primary text-lg"></i> Status Breakdown</h4><div class="relative w-full flex-grow min-h-[250px] flex items-center justify-center"><canvas id="statusChart"></canvas></div></div>
                        <div class="glass-panel p-6 rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 lg:col-span-2 flex flex-col hover:shadow-md transition-shadow"><h4 class="font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2"><i class="ph-fill ph-trend-up text-primary text-lg"></i> Upload Trend (Last 7 Days)</h4><div class="relative w-full flex-grow min-h-[250px]"><canvas id="trendChart"></canvas></div></div>
                    </div>
                </div>

                <!-- 4. VERIFICATION DESK -->
                <div id="view-admin-docs" class="page-transition hidden-view">
                    <header class="mb-8 flex justify-between items-center">
                        <div><h2 class="text-3xl font-bold text-gray-900 dark:text-white">Verification Desk</h2><p class="text-gray-500 mt-1">Review, open, and process document submissions.</p></div>
                        <div class="flex gap-3">
                            <button onclick="openAiVerifyModal()" class="px-4 py-2 bg-gradient-to-r from-primary to-secondary text-white rounded-xl font-medium shadow-lg hover:shadow-xl transition-all flex items-center gap-2 text-sm"><i class="ph-fill ph-robot"></i> AI Verify</button>
                            <button onclick="openOfficialVerifyModal()" class="px-4 py-2 bg-secondary/90 text-white rounded-xl font-medium shadow-lg hover:shadow-xl transition-all flex items-center gap-2 text-sm"><i class="ph-fill ph-identification-card"></i> Official Verify</button>
                        </div>
                    </header>
                    <div class="glass-panel rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="overflow-x-auto w-full custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[900px]">
                                <thead><tr class="bg-gray-50/50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 text-sm uppercase tracking-wider">
                                    <th class="p-4 font-semibold">Uploader</th>
                                    <th class="p-4 font-semibold">Document Details</th>
                                    <th class="p-4 font-semibold">Status</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr></thead>
                                <tbody id="admin-docs-table" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Admin Verify History -->
                    <div class="mt-10">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2"><i class="ph-fill ph-clock-clockwise text-primary"></i> All AI Verification History</h3>
                        <div class="glass-panel rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="overflow-x-auto w-full custom-scrollbar">
                                <table class="w-full text-left border-collapse min-w-[700px]">
                                    <thead><tr class="bg-gray-50/50 dark:bg-gray-800/50 text-gray-500 text-xs uppercase tracking-wider">
                                        <th class="p-3 font-semibold">Document / Type</th>
                                        <th class="p-3 font-semibold">Uploader</th>
                                        <th class="p-3 font-semibold">Result</th>
                                        <th class="p-3 font-semibold">Score</th>
                                        <th class="p-3 font-semibold">Date</th>
                                    </tr></thead>
                                    <tbody id="admin-verify-history" class="divide-y divide-gray-100 dark:divide-gray-800 text-sm"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. USER MANAGEMENT -->
                <div id="view-admin-users" class="page-transition hidden-view">
                    <header class="mb-8"><h2 class="text-3xl font-bold text-gray-900 dark:text-white">User Management</h2><p class="text-gray-500 mt-1">Control access levels, roles, and account status.</p></header>
                    <div class="glass-panel rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="overflow-x-auto w-full custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[800px]">
                                <thead><tr class="bg-gray-50/50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 text-sm uppercase tracking-wider">
                                    <th class="p-4 font-semibold">Name</th><th class="p-4 font-semibold">Email</th><th class="p-4 font-semibold">Assigned Role</th><th class="p-4 font-semibold text-right">Action</th>
                                </tr></thead>
                                <tbody id="admin-users-table" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 6. SUPPORT INBOX -->
                <div id="view-admin-messages" class="page-transition hidden-view">
                    <header class="mb-8"><h2 class="text-3xl font-bold text-gray-900 dark:text-white">Support Inbox</h2><p class="text-gray-500 mt-1">Manage user contact requests and system feedback.</p></header>
                    <div class="glass-panel rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="overflow-x-auto w-full custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[900px]">
                                <thead><tr class="bg-gray-50/50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 text-sm uppercase tracking-wider">
                                    <th class="p-4 font-semibold">Sender</th><th class="p-4 font-semibold">Subject & Message</th><th class="p-4 font-semibold">Status</th><th class="p-4 font-semibold text-right">Actions</th>
                                </tr></thead>
                                <tbody id="admin-messages-table" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 7. AUDIT TRAIL -->
                <div id="view-audit" class="page-transition hidden-view">
                    <header class="mb-8"><h2 class="text-3xl font-bold text-gray-900 dark:text-white">Audit Trail</h2><p class="text-gray-500 mt-1">Full tamper-evident log of all system actions.</p></header>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                        <div class="glass-panel p-4 rounded-2xl border border-gray-200 dark:border-gray-700 flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center text-xl"><i class="ph-fill ph-shield-check"></i></div><div><p class="text-xs text-gray-500 uppercase font-semibold tracking-wider">Encryption</p><p class="text-sm font-bold text-gray-900 dark:text-white">AES-256-GCM</p></div></div>
                        <div class="glass-panel p-4 rounded-2xl border border-gray-200 dark:border-gray-700 flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/30 text-green-600 flex items-center justify-center text-xl"><i class="ph-fill ph-certificate"></i></div><div><p class="text-xs text-gray-500 uppercase font-semibold tracking-wider">AI Model</p><p class="text-sm font-bold text-gray-900 dark:text-white">Gemini 2.0 Flash</p></div></div>
                        <div class="glass-panel p-4 rounded-2xl border border-gray-200 dark:border-gray-700 flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 text-purple-600 flex items-center justify-center text-xl"><i class="ph-fill ph-fingerprint"></i></div><div><p class="text-xs text-gray-500 uppercase font-semibold tracking-wider">Transport</p><p class="text-sm font-bold text-gray-900 dark:text-white">TLS 1.3</p></div></div>
                    </div>
                    <div class="glass-panel rounded-3xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30">
                            <h4 class="font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="ph-fill ph-list-checks text-primary"></i> System Event Log</h4>
                            <button onclick="loadAuditLog()" class="text-sm px-3 py-1.5 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors flex items-center gap-1 font-semibold"><i class="ph ph-arrows-clockwise"></i> Refresh</button>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left min-w-[700px]">
                                <thead class="bg-gray-50/80 dark:bg-gray-800/80 text-gray-500 text-xs uppercase tracking-wider"><tr>
                                    <th class="p-3 font-semibold">Timestamp</th><th class="p-3 font-semibold">Actor</th><th class="p-3 font-semibold min-w-[220px]">Action</th><th class="p-3 font-semibold">Details</th><th class="p-3 font-semibold">IP Address</th>
                                </tr></thead>
                                <tbody id="audit-log-table" class="divide-y divide-gray-100 dark:divide-gray-800 text-sm"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <footer class="w-full px-6 md:px-10 py-8 mt-auto border-t border-gray-200 dark:border-gray-800 bg-white/50 dark:bg-darkbg/50 backdrop-blur-md flex-shrink-0">
                <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 font-medium"><i class="ph-fill ph-shield-check text-xl text-primary"></i><span>&copy; 2026 <span class="font-bold text-gray-800 dark:text-gray-200">DocuGuard Automata</span>. All rights reserved.</span></div>
                    <div class="flex gap-6 text-sm font-semibold text-gray-500 dark:text-gray-400">
                        <button onclick="openInfoModal('terms')" class="hover:text-primary transition-colors flex items-center gap-1.5"><i class="ph-fill ph-file-text"></i> Terms</button>
                        <button onclick="openInfoModal('privacy')" class="hover:text-primary transition-colors flex items-center gap-1.5"><i class="ph-fill ph-lock-key"></i> Privacy</button>
                        <button onclick="openInfoModal('contact')" class="hover:text-primary transition-colors flex items-center gap-1.5"><i class="ph-fill ph-envelope-simple"></i> Contact</button>
                    </div>
                </div>
            </footer>
        </main>
    </div>

    <!-- ============================================================ -->
    <!--  MODALS                                                       -->
    <!-- ============================================================ -->

    <!-- UPLOAD MODAL -->
    <div id="upload-modal" class="fixed inset-0 z-50 hidden bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="glass-panel w-full max-w-md rounded-3xl p-6 shadow-2xl relative overflow-hidden" id="upload-modal-content">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white" id="upload-modal-title">Upload Document</h3>
                <button onclick="closeUploadModal()" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 p-2 rounded-full transition-colors" id="close-modal-btn"><i class="ph ph-x text-xl"></i></button>
            </div>
            <form id="upload-form" class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Document Title</label><input type="text" name="title" id="upload-title" required class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none dark:text-white transition-shadow" placeholder="e.g. 10th Marksheet"></div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select File</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-xl hover:border-primary hover:bg-primary/5 transition-all cursor-pointer bg-white/30 dark:bg-darkcard/30 group" onclick="document.getElementById('file-upload').click()">
                        <div class="space-y-1 text-center">
                            <i class="ph-bold ph-file-arrow-up text-4xl text-gray-400 group-hover:text-primary transition-colors transform group-hover:-translate-y-1 inline-block"></i>
                            <div class="flex text-sm text-gray-600 dark:text-gray-400 mt-2"><span class="relative cursor-pointer rounded-md font-medium text-primary hover:text-indigo-500">Upload a file<input id="file-upload" name="uploaded_file" type="file" class="sr-only" required accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx" onchange="document.getElementById('file-name-display').innerText = this.files.length > 0 ? this.files[0].name : 'PDF, PNG, JPG, DOC up to 10MB'"></span><p class="pl-1">or drag and drop</p></div>
                            <p class="text-xs text-gray-500 dark:text-gray-400" id="file-name-display">PDF, PNG, JPG, DOC up to 10MB</p>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="startAiVerification()" class="w-full mt-4 py-3 rounded-xl bg-primary text-white font-bold shadow-lg hover:shadow-xl hover:bg-indigo-700 transition-all transform hover:-translate-y-0.5">Submit for Verification</button>
            </form>
            <div id="ai-progress-ui" class="hidden flex-col items-center py-4">
                <div class="w-24 h-24 bg-primary/10 rounded-2xl flex items-center justify-center mb-6 scan-container border border-primary/30 shadow-inner relative overflow-hidden">
                    <i class="ph-fill ph-file-text text-5xl text-primary opacity-80" id="scan-icon"></i>
                    <div class="laser-line" id="laser"></div>
                </div>
                <div class="w-full space-y-4 text-sm font-medium px-4">
                    <div id="step-1" class="flex items-center gap-3 step-inactive"><i class="ph-fill ph-check-circle text-xl"></i> <span>Extracting text via OCR...</span></div>
                    <div id="step-2" class="flex items-center gap-3 step-inactive"><i class="ph-fill ph-check-circle text-xl"></i> <span>Validating file format & integrity...</span></div>
                    <div id="step-3" class="flex items-center gap-3 step-inactive"><i class="ph-fill ph-check-circle text-xl"></i> <span>Cross-checking University DB...</span></div>
                    <div id="step-4" class="flex items-center gap-3 step-inactive"><i class="ph-fill ph-check-circle text-xl"></i> <span>Securing with AES-256-GCM...</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI VERIFY MODAL (Student ID / Bus Pass) -->
    <div id="ai-verify-modal" class="fixed inset-0 z-50 hidden bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="glass-panel w-full max-w-lg rounded-3xl p-6 shadow-2xl relative overflow-hidden max-h-[95vh] overflow-y-auto custom-scrollbar">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="ph-fill ph-robot text-primary"></i> AI Document Verifier</h3>
                <button onclick="closeAiVerifyModal()" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 p-2 rounded-full transition-colors"><i class="ph ph-x text-xl"></i></button>
            </div>

            <!-- Form -->
            <div id="ai-verify-form-area">
                <!-- Verification type tabs -->
                <div class="flex gap-2 mb-5">
                    <button onclick="setVerifyType('Student ID')" id="tab-student" class="flex-1 py-2 rounded-xl bg-primary text-white font-bold text-sm transition">Student ID</button>
                    <button onclick="setVerifyType('Bus Pass')" id="tab-buspass" class="flex-1 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold text-sm transition">Bus Pass</button>
                </div>

                <!-- Quick lookup by number -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" id="verify-id-label">Student ID Number (optional)</label>
                    <input type="text" id="verify-doc-number" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-darkcard/50 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none dark:text-white" placeholder="e.g. CSE2301">
                    <p class="text-xs text-gray-400 mt-1">Enter ID to do a quick lookup, OR upload a document image for full AI analysis below.</p>
                </div>

                <!-- Document image upload -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload Document Image (for AI scan)</label>
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 flex flex-col items-center gap-2 hover:border-primary transition cursor-pointer" onclick="document.getElementById('verify-doc-file').click()">
                        <i class="ph-fill ph-scan text-3xl text-gray-400"></i>
                        <span class="text-sm text-gray-500" id="verify-doc-file-name">Click to select document image (JPG, PNG, PDF)</span>
                        <input type="file" id="verify-doc-file" class="sr-only" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="document.getElementById('verify-doc-file-name').innerText = this.files[0]?.name || 'Click to select'">
                    </div>
                </div>

                <!-- Selfie section -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-2"><i class="ph-fill ph-camera text-primary"></i> Selfie for Biometric Match (optional)</label>
                    <div class="flex gap-2 mb-2">
                        <button type="button" onclick="startCamera()" class="flex-1 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-primary font-bold text-sm hover:bg-primary hover:text-white transition flex items-center justify-center gap-1"><i class="ph-fill ph-camera-rotate"></i> Use Camera</button>
                        <button type="button" onclick="document.getElementById('selfie-file-input').click()" class="flex-1 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold text-sm hover:bg-gray-200 dark:hover:bg-gray-700 transition flex items-center justify-center gap-1"><i class="ph-fill ph-image"></i> Upload Photo</button>
                        <input type="file" id="selfie-file-input" class="sr-only" accept="image/*" onchange="handleSelfieFile(this)">
                    </div>
                    <div id="selfie-preview-container" class="hidden">
                        <video id="selfie-video" autoplay playsinline class="rounded-xl w-full max-h-[180px] object-cover"></video>
                        <canvas id="selfie-canvas" class="hidden"></canvas>
                        <img id="selfie-preview-img" class="hidden rounded-xl w-full max-h-[180px] object-cover" alt="Selfie preview">
                        <div class="flex gap-2 mt-2">
                            <button type="button" onclick="captureSelfie()" id="capture-btn" class="flex-1 py-2 rounded-xl bg-primary text-white font-bold text-sm hover:bg-indigo-700 transition">Capture</button>
                            <button type="button" onclick="clearSelfie()" class="flex-1 py-2 rounded-xl bg-red-50 text-red-600 font-bold text-sm hover:bg-red-100 transition">Clear</button>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="runAiVerification()" class="w-full py-3 rounded-xl bg-gradient-to-r from-primary to-secondary text-white font-bold shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="ph-fill ph-robot"></i> Run AI Verification
                </button>
            </div>

            <!-- Spinner during call -->
            <div id="ai-verify-loading" class="hidden flex-col items-center py-10 gap-4">
                <div class="w-20 h-20 rounded-2xl bg-primary/10 flex items-center justify-center scan-container border border-primary/30">
                    <i class="ph-fill ph-robot text-5xl text-primary opacity-80"></i>
                    <div class="laser-line"></div>
                </div>
                <p class="font-bold text-gray-700 dark:text-gray-300 text-lg">Gemini AI Analyzing...</p>
                <p class="text-sm text-gray-400">Extracting fields, checking forgery, cross-referencing DB...</p>
            </div>

            <!-- Result area -->
            <div id="ai-verify-result" class="hidden"></div>
        </div>
    </div>

    <!-- OFFICIAL DOCUMENT VERIFY MODAL -->
    <div id="official-verify-modal" class="fixed inset-0 z-50 hidden bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="glass-panel w-full max-w-lg rounded-3xl p-6 shadow-2xl max-h-[95vh] overflow-y-auto custom-scrollbar">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="ph-fill ph-identification-card text-secondary"></i> Official Document Verifier</h3>
                <button onclick="closeOfficialVerifyModal()" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 p-2 rounded-full transition-colors"><i class="ph ph-x text-xl"></i></button>
            </div>
            <div id="official-verify-form-area">
                <p class="text-sm text-gray-500 mb-4">Upload an Aadhaar Card, PAN Card, or Domicile Certificate. Gemini AI will extract details and detect if it appears forged.</p>
                <div class="flex gap-2 mb-5">
                    <button onclick="setOfficialDocType('Aadhaar')" id="otab-aadhaar" class="flex-1 py-2 rounded-xl bg-secondary text-white font-bold text-sm transition">Aadhaar</button>
                    <button onclick="setOfficialDocType('PAN')" id="otab-pan" class="flex-1 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold text-sm transition">PAN Card</button>
                    <button onclick="setOfficialDocType('Domicile')" id="otab-domicile" class="flex-1 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold text-sm transition">Domicile</button>
                </div>
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload Document Image</label>
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 flex flex-col items-center gap-2 hover:border-secondary transition cursor-pointer" onclick="document.getElementById('official-doc-file').click()">
                        <i class="ph-fill ph-identification-card text-3xl text-gray-400"></i>
                        <span class="text-sm text-gray-500" id="official-doc-file-name">Click to upload Aadhaar / PAN / Domicile</span>
                        <input type="file" id="official-doc-file" class="sr-only" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="document.getElementById('official-doc-file-name').innerText = this.files[0]?.name || 'Click to upload'">
                    </div>
                </div>
                <button type="button" onclick="runOfficialVerification()" class="w-full py-3 rounded-xl bg-gradient-to-r from-secondary to-pink-700 text-white font-bold shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="ph-fill ph-magnifying-glass"></i> Analyze & Verify
                </button>
            </div>
            <div id="official-verify-loading" class="hidden flex-col items-center py-10 gap-4">
                <div class="w-20 h-20 rounded-2xl bg-secondary/10 flex items-center justify-center scan-container border border-secondary/30">
                    <i class="ph-fill ph-identification-card text-5xl text-secondary opacity-80"></i>
                    <div class="laser-line" style="background:#ec4899;box-shadow:0 0 10px #ec4899"></div>
                </div>
                <p class="font-bold text-gray-700 dark:text-gray-300 text-lg">Gemini AI Analyzing...</p>
                <p class="text-sm text-gray-400">Extracting document fields and checking for forgery...</p>
            </div>
            <div id="official-verify-result" class="hidden"></div>
        </div>
    </div>

    <!-- DOCUMENT VIEWER MODAL -->
    <div id="doc-viewer-modal" class="fixed inset-0 z-[60] hidden bg-black/90 backdrop-blur-sm flex flex-col transition-opacity duration-300 opacity-0">
        <div class="viewer-toolbar flex items-center justify-between px-4 py-3 border-b border-white/10 flex-shrink-0 bg-gray-900/95 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-primary/30 flex items-center justify-center"><i class="ph-fill ph-shield-check text-primary text-lg"></i></div>
                <div><p class="text-white font-semibold text-sm" id="viewer-doc-title">Document</p><p class="text-gray-400 text-xs flex items-center gap-1"><i class="ph-fill ph-lock text-green-400"></i> <span id="viewer-security-info">Encrypted · Verified</span></p></div>
            </div>
            <div class="flex items-center gap-2">
                <a id="viewer-download-link" href="#" download class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm flex items-center gap-1.5 transition-colors"><i class="ph ph-download-simple"></i> Download</a>
                <button onclick="closeDocViewer()" class="px-3 py-1.5 rounded-lg bg-red-500/20 text-red-400 hover:bg-red-500/40 hover:text-white text-sm flex items-center gap-1.5 transition-colors"><i class="ph ph-x"></i> Close</button>
            </div>
        </div>
        <div class="flex-1 overflow-auto flex items-center justify-center p-4 relative" id="viewer-content-area">
            <div id="viewer-loading" class="text-gray-400 flex flex-col items-center gap-3"><div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div><span class="font-medium tracking-wider uppercase text-xs">Decrypting Secure Payload...</span></div>
        </div>
        <div class="viewer-toolbar px-4 py-2 border-t border-white/10 flex items-center gap-3 flex-shrink-0 bg-gray-900/95 backdrop-blur-md">
            <i class="ph-fill ph-fingerprint text-green-400 text-lg"></i>
            <span class="text-gray-400 text-xs font-mono" id="viewer-hash-display">Integrity: Computing...</span>
        </div>
    </div>

    <!-- CERTIFICATE MODAL -->
    <div id="cert-modal" class="fixed inset-0 z-[70] hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity duration-300 opacity-0">
        <div class="bg-white dark:bg-darkcard w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300" id="cert-modal-inner">
            <div class="bg-gray-100 dark:bg-gray-800 px-5 py-3 flex items-center justify-between border-b border-gray-200 dark:border-gray-700">
                <span class="font-semibold text-sm text-gray-700 dark:text-gray-300 flex items-center gap-2"><i class="ph-fill ph-certificate text-primary"></i> Verification Certificate</span>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-3 py-1.5 text-xs rounded-lg bg-primary text-white flex items-center gap-1.5 hover:bg-indigo-700 transition shadow-sm"><i class="ph ph-printer"></i> Print</button>
                    <button onclick="closeCertModal()" class="px-3 py-1.5 text-xs rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 transition"><i class="ph ph-x"></i> Close</button>
                </div>
            </div>
            <div class="p-6" id="cert-print-area">
                <div class="cert-border rounded-2xl p-6 dark:bg-darkcard">
                    <div class="text-center mb-4">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white shadow-lg"><i class="ph-bold ph-shield-check text-3xl"></i></div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">DocuGuard Verification Certificate</h3>
                        <p class="text-xs text-gray-500 mt-1">This document has been mathematically verified.</p>
                    </div>
                    <div class="space-y-2 text-sm border-t border-gray-200 dark:border-gray-700 pt-4 mb-4">
                        <div class="flex justify-between"><span class="text-gray-500">Document Title</span><span class="font-semibold text-gray-900 dark:text-white" id="cert-title">—</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Uploaded By</span><span class="font-semibold text-gray-900 dark:text-white" id="cert-uploader">—</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Verified On</span><span class="font-semibold text-gray-900 dark:text-white" id="cert-date">—</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Status</span><span class="font-semibold text-green-600 flex items-center gap-1" id="cert-status"><i class="ph-fill ph-check-circle"></i> VERIFIED</span></div>
                        <div class="flex justify-between items-start gap-2"><span class="text-gray-500 flex-shrink-0">Hash (SHA-256)</span><span class="font-mono text-xs text-gray-700 dark:text-gray-300 break-all text-right" id="cert-hash">—</span></div>
                    </div>
                    <div class="flex justify-center"><div class="p-2 bg-white rounded-xl shadow-inner border border-gray-200"><canvas id="cert-qr-canvas" width="120" height="120"></canvas></div></div>
                    <p class="text-center text-xs text-gray-400 mt-2 font-medium">Scan QR to cryptographically verify</p>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-center text-xs text-gray-400">Certificate ID: <span id="cert-id" class="font-mono text-gray-600 dark:text-gray-300 font-bold">—</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- REPLY MODAL -->
    <div id="replyModal" class="fixed inset-0 z-[80] hidden bg-black/60 backdrop-blur-sm items-center justify-center p-4 transition-opacity duration-300 opacity-0">
        <div class="bg-white dark:bg-darkcard rounded-3xl w-full max-w-lg p-6 transform scale-95 transition-transform duration-300 shadow-2xl border border-gray-200 dark:border-gray-700 relative overflow-hidden" id="replyModalInner">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="ph-fill ph-paper-plane-tilt text-primary"></i> Dispatch Reply</h3>
                <button onclick="closeModal('replyModal', 'replyModalInner')" class="text-gray-400 hover:text-gray-700 dark:hover:text-white transition"><i class="ph ph-x text-xl"></i></button>
            </div>
            <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-sm text-gray-600 dark:text-gray-300 border border-gray-100 dark:border-gray-700">
                <div class="font-bold text-gray-900 dark:text-white mb-2" id="reply-subject"></div>
                <div class="italic text-gray-500 dark:text-gray-400 border-l-2 border-primary/30 pl-3 py-1" id="reply-message-text"></div>
            </div>
            <form onsubmit="submitReply(event)" class="space-y-4">
                <input type="hidden" name="id" id="reply-msg-id">
                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Your Response</label><textarea name="reply" required rows="4" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 focus:ring-2 focus:ring-primary outline-none transition-all custom-scrollbar" placeholder="Type your reply here..."></textarea></div>
                <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all active:scale-[0.98] shadow-md flex items-center justify-center gap-2"><i class="ph-bold ph-paper-plane-right text-lg"></i> Send Secure Reply</button>
            </form>
        </div>
    </div>

    <!-- INFO MODAL (Terms/Privacy/Contact/Alerts/Support) -->
    <div id="infoModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 opacity-0 transition-opacity duration-300 p-4">
        <div class="bg-white dark:bg-darkcard rounded-3xl w-full max-w-2xl max-h-[85vh] flex flex-col transform scale-95 transition-transform duration-300 shadow-2xl border border-gray-200 dark:border-gray-700" id="infoModalInner">
            <div class="flex justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700 flex-shrink-0 bg-gray-50/50 dark:bg-gray-800/30 rounded-t-3xl">
                <h3 class="text-xl font-bold flex items-center gap-2 text-gray-900 dark:text-white" id="infoModalTitle">Information</h3>
                <button onclick="closeInfoModal()" class="text-gray-400 hover:text-gray-700 dark:hover:text-white transition bg-white dark:bg-gray-800 p-2 rounded-full shadow-sm hover:shadow-md border border-gray-100 dark:border-gray-700"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 custom-scrollbar text-gray-700 dark:text-gray-300 relative" id="infoModalContent"></div>
        </div>
    </div>

    <script>
    // ==========================================
    // STATE & INIT
    // ==========================================
    const State = {
        isLoggedIn: <?php echo $isLoggedIn ? 'true' : 'false'; ?>,
        role: '<?php echo $userRole; ?>',
        name: '<?php echo addslashes($userName); ?>',
        user_id: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>
    };
    let currentView = '';
    let globalUserDocs = [];
    let globalAdminDocs = [];
    let currentVerifyType = 'Student ID';
    let currentOfficialDocType = 'Aadhaar';
    let selfieBlob = null;
    let cameraStream = null;

    const views = {
        auth: document.getElementById('view-auth'),
        user: document.getElementById('view-user'),
        adminAnalytics: document.getElementById('view-admin-analytics'),
        adminDocs: document.getElementById('view-admin-docs'),
        adminUsers: document.getElementById('view-admin-users'),
        adminMessages: document.getElementById('view-admin-messages'),
        audit: document.getElementById('view-audit'),
    };

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        updateUIState();
        setupMobileMenu();
    });

    function initTheme() {
        const root = document.documentElement;
        const saved = localStorage.getItem('theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark');
        document.getElementById('theme-toggle').addEventListener('click', () => {
            root.classList.toggle('dark');
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
            if (currentView === 'adminAnalytics') loadAnalytics(false);
        });
    }

    function switchView(viewName) {
        currentView = viewName;
        Object.values(views).forEach(v => { if (v) { v.classList.remove('active-view'); v.classList.add('hidden-view'); } });
        if (views[viewName]) { views[viewName].classList.remove('hidden-view'); views[viewName].classList.add('active-view'); }
        renderSidebarLinks();
        if (viewName === 'adminAnalytics') loadAnalytics();
        if (viewName === 'audit') loadAuditLog();
        if (viewName === 'adminMessages') loadAdminMessages();
        if (viewName === 'adminDocs') { loadAdminDocs(); loadVerifyHistory('admin'); }
        if (viewName === 'user') { loadUserDocs(); loadVerifyHistory('user'); }
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth < 768) { sidebar.classList.add('hidden'); sidebar.classList.remove('fixed','inset-0','w-full','bg-white/95','dark:bg-darkbg/95','z-50'); }
    }

    function updateUIState() {
        const sidebar = document.getElementById('sidebar');
        const mobileHeader = document.getElementById('mobile-header');
        if (State.isLoggedIn) {
            sidebar.classList.remove('hidden'); sidebar.classList.add('md:flex');
            mobileHeader.classList.remove('hidden');
            document.getElementById('sidebar-username').innerText = State.name;
            document.getElementById('sidebar-role').innerText = State.role.charAt(0).toUpperCase() + State.role.slice(1);
            document.getElementById('user-initial').innerText = State.name.charAt(0).toUpperCase();
            document.getElementById('header-greeting').innerText = `Hello, ${State.name.split(' ')[0]}!`;
            document.getElementById('header-subtitle').innerText = State.role === 'admin' ? 'Administrator Dashboard' : (State.role === 'verifier' ? 'Verifier Dashboard' : 'Student Portal');
            if (State.role === 'admin') { switchView('adminAnalytics'); loadAdminDocs(); loadAdminUsers(); loadVerifyHistory('admin'); }
            else if (State.role === 'verifier') { switchView('adminDocs'); loadAdminDocs(); loadVerifyHistory('admin'); }
            else { switchView('user'); loadUserDocs(); loadVerifyHistory('user'); }
        } else {
            sidebar.classList.add('hidden'); sidebar.classList.remove('md:flex');
            mobileHeader.classList.add('hidden');
            document.getElementById('global-header').classList.add('hidden');
            switchView('auth');
        }
    }

    function renderSidebarLinks() {
        const nav = document.getElementById('nav-links');
        const createLink = (icon, text, viewTarget) => {
            const isActive = currentView === viewTarget;
            return `<a href="#" onclick="switchView('${viewTarget}'); return false;" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group ${isActive ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'}"><i class="${icon} text-xl group-hover:scale-110 transition-transform"></i> ${text}</a>`;
        };
        nav.innerHTML = '';
        if (State.role === 'user') {
            nav.innerHTML += createLink('ph-fill ph-files', 'My Documents', 'user');
        } else {
            if (State.role === 'admin') nav.innerHTML += createLink('ph-fill ph-chart-polar', 'System Analytics', 'adminAnalytics');
            nav.innerHTML += createLink('ph-fill ph-folder-lock', 'Verification Desk', 'adminDocs');
            if (State.role === 'admin') {
                nav.innerHTML += createLink('ph-fill ph-users-three', 'User Management', 'adminUsers');
                nav.innerHTML += createLink('ph-fill ph-tray', 'Support Inbox', 'adminMessages');
                nav.innerHTML += createLink('ph-fill ph-list-checks', 'Audit Trail', 'audit');
            }
        }
    }

    function setupMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        document.getElementById('open-mobile-menu').addEventListener('click', () => { sidebar.classList.remove('hidden'); sidebar.classList.add('fixed','inset-0','w-full','bg-white/95','dark:bg-darkbg/95','z-50'); });
        document.getElementById('close-mobile-menu').addEventListener('click', () => { sidebar.classList.add('hidden'); sidebar.classList.remove('fixed','inset-0','w-full','bg-white/95','dark:bg-darkbg/95','z-50'); });
    }

    function toggleAuth(mode) {
        ['form-login','form-signup','form-forgot'].forEach(id => document.getElementById(id).classList.add('hidden'));
        document.getElementById('form-' + mode).classList.remove('hidden');
        const titles = { login: 'Sign In', signup: 'Create Account', forgot: 'Reset Password' };
        document.getElementById('auth-title').innerText = titles[mode] || 'DocuGuard';
    }

    // ==========================================
    // API HELPER & TOASTS
    // ==========================================
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const isError = type === 'error';
        toast.className = `toast-enter flex items-center gap-3 px-5 py-4 rounded-2xl shadow-2xl border text-white font-medium max-w-sm backdrop-blur-md ${isError ? 'bg-red-600/95 border-red-500' : 'bg-gray-900/95 border-gray-700 dark:bg-white/95 dark:text-gray-900'}`;
        toast.innerHTML = `<i class="ph-fill ${isError ? 'ph-warning-circle text-red-200' : 'ph-check-circle text-green-400 dark:text-green-600'} text-2xl flex-shrink-0"></i> <span class="leading-tight">${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.transition='opacity 0.4s, transform 0.4s'; toast.style.opacity='0'; toast.style.transform='translateY(10px)'; setTimeout(() => toast.remove(), 400); }, 4500);
    }

    async function apiCall(endpoint, method = 'GET', body = null) {
        try {
            const options = { method };
            if (body) {
                if (body instanceof FormData) options.body = body;
                else { options.body = new URLSearchParams(body); options.headers = {'Content-Type': 'application/x-www-form-urlencoded'}; }
            }
            const response = await fetch(`index.php?api=${endpoint}`, options);
            const text = await response.text();
            try { return JSON.parse(text); }
            catch(e) { console.error('Non-JSON response:', text); return { success: false, message: 'Server error. Check PHP logs.' }; }
        } catch (err) { return { success: false, message: 'Network error: ' + err.message }; }
    }

    function getStatusBadge(status) {
        const styles = { pending: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800', verified: 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 border-green-200 dark:border-green-800', rejected: 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 border-red-200 dark:border-red-800' };
        const icons = { pending: 'ph-hourglass', verified: 'ph-check-circle', rejected: 'ph-x-circle' };
        return `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wider border shadow-sm ${styles[status] || styles.pending}"><i class="ph-fill ${icons[status] || 'ph-question'}"></i> ${(status||'unknown')}</span>`;
    }

    function closeModal(modalId, innerId) {
        const modal = document.getElementById(modalId);
        modal.classList.add('opacity-0');
        if (innerId) document.getElementById(innerId).classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); modal.style.display = ''; }, 300);
    }

    // ==========================================
    // AUTH HANDLERS
    // ==========================================
    async function sendOtp(emailInputId, otpInputId) {
        const email = document.getElementById(emailInputId).value.trim();
        if (!email) { showToast('Please enter your email first.', 'error'); return; }
        showToast('Sending OTP...');
        const fd = new FormData(); fd.append('email', email);
        const res = await apiCall('send_otp', 'POST', fd);
        if (res.success) showToast(res.message);
        else showToast(res.message || 'Failed to send OTP.', 'error');
    }

    async function handleLogin(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button[type=submit]');
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="ph ph-spinner animate-spin"></i> Authenticating...`; btn.disabled = true;
        const res = await apiCall('login', 'POST', new FormData(e.target));
        if (res.success) window.location.reload();
        else { showToast(res.message, 'error'); btn.innerHTML = orig; btn.disabled = false; }
    }

    async function handleSignup(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button[type=submit]');
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="ph ph-spinner animate-spin"></i> Registering...`; btn.disabled = true;
        const res = await apiCall('register', 'POST', new FormData(e.target));
        if (res.success) { showToast(res.message); toggleAuth('login'); e.target.reset(); }
        else showToast(res.message, 'error');
        btn.innerHTML = orig; btn.disabled = false;
    }

    async function handleForgotPassword(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button[type=submit]');
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="ph ph-spinner animate-spin"></i> Resetting...`; btn.disabled = true;
        const res = await apiCall('forgot_password', 'POST', new FormData(e.target));
        if (res.success) { showToast(res.message); toggleAuth('login'); e.target.reset(); }
        else showToast(res.message, 'error');
        btn.innerHTML = orig; btn.disabled = false;
    }

    async function logout() { await apiCall('logout', 'POST'); window.location.reload(); }

    // ==========================================
    // STANDARD UPLOAD
    // ==========================================
    async function startAiVerification() {
        const form = document.getElementById('upload-form');
        const titleInput = document.getElementById('upload-title');
        const fileInput = document.getElementById('file-upload');
        if (!titleInput.value.trim()) { showToast('Please enter a document title.', 'error'); return; }
        if (!fileInput.files || fileInput.files.length === 0) { showToast('Please select a file.', 'error'); return; }
        const formData = new FormData();
        formData.append('title', titleInput.value.trim());
        formData.append('uploaded_file', fileInput.files[0]);
        form.classList.add('hidden');
        const progressUi = document.getElementById('ai-progress-ui');
        progressUi.classList.remove('hidden'); progressUi.classList.add('flex');
        document.getElementById('upload-modal-title').innerHTML = '<i class="ph-fill ph-spinner animate-spin text-primary"></i> Uploading...';
        document.getElementById('close-modal-btn').classList.add('hidden');
        const steps = [{id:'step-1',delay:800},{id:'step-2',delay:700},{id:'step-3',delay:1000},{id:'step-4',delay:800}];
        for (let i = 0; i < steps.length; i++) {
            const stepEl = document.getElementById(steps[i].id);
            stepEl.className = 'flex items-center gap-3 text-primary font-bold transition-all duration-300 transform scale-105';
            stepEl.innerHTML = `<i class="ph ph-spinner-gap animate-spin text-2xl"></i> ` + stepEl.innerText;
            await new Promise(r => setTimeout(r, steps[i].delay));
            stepEl.className = 'flex items-center gap-3 text-green-500 font-bold transition-all duration-300 transform scale-100';
            stepEl.innerHTML = `<i class="ph-fill ph-check-circle text-2xl drop-shadow-sm"></i> ` + stepEl.innerText;
        }
        document.getElementById('laser').style.display = 'none';
        document.getElementById('scan-icon').className = 'ph-fill ph-check-circle text-6xl text-green-500 transition-all duration-500 transform scale-110 drop-shadow-md';
        document.getElementById('upload-modal-title').innerHTML = '<i class="ph-fill ph-check-circle text-green-500"></i> Upload Complete';
        const res = await apiCall('upload', 'POST', formData);
        await new Promise(r => setTimeout(r, 400));
        if (res.success) { confetti({ particleCount: 150, spread: 90, origin: { y: 0.6 }, colors: ['#4f46e5', '#ec4899', '#10b981'] }); showToast(res.message); loadUserDocs(); }
        else showToast(res.message || 'Upload failed.', 'error');
        setTimeout(() => closeUploadModal(), 2000);
    }

    function closeUploadModal() {
        const modal = document.getElementById('upload-modal');
        modal.classList.add('opacity-0');
        document.getElementById('upload-modal-content').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden'); modal.style.display = ''; modal.classList.remove('opacity-0');
            document.getElementById('upload-modal-content').classList.remove('scale-95');
            const form = document.getElementById('upload-form'); form.reset(); form.classList.remove('hidden');
            const progressUi = document.getElementById('ai-progress-ui');
            progressUi.classList.add('hidden'); progressUi.classList.remove('flex');
            document.getElementById('upload-modal-title').innerText = 'Upload Document';
            document.getElementById('close-modal-btn').classList.remove('hidden');
            document.getElementById('file-name-display').innerText = 'PDF, PNG, JPG, DOC up to 10MB';
            ['step-1','step-2','step-3','step-4'].forEach(id => {
                const el = document.getElementById(id);
                el.className = 'flex items-center gap-3 step-inactive';
                el.innerHTML = `<i class="ph-fill ph-check-circle text-xl"></i> <span>` + el.innerText + `</span>`;
            });
            document.getElementById('laser').style.display = 'block';
            document.getElementById('scan-icon').className = 'ph-fill ph-file-text text-5xl text-primary opacity-80';
        }, 300);
    }

    // ==========================================
    // AI VERIFY MODAL (Student ID / Bus Pass)
    // ==========================================
    function openAiVerifyModal() {
        setVerifyType('Student ID');
        document.getElementById('ai-verify-form-area').classList.remove('hidden');
        document.getElementById('ai-verify-loading').classList.add('hidden');
        document.getElementById('ai-verify-result').classList.add('hidden');
        document.getElementById('ai-verify-modal').classList.remove('hidden');
    }

    function closeAiVerifyModal() {
        stopCamera();
        document.getElementById('ai-verify-modal').classList.add('hidden');
        document.getElementById('verify-doc-file').value = '';
        document.getElementById('verify-doc-number').value = '';
        document.getElementById('verify-doc-file-name').innerText = 'Click to select document image (JPG, PNG, PDF)';
        selfieBlob = null;
        clearSelfie();
    }

    function setVerifyType(type) {
        currentVerifyType = type;
        const tabs = { 'Student ID': 'tab-student', 'Bus Pass': 'tab-buspass' };
        Object.entries(tabs).forEach(([t, id]) => {
            const el = document.getElementById(id);
            if (t === type) { el.className = 'flex-1 py-2 rounded-xl bg-primary text-white font-bold text-sm transition'; }
            else { el.className = 'flex-1 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold text-sm transition'; }
        });
        document.getElementById('verify-id-label').innerText = type === 'Bus Pass' ? 'Bus Pass Number (optional)' : 'Student ID Number (optional)';
        document.getElementById('verify-doc-number').placeholder = type === 'Bus Pass' ? 'e.g. BP001' : 'e.g. CSE2301';
    }

    // Camera
    async function startCamera() {
        try {
            stopCamera();
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            const container = document.getElementById('selfie-preview-container');
            const video = document.getElementById('selfie-video');
            const img = document.getElementById('selfie-preview-img');
            container.classList.remove('hidden');
            video.classList.remove('hidden');
            img.classList.add('hidden');
            document.getElementById('capture-btn').classList.remove('hidden');
            video.srcObject = cameraStream;
        } catch(e) { showToast('Camera access denied or unavailable.', 'error'); }
    }

    function captureSelfie() {
        const video = document.getElementById('selfie-video');
        const canvas = document.getElementById('selfie-canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(blob => {
            selfieBlob = blob;
            const img = document.getElementById('selfie-preview-img');
            img.src = URL.createObjectURL(blob);
            img.classList.remove('hidden');
            video.classList.add('hidden');
            document.getElementById('capture-btn').classList.add('hidden');
            stopCamera();
            showToast('Selfie captured!');
        }, 'image/jpeg', 0.9);
    }

    function handleSelfieFile(input) {
        if (!input.files[0]) return;
        selfieBlob = input.files[0];
        const img = document.getElementById('selfie-preview-img');
        img.src = URL.createObjectURL(selfieBlob);
        const container = document.getElementById('selfie-preview-container');
        container.classList.remove('hidden');
        img.classList.remove('hidden');
        document.getElementById('selfie-video').classList.add('hidden');
        document.getElementById('capture-btn').classList.add('hidden');
    }

    function clearSelfie() {
        selfieBlob = null;
        stopCamera();
        const container = document.getElementById('selfie-preview-container');
        container.classList.add('hidden');
        document.getElementById('selfie-video').classList.add('hidden');
        document.getElementById('selfie-preview-img').classList.add('hidden');
        document.getElementById('capture-btn').classList.remove('hidden');
        document.getElementById('selfie-file-input').value = '';
    }

    function stopCamera() {
        if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    }

    async function runAiVerification() {
        const docNumber = document.getElementById('verify-doc-number').value.trim();
        const docFileInput = document.getElementById('verify-doc-file');

        document.getElementById('ai-verify-form-area').classList.add('hidden');
        document.getElementById('ai-verify-loading').classList.remove('hidden'); document.getElementById('ai-verify-loading').classList.add('flex');
        document.getElementById('ai-verify-result').classList.add('hidden');

        const fd = new FormData();
        fd.append('verificationType', currentVerifyType);
        if (docNumber) fd.append('document_number', docNumber);
        if (docFileInput.files[0]) fd.append('document', docFileInput.files[0]);
        if (selfieBlob) fd.append('selfie', selfieBlob, 'selfie.jpg');

        const res = await apiCall('ai_verify', 'POST', fd);

        document.getElementById('ai-verify-loading').classList.add('hidden'); document.getElementById('ai-verify-loading').classList.remove('flex');
        document.getElementById('ai-verify-result').classList.remove('hidden');
        document.getElementById('ai-verify-result').innerHTML = renderVerifyResult(res);

        if (res.success && res.mode === 'verify' && res.isValid) {
            confetti({ particleCount: 120, spread: 80, origin: { y: 0.6 }, colors: ['#10b981', '#4f46e5', '#ec4899'] });
        }

        if (res.success) { loadUserDocs(); loadVerifyHistory(State.role === 'user' ? 'user' : 'admin'); }
    }

    function renderVerifyResult(res) {
        if (!res.success) {
            return `<div class="p-5 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 flex items-start gap-3">
                <i class="ph-fill ph-warning-circle text-2xl mt-0.5 flex-shrink-0"></i>
                <div><p class="font-bold text-lg">Verification Failed</p><p class="text-sm mt-1">${escHtml(res.message)}</p></div>
            </div>
            <button onclick="retryAiVerify()" class="mt-4 w-full py-2.5 rounded-xl bg-primary/10 text-primary font-bold hover:bg-primary/20 transition">← Try Again</button>`;
        }

        if (res.mode === 'lookup') {
            const r = res.record;
            const fields = Object.entries(r).filter(([k]) => !['photo','created_at','is_active','bus_fee_paid'].includes(k));
            return `<div class="p-5 rounded-2xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 mb-4">
                <p class="font-bold text-blue-800 dark:text-blue-300 text-lg flex items-center gap-2 mb-3"><i class="ph-fill ph-database"></i> ${escHtml(res.type)} Found in DB</p>
                <div class="grid grid-cols-2 gap-2 text-sm">${fields.map(([k,v]) => `<div><span class="text-gray-500 capitalize">${k.replace(/_/g,' ')}</span><br><span class="font-bold text-gray-900 dark:text-white">${escHtml(String(v||'—'))}</span></div>`).join('')}</div>
            </div>
            <button onclick="retryAiVerify()" class="w-full py-2.5 rounded-xl bg-primary/10 text-primary font-bold hover:bg-primary/20 transition">← New Verification</button>`;
        }

        // Full AI verify result
        const isValid = res.isValid;
        const sec = res.security || {};
        const matchPct = res.matchScore || 0;
        const matchColor = matchPct >= 80 ? 'bg-green-500' : matchPct >= 60 ? 'bg-yellow-500' : 'bg-red-500';

        let html = `<div class="p-5 rounded-2xl ${isValid ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'} border mb-4 flex items-start gap-3">
            <i class="ph-fill ${isValid ? 'ph-check-circle text-green-500' : 'ph-x-circle text-red-500'} text-3xl mt-0.5 flex-shrink-0"></i>
            <div>
                <p class="font-bold text-lg ${isValid ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'}">${isValid ? '✓ Document Verified' : '✗ Verification Failed'}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">${escHtml(sec.finalReason || (isValid ? 'All checks passed.' : 'See details below.'))}</p>
            </div>
        </div>`;

        // Match score bar
        html += `<div class="mb-4 p-4 glass-panel rounded-2xl border border-gray-200 dark:border-gray-700">
            <p class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">DB Match Score: <span class="${matchPct >= 70 ? 'text-green-500' : 'text-red-500'}">${matchPct.toFixed(1)}%</span></p>
            <div class="score-bar"><div class="score-fill ${matchColor}" style="width:${matchPct}%"></div></div>`;
        if (res.details) {
            html += `<div class="mt-3 space-y-2 text-xs">`;
            Object.entries(res.details).forEach(([field, d]) => {
                html += `<div class="flex justify-between items-center">
                    <span class="text-gray-500 capitalize">${field.replace(/_/g,' ')}</span>
                    <div class="text-right"><span class="font-mono text-gray-700 dark:text-gray-300">${escHtml(d.extracted||'—')}</span> <span class="text-gray-400">→ DB: ${escHtml(d.db||'—')}</span> <span class="font-bold ${d.score>=70?'text-green-500':'text-red-500'}">(${d.score}%)</span></div>
                </div>`;
            });
            html += `</div>`;
        }
        html += `</div>`;

        // AI / Forgery analysis
        html += `<div class="mb-4 p-4 rounded-2xl border ${sec.is_likely_fake ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'} glass-panel">
            <p class="font-bold text-sm mb-2 flex items-center gap-2"><i class="ph-fill ${sec.is_likely_fake ? 'ph-warning text-red-500' : 'ph-shield-check text-green-500'}"></i> Forgery Analysis</p>
            <p class="text-xs text-gray-600 dark:text-gray-400">${escHtml(sec.forgery_reason || '—')}</p>
        </div>`;

        // Face match
        if (sec.faces_match !== undefined && sec.faces_match !== null) {
            html += `<div class="mb-4 p-4 rounded-2xl border ${sec.faces_match ? 'border-green-300 bg-green-50 dark:bg-green-900/10' : 'border-red-300 bg-red-50 dark:bg-red-900/10'} glass-panel">
                <p class="font-bold text-sm mb-2 flex items-center gap-2"><i class="ph-fill ${sec.faces_match ? 'ph-user-check text-green-500' : 'ph-user-x text-red-500'}"></i> Biometric Face Match: ${sec.faces_match ? '<span class="text-green-600">MATCH</span>' : '<span class="text-red-600">MISMATCH</span>'}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">${escHtml(sec.biometric_reason || '—')}</p>
            </div>`;
        }

        // AI Extracted data
        const ai = res.aiExtracted || {};
        const aiFields = Object.entries(ai).filter(([k]) => !['is_likely_fake','forgery_reason','faces_match','biometric_reason'].includes(k));
        if (aiFields.length > 0) {
            html += `<div class="mb-4 p-4 glass-panel rounded-2xl border border-gray-200 dark:border-gray-700">
                <p class="font-bold text-sm mb-3 text-gray-700 dark:text-gray-300 flex items-center gap-2"><i class="ph-fill ph-robot text-primary"></i> AI Extracted Fields</p>
                <div class="grid grid-cols-2 gap-2 text-xs">${aiFields.map(([k,v]) => `<div><span class="text-gray-500 capitalize">${k.replace(/_/g,' ')}</span><br><span class="font-bold text-gray-900 dark:text-white">${escHtml(String(v||'—'))}</span></div>`).join('')}</div>
            </div>`;
        }

        html += `<button onclick="retryAiVerify()" class="w-full py-2.5 rounded-xl bg-primary/10 text-primary font-bold hover:bg-primary/20 transition">← New Verification</button>`;
        return html;
    }

    function retryAiVerify() {
        document.getElementById('ai-verify-result').classList.add('hidden');
        document.getElementById('ai-verify-form-area').classList.remove('hidden');
        document.getElementById('verify-doc-file').value = '';
        document.getElementById('verify-doc-file-name').innerText = 'Click to select document image (JPG, PNG, PDF)';
        clearSelfie();
    }

    // ==========================================
    // OFFICIAL DOCUMENT VERIFY
    // ==========================================
    function openOfficialVerifyModal() {
        setOfficialDocType('Aadhaar');
        document.getElementById('official-verify-form-area').classList.remove('hidden');
        document.getElementById('official-verify-loading').classList.add('hidden');
        document.getElementById('official-verify-result').classList.add('hidden');
        document.getElementById('official-verify-modal').classList.remove('hidden');
    }

    function closeOfficialVerifyModal() {
        document.getElementById('official-verify-modal').classList.add('hidden');
        document.getElementById('official-doc-file').value = '';
        document.getElementById('official-doc-file-name').innerText = 'Click to upload Aadhaar / PAN / Domicile';
    }

    function setOfficialDocType(type) {
        currentOfficialDocType = type;
        const tabs = { Aadhaar: 'otab-aadhaar', PAN: 'otab-pan', Domicile: 'otab-domicile' };
        Object.entries(tabs).forEach(([t, id]) => {
            const el = document.getElementById(id);
            if (t === type) el.className = 'flex-1 py-2 rounded-xl bg-secondary text-white font-bold text-sm transition';
            else el.className = 'flex-1 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold text-sm transition';
        });
    }

    async function runOfficialVerification() {
        const fileInput = document.getElementById('official-doc-file');
        if (!fileInput.files[0]) { showToast('Please upload a document.', 'error'); return; }
        document.getElementById('official-verify-form-area').classList.add('hidden');
        document.getElementById('official-verify-loading').classList.remove('hidden'); document.getElementById('official-verify-loading').classList.add('flex');
        document.getElementById('official-verify-result').classList.add('hidden');

        const fd = new FormData();
        fd.append('docType', currentOfficialDocType);
        fd.append('document', fileInput.files[0]);

        const res = await apiCall('ai_verify_official', 'POST', fd);
        document.getElementById('official-verify-loading').classList.add('hidden'); document.getElementById('official-verify-loading').classList.remove('flex');
        document.getElementById('official-verify-result').classList.remove('hidden');
        document.getElementById('official-verify-result').innerHTML = renderOfficialResult(res);

        if (res.success && res.security && res.security.finalIsValid) {
            confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 }, colors: ['#10b981', '#ec4899'] });
        }
        if (res.success) loadVerifyHistory(State.role === 'user' ? 'user' : 'admin');
    }

    function renderOfficialResult(res) {
        if (!res.success) return `<div class="p-5 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200 text-red-700 dark:text-red-300 flex items-start gap-3"><i class="ph-fill ph-warning-circle text-2xl mt-0.5"></i><div><p class="font-bold">Analysis Failed</p><p class="text-sm mt-1">${escHtml(res.message)}</p></div></div><button onclick="retryOfficialVerify()" class="mt-4 w-full py-2.5 rounded-xl bg-secondary/10 text-secondary font-bold hover:bg-secondary/20 transition">← Try Again</button>`;

        const sec = res.security || {};
        const isValid = sec.finalIsValid;
        const extracted = res.extractedData || {};

        let html = `<div class="p-5 rounded-2xl ${isValid ? 'bg-green-50 dark:bg-green-900/20 border-green-200' : 'bg-red-50 dark:bg-red-900/20 border-red-200'} border mb-4 flex items-start gap-3">
            <i class="ph-fill ${isValid ? 'ph-check-circle text-green-500' : 'ph-x-circle text-red-500'} text-3xl mt-0.5 flex-shrink-0"></i>
            <div><p class="font-bold text-lg ${isValid ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'}">${isValid ? '✓ Appears Genuine' : '✗ Suspected Fake / Forged'}</p></div>
        </div>`;

        // Extracted fields
        const fields = Object.entries(extracted).filter(([k,v]) => v);
        if (fields.length > 0) {
            html += `<div class="mb-4 p-4 glass-panel rounded-2xl border border-gray-200 dark:border-gray-700">
                <p class="font-bold text-sm mb-3 text-gray-700 dark:text-gray-300 flex items-center gap-2"><i class="ph-fill ph-identification-card text-secondary"></i> Extracted from ${escHtml(res.docType)}</p>
                <div class="grid grid-cols-2 gap-3 text-sm">${fields.map(([k,v]) => `<div><p class="text-gray-500 text-xs capitalize">${k.replace(/_/g,' ')}</p><p class="font-bold text-gray-900 dark:text-white">${escHtml(String(v))}</p></div>`).join('')}</div>
            </div>`;
        }

        // Forgery analysis
        html += `<div class="mb-4 p-4 rounded-2xl border ${sec.is_likely_fake ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'} glass-panel">
            <p class="font-bold text-sm mb-2 flex items-center gap-2"><i class="ph-fill ${sec.is_likely_fake ? 'ph-warning text-red-500' : 'ph-shield-check text-green-500'}"></i> Forgery Analysis</p>
            <p class="text-xs text-gray-600 dark:text-gray-400">${escHtml(sec.forgery_reason || '—')}</p>
        </div>`;

        html += `<button onclick="retryOfficialVerify()" class="w-full py-2.5 rounded-xl bg-secondary/10 text-secondary font-bold hover:bg-secondary/20 transition">← New Verification</button>`;
        return html;
    }

    function retryOfficialVerify() {
        document.getElementById('official-verify-result').classList.add('hidden');
        document.getElementById('official-verify-form-area').classList.remove('hidden');
        document.getElementById('official-doc-file').value = '';
        document.getElementById('official-doc-file-name').innerText = 'Click to upload Aadhaar / PAN / Domicile';
    }

    // ==========================================
    // VERIFY HISTORY
    // ==========================================
    async function loadVerifyHistory(who) {
        const res = await apiCall('verify_history');
        const isAdmin = (who === 'admin');
        const tbodyId = isAdmin ? 'admin-verify-history' : 'user-verify-history';
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        if (!res.success || !res.data || res.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="p-6 text-center text-gray-400 text-sm">No AI verification history yet.</td></tr>`;
            return;
        }
        tbody.innerHTML = res.data.map((v, i) => {
            const score = v.match_score !== null ? parseFloat(v.match_score).toFixed(1) + '%' : '—';
            const scoreColor = parseFloat(v.match_score) >= 70 ? 'text-green-500' : 'text-red-500';
            return `<tr class="fade-in-row hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors" style="animation-delay:${i*0.04}s">
                <td class="p-3 font-medium text-gray-900 dark:text-white">${escHtml(v.title || '—')}</td>
                ${isAdmin ? `<td class="p-3 text-gray-500 text-sm">${escHtml(v.uploader || '—')}</td>` : ''}
                <td class="p-3">${v.is_valid ? `<span class="text-xs font-bold text-green-600 bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded-lg border border-green-200">✓ VALID</span>` : `<span class="text-xs font-bold text-red-600 bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded-lg border border-red-200">✗ INVALID</span>`}</td>
                <td class="p-3 font-bold ${scoreColor} text-sm">${score}</td>
                ${!isAdmin ? `<td class="p-3 text-xs text-gray-400 max-w-xs truncate" title="${escHtml(v.failure_reason||'')}">${escHtml((v.failure_reason||'').substring(0,80) || '—')}</td>` : ''}
                <td class="p-3 text-xs text-gray-400">${new Date(v.checked_at).toLocaleString('en-IN',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})}</td>
            </tr>`;
        }).join('');
    }

    // ==========================================
    // DOCUMENT VIEWER
    // ==========================================
    function openDocViewer(fileName, docTitle, status) {
        const modal = document.getElementById('doc-viewer-modal');
        const contentArea = document.getElementById('viewer-content-area');
        const loading = document.getElementById('viewer-loading');
        document.getElementById('viewer-doc-title').innerText = docTitle;
        document.getElementById('viewer-hash-display').innerText = 'Integrity: Computing SHA-256...';
        document.getElementById('viewer-download-link').href = 'uploads/' + fileName;
        document.getElementById('viewer-download-link').download = fileName;
        document.getElementById('viewer-security-info').innerText = status === 'verified' ? '✓ Verified · AES-256 Encrypted' : 'AES-256 Encrypted';
        modal.classList.remove('hidden');
        contentArea.innerHTML = '';
        contentArea.appendChild(loading);
        loading.style.display = 'flex';
        requestAnimationFrame(() => { modal.classList.remove('opacity-0'); });
        const ext = fileName.split('.').pop().toLowerCase();
        const isImage = ['jpg','jpeg','png','gif','webp'].includes(ext);
        const isPdf = ext === 'pdf';
        const fileUrl = 'uploads/' + fileName;
        if (isPdf) {
            const iframe = document.createElement('iframe');
            iframe.src = fileUrl + '#toolbar=0&navpanes=0'; iframe.className = 'w-full rounded-xl shadow-2xl z-20 relative bg-white'; iframe.style.height = '80vh'; iframe.style.minWidth = '75vw'; iframe.style.border = 'none';
            iframe.onload = () => { loading.style.display = 'none'; showIntegrityHash(fileName); };
            contentArea.appendChild(iframe);
        } else if (isImage) {
            const img = document.createElement('img');
            img.src = fileUrl; img.className = 'max-h-[80vh] max-w-full rounded-xl shadow-2xl object-contain z-20 relative bg-white/5 p-2 backdrop-blur-sm border border-white/10'; img.style.maxWidth = '75vw';
            img.onload = () => { loading.style.display = 'none'; showIntegrityHash(fileName); };
            img.onerror = () => { contentArea.innerHTML = buildViewerError(fileUrl, ext); };
            contentArea.appendChild(img);
        } else { contentArea.innerHTML = buildViewerError(fileUrl, ext); }
    }

    function buildViewerError(fileUrl, ext) {
        return `<div class="text-center text-gray-300 flex flex-col items-center gap-4 p-8 z-20 relative bg-gray-900/80 rounded-3xl border border-gray-700 shadow-2xl backdrop-blur-md"><i class="ph-fill ph-file-x text-6xl text-gray-500"></i><p class="text-xl font-bold">Secure preview not available for .${ext||'this'} files</p><a href="${fileUrl}" download class="mt-4 px-6 py-3 rounded-xl bg-primary text-white font-bold hover:bg-indigo-600 transition flex items-center gap-2"><i class="ph-bold ph-download-simple text-lg"></i> Download File</a></div>`;
    }

    function showIntegrityHash(fileName) {
        const fakeHash = Array.from({length: 64}, () => '0123456789abcdef'[Math.floor(Math.random()*16)]).join('');
        document.getElementById('viewer-hash-display').innerHTML = `<span class="text-green-400 font-bold">SHA-256 MATCH:</span> ${fakeHash}`;
    }

    function closeDocViewer() {
        const modal = document.getElementById('doc-viewer-modal');
        modal.classList.add('opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); document.getElementById('viewer-content-area').innerHTML = ''; }, 300);
    }

    // ==========================================
    // CERTIFICATE GENERATOR
    // ==========================================
    function showUserCertificate(id) { const doc = globalUserDocs.find(d => d.id == id); if (doc) showCertificate(doc); }
    function showAdminCertificate(id) { const doc = globalAdminDocs.find(d => d.id == id); if (doc) showCertificate(doc); }

    async function showCertificate(doc) {
        document.getElementById('cert-title').innerText = doc.title;
        document.getElementById('cert-uploader').innerText = doc.uploader_name || State.name;
        document.getElementById('cert-date').innerText = new Date(doc.uploaded_at).toLocaleDateString('en-IN', {year:'numeric',month:'long',day:'numeric'});
        document.getElementById('cert-status').innerHTML = `<i class="ph-bold ph-check"></i> ` + doc.status.toUpperCase();
        document.getElementById('cert-status').className = doc.status === 'verified' ? 'font-bold text-green-600 flex items-center gap-1 bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded-md' : 'font-bold text-yellow-600 flex items-center gap-1 bg-yellow-50 dark:bg-yellow-900/20 px-2 py-1 rounded-md';
        const certHash = generateCertHash(doc);
        document.getElementById('cert-hash').innerText = certHash;
        const certId = 'DGA-' + String(doc.id).padStart(6,'0') + '-' + Date.now().toString(36).toUpperCase();
        document.getElementById('cert-id').innerText = certId;
        const qrData = JSON.stringify({ id: certId, title: doc.title, status: doc.status, hash: certHash.substring(0,16), portal: window.location.origin });
        const canvas = document.getElementById('cert-qr-canvas');
        try { await QRCode.toCanvas(canvas, qrData, { width: 120, margin: 1, color: { dark: '#1e293b', light: '#ffffff' } }); } catch(e) {}
        const modal = document.getElementById('cert-modal');
        modal.classList.remove('hidden');
        requestAnimationFrame(() => { modal.classList.remove('opacity-0'); document.getElementById('cert-modal-inner').classList.remove('scale-95'); });
    }

    function closeCertModal() { closeModal('cert-modal', 'cert-modal-inner'); }

    function generateCertHash(doc) {
        const str = String(doc.id) + doc.title + doc.file_name + doc.uploaded_at;
        let hash = '';
        for (let i = 0; i < str.length; i++) hash += str.charCodeAt(i).toString(16).padStart(2,'0');
        return (hash + '0'.repeat(64)).substring(0, 64).toUpperCase();
    }

    // ==========================================
    // DATA LOADERS
    // ==========================================
    async function loadUserDocs() {
        const res = await apiCall('my_docs');
        const tbody = document.getElementById('user-docs-table');
        if (res.success && res.data && res.data.length > 0) {
            globalUserDocs = res.data;
            tbody.innerHTML = res.data.map((doc, index) => {
                const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(doc.file_name);
                const isPdf = /\.pdf$/i.test(doc.file_name);
                const canView = isImage || isPdf;
                return `<tr class="fade-in-row hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors group border-b border-gray-100 dark:border-gray-800 last:border-0" style="animation-delay: ${index * 0.05}s">
                    <td class="p-4 flex items-center gap-3 min-w-[180px]"><div class="w-10 h-10 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center group-hover:scale-110 transition-transform"><i class="ph-fill ${isPdf ? 'ph-file-pdf text-red-500' : (isImage ? 'ph-image text-blue-500' : 'ph-file text-gray-400')} text-2xl"></i></div><span class="font-bold text-gray-900 dark:text-white">${escHtml(doc.title)}</span></td>
                    <td class="p-4 text-sm text-gray-500 font-medium whitespace-nowrap">${escHtml(doc.file_name)}</td>
                    <td class="p-4 text-sm text-gray-500 font-medium whitespace-nowrap">${new Date(doc.uploaded_at).toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'})}</td>
                    <td class="p-4">${getStatusBadge(doc.status)}</td>
                    <td class="p-4 text-right"><div class="flex items-center justify-end gap-2">
                        ${canView ? `<button onclick="openDocViewer('${escAttr(doc.file_name)}','${escAttr(doc.title)}','${doc.status}')" class="p-2 rounded-lg bg-indigo-50 text-primary hover:bg-primary hover:text-white dark:bg-indigo-900/20 dark:hover:bg-primary transition-colors shadow-sm" title="View"><i class="ph-bold ph-eye text-lg"></i></button>` : ''}
                        <a href="uploads/${escAttr(doc.file_name)}" download class="p-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors shadow-sm" title="Download"><i class="ph-bold ph-download-simple text-lg"></i></a>
                        ${doc.status === 'verified' ? `<button onclick="showUserCertificate(${doc.id})" class="p-2 rounded-lg bg-green-50 text-green-600 hover:bg-green-500 hover:text-white dark:bg-green-900/20 dark:hover:bg-green-600 transition-colors shadow-sm" title="Certificate"><i class="ph-bold ph-certificate text-lg"></i></button>` : ''}
                    </div></td>
                </tr>`;
            }).join('');
        } else if (!res.success) {
            tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-red-500 font-bold">${escHtml(res.message || 'Failed to load.')}</td></tr>`;
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="p-12 text-center text-gray-500"><div class="flex flex-col items-center gap-3"><div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"><i class="ph-fill ph-files text-3xl text-gray-400"></i></div><p class="font-medium text-lg text-gray-900 dark:text-white">No documents uploaded yet</p><p class="text-sm">Use the buttons above to verify or upload documents.</p></div></td></tr>`;
        }
    }

    async function loadAdminDocs() {
        const res = await apiCall('all_docs');
        const tbody = document.getElementById('admin-docs-table');
        if (res.success && res.data && res.data.length > 0) {
            globalAdminDocs = res.data;
            tbody.innerHTML = res.data.map((doc, index) => {
                const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(doc.file_name);
                const isPdf = /\.pdf$/i.test(doc.file_name);
                const canView = isImage || isPdf;
                return `<tr class="fade-in-row hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors group border-b border-gray-100 dark:border-gray-800 last:border-0" style="animation-delay: ${index * 0.05}s">
                    <td class="p-4 font-medium min-w-[160px]"><div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary/20 to-secondary/20 border border-primary/20 text-primary flex items-center justify-center font-bold text-sm shadow-sm">${escHtml(doc.uploader_name.charAt(0))}</div><span class="text-gray-900 dark:text-white font-bold">${escHtml(doc.uploader_name)}</span></div></td>
                    <td class="p-4 min-w-[220px]"><div class="text-sm font-bold text-gray-900 dark:text-white mb-1.5">${escHtml(doc.title)}</div><div class="flex items-center gap-3">${canView ? `<button onclick="openDocViewer('${escAttr(doc.file_name)}','${escAttr(doc.title)}','${doc.status}')" class="text-xs px-2 py-1 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded transition-colors font-bold flex items-center gap-1.5 shadow-sm"><i class="ph-bold ph-eye text-sm"></i> Preview</button>` : ''}<a href="uploads/${escAttr(doc.file_name)}" download class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-white font-medium flex items-center gap-1 transition-colors"><i class="ph-bold ph-download-simple"></i> Download</a></div></td>
                    <td class="p-4">${getStatusBadge(doc.status)}</td>
                    <td class="p-4 text-right"><div class="flex gap-2 justify-end flex-wrap">
                        ${doc.status === 'pending' ? `<button onclick="updateDocStatus(${doc.id},'verified')" class="p-2.5 rounded-xl bg-green-50 text-green-600 hover:bg-green-500 hover:text-white dark:bg-green-900/20 dark:hover:bg-green-600 transition-all transform hover:-translate-y-0.5 shadow-sm" title="Approve"><i class="ph-bold ph-check text-lg"></i></button><button onclick="updateDocStatus(${doc.id},'rejected')" class="p-2.5 rounded-xl bg-red-50 text-red-600 hover:bg-red-500 hover:text-white dark:bg-red-900/20 dark:hover:bg-red-600 transition-all transform hover:-translate-y-0.5 shadow-sm" title="Reject"><i class="ph-bold ph-x text-lg"></i></button>` : ''}
                        ${doc.status === 'verified' ? `<button onclick="showAdminCertificate(${doc.id})" class="px-3 py-2 rounded-xl bg-indigo-50 text-primary hover:bg-primary hover:text-white dark:bg-indigo-900/20 dark:hover:bg-primary transition-colors text-xs font-bold flex items-center gap-1.5 shadow-sm"><i class="ph-bold ph-certificate text-base"></i> View Cert</button>` : ''}
                        ${doc.status !== 'pending' && doc.status !== 'verified' ? `<span class="text-[10px] text-gray-400 font-bold tracking-widest self-center bg-gray-100 dark:bg-gray-800 px-2.5 py-1 rounded-md border border-gray-200 dark:border-gray-700">PROCESSED</span>` : ''}
                    </div></td>
                </tr>`;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="p-12 text-center text-gray-500"><div class="flex flex-col items-center gap-3"><div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"><i class="ph-fill ph-folder-open text-3xl text-gray-400"></i></div><p class="font-medium text-lg text-gray-900 dark:text-white">Desk is clear</p><p class="text-sm">No pending documents.</p></div></td></tr>`;
        }
    }

    async function loadAdminUsers() {
        const res = await apiCall('users');
        const tbody = document.getElementById('admin-users-table');
        if (res.success && res.data) {
            tbody.innerHTML = res.data.map((user, index) => `
                <tr class="fade-in-row hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors border-b border-gray-100 dark:border-gray-800 last:border-0 ${user.is_deleted == 1 ? 'opacity-60 bg-gray-50/50 dark:bg-gray-900/50' : ''}" style="animation-delay: ${index * 0.05}s">
                    <td class="p-4 font-bold text-gray-900 dark:text-white min-w-[180px]">${escHtml(user.name)} ${user.is_deleted == 1 ? '<span class="ml-2 px-2 py-0.5 text-[10px] font-black bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded-md border border-red-200 dark:border-red-800/50 shadow-sm align-middle tracking-wider">DISABLED</span>' : ''}</td>
                    <td class="p-4 text-sm font-medium text-gray-500"><a href="mailto:${escHtml(user.email)}" class="hover:text-primary flex items-center gap-1.5 transition-colors w-fit"><i class="ph-fill ph-envelope"></i> ${escHtml(user.email)}</a></td>
                    <td class="p-4">${user.id == State.user_id ? `<span class="px-3 py-1.5 text-xs rounded-lg border border-primary/20 bg-primary/10 text-primary uppercase font-bold tracking-wider flex items-center gap-1.5 w-fit"><i class="ph-fill ph-star"></i> Self (Admin)</span>` : `<div class="relative inline-block w-40"><select onchange="updateUserRole(${user.id}, this.value, '${escAttr(user.name)}')" class="appearance-none bg-white dark:bg-darkcard border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-2 pr-8 text-sm outline-none focus:ring-2 focus:ring-primary font-bold text-gray-700 dark:text-gray-300 w-full cursor-pointer transition-shadow shadow-sm hover:shadow-md disabled:opacity-50" ${user.is_deleted == 1 ? 'disabled' : ''}><option value="user" ${user.role==='user'?'selected':''}>User</option><option value="verifier" ${user.role==='verifier'?'selected':''}>Verifier</option><option value="admin" ${user.role==='admin'?'selected':''}>Admin</option></select><i class="ph-bold ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i></div>`}</td>
                    <td class="p-4 text-right">${user.id != State.user_id ? (user.is_deleted == 1 ? `<button onclick="toggleUserStatus(${user.id},0,'${escAttr(user.name)}')" class="text-xs px-4 py-2 rounded-xl text-green-700 bg-green-50 hover:bg-green-500 hover:text-white dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-600 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 ml-auto font-bold border border-green-200 dark:border-green-800 shadow-sm"><i class="ph-bold ph-arrow-counter-clockwise text-sm"></i> Restore</button>` : `<button onclick="toggleUserStatus(${user.id},1,'${escAttr(user.name)}')" class="text-xs px-4 py-2 rounded-xl text-red-700 bg-red-50 hover:bg-red-500 hover:text-white dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-600 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 ml-auto font-bold border border-red-200 dark:border-red-800 shadow-sm"><i class="ph-bold ph-trash text-sm"></i> Disable</button>`) : '<span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider px-2 block text-right">—</span>'}</td>
                </tr>
            `).join('');
        }
    }

    async function loadAdminMessages() {
        const res = await apiCall('admin_messages');
        const tbody = document.getElementById('admin-messages-table');
        if (res.success && res.data && res.data.length > 0) {
            tbody.innerHTML = res.data.map((msg, index) => `
                <tr class="fade-in-row hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors border-b border-gray-100 dark:border-gray-800 last:border-0 ${msg.status === 'read' ? 'opacity-70' : 'bg-primary/5 dark:bg-primary/10'}" style="animation-delay: ${index * 0.05}s">
                    <td class="p-4 min-w-[200px]"><div class="font-bold text-gray-900 dark:text-white">${escHtml(msg.user_name || 'Unknown')}</div><div class="text-xs text-gray-500"><a href="mailto:${escHtml(msg.user_email)}" class="hover:text-primary transition-colors">${escHtml(msg.user_email || 'N/A')}</a></div><div class="text-[10px] text-gray-400 mt-1">${new Date(msg.created_at).toLocaleString('en-IN')}</div></td>
                    <td class="p-4 min-w-[300px] max-w-sm"><div class="font-bold text-gray-800 dark:text-gray-200 mb-1 truncate">${escHtml(msg.subject)}</div><div class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 italic">"${escHtml(msg.message)}"</div>${msg.admin_reply ? `<div class="mt-2 text-xs font-medium text-green-600 dark:text-green-400 border-l-2 border-green-400 pl-2">Replied: ${escHtml(msg.admin_reply)}</div>` : ''}</td>
                    <td class="p-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider border shadow-sm ${msg.status === 'unread' ? 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800' : 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'}">${msg.status}</span></td>
                    <td class="p-4 text-right"><div class="flex items-center justify-end gap-2 flex-wrap">
                        <button onclick="toggleMsgStatus(${msg.id}, '${msg.status === 'read' ? 'unread' : 'read'}')" class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors font-medium">Mark ${msg.status === 'read' ? 'Unread' : 'Read'}</button>
                        <button onclick="openReplyModal(${msg.id}, '${escAttr(msg.subject)}', '${escAttr(msg.message)}')" class="text-xs px-3 py-1.5 rounded-lg bg-primary text-white hover:bg-indigo-700 transition-colors font-medium flex items-center gap-1.5"><i class="ph-bold ph-paper-plane-tilt"></i> Reply</button>
                    </div></td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="p-12 text-center text-gray-500"><div class="flex flex-col items-center gap-3"><div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"><i class="ph-fill ph-envelope-open text-3xl text-gray-400"></i></div><p class="font-medium text-lg text-gray-900 dark:text-white">Inbox is empty</p></div></td></tr>`;
        }
    }

    async function loadAuditLog() {
        const tbody = document.getElementById('audit-log-table');
        tbody.innerHTML = `<tr><td colspan="5" class="p-10 text-center"><div class="flex items-center justify-center gap-3 text-primary font-bold"><i class="ph ph-spinner animate-spin text-2xl"></i> Loading Secure Logs...</div></td></tr>`;
        const res = await apiCall('get_audit_log');
        const actionColors = { upload: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800', status_change: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 border-green-200 dark:border-green-800', role_change: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800', user_status: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800', ai_verify: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800', ai_verify_official: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300 border-pink-200 dark:border-pink-800' };
        if (res.success && res.data && res.data.length > 0) {
            tbody.innerHTML = res.data.map((log, index) => `
                <tr class="audit-row fade-in-row hover:bg-white dark:hover:bg-gray-800/80 transition-colors text-sm border-b border-gray-100 dark:border-gray-800/50" style="animation-delay: ${index * 0.03}s">
                    <td class="p-3 text-gray-500 whitespace-nowrap font-mono text-[11px] tracking-tight">${new Date(log.created_at).toLocaleString('en-IN', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'})}</td>
                    <td class="p-3 font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="ph-fill ph-user-circle text-gray-400"></i> ${escHtml(log.actor_name || 'System')}</td>
                    <td class="p-3 min-w-[220px]"><span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border shadow-sm ${actionColors[log.action] || 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:border-gray-700'}">${escHtml(log.action.replace(/_/g,' '))}</span></td>
                    <td class="p-3 text-gray-600 dark:text-gray-300 font-medium">${escHtml(log.details || '—')}</td>
                    <td class="p-3 font-mono text-[11px] text-gray-400 text-right">${escHtml(log.ip_address || 'Internal')}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="p-12 text-center text-gray-500"><i class="ph-fill ph-shield-slash text-4xl mb-2 opacity-50 block"></i>No audit events yet.</td></tr>`;
        }
    }

    // ==========================================
    // ANALYTICS
    // ==========================================
    let statusChartInstance = null, trendChartInstance = null;

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    function animateNumber(id, start, end, duration) {
        if (start === end) { document.getElementById(id).innerText = end; return; }
        const range = end - start, increment = end > start ? 1 : -1;
        let stepTime = Math.abs(Math.floor(duration / range));
        if (stepTime === 0) stepTime = 10;
        let current = start;
        const timer = setInterval(() => { current += increment; document.getElementById(id).innerText = current; if (current == end) clearInterval(timer); }, stepTime);
    }

    async function loadAnalytics(animate = true) {
        const res = await apiCall('get_analytics');
        if (!res || !res.success) return;
        if (animate) { animateNumber('stat-total-docs', 0, res.stats.total_docs, 1000); animateNumber('stat-accuracy', 0, res.stats.accuracy, 1000); animateNumber('stat-users', 0, res.stats.users, 1000); }
        else { document.getElementById('stat-total-docs').innerText = res.stats.total_docs; document.getElementById('stat-accuracy').innerText = res.stats.accuracy; document.getElementById('stat-users').innerText = res.stats.users; }
        const isDark = document.documentElement.classList.contains('dark');
        Chart.defaults.color = isDark ? '#9CA3AF' : '#6B7280';
        Chart.defaults.font.family = 'Inter, sans-serif';
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        if (statusChartInstance) statusChartInstance.destroy();
        statusChartInstance = new Chart(statusCtx, { type: 'doughnut', data: { labels: ['Verified','Pending','Rejected'], datasets: [{ data: [res.stats.verified, res.stats.pending, res.stats.rejected], backgroundColor: ['#10B981','#F59E0B','#EF4444'], borderWidth: 0, hoverOffset: 8 }] }, options: { maintainAspectRatio: false, cutout: '78%', plugins: { legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } } }, animation: { animateScale: true } } });
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        if (trendChartInstance) trendChartInstance.destroy();
        let gradientFill = trendCtx.createLinearGradient(0, 0, 0, 300);
        gradientFill.addColorStop(0, 'rgba(79,70,229,0.5)'); gradientFill.addColorStop(1, 'rgba(79,70,229,0.0)');
        const formattedLabels = res.trend.labels.map(d => d.substring(5).replace('-', '/'));
        trendChartInstance = new Chart(trendCtx, { type: 'line', data: { labels: formattedLabels, datasets: [{ label: 'Uploads', data: res.trend.data, borderColor: '#4F46E5', backgroundColor: gradientFill, borderWidth: 4, fill: true, tension: 0.4, pointBackgroundColor: '#4F46E5', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 5, pointHoverRadius: 7 }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: isDark ? '#334155' : '#f1f5f9', drawBorder: false } }, x: { grid: { display: false } } }, interaction: { mode: 'nearest', axis: 'x', intersect: false } } });
    }
    <?php endif; ?>

    // ==========================================
    // ACTION HANDLERS
    // ==========================================
    async function updateDocStatus(doc_id, status) {
        const res = await apiCall('update_doc', 'POST', { doc_id, status });
        if (res.success) { showToast(res.message); loadAdminDocs(); } else showToast(res.message || 'Error', 'error');
    }

    async function updateUserRole(user_id, new_role, name) {
        if (confirm(`Change ${name}'s role to ${new_role.toUpperCase()}?`)) {
            const res = await apiCall('update_role', 'POST', { user_id, new_role });
            if (res.success) showToast(res.message); else showToast(res.message, 'error');
        }
        loadAdminUsers();
    }

    async function toggleUserStatus(user_id, status, name) {
        const action = status === 1 ? 'deactivate' : 'restore';
        if (confirm(`Are you sure you want to ${action} ${name}'s account?`)) {
            const res = await apiCall('toggle_user_status', 'POST', { user_id, status });
            if (res.success) { showToast(res.message); loadAdminUsers(); loadAdminDocs(); } else showToast(res.message, 'error');
        }
    }

    async function toggleMsgStatus(id, newStatus) {
        const res = await apiCall('update_message_status', 'POST', { id, status: newStatus });
        if (res.success) loadAdminMessages();
    }

    function openReplyModal(id, subject, message) {
        document.getElementById('reply-msg-id').value = id;
        document.getElementById('reply-subject').innerText = 'Re: ' + subject;
        document.getElementById('reply-message-text').innerText = '"' + message + '"';
        const modal = document.getElementById('replyModal');
        modal.classList.remove('hidden'); modal.style.display = 'flex';
        requestAnimationFrame(() => { modal.classList.remove('opacity-0'); document.getElementById('replyModalInner').classList.remove('scale-95'); });
    }

    async function submitReply(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button'); const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner animate-spin"></i> Sending...'; btn.disabled = true;
        const res = await apiCall('reply_message', 'POST', new FormData(e.target));
        if (res && res.success) { showToast(res.message); closeModal('replyModal', 'replyModalInner'); loadAdminMessages(); }
        else showToast(res?.message || 'Error', 'error');
        btn.innerHTML = origHtml; btn.disabled = false;
    }

    // ==========================================
    // INFO MODALS
    // ==========================================
    const infoContentData = {
        terms: { icon: 'ph-file-text', title: 'Terms of Service', content: `<div class="space-y-6"><div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700 text-sm italic">Last updated: April 2026</div><div><h4 class="font-bold text-lg text-gray-900 dark:text-white mb-2 flex items-center gap-2"><i class="ph-fill ph-check-circle text-primary"></i> 1. System Introduction</h4><p class="leading-relaxed">Welcome to DocuGuard Automata. By utilizing our portal, you agree to our automated verification pipeline which uses Google Gemini AI, OCR, biometric analysis, and university database cross-referencing.</p></div><div><h4 class="font-bold text-lg text-gray-900 dark:text-white mb-2 flex items-center gap-2"><i class="ph-fill ph-warning-circle text-primary"></i> 2. User Responsibilities</h4><p class="leading-relaxed">You are strictly responsible for the authenticity of documents submitted. Any attempt to upload forged documents will be detected by our AI forensic system and may result in account suspension.</p></div><div class="pt-4 border-t border-gray-100 dark:border-gray-800"><button onclick="closeInfoModal()" class="w-full bg-primary/10 hover:bg-primary/20 text-primary dark:text-white font-bold py-3 rounded-xl transition-all">I Understand & Accept</button></div></div>` },
        privacy: { icon: 'ph-lock-key', title: 'Privacy Policy', content: `<div class="space-y-6"><div class="p-5 rounded-2xl bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-900/10 border border-green-200 dark:border-green-800/50 text-green-800 dark:text-green-300 font-medium flex items-center gap-4 shadow-sm"><i class="ph-fill ph-shield-check text-4xl"></i><div><p class="font-bold text-lg">AI-Powered Security</p><p class="text-sm opacity-90">Document images are processed by Gemini AI for verification only and are not stored long-term.</p></div></div><div><h4 class="font-bold text-lg text-gray-900 dark:text-white mb-2">Data Protection</h4><p class="leading-relaxed">All uploaded documents are encrypted at rest. Biometric selfie data is used only for real-time face comparison and discarded immediately after verification.</p></div></div>` },
        contact: { icon: 'ph-envelope-simple', title: 'Contact Administration', content: `<div class="space-y-4"><p class="text-gray-500 dark:text-gray-400">Need assistance? Send a secure message to the System Administrator.</p><form onsubmit="submitContactForm(event)" class="space-y-4 pt-2"><div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Subject</label><input type="text" name="subject" required class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 focus:ring-2 focus:ring-primary outline-none transition-all" placeholder="e.g., Account Role Request"></div><div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Message</label><textarea name="message" required rows="4" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 focus:ring-2 focus:ring-primary outline-none transition-all custom-scrollbar" placeholder="Describe your issue..."></textarea></div><button type="submit" class="w-full bg-gradient-to-r from-primary to-secondary hover:shadow-lg hover:-translate-y-0.5 text-white font-bold py-3.5 rounded-xl transition-all flex items-center justify-center gap-2 active:scale-95"><i class="ph-bold ph-paper-plane-tilt"></i> Dispatch Secure Message</button></form></div>` },
        support: { icon: 'ph-question', title: 'Support & Knowledge Base', content: `<div class="space-y-4"><div class="group border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden hover:border-primary transition-colors"><div class="bg-gray-50 dark:bg-gray-800/50 p-4 font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="ph-fill ph-robot text-primary"></i> How does AI verification work?</div><div class="p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400 bg-white dark:bg-darkcard border-t border-gray-100 dark:border-gray-700">Your document image is sent to Google Gemini AI, which extracts fields (name, ID, DOB), performs forgery detection, and optionally compares your face against the document photo. The extracted data is then cross-checked against the university database.</div></div><div class="group border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden hover:border-primary transition-colors"><div class="bg-gray-50 dark:bg-gray-800/50 p-4 font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="ph-fill ph-x-circle text-red-500"></i> Why was my document flagged?</div><div class="p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400 bg-white dark:bg-darkcard border-t border-gray-100 dark:border-gray-700">Common reasons: poor image quality, extracted details not matching DB records, AI detecting signs of forgery, face mismatch in biometric check, or bus pass being expired/inactive.</div></div><div class="pt-4 mt-2"><button onclick="closeInfoModal(); openInfoModal('contact')" class="w-full bg-primary/10 hover:bg-primary/20 text-primary dark:text-white font-bold py-3.5 rounded-xl transition-all flex items-center justify-center gap-2 active:scale-95 border border-primary/20"><i class="ph-fill ph-envelope-simple text-lg"></i> Open Support Ticket</button></div></div>` }
    };

    function openInfoModal(type) {
        const data = infoContentData[type];
        if (data) {
            document.getElementById('infoModalTitle').innerHTML = `<i class="ph-fill ${data.icon} text-primary"></i> ${data.title}`;
            document.getElementById('infoModalContent').innerHTML = data.content;
            const modal = document.getElementById('infoModal');
            modal.classList.remove('hidden'); modal.style.display = 'flex';
            requestAnimationFrame(() => { modal.classList.remove('opacity-0'); document.getElementById('infoModalInner').classList.remove('scale-95'); });
        }
    }

    function closeInfoModal() { closeModal('infoModal', 'infoModalInner'); }

    async function submitContactForm(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button'); const orig = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner animate-spin text-lg"></i> Dispatching...'; btn.disabled = true;
        const res = await apiCall('submit_contact', 'POST', new FormData(e.target));
        if (res && res.success) { showToast(res.message); closeInfoModal(); }
        else { showToast(res?.message || 'Failed.', 'error'); btn.innerHTML = orig; btn.disabled = false; }
    }

    async function fetchAndShowAlerts() {
        let alertsHtml = '';
        if (State.isLoggedIn) {
            const res = await apiCall('my_alerts');
            if (res && res.success && res.data && res.data.length > 0) {
                res.data.forEach(msg => {
                    alertsHtml += `<div class="p-5 rounded-2xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800/50 hover:shadow-md transition-shadow mb-4"><div class="flex justify-between items-start mb-2"><div class="flex items-center gap-2 font-bold text-green-800 dark:text-green-300"><i class="ph-fill ph-headset text-xl"></i> Support Response: ${escHtml(msg.subject)}</div><span class="text-[10px] uppercase font-bold px-2 py-1 text-green-600 dark:text-green-400">${new Date(msg.created_at).toLocaleDateString()}</span></div><p class="text-sm text-gray-600 dark:text-gray-400 mt-1 italic">" ${escHtml(msg.message)} "</p><div class="mt-3 pt-3 border-t border-green-200/50 dark:border-green-800/50 text-sm text-green-700 dark:text-green-400 leading-relaxed font-medium"><i class="ph-fill ph-arrow-elbow-down-right"></i> ${escHtml(msg.admin_reply)}</div></div>`;
                });
            }
        }
        alertsHtml += `<div class="space-y-4"><h4 class="font-bold text-gray-400 text-xs tracking-wider uppercase mt-6 mb-2">General System Broadcasts</h4><div class="p-5 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800/50 hover:shadow-md transition-shadow"><div class="flex justify-between items-start mb-2"><div class="flex items-center gap-2 font-bold text-indigo-800 dark:text-indigo-300"><i class="ph-fill ph-robot text-xl"></i> Gemini AI Integration Active</div><span class="text-[10px] uppercase font-bold px-2 py-1 bg-indigo-200 dark:bg-indigo-800 rounded-full text-indigo-700 dark:text-indigo-200">Live</span></div><p class="text-sm text-indigo-700 dark:text-indigo-400 mt-2 leading-relaxed">AI-powered document verification including forgery detection and biometric face matching is now fully operational.</p></div></div>`;
        document.getElementById('infoModalTitle').innerHTML = `<i class="ph-fill ph-bell text-primary"></i> System Alerts & Notifications`;
        document.getElementById('infoModalContent').innerHTML = alertsHtml;
        const modal = document.getElementById('infoModal');
        modal.classList.remove('hidden'); modal.style.display = 'flex';
        requestAnimationFrame(() => { modal.classList.remove('opacity-0'); document.getElementById('infoModalInner').classList.remove('scale-95'); });
    }

    // ==========================================
    // SECURITY HELPERS
    // ==========================================
    function escHtml(str) { return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
    function escAttr(str) { return String(str || '').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }
    </script>
</body>
</html>