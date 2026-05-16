<?php
// ============================================
// DocuGuard - Bus Pass & Student ID Verification API
// Author: Sujal Patidar
// Module: AI-powered Student ID + Bus Pass verification with biometric face matching
// ============================================

// Endpoint: POST /index.php?api=1&action=ai_verify
// Supports: Student ID card scan + database cross-reference
//           Bus Pass validation + face biometric match

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

