# DocuGuard — Frontend Module
**Branch:** `feature/frontend`  
**Developer:** Shreyas Purohit  
**Module:** UI/UX, Styling, Frontend Logic

---

## Files Modified / Added

| File | Description |
|---|---|
| `assets/css/style.css` | Custom styles — glassmorphism panels, animations, dark mode, scan laser effect, score bars |
| `assets/js/app.js` | Frontend SPA logic — state management, auth handlers, document viewer, certificate generator, data loaders |
| `templates/partials/body.php` | Full HTML layout — sidebar, all modals (upload, AI verify, official verify, doc viewer, certificate), all view containers |

---

## Key Features Implemented

- **Glassmorphism UI** — backdrop blur panels, gradient borders
- **Dark / Light Mode** — full theme toggle with localStorage persistence
- **Responsive Sidebar** — mobile hamburger menu, collapsible navigation
- **Toast Notification System** — animated slide-in alerts (success/error/info)
- **Document Upload Modal** — drag-drop, AI progress steps animation
- **AI Verify Modal** — camera selfie capture, file upload, result rendering with score bars
- **Official Doc Verify Modal** — Aadhaar/PAN/Domicile type selector
- **Secure Document Viewer** — iframe-based with toolbar
- **Verification Certificate** — printable with QR code generation
- **Auth Flow UI** — Login/Register/Forgot Password with OTP input

---

## Tech Used
- Tailwind CSS (CDN)
- Phosphor Icons
- Chart.js (analytics dashboard)
- QRCode.js (certificate QR)
- Canvas Confetti (success animation)
- Vanilla JavaScript (no framework)
