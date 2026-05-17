<?php
// ============================================
// DocuGuard - Official Document Verification API
// Author: Sujal Patel
// Module: Aadhaar / PAN / Domicile AI Verification
// ============================================

// Requires: config/config.php, includes/helpers.php

// --- ENDPOINT: ai_verify_official ---
// POST /index.php?api=1&action=ai_verify_official
// Accepts: docType (Aadhaar|PAN|Domicile), document image file
// Returns: JSON with is_valid, extracted fields, forgery flags

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

