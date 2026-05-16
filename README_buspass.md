# DocuGuard — Bus Pass & Student ID Verification Module
**Branch:** `feature/bus-pass`  
**Developer:** Sujal Patidar  
**Module:** AI Student ID Verification + Bus Pass Validation + Biometric Face Matching

---

## Files Modified / Added

| File | Description |
|---|---|
| `api/buspass_verify.php` | REST endpoint — Student ID scan + Bus Pass verification logic |
| `api/buspass_verify_ui.js` | Frontend JS — AI verify modal, selfie camera, result rendering with match scores |
| `config/config.php` | App configuration — DB connection, Gemini API key, email config |

---

## API Endpoint

```
POST /index.php?api=1&action=ai_verify
```

**Request (multipart/form-data):**
| Field | Type | Description |
|---|---|---|
| `verificationType` | string | `Student ID` / `Bus Pass` |
| `document_number` | string | Student ID or Pass number (for lookup-only mode) |
| `document` | file | Image of the ID/pass card |
| `selfie` | file | (Optional) Selfie for biometric face matching |

**Two Modes:**
1. **Lookup-only** — Enter student ID → system cross-checks database directly (no AI needed)
2. **AI Scan mode** — Upload card image → Gemini Vision extracts details → cross-references DB

---

## How It Works

### Student ID Verification
1. Upload student ID card image
2. Gemini Vision API extracts: name, student ID, branch, year, photo
3. Extracted data cross-referenced against `students` table in MySQL
4. Fuzzy name matching (Levenshtein) handles OCR imperfections
5. Match score calculated and returned with verification result

### Bus Pass Verification
1. Upload bus pass image
2. Gemini extracts: pass number, student ID, route, validity dates
3. Cross-references `bus_passes` table — checks active status + expiry
4. If selfie uploaded → **biometric face match** against student photo in DB

### Biometric Face Matching
- Uses `geminiAnalyzeMulti()` — sends both selfie and ID photo to Gemini
- Gemini performs visual facial similarity analysis
- Returns match confidence score (0-100%)
- Threshold: 60%+ = match accepted

---

## Verification Flow Diagram

```
User uploads card image
        ↓
Gemini Vision API → Extract fields
        ↓
Cross-reference MySQL (students / bus_passes table)
        ↓
Fuzzy match name + validate dates/status
        ↓  
[Optional] Selfie → Gemini face comparison
        ↓
Store result in verification_results table
        ↓
Return JSON with score, validity, extracted data
```

---

## Tech Used
- Google Gemini 2.5 Flash Vision API
- PHP cURL + multipart form handling  
- PDO prepared statements (SQL injection safe)
- Levenshtein fuzzy matching
- Base64 image encoding for API transmission
