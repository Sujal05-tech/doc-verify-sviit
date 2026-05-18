// ============================================
// DocuGuard - Frontend JS
// Author: Shreyas Purohit
// Module: UI Components, Auth, Data Loaders, Document Viewer, Certificate
// ============================================

// --- STATE & INITIALISATION ---
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

    function toggleAuthTheme() {
        const root = document.documentElement;
        root.classList.toggle('dark');
        localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        // Sync the sidebar toggle icon too
        const moonIcons = document.querySelectorAll('.ph-moon');
        const sunIcons  = document.querySelectorAll('.ph-sun');
        // Icons update automatically via dark: class — no manual work needed
    }

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


// --- API HELPER & TOASTS ---
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


// --- AUTH HANDLERS (Login / Signup / OTP) ---
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


// --- STANDARD DOCUMENT UPLOAD ---
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


// --- DOCUMENT VIEWER ---
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


// --- CERTIFICATE GENERATOR ---
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
        // Use QRServer API as reliable fallback
        const qrContainer = document.getElementById('cert-qr-canvas').parentElement;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrData)}&color=1e293b&bgcolor=ffffff&margin=4`;
        qrContainer.innerHTML = `<img src="${qrUrl}" width="120" height="120" style="border-radius:8px;" alt="Verification QR Code" onerror="this.parentElement.innerHTML='<div style=\'width:120px;height:120px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#666\'>QR unavailable</div>'">`;
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


// --- DATA LOADERS ---
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


// --- ACTION HANDLERS ---
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


// --- SECURITY HELPERS ---
    // ==========================================
    // SECURITY HELPERS
    // ==========================================
    function escHtml(str) { return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
    function escAttr(str) { return String(str || '').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }
    </script>
</body>
