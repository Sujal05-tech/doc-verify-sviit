
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
                        <!-- Auth Page Header Banner -->
                        <div class="w-full mb-8 rounded-3xl overflow-hidden relative" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 40%, #ec4899 100%); min-height: 160px;">
                            <!-- Decorative blobs -->
                            <div style="position:absolute;top:-30px;left:-30px;width:160px;height:160px;background:rgba(255,255,255,0.08);border-radius:50%;"></div>
                            <div style="position:absolute;bottom:-40px;right:-20px;width:200px;height:200px;background:rgba(255,255,255,0.06);border-radius:50%;"></div>
                            <div style="position:absolute;top:20px;right:40px;width:80px;height:80px;background:rgba(255,255,255,0.05);border-radius:50%;"></div>
                            <!-- Content -->
                            <div class="relative z-10 p-8 flex flex-col items-center text-center">
                                <!-- Dark mode toggle positioned top-right of banner -->
                                <button onclick="toggleAuthTheme()" id="auth-theme-btn"
                                    class="absolute top-4 right-4 w-9 h-9 rounded-xl bg-white/15 hover:bg-white/30 border border-white/25 flex items-center justify-center transition-all backdrop-blur-sm shadow-md"
                                    title="Toggle dark/light mode">
                                    <i class="ph ph-moon text-white text-lg dark:hidden" id="auth-moon-icon"></i>
                                    <i class="ph ph-sun text-white text-lg hidden dark:block" id="auth-sun-icon"></i>
                                </button>
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg border border-white/30">
                                        <i class="ph-bold ph-shield-check text-white text-2xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h1 class="text-2xl font-black text-white tracking-tight">DocuGuard</h1>
                                        <p class="text-white/70 text-xs font-semibold uppercase tracking-widest">Automata</p>
                                    </div>
                                </div>
                                <p class="text-white/80 text-sm font-medium max-w-xs leading-relaxed">AI-Powered Document Verification Portal — Secure, Smart & Instant</p>
                                <div class="flex gap-4 mt-4 flex-wrap justify-center">
                                    <span class="flex items-center gap-1.5 text-white/70 text-xs font-semibold bg-white/10 px-3 py-1.5 rounded-full border border-white/20">
                                        <i class="ph-fill ph-robot text-sm"></i> Gemini AI
                                    </span>
                                    <span class="flex items-center gap-1.5 text-white/70 text-xs font-semibold bg-white/10 px-3 py-1.5 rounded-full border border-white/20">
                                        <i class="ph-fill ph-fingerprint text-sm"></i> Biometric
                                    </span>
                                    <span class="flex items-center gap-1.5 text-white/70 text-xs font-semibold bg-white/10 px-3 py-1.5 rounded-full border border-white/20">
                                        <i class="ph-fill ph-lock text-sm"></i> Encrypted
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Logo + Title -->
                        <div class="text-center mb-8 group cursor-default">
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
                        <button onclick="openInfoModal('project')" class="hover:text-primary transition-colors flex items-center gap-1.5"><i class="ph-fill ph-student"></i> Project</button>
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
                    <div class="flex justify-center"><div class="p-2 bg-white rounded-xl shadow-inner border border-gray-200"><div id="cert-qr-canvas" style="width:120px;height:120px;"></div></div></div>
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

