<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Portal Login &bull; Bank Complaint Manager ERP</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-grid-pattern {
            background-size: 40px 40px;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">

    <div class="max-w-4xl w-full bg-slate-900 rounded-3xl shadow-2xl border border-slate-800 overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-[580px]">

        <!-- Left Visual & Value Proposition Column (5 cols on desktop) -->
        <div class="lg:col-span-5 bg-gradient-to-br from-slate-900 via-slate-900 to-sky-950 p-8 sm:p-10 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-slate-800 relative bg-grid-pattern overflow-hidden">
            
            <!-- Subtle Radial Glow -->
            <div class="absolute -top-24 -left-24 w-80 h-80 bg-sky-500/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -right-24 w-80 h-80 bg-indigo-500/15 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10">
                <!-- Brand Badge -->
                <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-400 text-xs font-semibold mb-6">
                    <span class="w-2 h-2 rounded-full bg-sky-400 animate-ping"></span>
                    <span>Bank Infrastructure Operations</span>
                </div>

                <!-- Title & Logo -->
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-sky-500/20">
                        <i class="fa-solid fa-building-columns text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-extrabold tracking-tight text-white leading-tight">Bank Complaint Manager</h1>
                        <span class="text-xs text-slate-400 font-medium tracking-wide">Enterprise Field Operations ERP</span>
                    </div>
                </div>

                <p class="text-xs text-slate-300 leading-relaxed mt-4">
                    Unified banking operations ERP featuring automated complaint intake, intelligent engineer routing, real-time SLA lifecycle tracking, spare parts logistics, and audited travel reimbursements.
                </p>

                <!-- Value Props Feature List -->
                <div class="mt-8 space-y-3.5 text-xs">
                    <div class="flex items-start space-x-3 text-slate-300">
                        <div class="w-6 h-6 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 shrink-0 mt-0.5">
                            <i class="fa-solid fa-bolt text-[11px]"></i>
                        </div>
                        <div>
                            <span class="font-semibold text-white">Automated Complaint Ingestion</span>
                            <div class="text-[11px] text-slate-400">Classifies incoming bank dispatches with SLA precision</div>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3 text-slate-300">
                        <div class="w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0 mt-0.5">
                            <i class="fa-solid fa-location-dot text-[11px]"></i>
                        </div>
                        <div>
                            <span class="font-semibold text-white">Intelligent Engineer Dispatch</span>
                            <div class="text-[11px] text-slate-400">Calculates precise GPS route distances and travel times</div>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3 text-slate-300">
                        <div class="w-6 h-6 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 shrink-0 mt-0.5">
                            <i class="fa-solid fa-clock-rotate-left text-[11px]"></i>
                        </div>
                        <div>
                            <span class="font-semibold text-white">SLA Timelines & Daily Progress</span>
                            <div class="text-[11px] text-slate-400">Multi-day feedback audit with approval pause clocks</div>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3 text-slate-300">
                        <div class="w-6 h-6 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0 mt-0.5">
                            <i class="fa-solid fa-boxes-stacked text-[11px]"></i>
                        </div>
                        <div>
                            <span class="font-semibold text-white">Inventory &amp; Expense Control</span>
                            <div class="text-[11px] text-slate-400">Multi-warehouse spare parts ledger and audited claims</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Badge -->
            <div class="mt-8 pt-6 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
                <span>Production Release</span>
                <span class="flex items-center space-x-1.5 text-emerald-400 font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>System Operational</span>
                </span>
            </div>
        </div>

        <!-- Right Authentication Column (7 cols on desktop) -->
        <div class="lg:col-span-7 bg-white text-slate-800 p-8 sm:p-12 flex flex-col justify-between">

            <div>
                <!-- Form Header -->
                <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Portal Sign In</h2>
                        <p class="text-xs text-slate-500 mt-1">Enter your enterprise username or email to access your workspace</p>
                    </div>
                    <div class="flex items-center space-x-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-medium">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                        <span>SSL 256-Bit</span>
                    </div>
                </div>

                <!-- Validation Alerts -->
                @if($errors->any())
                    <div class="mb-6 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-xl text-xs text-rose-800 space-y-1 shadow-xs">
                        <div class="font-bold flex items-center space-x-2">
                            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
                            <span>Authentication Failed</span>
                        </div>
                        @foreach($errors->all() as $err)
                            <div class="pl-5">{{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-xl text-xs text-rose-800 shadow-xs">
                        <div class="font-bold flex items-center space-x-2">
                            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                <!-- Standard Form -->
                <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label for="login-email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Work Email or Username</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 text-sm">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <input type="text" id="login-email" name="email" value="{{ old('email') }}" required autofocus
                                class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition"
                                placeholder="e.g. username or employee@banksupport.com">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="login-password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Access Password</label>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 text-sm">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" id="login-password" name="password" required 
                                class="w-full pl-10 pr-10 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition"
                                placeholder="Enter your access password">
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i id="password-toggle-icon" class="fa-solid fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1">
                        <label class="flex items-center space-x-2 text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded text-sky-600 border-slate-300 focus:ring-sky-500" checked>
                            <span class="font-medium">Keep me signed in</span>
                        </label>
                        <span class="text-slate-400 text-[11px] flex items-center space-x-1">
                            <i class="fa-solid fa-shield text-[10px] text-sky-600"></i>
                            <span>Role-Based Access</span>
                        </span>
                    </div>

                    <button type="submit" class="w-full bg-slate-900 hover:bg-sky-700 text-white font-bold py-3.5 rounded-xl text-sm shadow-md hover:shadow-lg transition flex items-center justify-center space-x-2.5 cursor-pointer">
                        <span>Sign In to Operations Console</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </form>

                <!-- Help Notice -->
                <div class="mt-8 p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start space-x-3 text-xs text-slate-600">
                    <i class="fa-solid fa-circle-info text-sky-600 mt-0.5 shrink-0"></i>
                    <div class="text-[11px] leading-relaxed">
                        <span class="font-semibold text-slate-700">Need help accessing your account?</span> Contact your Regional Operations Manager or Help Desk Administrator to issue or reset credentials.
                    </div>
                </div>

            </div>

            <!-- Security Footer Note -->
            <div class="mt-8 pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between text-[11px] text-slate-400 gap-2">
                <span>&copy; {{ date('Y') }} Bank Engineering Operations CMS</span>
                <span class="flex items-center space-x-1">
                    <i class="fa-solid fa-lock text-[10px]"></i>
                    <span>Authorized Personnel Only</span>
                </span>
            </div>

        </div>

    </div>

    <script>
        function togglePasswordVisibility() {
            const pwd = document.getElementById('login-password');
            const icon = document.getElementById('password-toggle-icon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
