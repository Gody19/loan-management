<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Register - FinancePro</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
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
                        "display-lg": ["Inter"],
                        "body-lg": ["Inter"],
                        "headline-sm": ["Inter"],
                        "label-lg": ["Inter"],
                        "headline-md": ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .step-transition {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
    </style>
</head>

<body class="bg-background text-on-background min-h-screen flex items-center justify-center p-md md:p-2xl">
    <!-- Top Navigation Anchor (As per Shell Visibility suppression: suppressed for registration) -->
    <main class="w-full max-w-container-max flex flex-col md:flex-row items-stretch gap-xl min-h-[800px]">
        <!-- Left Side: Visual Anchor & Branding -->
        <section
            class="hidden lg:flex flex-col justify-between w-1/3 p-2xl bg-primary rounded-xl relative overflow-hidden text-on-primary">
            <div class="relative z-10">
                <h1 class="font-headline-md text-headline-md font-bold mb-md">FinancePro</h1>
                <p class="font-body-lg text-body-lg opacity-90 max-w-xs">Elevate your financial journey with
                    institutional-grade tools and seamless loan management.</p>
            </div>
            <!-- Contextual Branding Image -->
            <div class="relative z-10 mt-xl">
                <div class="glass-panel p-lg rounded-xl border border-white/20 bg-white/10">
                    <div class="flex items-center gap-sm mb-md">
                        <span class="material-symbols-outlined text-tertiary-fixed">verified_user</span>
                        <span class="font-label-lg text-label-lg">Secure Registration</span>
                    </div>
                    <p class="font-body-sm text-body-sm opacity-80 italic">"The fastest onboarding experience I've had
                        with any financial institution."</p>
                    <div class="flex items-center gap-sm mt-md">
                        <div class="w-8 h-8 rounded-full bg-surface-container-high"></div>
                        <span class="font-label-md text-label-md">Michael Chen, SME Owner</span>
                    </div>
                </div>
            </div>
            <!-- Background Aesthetic -->
            <div
                class="absolute top-0 right-0 -mr-24 -mt-24 w-96 h-96 bg-primary-container rounded-full blur-3xl opacity-20">
            </div>
            <div
                class="absolute bottom-0 left-0 -ml-24 -mb-24 w-96 h-96 bg-tertiary-container rounded-full blur-3xl opacity-20">
            </div>
        </section>
        <!-- Right Side: Registration Wizard -->
        <section class="flex-1 glass-panel rounded-xl shadow-lg p-lg md:p-2xl flex flex-col">
            <!-- Progress Indicator -->
            <div class="mb-2xl">
                <div class="flex items-center justify-between mb-sm px-xs">
                    <div class="font-label-lg text-label-lg text-primary font-bold" id="step-label-1">Personal Info
                    </div>
                    <div class="font-label-lg text-label-lg text-outline" id="step-label-2">Security</div>
                    <div class="font-label-lg text-label-lg text-outline" id="step-label-3">Verification</div>
                </div>
                <div class="h-2 w-full bg-surface-container-high rounded-full overflow-hidden flex">
                    <div class="h-full bg-primary transition-all duration-500 ease-out" id="progress-bar"
                        style="width: 33.33%"></div>
                </div>
            </div>
            <form class="flex-1 flex flex-col" id="registration-form">
                <!-- Step 1: Personal Info -->
                <div class="step-transition" id="step-content-1">
                    <div class="mb-xl">
                        <h2 class="font-headline-md text-headline-md text-on-surface mb-xs">Welcome to FinancePro</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant">Tell us a bit about yourself to get
                            started.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Full Name</label>
                            <input
                                class="h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md transition-all"
                                placeholder="Johnathan Doe" type="text" />
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Username</label>
                            <input
                                class="h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md transition-all"
                                placeholder="johndoe_finance" type="text" />
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Email Address</label>
                            <input
                                class="h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md transition-all"
                                placeholder="john@example.com" type="email" />
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Phone Number</label>
                            <input
                                class="h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md transition-all"
                                placeholder="+1 (555) 000-0000" type="tel" />
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">NIDA Number</label>
                            <input
                                class="h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md transition-all"
                                placeholder="19900101-XXXXX-XXXXX" type="text" />
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Date of Birth</label>
                            <input
                                class="h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md transition-all"
                                type="date" />
                        </div>
                        <div class="flex flex-col gap-xs md:col-span-2">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Gender</label>
                            <div class="flex gap-md">
                                <label
                                    class="flex-1 flex items-center gap-sm p-md border border-outline-variant rounded-lg cursor-pointer hover:bg-surface-container-low transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary-container/10">
                                    <input class="text-primary focus:ring-primary" name="gender" type="radio"
                                        value="male" />
                                    <span class="font-body-md text-body-md">Male</span>
                                </label>
                                <label
                                    class="flex-1 flex items-center gap-sm p-md border border-outline-variant rounded-lg cursor-pointer hover:bg-surface-container-low transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary-container/10">
                                    <input class="text-primary focus:ring-primary" name="gender" type="radio"
                                        value="female" />
                                    <span class="font-body-md text-body-md">Female</span>
                                </label>
                                <label
                                    class="flex-1 flex items-center gap-sm p-md border border-outline-variant rounded-lg cursor-pointer hover:bg-surface-container-low transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary-container/10">
                                    <input class="text-primary focus:ring-primary" name="gender" type="radio"
                                        value="other" />
                                    <span class="font-body-md text-body-md">Other</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Step 2: Security (Hidden by Default) -->
                <div class="step-transition hidden" id="step-content-2">
                    <div class="mb-xl">
                        <h2 class="font-headline-md text-headline-md text-on-surface mb-xs">Secure Your Account</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant">Choose a strong password to protect
                            your assets.</p>
                    </div>
                    <div class="flex flex-col gap-xl">
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Password</label>
                            <div class="relative">
                                <input
                                    class="w-full h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md pr-12 transition-all"
                                    placeholder="••••••••" type="password" />
                                <button
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant hover:text-on-surface-variant"
                                    type="button">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                            <div class="mt-sm flex gap-xs">
                                <div class="h-1 flex-1 bg-error rounded-full"></div>
                                <div class="h-1 flex-1 bg-surface-container-high rounded-full"></div>
                                <div class="h-1 flex-1 bg-surface-container-high rounded-full"></div>
                                <div class="h-1 flex-1 bg-surface-container-high rounded-full"></div>
                            </div>
                            <p class="font-label-md text-label-md text-on-surface-variant mt-xs">Password must be at
                                least 8 characters long.</p>
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">Confirm Password</label>
                            <input
                                class="h-12 border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-lg font-body-md text-body-md px-md transition-all"
                                placeholder="••••••••" type="password" />
                        </div>
                        <div class="bg-surface-container p-lg rounded-xl border border-outline-variant/50">
                            <h4 class="font-label-lg text-label-lg text-on-surface mb-sm flex items-center gap-xs">
                                <span class="material-symbols-outlined text-primary scale-90">info</span>
                                Security Best Practices
                            </h4>
                            <ul class="font-body-sm text-body-sm text-on-surface-variant space-y-xs list-disc pl-lg">
                                <li>Use a combination of letters, numbers, and symbols.</li>
                                <li>Avoid using common words or personal birthdays.</li>
                                <li>Enable 2FA in settings after registration.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Step 3: Verification (Hidden by Default) -->
                <div class="step-transition hidden" id="step-content-3">
                    <div class="mb-xl">
                        <h2 class="font-headline-md text-headline-md text-on-surface mb-xs">Verify Identity</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant">We need to verify your National ID
                            to comply with financial regulations.</p>
                    </div>
                    <div class="flex flex-col gap-xl">
                        <div class="flex flex-col gap-xs">
                            <label class="font-label-lg text-label-lg text-on-surface-variant">National ID
                                Document</label>
                            <div
                                class="border-2 border-dashed border-outline-variant hover:border-primary hover:bg-primary-container/5 rounded-xl p-3xl flex flex-col items-center justify-center cursor-pointer transition-all">
                                <span class="material-symbols-outlined text-primary text-4xl mb-md">cloud_upload</span>
                                <p class="font-body-md text-body-md text-on-surface font-semibold">Click to upload or
                                    drag and drop</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">PNG, JPG or PDF (max.
                                    10MB)</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-md">
                            <label class="flex items-start gap-md group cursor-pointer">
                                <div class="mt-xs">
                                    <input
                                        class="w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer"
                                        type="checkbox" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-body-md text-body-md text-on-surface">I agree to the <a
                                            class="text-primary font-bold hover:underline" href="#">Terms &amp;
                                            Conditions</a> and <a class="text-primary font-bold hover:underline"
                                            href="#">Privacy Policy</a>.</span>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant">I understand that
                                        FinancePro will verify my data with national registries.</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-md group cursor-pointer">
                                <div class="mt-xs">
                                    <input
                                        class="w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer"
                                        type="checkbox" />
                                </div>
                                <span class="font-body-md text-body-md text-on-surface">I would like to receive market
                                    insights and product updates.</span>
                            </label>
                        </div>
                        <!-- Success Simulation State (Invisible) -->
                        <div class="hidden text-center py-xl" id="success-message">
                            <div
                                class="w-20 h-20 bg-tertiary-container text-on-tertiary-container rounded-full flex items-center justify-center mx-auto mb-lg">
                                <span class="material-symbols-outlined text-4xl"
                                    style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            </div>
                            <h3 class="font-headline-md text-headline-md text-on-surface">Account Created!</h3>
                            <p class="font-body-md text-body-md text-on-surface-variant mt-sm">Your information is being
                                reviewed. We'll notify you shortly.</p>
                        </div>
                    </div>
                </div>
                <!-- Form Navigation Actions -->
                <div class="mt-auto pt-2xl flex items-center justify-between">
                    <button
                        class="hidden flex items-center gap-xs font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors active:scale-95"
                        id="prev-btn" type="button">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back
                    </button>
                    <div class="flex-1"></div>
                    <button
                        class="h-14 px-3xl bg-primary text-on-primary rounded-lg font-label-lg text-label-lg font-bold shadow-md hover:bg-primary-container active:scale-95 transition-all"
                        id="next-btn" type="button">
                        Next Step
                    </button>
                </div>
            </form>
        </section>
    </main>
    <!-- Footer Suppression Check: Suppressed for Transactional/Focused Wizard -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let currentStep = 1;
            const totalSteps = 3;

            const nextBtn = document.getElementById('next-btn');
            const prevBtn = document.getElementById('prev-btn');
            const progressBar = document.getElementById('progress-bar');

            const updateUI = () => {
                // Handle Step Contents
                for (let i = 1; i <= totalSteps; i++) {
                    const content = document.getElementById(`step-content-${i}`);
                    const label = document.getElementById(`step-label-${i}`);

                    if (i === currentStep) {
                        content.classList.remove('hidden');
                        label.classList.add('text-primary', 'font-bold');
                        label.classList.remove('text-outline');
                    } else {
                        content.classList.add('hidden');
                        label.classList.remove('text-primary', 'font-bold');
                        label.classList.add('text-outline');
                    }
                }

                // Handle Buttons
                if (currentStep === 1) {
                    prevBtn.classList.add('hidden');
                } else {
                    prevBtn.classList.remove('hidden');
                }

                if (currentStep === totalSteps) {
                    nextBtn.innerText = 'Complete Application';
                } else {
                    nextBtn.innerText = 'Next Step';
                }

                // Handle Progress Bar
                const percentage = (currentStep / totalSteps) * 100;
                progressBar.style.width = `${percentage}%`;
            };

            nextBtn.addEventListener('click', () => {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateUI();
                } else {
                    // Final Submission Logic
                    const form = document.getElementById('registration-form');
                    const contents = [1, 2, 3].map(i => document.getElementById(`step-content-${i}`));
                    contents.forEach(c => c.classList.add('hidden'));

                    document.getElementById('success-message').classList.remove('hidden');
                    nextBtn.innerText = 'Go to Dashboard';
                    nextBtn.onclick = () => window.location.reload(); // Redirect simulation
                    prevBtn.classList.add('hidden');
                }
            });

            prevBtn.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    updateUI();
                }
            });

            // Password Visibility Toggle Logic
            const eyeBtn = document.querySelector('button .material-symbols-outlined:contains("visibility")')?.parentElement;
            if (eyeBtn) {
                eyeBtn.addEventListener('click', (e) => {
                    const input = eyeBtn.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    eyeBtn.querySelector('span').innerText = type === 'password' ? 'visibility' : 'visibility_off';
                });
            }
        });
    </script>
</body>

</html>