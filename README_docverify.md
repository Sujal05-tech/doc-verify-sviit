# DocuGuard — Official Document Verification Module
**Branch:** `feature/doc-verify`  
**Developer:** Sujal Patel  
**Module:** Aadhaar / PAN / Domicile AI Verification

---

## Files Modified / Added

| File | Description |
|---|---|
| `api/official_verify.php` | REST endpoint for official government document verification |
| `api/official_verify_ui.js` | Frontend JS — modal controls, form handling, result rendering for official docs |
| `includes/helpers.php` | Gemini Vision API integration — `geminiAnalyze()`, `geminiAnalyzeMulti()`, `fuzzyScore()`, OTP email |

---

## API Endpoint

```
POST /index.php?api=1&action=ai_verify_official
```

**Request (multipart/form-data):**
| Field | Type | Description |
|---|---|---|
| `docType` | string | `Aadhaar` / `PAN` / `Domicile` |
| `document` | file | Image of the document (JPG/PNG/PDF) |

**Response:**
```json
{
  "success": true,
  "result": {
    "is_valid": true,
    "doc_type": "Aadhaar",
    "extracted": {
      "name": "Amit Patel",
      "dob": "1999-05-12",
      "id_number": "XXXX-XXXX-1234"
    },
    "forgery_flags": [],
    "confidence_score": 94.5
  }
}
```

---

## How It Works

1. User uploads document image via the Official Verify modal
2. Image converted to base64 and sent to **Gemini 2.5 Flash Vision API**
3. Custom prompt instructs Gemini to extract fields AND check for forgery indicators:
   - Font inconsistencies
   - Metadata anomalies  
   - Missing security features
   - Pixel manipulation artifacts
4. Result stored in `verification_results` table with audit log entry
5. Verification certificate generated on success

---

## Forgery Detection Checks
- Aadhaar: hologram presence, QR code validity, font consistency
- PAN: embossing check, issuer watermark, signature field
- Domicile: official seal, authorized signatory, date format

---

## Tech Used
- Google Gemini 2.5 Flash Vision API (with fallback to 2.0 Flash)
- PHP cURL for API calls
- Levenshtein distance for fuzzy name matching
- Base64 image encoding
