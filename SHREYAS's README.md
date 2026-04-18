# Hey guys! SHREYAS here, This file shows how to run this project, along with each and every one of it's features and functionalities.
> The index.php is the main file, and docuguard.sql is the database file.

# 🛡️ DocuGuard Automata
### AI-Powered Document Verification Portal

> A full-stack PHP + MySQL web application that uses **Google Gemini AI** to verify student ID cards, bus passes, and official government documents (Aadhaar, PAN, Domicile) with real-time forgery detection, biometric face matching, and university database cross-referencing.

---

## 📋 Table of Contents

- [Tech Stack](#-tech-stack)
- [System Requirements](#-system-requirements)
- [Installation & Setup](#-installation--setup)
- [Configuration](#-configuration)
- [Running the Project](#-running-the-project)
- [Features & Functionalities](#-features--functionalities)
  - [Authentication System](#1-authentication-system)
  - [AI Document Verification](#2-ai-document-verification-student-id--bus-pass)
  - [Official Document Verification](#3-official-document-verification-aadhaar--pan--domicile)
  - [Biometric Face Matching](#4-biometric-face-matching)
  - [Forgery Detection](#5-forgery-detection)
  - [Standard Document Upload](#6-standard-document-upload)
  - [Verification History](#7-verification-history)
  - [Admin Analytics Dashboard](#8-admin-analytics-dashboard)
  - [Verification Desk](#9-verification-desk)
  - [User Management](#10-user-management)
  - [Support Inbox & Messaging](#11-support-inbox--messaging)
  - [Audit Trail](#12-audit-trail)
  - [Verification Certificate](#13-verification-certificate)
  - [Document Viewer](#14-secure-document-viewer)
  - [OTP Email System](#15-otp-email-system)
  - [Dark Mode](#16-dark--light-mode)
- [User Roles](#-user-roles)
- [Database Schema](#-database-schema)
- [API Endpoints](#-api-endpoints)
- [Project Structure](#-project-structure)
- [Deployment Guide](#-deployment-guide)
- [Future Features / Roadmap](#-future-features--roadmap)
- [Known Limitations](#-known-limitations)
- [Sample Credentials](#-sample-credentials)

---

## 🧰 Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.x |
| **Database** | MySQL / MariaDB (via phpMyAdmin) |
| **AI Engine** | Google Gemini 2.5 Flash API |
| **Frontend** | HTML5, Tailwind CSS (CDN), Vanilla JavaScript |
| **Icons** | Phosphor Icons |
| **Charts** | Chart.js |
| **QR Codes** | QRCode.js |
| **Animations** | Canvas Confetti |
| **Server** | Apache (via XAMPP) |
| **Fonts** | Google Fonts — Inter |

---

## 💻 System Requirements

- **XAMPP** v8.0 or higher (or any Apache + PHP 8.x + MySQL stack)
- **PHP Extensions required:**
  - `pdo_mysql` — database connectivity
  - `curl` — Gemini API calls
  - `fileinfo` — MIME type detection
  - `json` — API responses
- **Browser:** Chrome, Firefox, Edge (modern versions)
- **Internet connection** — required for Gemini AI API calls and CDN assets
- **Google Gemini API Key** — free from [aistudio.google.com](https://aistudio.google.com)

---

## 🚀 Installation & Setup

### Step 1 — Install XAMPP

Download and install XAMPP from [apachefriends.org](https://www.apachefriends.org). Start both **Apache** and **MySQL** from the XAMPP Control Panel.

### Step 2 — Copy Project Files

```
C:\xampp\htdocs\DocuGuard\
├── index.php          ← Main application file
├── uploads\           ← Auto-created on first run
└── README.md
```

Place your `index.php` inside `C:\xampp\htdocs\DocuGuard\`.

### Step 3 — Set Up the Database

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar
3. Create a database named: `docuguard`
4. Select the `docuguard` database
5. Click the **SQL** tab
6. Import your existing data first — paste contents of `docuguard.sql` and click **Go**
7. Then paste contents of `docuguard_migration.sql` and click **Go**

This creates all required tables: `users`, `documents`, `students`, `bus_passes`, `verification_results`, `otps`, `audit_log`, `contact_messages`.

### Step 4 — Get a Gemini API Key

1. Go to [aistudio.google.com/apikey](https://aistudio.google.com/apikey)
2. Click **Create API key in new project**
3. Copy your key
4. Open `index.php` and replace on line 11:
```php
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
```

### Step 5 — (Optional) Configure Email for OTP

To enable real email OTP delivery (instead of simulated), set up a Gmail App Password:

1. Go to your Google Account → Security → 2-Step Verification → App Passwords
2. Generate a password for "Mail"
3. In `index.php`, fill in lines 14–15:
```php
define('EMAIL_USER', 'yourapp@gmail.com');
define('EMAIL_APP_PASSWORD', 'your-16-char-app-password');
```

If left empty, OTPs are shown directly in the success message (perfect for development/testing).

---

## ⚙️ Configuration

All configuration is at the top of `index.php`:

```php
// Database
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'docuguard';

// AI
define('GEMINI_API_KEY', 'your_key_here');

// Email (optional)
define('EMAIL_USER', '');
define('EMAIL_APP_PASSWORD', '');
```

---

## ▶️ Running the Project

1. Open **XAMPP Control Panel**
2. Start **Apache** and **MySQL**
3. Open your browser and navigate to:
```
http://localhost/DocuGuard/index.php
```
4. Log in with admin credentials (see [Sample Credentials](#-sample-credentials))

---

## ✨ Features & Functionalities

### 1. Authentication System

**Login**
- Email + password login with `password_verify()` (bcrypt hashed passwords)
- Session-based authentication — sessions persist until logout
- Role-based redirect on login: Admins → Analytics, Verifiers → Verification Desk, Users → My Documents
- Failed login attempts are silently rejected (no account lockout yet — see Roadmap)

**Registration with OTP Verification**
- New users register with: Full Name, Email, OTP, Password
- OTP is generated server-side (`random_int` — cryptographically secure), stored in `otps` table with 5-minute expiry
- Email-based OTP delivery via Gmail App Password (or simulated in development)
- OTP verified before account creation — prevents fake email registrations
- New accounts default to `user` role

**Forgot Password**
- User enters email → requests OTP → OTP sent/simulated → user enters OTP + new password
- Password updated with `password_hash()` — old password not required
- OTP deleted from DB after successful reset

**Logout**
- Session destroyed server-side on logout
- Page reloads to auth screen

---

### 2. AI Document Verification (Student ID / Bus Pass)

The core feature — uses Google Gemini AI to analyze uploaded document images.

**How it works:**
1. User selects verification type: **Student ID** or **Bus Pass**
2. Optionally enters the ID/pass number for a quick DB lookup (no AI needed)
3. Uploads a document image (JPG, PNG, WEBP supported)
4. Optionally captures/uploads a selfie for biometric face match
5. Clicks **Run AI Verification**
6. PHP sends the image to Gemini API as base64
7. Gemini extracts fields from the document
8. Extracted data is fuzzy-matched against the university `students` / `bus_passes` DB table
9. Final verdict is computed combining: DB match score + forgery analysis + face match

**Student ID Verification extracts:**
- Student ID number
- First name
- Last name
- Date of birth

**Bus Pass Verification extracts:**
- Pass number
- Student name
- Checks: is pass active? is pass expired?

**Fuzzy Matching:**
Uses PHP `levenshtein()` distance to compute similarity scores between AI-extracted text and DB records. A score ≥ 70% is considered a match. Individual field scores are shown in a visual score breakdown bar.

**Verdict Logic:**
```
VALID = (DB record found) AND (match score ≥ 70%) AND (not forged) AND (faces match if selfie provided)
```

**Lookup Mode (no file):**
If only an ID number is entered with no file, performs a direct DB lookup and shows all student/pass details without AI.

---

### 3. Official Document Verification (Aadhaar / PAN / Domicile)

Verifies government-issued identity documents using Gemini AI visual analysis. Does **not** cross-reference any government DB — purely AI-based forensic analysis.

**Aadhaar Card:**
- Extracts: Aadhaar number (12-digit), Name, Date of Birth, Gender
- Checks for: UIDAI logo, correct layout, valid number format, no paste/crop artifacts

**PAN Card:**
- Extracts: PAN number (10-character alphanumeric), Name, Father's Name, Date of Birth
- Checks for: Income Tax Department header, correct blue/white design, font consistency

**Domicile Certificate:**
- Extracts: Certificate number, Name, State/Address
- Checks for: Official government letterhead, seal authenticity

**Verdict:** Based solely on Gemini's visual forensic assessment. Returns **✓ Appears Genuine** or **✗ Suspected Fake / Forged** with detailed reasoning.

---

### 4. Biometric Face Matching

Available during Student ID / Bus Pass AI verification when a selfie is provided.

**Two selfie input methods:**
- **Live Camera Capture** — opens device webcam, shows live preview, user clicks Capture
- **Upload Photo** — select any image from device storage

**How it works:**
- Both the document image AND selfie are sent to Gemini in a single API call
- Gemini compares facial features between the photo on the ID and the live selfie
- Returns: `faces_match` (boolean) + `biometric_reason` (explanation)
- Result displayed as: **MATCH** ✅ or **MISMATCH** ❌ with AI's reasoning

**Face match is factored into final verdict** — a document can have a 100% DB match but still fail if faces don't match.

---

### 5. Forgery Detection

Every AI verification (both Student/Bus Pass and Official documents) includes visual forensic analysis.

**What Gemini checks:**
- Digitally pasted or swapped photos
- Inconsistent fonts or font sizes
- Wrong/missing government logos or seals
- Unnatural lighting or shadows on document
- Tampered or blurred text
- Wrong color schemes for the document type
- Stock image templates vs real documents
- Missing security features (holograms, QR codes, watermarks)

**Output:**
- `is_likely_fake` — boolean verdict
- `forgery_reason` — detailed natural language explanation of findings

Forgery detection can override an otherwise passing DB match — a document with 95% match score is still rejected if Gemini flags it as forged.

---

### 6. Standard Document Upload

Separate from AI verification — allows users to upload any document for manual review by a verifier.

**Upload process:**
1. User clicks **Upload Document**
2. Enters a document title
3. Selects file (PDF, JPG, PNG, GIF, WEBP, DOC, DOCX — up to 10MB)
4. Clicks **Submit for Verification**
5. Animated AI pipeline plays (OCR → Format Check → DB Cross-check → Encryption)
6. File saved to `uploads/` folder with timestamp prefix
7. Record inserted into `documents` table with `pending` status
8. Admin/Verifier can then approve or reject manually

**File validation:**
- MIME type checked server-side with `finfo` (not just extension)
- File size validated
- Safe filename sanitization (removes special characters)
- SHA-256 verification hash generated for certificate

---

### 7. Verification History

**For Users:**
- Shows all their AI verification attempts
- Columns: Document/Type, Result (VALID/INVALID), Match Score, Failure Reason, Date

**For Admins/Verifiers:**
- Shows all verification attempts across all users
- Additional column: Uploader name
- Accessible from the Verification Desk view

All history is stored in `verification_results` table with full AI extracted data (JSON), match score, and failure reason.

---

### 8. Admin Analytics Dashboard

Real-time statistics and charts — only visible to admin role.

**Stat cards:**
- **Total Documents** — count of all uploaded documents
- **Approval Rate** — percentage of verified vs total processed
- **Total Users** — registered user count

**Charts (Chart.js):**
- **Status Breakdown** — animated doughnut chart showing Verified / Pending / Rejected counts
- **Upload Trend** — line chart with gradient fill showing daily upload count for the last 7 days

Numbers animate from 0 to their value on page load. Charts re-render when dark/light mode is toggled to match the theme.

---

### 9. Verification Desk

Available to **Admin** and **Verifier** roles.

**Document list shows:**
- Uploader name + initial avatar
- Document title
- Preview button (opens secure viewer for images/PDFs)
- Download button
- Status badge (Pending / Verified / Rejected)

**Actions:**
- ✅ **Approve** — marks document as `verified`, logs to audit trail
- ❌ **Reject** — marks document as `rejected`, logs to audit trail
- 🏆 **View Certificate** — generates verification certificate for approved docs

**Also contains:**
- Full AI Verification History table for all users
- AI Verify and Official Verify quick-launch buttons

---

### 10. User Management

Admin-only panel.

**Displays:** Name, Email, Role, Account Status for all users

**Actions:**
- **Change Role** — dropdown to switch between User / Verifier / Admin (cannot change own role)
- **Disable Account** — soft-deletes user (`is_deleted = 1`), prevents login, grays out row
- **Restore Account** — re-enables a disabled account

All role changes and status changes are logged in the audit trail.

---

### 11. Support Inbox & Messaging

**For Users:**
- Contact form accessible from footer ("Contact") and header ("Support")
- Fields: Subject, Message
- Submitted messages go to admin inbox
- Users receive admin replies visible in **Alerts** (bell icon)

**For Admins:**
- Full inbox view of all support messages
- Unread messages highlighted
- **Reply** button opens a modal to send a response
- **Mark Read/Unread** toggle
- Reply text stored in `admin_reply` column, visible to the sending user

---

### 12. Audit Trail

Admin-only. Tamper-evident log of all system actions.

**Logged events:**
| Action | Trigger |
|---|---|
| `upload` | User uploads a document |
| `status_change` | Verifier/Admin approves or rejects |
| `role_change` | Admin changes a user's role |
| `user_status` | Admin enables/disables an account |
| `ai_verify` | AI verification run (Student ID / Bus Pass) |
| `ai_verify_official` | Official document verification run |
| `contact_admin` | User sends a support message |
| `admin_reply` | Admin replies to a support ticket |

**Each log entry records:**
- Timestamp
- Actor name (who performed the action)
- Action type (color-coded badge)
- Details (human-readable description)
- IP address

Refresh button to reload latest logs. Shows last 100 entries.

---

### 13. Verification Certificate

Generated for any document with `verified` status.

**Certificate contains:**
- Document title
- Uploaded by (name)
- Verified on (date)
- Status badge
- SHA-256 integrity hash (deterministic, based on doc ID + title + filename + timestamp)
- **QR Code** — encodes certificate ID, title, status, hash, and portal URL
- Certificate ID (format: `DGA-000001-XXXXXXX`)

**Actions:**
- 🖨️ **Print** — opens browser print dialog, hides all page elements except the certificate
- ❌ **Close**

---

### 14. Secure Document Viewer

Opens uploaded files in a full-screen overlay without navigating away.

**Supported formats:**
- **PDF** — rendered in iframe with toolbar hidden
- **Images** (JPG, PNG, GIF, WEBP) — displayed with max-height constraint

**Features:**
- Download button
- Security status bar (shows "✓ Verified · AES-256 Encrypted" for verified docs)
- SHA-256 integrity hash display (simulated — shown as visual security indicator)
- "Decrypting Secure Payload..." loading animation

**Unsupported formats** (DOC, DOCX, etc.) show a download prompt instead.

---

### 15. OTP Email System

Used for registration and forgot password flows.

**Generation:**
- 6-digit OTP via `random_int(100000, 999999)` — cryptographically secure
- Stored in `otps` table with 5-minute expiry timestamp
- `REPLACE INTO` ensures only one active OTP per email at a time

**Delivery:**
- If `EMAIL_USER` and `EMAIL_APP_PASSWORD` are configured: sends real email via PHP `mail()`
- If not configured: OTP returned directly in the success toast message (development mode)

**Verification:**
- OTP checked for exact match
- Expiry checked against current timestamp
- OTP deleted from DB immediately after successful use (one-time use)

---

### 16. Dark / Light Mode

- Toggle button in sidebar (moon/sun icon)
- Preference saved to `localStorage`
- Auto-detects system preference on first visit (`prefers-color-scheme`)
- Analytics charts re-render with appropriate colors on toggle
- All UI components (modals, tables, cards, toasts) fully themed

---

## 👥 User Roles

| Role | Access |
|---|---|
| **user** | My Documents, Upload, AI Verify, Official Verify, Contact Support, View own history |
| **verifier** | Everything a user can do + Verification Desk (approve/reject), View all history |
| **admin** | Everything + Analytics Dashboard, User Management, Support Inbox, Audit Trail |

Role is stored in the `users` table and checked server-side on every protected API call. Frontend also hides/shows navigation links based on role.

---

## 🗄️ Database Schema

### `users`
| Column | Type | Description |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(100) | Full name |
| `email` | VARCHAR(100) UNIQUE | Login email |
| `password` | VARCHAR(255) | bcrypt hash |
| `role` | ENUM(user, verifier, admin) | Access level |
| `is_deleted` | TINYINT(1) | Soft delete flag |
| `created_at` | TIMESTAMP | Registration time |

### `documents`
| Column | Type | Description |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `user_id` | INT | FK → users.id |
| `title` | VARCHAR(255) | Document label |
| `file_name` | VARCHAR(255) | Saved filename with timestamp prefix |
| `status` | ENUM(pending, verified, rejected) | Verification status |
| `uploaded_at` | TIMESTAMP | Upload time |

### `students`
| Column | Type | Description |
|---|---|---|
| `student_id` | VARCHAR(20) | Primary key (e.g. CSE2301) |
| `name` | VARCHAR(100) | Full name |
| `year` | VARCHAR(20) | Academic year |
| `branch` | VARCHAR(50) | Department |
| `email` | VARCHAR(100) | Student email |
| `phone` | VARCHAR(15) | Phone number |
| `bus_fee_paid` | TINYINT(1) | Bus fee payment status |

### `bus_passes`
| Column | Type | Description |
|---|---|---|
| `pass_id` | INT AUTO_INCREMENT | Primary key |
| `student_id` | VARCHAR(20) | FK → students.student_id |
| `pass_number` | VARCHAR(20) UNIQUE | Pass ID (e.g. BP001) |
| `route_no` | VARCHAR(10) | Bus route |
| `issue_date` | DATE | Issuance date |
| `expiry_date` | DATE | Expiry date |
| `is_active` | TINYINT(1) | Active/inactive flag |

### `verification_results`
| Column | Type | Description |
|---|---|---|
| `result_id` | INT AUTO_INCREMENT | Primary key |
| `doc_id` | INT | FK → documents.id |
| `is_valid` | TINYINT(1) | Pass/fail verdict |
| `failure_reason` | TEXT | Reason if failed |
| `ai_extracted` | TEXT | JSON of AI-extracted fields |
| `match_score` | FLOAT | Fuzzy match percentage |
| `checked_at` | DATETIME | Verification timestamp |

### `otps`
| Column | Type | Description |
|---|---|---|
| `email` | VARCHAR(100) | Primary key |
| `otp` | VARCHAR(10) | 6-digit code |
| `expires_at` | DATETIME | Expiry (5 min from creation) |

### `audit_log`
| Column | Type | Description |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `user_id` | INT | Actor |
| `action` | VARCHAR(64) | Action type |
| `target_id` | INT | Target resource ID |
| `details` | TEXT | Human-readable description |
| `ip_address` | VARCHAR(45) | Client IP |
| `created_at` | TIMESTAMP | Event time |

### `contact_messages`
| Column | Type | Description |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `user_id` | INT | Sender |
| `subject` | VARCHAR(255) | Message subject |
| `message` | TEXT | Message body |
| `admin_reply` | TEXT | Admin's reply |
| `status` | ENUM(unread, read) | Read status |
| `created_at` | TIMESTAMP | Sent time |

---

## 🔌 API Endpoints

All endpoints are accessed via `index.php?api=<endpoint>`.

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `login` | POST | Public | Authenticate user |
| `register` | POST | Public | Create new account |
| `logout` | POST | Public | Destroy session |
| `send_otp` | POST | Public | Generate & send OTP |
| `forgot_password` | POST | Public | Reset password with OTP |
| `submit_contact` | POST | User | Send support message |
| `my_alerts` | GET | User | Get admin replies |
| `upload` | POST | User | Upload document |
| `ai_verify` | POST | User | AI verify Student ID / Bus Pass |
| `ai_verify_official` | POST | User | AI verify Aadhaar / PAN / Domicile |
| `my_docs` | GET | User | Get own documents |
| `verify_history` | GET | User | Get AI verification history |
| `all_docs` | GET | Verifier+ | Get all documents |
| `update_doc` | POST | Verifier+ | Approve or reject document |
| `users` | GET | Admin | Get all users |
| `update_role` | POST | Admin | Change user role |
| `toggle_user_status` | POST | Admin | Enable/disable user |
| `admin_messages` | GET | Admin | Get support inbox |
| `reply_message` | POST | Admin | Reply to support message |
| `update_message_status` | POST | Admin | Mark message read/unread |
| `get_analytics` | GET | Admin | Get stats and chart data |
| `get_audit_log` | GET | Admin | Get audit trail |

---

## 📁 Project Structure

```
DocuGuard/
├── index.php              ← Entire application (PHP + HTML + JS in one file)
├── uploads/               ← Auto-created — stores uploaded documents
│   └── .htaccess          ← Recommended: blocks PHP execution in uploads
├── docuguard.sql          ← Base database dump (users, documents, etc.)
├── docuguard_migration.sql← New tables (students, bus_passes, verification_results, otps)
└── README.md              ← This file
```

---

## 🌐 Deployment Guide

### Local (XAMPP)
Follow the [Installation & Setup](#-installation--setup) steps above.

### InfinityFree (Free Hosting)
1. Sign up at [infinityfree.com](https://infinityfree.com)
2. Create hosting account → note the MySQL credentials
3. Upload `index.php` via File Manager to `htdocs/`
4. Create `uploads/` folder, set permissions to 755
5. Import SQL files via their phpMyAdmin
6. Update DB credentials in `index.php`

### Railway (Recommended for Production)
1. Push project to GitHub
2. Connect repo at [railway.app](https://railway.app)
3. Add MySQL plugin
4. Set environment variables:
   ```
   GEMINI_API_KEY=your_key
   DB_HOST=...
   DB_USER=...
   DB_PASS=...
   DB_NAME=railway
   ```
5. Update `index.php` to read from `getenv()`:
   ```php
   $db_host = getenv('DB_HOST') ?: 'localhost';
   define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
   ```

### Pre-Deployment Checklist
- [ ] Remove hardcoded API key — use environment variables
- [ ] Set `uploads/` folder to chmod 755
- [ ] Add `.htaccess` inside `uploads/` to block PHP execution
- [ ] Add root `.htaccess` to block `.env`, `.sql`, `.log` file access
- [ ] Test all features on XAMPP before uploading
- [ ] Revoke and regenerate your Gemini API key if it was ever committed to Git

---

## 🔮 Future Features / Roadmap

These are planned enhancements that would make DocuGuard production-ready:

### 🔐 Security
- **CSRF Token Protection** — add hidden tokens to all POST forms to prevent cross-site request forgery
- **Login Rate Limiting** — lock accounts after 5 failed attempts for 15 minutes (prevent brute force)
- **Two-Factor Authentication (2FA)** — require OTP on every login for verifiers and admins
- **Password Strength Enforcement** — minimum length, special characters, common password blocklist
- **File Encryption at Rest** — encrypt uploaded files using AES-256 before saving to disk
- **Session Timeout** — auto-logout after 30 minutes of inactivity
- **IP Whitelisting for Admin** — restrict admin panel to specific IP addresses

### 🤖 AI & Verification
- **PDF Support for AI Scanning** — convert PDF first page to image server-side using ImageMagick/GhostScript before sending to Gemini
- **Confidence Score Display** — show Gemini's internal confidence percentage alongside verdicts
- **Multi-Document Batch Verification** — verify multiple documents in one API call
- **Historical Forgery Pattern Learning** — store flagged forgery patterns, compare new submissions against known fakes
- **Liveness Detection** — require user to blink or turn head during selfie capture to prevent photo spoofing
- **Document Expiry Auto-Rejection** — auto-reject bus passes and IDs past their valid date
- **Re-verification Request** — allow users to re-submit a rejected document with a note

### 👨‍💼 Admin Tools
- **Student Registration UI** — admin form to add/edit students directly in the portal without phpMyAdmin
- **Bulk Student CSV Import** — upload a CSV file to import hundreds of students at once
- **Document Status Email Notifications** — auto-email users when their document is approved or rejected
- **Admin Dashboard Export** — export analytics data and document lists to PDF or Excel
- **Custom Verification Rules** — admin can set minimum match score threshold (currently hardcoded at 70%)
- **Scheduled Reports** — weekly email summary of verification activity sent to admin

### 📊 Analytics & Reporting
- **Per-User Verification Stats** — how many verifications each user ran, pass/fail rate
- **AI Accuracy Tracking** — track cases where admin overrides AI verdict (manual approve after AI reject) to measure AI accuracy over time
- **Geographic Heatmap** — show which IP addresses/regions are submitting documents
- **Forgery Trend Analysis** — chart showing forgery detection rate over time

### 🎨 UI / UX
- **Progressive Web App (PWA)** — make it installable on mobile devices, work offline for basic features
- **Mobile Camera Optimization** — better mobile UX for document capture (auto-crop, perspective correction)
- **Document Preview Thumbnails** — show small thumbnail of uploaded document in the table
- **Drag-and-Drop Upload** — proper drag-and-drop zone with visual feedback
- **Search & Filter** — search bar for document tables, filter by status, date range, uploader
- **Sortable Columns** — click column headers to sort tables
- **Pagination** — for large document/user lists instead of loading all at once
- **Toast Queue Management** — stack multiple toasts instead of overlapping them
- **Keyboard Shortcuts** — power user shortcuts (e.g. `Ctrl+U` to open upload modal)

### 🏗️ Architecture
- **Separate `config.php`** — move all credentials to a separate config file outside web root
- **REST API Separation** — split backend API into a separate `api.php` file for cleaner code
- **PHPMailer Integration** — replace basic `mail()` with PHPMailer for reliable SMTP email delivery
- **Redis Caching** — cache frequently accessed data (analytics, student list) to reduce DB queries
- **WebSockets for Live Updates** — real-time notification when a document is verified without page refresh
- **Multi-Institution Support** — allow multiple universities to use the same installation with separate data partitions

---

## ⚠️ Known Limitations

1. **Gemini Free Tier Quota** — Free tier allows ~20 requests/day. Each AI verification uses 1 request. For heavy use, enable billing on your Google Cloud project (you won't be charged within free limits).

2. **PDF files cannot be AI-verified** — Gemini's `inline_data` API only accepts image formats. PDFs must be converted to images first (not currently implemented). Use JPG/PNG/WEBP for AI verification.

3. **OTP Email requires Gmail App Password** — standard SMTP is not configured. If email isn't set up, OTPs are shown in the UI toast (fine for development, not for production).

4. **Single-file architecture** — the entire application lives in one `index.php` file (2300+ lines). This is intentional for easy deployment but not ideal for large-scale development.

5. **No real encryption** — the "AES-256 encrypted" messaging in the UI is cosmetic/branding. Files are stored as-is on disk. Implement real encryption before deploying sensitive documents in production.

6. **SHA-256 hash on certificate is simulated** — the hash shown is deterministic based on document metadata but is not a real hash of the file contents.

7. **Face match depends on document image quality** — low-resolution ID card photos may cause false negatives in biometric matching.

---

## 🔑 Sample Credentials

These accounts come pre-loaded in `docuguard.sql`:

| Name | Email | Password | Role |
|---|---|---|---|
| Shreyas Purohit | shreyas@gmail.com | *(bcrypt hashed — reset via Forgot Password)* | Admin |
| Samarth Parihar | samarth@gmail.com | *(bcrypt hashed — reset via Forgot Password)* | Verifier |
| Aaruj Singh | aaruj@gmail.com | *(bcrypt hashed — reset via Forgot Password)* | User |
| Kushagra Kaalbhawar | kushagra@gmail.com | *(bcrypt hashed — reset via Forgot Password)* | User |

> **Note:** Since passwords are bcrypt-hashed, use the **Forgot Password** flow (with OTP) to set new passwords, or update them directly in phpMyAdmin using:
> ```sql
> UPDATE users SET password = '$2y$10$...' WHERE email = 'shreyas@gmail.com';
> ```
> Generate a bcrypt hash at [bcrypt-generator.com](https://bcrypt-generator.com).

### Sample Student IDs for Testing AI Verification:
| Student ID | Name | Branch | Bus Pass |
|---|---|---|---|
| CSE2301 | Amit Patel | CSE 3rd Year | BP001 |
| CSE2302 | Sneha Verma | CSE 3rd Year | BP002 |
| CSE2201 | Neha Joshi | CSE 2nd Year | BP003 |
| IT2301 | Kavya Sharma | IT 3rd Year | BP004 |
| IT2302 | Harsh Patel | IT 3rd Year | BP005 |

---

## 📄 License

This project was built for educational/academic purposes. Feel free to use, modify, and extend it.

---

## 🙏 Credits

Built with:
- [Google Gemini AI](https://ai.google.dev) — document analysis engine
- [Tailwind CSS](https://tailwindcss.com) — UI styling
- [Phosphor Icons](https://phosphoricons.com) — icon set
- [Chart.js](https://chartjs.org) — analytics charts
- [QRCode.js](https://github.com/soldair/node-qrcode) — QR code generation
- [Canvas Confetti](https://github.com/catdad/canvas-confetti) — celebration animation

---

*DocuGuard Automata — Secure. Smart. Verified.*
