<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "primary-fixed-dim": "#b4c5ff",
                        "surface": "#f8f9ff",
                        "surface-bright": "#f8f9ff",
                        "tertiary-fixed-dim": "#4edea3",
                        "on-secondary-fixed-variant": "#3c475a",
                        "inverse-on-surface": "#eaf1ff",
                        "background": "#f8f9ff",
                        "surface-dim": "#cbdbf5",
                        "on-secondary": "#ffffff",
                        "error-container": "#ffdad6",
                        "surface-container-high": "#dce9ff",
                        "surface-tint": "#0053db",
                        "on-error-container": "#93000a",
                        "on-tertiary-fixed": "#002113",
                        "primary": "#004ac6",
                        "on-tertiary-fixed-variant": "#005236",
                        "on-error": "#ffffff",
                        "on-surface-variant": "#434655",
                        "outline": "#737686",
                        "on-secondary-fixed": "#111c2d",
                        "inverse-surface": "#213145",
                        "tertiary-container": "#007d55",
                        "on-secondary-container": "#586377",
                        "secondary": "#545f73",
                        "on-tertiary": "#ffffff",
                        "outline-variant": "#c3c6d7",
                        "surface-container-low": "#eff4ff",
                        "surface-container": "#e5eeff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#006242",
                        "on-surface": "#0b1c30",
                        "tertiary-fixed": "#6ffbbe",
                        "secondary-container": "#d5e0f8",
                        "on-primary-container": "#eeefff",
                        "on-primary-fixed": "#00174b",
                        "secondary-fixed-dim": "#bcc7de",
                        "inverse-primary": "#b4c5ff",
                        "on-primary": "#ffffff",
                        "error": "#ba1a1a",
                        "on-primary-fixed-variant": "#003ea8",
                        "secondary-fixed": "#d8e3fb",
                        "surface-container-highest": "#d3e4fe",
                        "on-tertiary-container": "#bdffdb",
                        "primary-fixed": "#dbe1ff",
                        "surface-variant": "#d3e4fe",
                        "primary-container": "#2563eb",
                        "on-background": "#0b1c30"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "sm": "8px",
                        "xs": "4px",
                        "base": "4px",
                        "3xl": "64px",
                        "margin-mobile": "16px",
                        "gutter": "24px",
                        "md": "16px",
                        "xl": "32px",
                        "lg": "24px",
                        "container-max": "1280px",
                        "2xl": "48px"
                    },
                    "fontFamily": {
                        "body-md": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "body-sm": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "display-lg": ["Inter"],
                        "body-lg": ["Inter"],
                        "headline-sm": ["Inter"],
                        "display-md": ["Inter"],
                        "label-lg": ["Inter"],
                        "headline-md": ["Inter"],
                        "code": ["Inter"]
                    },
                    "fontSize": {
                        "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                        "label-md": ["12px", { "lineHeight": "16px", "fontWeight": "500" }],
                        "headline-lg": ["30px", { "lineHeight": "38px", "fontWeight": "600" }],
                        "body-sm": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
                        "headline-lg-mobile": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
                        "display-lg": ["48px", { "lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "body-lg": ["18px", { "lineHeight": "28px", "fontWeight": "400" }],
                        "headline-sm": ["20px", { "lineHeight": "28px", "fontWeight": "600" }],
                        "display-md": ["36px", { "lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "label-lg": ["14px", { "lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "600" }],
                        "headline-md": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
                        "code": ["14px", { "lineHeight": "20px", "fontWeight": "400" }]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .active-interaction:active {
            transform: scale(0.98);
            transition: transform 0.1s;
        }

        .hero-pattern {
            background-color: #f8f9ff;
            background-image: radial-gradient(#d3e4fe 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>

<body class="bg-background text-on-background min-h-screen flex flex-col">
    <!-- Main Content: Split Screen Login -->
    <main class="flex-grow flex flex-col md:flex-row">
        <!-- Left Side: Professional Finance Visuals (Desktop Only) -->
        <div
            class="hidden md:flex md:w-1/2 lg:w-3/5 bg-primary relative overflow-hidden items-center justify-center p-2xl">
            <!-- Animated Background Placeholder -->

            <div class="relative z-10 max-w-lg text-white">
                <div class="mb-xl">
                    <span
                        class="inline-flex items-center justify-center p-md bg-white/10 backdrop-blur-md rounded-xl mb-lg">
                        <span class="material-symbols-outlined text-[48px]"
                            style="font-variation-settings: 'FILL' 1;">account_balance</span>
                    </span>
                    <h1 class="font-display-md text-display-md mb-md leading-tight">Master your capital with FinancePro.
                    </h1>
                    <p class="font-body-lg text-body-lg text-primary-fixed-dim">Join over 50,000 enterprises worldwide
                        using our high-fidelity tools for loan management and strategic asset allocation.</p>
                </div>
                <!-- Testimonial / Social Proof -->
                <div class="mt-3xl pt-xl border-t border-white/20">
                    <div class="flex gap-sm mb-md">
                        <span class="material-symbols-outlined text-tertiary-fixed">star</span>
                        <span class="material-symbols-outlined text-tertiary-fixed">star</span>
                        <span class="material-symbols-outlined text-tertiary-fixed">star</span>
                        <span class="material-symbols-outlined text-tertiary-fixed">star</span>
                        <span class="material-symbols-outlined text-tertiary-fixed">star</span>
                    </div>
                    <blockquote class="font-body-md text-body-md italic mb-sm">"The most intuitive enterprise finance
                        platform we've integrated in a decade. Speed and security are unmatched."</blockquote>
                    <cite class="font-label-lg text-label-lg not-italic opacity-80">— Sarah Jenkins, CFO at
                        NexaCorp</cite>
                </div>
            </div>
            <!-- Atmospheric Image Element -->
            <div class="absolute bottom-0 right-0 w-full h-full opacity-20 pointer-events-none">
                <img class="w-full h-full object-cover"
                    data-alt="A sophisticated architectural shot of a modern glass skyscraper reflecting a clear blue sky, symbolizing corporate strength and financial clarity. The perspective is looking upwards, emphasizing growth and stability in a high-key, clean professional environment that aligns with the FinancePro brand colors of deep blues and bright whites."
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuD-_mxQvzOq5tmoXL61K_ChfjWbvrTN6Z_vs41FLXeWLwTHxZ9bZoxHruqvjEgdouYnl0rKyOI9rvHiE4yGFpqJ6YJhZcKkFWYIKWRWGkIZhDqV_c9Fot941zjsZKXYMeO2yGAN5aGzOm40UFf2tmCfKXhtNbDTB1tLQVCPM6avGufV7SdQr5ZsJwUEUto7ZWW1p7zjAuVoPGVvOil5MnQSvGY41lZp3oh4iYd7Ak3152vXDqTH99jT0bmgHHKIONxqE8bKwAfzezg" />
            </div>
        </div>
        <!-- Right Side: Login Form -->
        <div
            class="w-full md:w-1/2 lg:w-2/5 flex flex-col justify-center items-center p-margin-mobile md:p-2xl hero-pattern">
            <div class="w-full max-w-[440px] glass-panel p-xl rounded-xl shadow-lg border border-outline-variant">
                <!-- Brand Header -->
                <div class="text-center mb-xl">
                    <div class="inline-flex items-center gap-sm mb-md">
                        <span class="material-symbols-outlined text-primary text-headline-md"
                            style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
                        <span class="font-headline-md text-headline-md font-bold text-primary">FinancePro</span>
                    </div>
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Welcome Back</h2>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">Access your enterprise dashboard
                    </p>
                </div>
                <!-- Login Form -->
                <form class="space-y-lg" onsubmit="return false;">
                    <!-- Email Field -->
                    <div class="space-y-xs">
                        <label class="font-label-lg text-label-lg text-on-surface-variant ml-xs" for="email">Email
                            Address</label>
                        <div class="relative">
                            <span
                                class="material-symbols-outlined absolute left-md top-1/2 -translate-y-1/2 text-outline">mail</span>
                            <input
                                class="w-full h-[48px] pl-2xl pr-md bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all text-body-md"
                                id="email" placeholder="name@company.com" type="email" />
                        </div>
                    </div>
                    <!-- Password Field -->
                    <div class="space-y-xs">
                        <div class="flex justify-between items-center px-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant"
                                for="password">Password</label>
                            <a class="font-label-md text-label-md text-primary hover:underline transition-all"
                                href="#">Forgot Password?</a>
                        </div>
                        <div class="relative">
                            <span
                                class="material-symbols-outlined absolute left-md top-1/2 -translate-y-1/2 text-outline">lock</span>
                            <input
                                class="w-full h-[48px] pl-2xl pr-2xl bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all text-body-md"
                                id="password" placeholder="••••••••" type="password" />
                            <button
                                class="absolute right-md top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors"
                                onclick="togglePassword()" type="button">
                                <span class="material-symbols-outlined" id="toggleIcon">visibility</span>
                            </button>
                        </div>
                    </div>
                    <!-- Remember Me -->
                    <div class="flex items-center gap-sm px-xs">
                        <input class="w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary"
                            id="remember" type="checkbox" />
                        <label class="font-body-sm text-body-sm text-on-surface-variant cursor-pointer select-none"
                            for="remember">Stay logged in for 30 days</label>
                    </div>
                    <!-- Actions -->
                    <div class="pt-md space-y-md">
                        <button
                            class="w-full h-[48px] bg-primary text-white font-label-lg text-label-lg rounded-lg shadow-sm hover:bg-[#003ea8] active-interaction transition-all flex items-center justify-center gap-sm"
                            type="submit">
                            Login to Dashboard
                            <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                        </button>
                        <div class="relative flex py-sm items-center">
                            <div class="flex-grow border-t border-outline-variant"></div>
                            <span class="flex-shrink mx-md font-label-md text-label-md text-outline">OR</span>
                            <div class="flex-grow border-t border-outline-variant"></div>
                        </div>
                        <button
                            class="w-full h-[48px] bg-surface-container-low text-on-surface border border-outline-variant font-label-lg text-label-lg rounded-lg hover:bg-surface-container-high active-interaction transition-all flex items-center justify-center gap-sm"
                            type="button">
                            <img alt="Google Logo" class="w-5 h-5"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuAm1rKyTfRiI_juc0lTKzP-p1a7cC2S8qofvFzudZQyswE_KR7xYG4hTcLrVJiatAiaBhsmOWX1H5bdBNG3lglKTPn5J7iWW5cZH55pKXu-K9Ca3gg1fyUsze7q3PFQKi7fVxtjvJIJ6_-eebC0rOODDdwcfEH4hS1oQ4G83smYWBtPLe7cawqB8lxo6Um6bQ4ptiKxflj_AS7T5doBIVEG4XOGxWPWseKqzBqakktBvyEC1GZR4j0LRQILspOGFsnLI68fzXpg7p8" />
                            Sign in with SSO
                        </button>
                    </div>
                </form>
                <!-- Footer Link -->
                <div class="mt-xl text-center">
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                        Don't have an enterprise account?
                        <a class="text-primary font-bold hover:underline ml-xs" href="{{ route('register') }}">Create Account</a>
                    </p>
                </div>
            </div>
            <!-- Mobile Footer / Legal -->
            <div class="mt-xl text-center px-md">
                <p class="font-label-md text-label-md text-outline">
                    © 2024 FinancePro Solutions. Protected by industry-standard 256-bit AES encryption.
                </p>
                <div class="flex justify-center gap-lg mt-sm font-label-md text-label-md text-on-surface-variant">
                    <a class="hover:text-primary transition-colors" href="#">Privacy Policy</a>
                    <a class="hover:text-primary transition-colors" href="#">Security</a>
                    <a class="hover:text-primary transition-colors" href="#">Support</a>
                </div>
            </div>
        </div>
    </main>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = 'visibility';
            }
        }

        // Simple button interaction feedback
        document.querySelectorAll('button').forEach(button => {
            button.addEventListener('mousedown', () => {
                button.classList.add('scale-95');
            });
            button.addEventListener('mouseup', () => {
                button.classList.remove('scale-95');
            });
            button.addEventListener('mouseleave', () => {
                button.classList.remove('scale-95');
            });
        });
    </script>
</body>

</html>