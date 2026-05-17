// ============================================
// DocuGuard - Official Document Verify Frontend
// Author: Sujal Patel
// Module: Aadhaar/PAN/Domicile UI, AI result rendering
// ============================================

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

