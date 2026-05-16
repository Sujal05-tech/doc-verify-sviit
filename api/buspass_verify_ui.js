// ============================================
// DocuGuard - Bus Pass / Student ID Verify Frontend
// Author: Sujal Patidar
// Module: AI Verify modal, camera selfie, face match UI, result rendering
// ============================================

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

