<!DOCTYPE html>

<html class="scroll-smooth" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>FinancePro | Smart Loans for Future Growth</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet" />
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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.5);
        }
    </style>
</head>

<body
    class="bg-background text-on-surface font-body-md selection:bg-primary-container selection:text-on-primary-container">
    <!-- Navigation -->
    <header
        class="sticky top-0 w-full z-50 flex items-center justify-between px-margin-mobile md:px-xl h-16 max-w-container-max mx-auto bg-surface-container-lowest border-b border-outline-variant shadow-sm">
        <div class="flex items-center gap-md">
            <span class="font-headline-md text-headline-md font-bold text-primary">FinancePro</span>
        </div>
        <nav class="hidden md:flex items-center gap-lg">
            <a class="font-body-md text-body-md text-primary border-b-2 border-primary pb-1" href="#">Product</a>
            <a class="font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
                href="#">Solutions</a>
            <a class="font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
                href="#">Pricing</a>
            <a class="font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
                href="#">Resources</a>
        </nav>
        <div class="flex items-center gap-md">
            <button class="hidden md:block font-label-lg text-label-lg text-on-surface-variant hover:text-primary transition-colors"> 
                <a href="{{ route('login') }}">
                Log In</a>
            </button>
            <button
                class="bg-primary text-on-primary font-label-lg text-label-lg px-lg py-sm rounded-lg active:scale-95 duration-150 transition-all shadow-sm">Get
                Started</button>
        </div>
    </header>
    <main>
        <!-- Hero Section -->
        <section class="relative pt-xl pb-3xl overflow-hidden">
            <div
                class="max-w-container-max mx-auto px-margin-mobile md:px-xl grid grid-cols-1 lg:grid-cols-12 gap-xl items-center">
                <div class="lg:col-span-7 space-y-lg relative z-10">
                    <div
                        class="inline-flex items-center gap-xs px-sm py-1 bg-primary-fixed-dim/20 rounded-full border border-primary-fixed-dim/30">
                        <span class="material-symbols-outlined text-primary scale-75">verified</span>
                        <span class="text-label-md font-label-md text-primary tracking-wide">TRUSTED BY 2M+ USERS</span>
                    </div>
                    <h1
                        class="font-display-md text-display-md lg:font-display-lg lg:text-display-lg text-on-surface leading-tight">
                        Smart Loans for Your <span class="text-primary">Financial Freedom.</span>
                    </h1>
                    <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">
                        Unlock your potential with flexible financing options tailored to your needs. Instant approvals,
                        transparent rates, and a process that respects your time.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-md pt-md">
                        <button
                            class="bg-primary-container text-on-primary-container font-label-lg text-label-lg px-2xl py-md rounded-xl shadow-lg active:scale-95 transition-all flex items-center justify-center gap-sm">
                            Apply Now
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </button>
                        <button
                            class="bg-surface-container-high text-on-surface font-label-lg text-label-lg px-2xl py-md rounded-xl active:scale-95 transition-all flex items-center justify-center border border-outline-variant">
                            Calculate My Loan
                        </button>
                    </div>
                    <div class="flex items-center gap-xl pt-lg border-t border-outline-variant/30 mt-xl">
                        <div class="flex flex-col">
                            <span class="font-headline-sm text-headline-sm text-on-surface">3.5%</span>
                            <span class="font-label-md text-label-md text-on-surface-variant">Annual Rate</span>
                        </div>
                        <div class="h-8 w-px bg-outline-variant"></div>
                        <div class="flex flex-col">
                            <span class="font-headline-sm text-headline-sm text-on-surface">15min</span>
                            <span class="font-label-md text-label-md text-on-surface-variant">Avg Approval</span>
                        </div>
                        <div class="h-8 w-px bg-outline-variant"></div>
                        <div class="flex flex-col">
                            <span class="font-headline-sm text-headline-sm text-on-surface">100%</span>
                            <span class="font-label-md text-label-md text-on-surface-variant">Digital Process</span>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-5 relative h-[500px]">
                    <div
                        class="absolute inset-0 bg-gradient-to-tr from-primary/10 to-transparent rounded-3xl -z-10 blur-3xl">
                    </div>
                    <img class="w-full h-full object-cover rounded-[2rem] shadow-2xl border border-white/50"
                        data-alt="A professional young entrepreneur smiling in a modern sunlit home office, reviewing financial documents on a sleek tablet device. The lighting is warm and natural, creating an atmosphere of success and reliability. The overall color palette features clean whites, soft grays, and corporate blue accents, reflecting a high-end modern finance brand identity."
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuCLZzNaoBk0aJ4jW10Kpne_XFr5QBSwmkWxWFp14RnXz8H6lVcit0xuKZmi5bITiWfEPdJOjtKEzMjDdhzuus-WX6AeNeAJENC5AdGtlPj8386Dxs_2kDin7CsjkJuXdPkdvGAeNHdJPo9jM9Hk-VXhhZu9DqvZSSIEoy1xDT1fbVeJk_Mu6YS83ceVgNj3ffJM4fQ3Ji8HwchJpwB8LG1YW4P3Yrfp9eGds9Ai8C8VpEBqNWH-agX5zaYLC3yktXuEX5CdsRYTQrg" />
                    <!-- Floating Stat Card Overlay -->
                    <div
                        class="absolute -bottom-6 -left-6 glass-card p-lg rounded-2xl shadow-xl max-w-[240px] animate-bounce-slow">
                        <div class="flex items-center gap-md mb-sm">
                            <div
                                class="w-10 h-10 rounded-full bg-tertiary-fixed text-on-tertiary-fixed flex items-center justify-center">
                                <span class="material-symbols-outlined">trending_up</span>
                            </div>
                            <div class="font-label-lg text-label-lg text-on-surface">Funds Disbursed</div>
                        </div>
                        <div class="font-headline-sm text-headline-sm text-on-surface">$2.4B+</div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Features Bento Grid -->
        <section class="py-3xl bg-surface-container-low">
            <div class="max-w-container-max mx-auto px-margin-mobile md:px-xl">
                <div class="text-center max-w-2xl mx-auto mb-2xl">
                    <h2
                        class="font-headline-lg text-headline-lg lg:font-display-md lg:text-display-md text-on-surface mb-sm">
                        Designed for Modern Finance</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Powerful features to help you manage
                        your assets and secure the capital you need instantly.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-lg">
                    <!-- Feature Card 1 -->
                    <div
                        class="bg-surface-container-lowest p-xl rounded-2xl border border-outline-variant shadow-sm hover:shadow-md transition-shadow group">
                        <div
                            class="w-14 h-14 bg-primary/10 text-primary rounded-xl flex items-center justify-center mb-lg group-hover:bg-primary group-hover:text-on-primary transition-all duration-300">
                            <span class="material-symbols-outlined"
                                style="font-variation-settings: 'FILL' 1;">bolt</span>
                        </div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm">Instant Approval</h3>
                        <p class="font-body-md text-body-md text-on-surface-variant">Our AI-driven engine processes your
                            application in seconds, giving you an immediate decision without the wait.</p>
                    </div>
                    <!-- Feature Card 2 -->
                    <div
                        class="bg-surface-container-lowest p-xl rounded-2xl border border-outline-variant shadow-sm hover:shadow-md transition-shadow group">
                        <div
                            class="w-14 h-14 bg-primary/10 text-primary rounded-xl flex items-center justify-center mb-lg group-hover:bg-primary group-hover:text-on-primary transition-all duration-300">
                            <span class="material-symbols-outlined"
                                style="font-variation-settings: 'FILL' 1;">percent</span>
                        </div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm">Low Interest Rates</h3>
                        <p class="font-body-md text-body-md text-on-surface-variant">Benefit from industry-leading rates
                            that reward your financial health and help you save over the long term.</p>
                    </div>
                    <!-- Feature Card 3 -->
                    <div
                        class="bg-surface-container-lowest p-xl rounded-2xl border border-outline-variant shadow-sm hover:shadow-md transition-shadow group">
                        <div
                            class="w-14 h-14 bg-primary/10 text-primary rounded-xl flex items-center justify-center mb-lg group-hover:bg-primary group-hover:text-on-primary transition-all duration-300">
                            <span class="material-symbols-outlined"
                                style="font-variation-settings: 'FILL' 1;">support_agent</span>
                        </div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm">24/7 Support</h3>
                        <p class="font-body-md text-body-md text-on-surface-variant">Our dedicated financial advisors
                            are available around the clock to assist you with any questions or needs.</p>
                    </div>
                </div>
            </div>
        </section>
        <!-- Loan Process Visual Guide -->
        <section class="py-3xl bg-surface-container-lowest relative overflow-hidden">
            <div class="max-w-container-max mx-auto px-margin-mobile md:px-xl">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-2xl items-center">
                    <div class="space-y-lg">
                        <h2 class="font-display-md text-display-md text-on-surface">How It Works</h2>
                        <p class="font-body-lg text-body-lg text-on-surface-variant">Getting funded is easier than ever.
                            Follow our streamlined three-step process to get the capital you need today.</p>
                        <div class="space-y-xl pt-lg">
                            <div class="flex gap-lg">
                                <div
                                    class="flex-shrink-0 w-12 h-12 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-lg">
                                    1</div>
                                <div>
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface">Register Account</h4>
                                    <p class="font-body-md text-body-md text-on-surface-variant">Sign up in minutes with
                                        your basic information and secure your personal financial dashboard.</p>
                                </div>
                            </div>
                            <div class="flex gap-lg">
                                <div
                                    class="flex-shrink-0 w-12 h-12 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-lg">
                                    2</div>
                                <div>
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface">Apply for Loan</h4>
                                    <p class="font-body-md text-body-md text-on-surface-variant">Select your loan
                                        amount, choose your repayment terms, and submit your digital application.</p>
                                </div>
                            </div>
                            <div class="flex gap-lg">
                                <div
                                    class="flex-shrink-0 w-12 h-12 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-lg">
                                    3</div>
                                <div>
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface">Get Funded</h4>
                                    <p class="font-body-md text-body-md text-on-surface-variant">Once approved, funds
                                        are deposited directly into your linked account within 24 hours.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="relative bg-surface-container rounded-[2rem] p-lg border border-outline-variant overflow-hidden">
                        <img class="w-full h-full object-cover rounded-2xl shadow-lg"
                            data-alt="A high-quality digital screen showing a minimalist banking application interface with data charts and success checkmarks. The background is a blurred office environment with cool blue and slate tones. The composition is clean and technical, emphasizing the ease of the digital loan application process through visual clarity and modern design aesthetics."
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuB9aY4Lt7msTPgWonv4YHtBritKp5rkI_8jpKC0uACSjcUng5xkrW7NL_brdFzgETw0i6EdwNoOXITB_OTiczdOtQbLiB0KLkqsUhvCiDOVwdEvDnq5qgEcWyLyS_OZvY6rn9-z0fempUep8tWEfH6q5hUtRZAU1tmeVADC8lkWp2WIH07339YSHVunipGzL72XRvfJeMggFHAcuRfPV8023hBmwObZ2BA8RboHbv9hBzIOfpJBdwL8TyPC6H71zzn1rljOa4VEiuU" />
                    </div>
                </div>
            </div>
        </section>
        <!-- Benefits Section -->
        <section class="py-3xl bg-inverse-surface text-inverse-on-surface">
            <div class="max-w-container-max mx-auto px-margin-mobile md:px-xl text-center">
                <h2 class="font-display-md text-display-md mb-2xl">Why Choose FinancePro?</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-xl">
                    <div class="flex flex-col items-center p-lg">
                        <span class="material-symbols-outlined text-primary-fixed-dim text-5xl mb-lg"
                            style="font-variation-settings: 'FILL' 1;">shield</span>
                        <h3 class="font-headline-sm text-headline-sm mb-sm">Ultimate Security</h3>
                        <p class="font-body-md text-body-md opacity-80">Bank-grade encryption and multi-factor
                            authentication keep your data and funds safe at all times.</p>
                    </div>
                    <div class="flex flex-col items-center p-lg">
                        <span class="material-symbols-outlined text-primary-fixed-dim text-5xl mb-lg"
                            style="font-variation-settings: 'FILL' 1;">visibility</span>
                        <h3 class="font-headline-sm text-headline-sm mb-sm">Total Transparency</h3>
                        <p class="font-body-md text-body-md opacity-80">No hidden fees, no fine print surprises. We
                            clearly state all terms before you sign anything.</p>
                    </div>
                    <div class="flex flex-col items-center p-lg">
                        <span class="material-symbols-outlined text-primary-fixed-dim text-5xl mb-lg"
                            style="font-variation-settings: 'FILL' 1;">tune</span>
                        <h3 class="font-headline-sm text-headline-sm mb-sm">Max Flexibility</h3>
                        <p class="font-body-md text-body-md opacity-80">Adjust your repayment schedules or pay off your
                            loan early without any penalties or extra costs.</p>
                    </div>
                </div>
            </div>
        </section>
        <!-- Testimonials & FAQ Grid -->
        <section class="py-3xl bg-surface">
            <div class="max-w-container-max mx-auto px-margin-mobile md:px-xl">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3xl">
                    <!-- Testimonials -->
                    <div>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xl">What Our Clients Say</h2>
                        <div class="space-y-lg">
                            <div
                                class="bg-surface-container-lowest p-lg rounded-2xl border border-outline-variant shadow-sm">
                                <div class="flex items-center gap-md mb-md">
                                    <img class="w-12 h-12 rounded-full object-cover"
                                        data-alt="A headshot portrait of a confident male professional in his late 30s, wearing a tailored navy blazer and a light blue dress shirt. He is smiling warmly in a bright, modern corporate setting with soft natural light. The visual style is high-fidelity, polished, and trustworthy, perfectly fitting a premium financial service testimonial."
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuAg0pPhK2VoGN8bPebPC4ChFJABNz5nSHFlvGz41U0Auy4s61Hl4zTgFdHSEPZLflUK8utO8MstrbKxLxPBB7vuVANkCthQ8I_2ox332dfDfxn5OWqKY2YmXp9Av-3gyP5bDVF6HdqkhMJdFEV5l8kPfaL1c_Sw1QJOJfXX31mtf0LQ64lm9UsmLyhhUQIiRTvyrnkZCZsM8Bd_Q6xSpOPcVOwtL6IwnjvWqoSzvD4DDFYkPcuZDyVD1J-CsrpclZeF4ANJMA_NlEQ" />
                                    <div>
                                        <div class="font-label-lg text-label-lg text-on-surface">Robert Chen</div>
                                        <div class="font-label-md text-label-md text-on-surface-variant">Tech Startup
                                            Founder</div>
                                    </div>
                                </div>
                                <p class="font-body-md text-body-md text-on-surface-variant italic">"FinancePro provided
                                    the bridge funding we needed for our expansion. The speed of approval was honestly
                                    mind-blowing compared to traditional banks."</p>
                            </div>
                            <div
                                class="bg-surface-container-lowest p-lg rounded-2xl border border-outline-variant shadow-sm">
                                <div class="flex items-center gap-md mb-md">
                                    <img class="w-12 h-12 rounded-full object-cover"
                                        data-alt="A professional woman in a business casual outfit, looking into the camera with a calm and reassuring smile. The background is a blurred office with clean, professional lighting. The image captures a sense of professional success and satisfaction, using a color palette of soft neutrals and corporate blue tones."
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBwHY7R82yU3PIX7Y-LgIcstwvJAXNoadsYmXS3gpTPhkhXOYP1Qaorvr_VgvZU-ryIGxhatrHQ7I60TrKHqo82JsW4LSHWWh5ftXqUh2IiaIVfWvY3_QDclOMse5PaxlniNSQQ_nltZQIMtTUR0tbTYYcNvwren0yi5QiX0N8CCKYduooj_WM0TJNSgmsund9gh3ShXdz-haxi1Kwja3i5eZEeh2y8OhPtRzvo6nty0ejOzDtBGtrBBDpHa3lLGi6lvDe2WpaE4Ag" />
                                    <div>
                                        <div class="font-label-lg text-label-lg text-on-surface">Sarah Jenkins</div>
                                        <div class="font-label-md text-label-md text-on-surface-variant">Small Business
                                            Owner</div>
                                    </div>
                                </div>
                                <p class="font-body-md text-body-md text-on-surface-variant italic">"The transparency is
                                    what sold me. I knew exactly what my monthly payments would be and there were zero
                                    hidden charges. Highly recommend!"</p>
                            </div>
                        </div>
                    </div>
                    <!-- FAQ -->
                    <div>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xl">Common Questions</h2>
                        <div class="space-y-md">
                            <div
                                class="border border-outline-variant rounded-xl p-md bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer group">
                                <div class="flex justify-between items-center">
                                    <span class="font-label-lg text-label-lg text-on-surface">How long does approval
                                        take?</span>
                                    <span
                                        class="material-symbols-outlined text-primary group-hover:rotate-180 transition-transform">expand_more</span>
                                </div>
                            </div>
                            <div
                                class="border border-outline-variant rounded-xl p-md bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer group">
                                <div class="flex justify-between items-center">
                                    <span class="font-label-lg text-label-lg text-on-surface">What documents are
                                        required?</span>
                                    <span
                                        class="material-symbols-outlined text-primary group-hover:rotate-180 transition-transform">expand_more</span>
                                </div>
                            </div>
                            <div
                                class="border border-outline-variant rounded-xl p-md bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer group">
                                <div class="flex justify-between items-center">
                                    <span class="font-label-lg text-label-lg text-on-surface">Can I repay the loan
                                        early?</span>
                                    <span
                                        class="material-symbols-outlined text-primary group-hover:rotate-180 transition-transform">expand_more</span>
                                </div>
                            </div>
                            <div
                                class="border border-outline-variant rounded-xl p-md bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer group">
                                <div class="flex justify-between items-center">
                                    <span class="font-label-lg text-label-lg text-on-surface">Are there any hidden
                                        fees?</span>
                                    <span
                                        class="material-symbols-outlined text-primary group-hover:rotate-180 transition-transform">expand_more</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- CTA Section -->
        <section class="py-3xl">
            <div class="max-w-container-max mx-auto px-margin-mobile md:px-xl">
                <div
                    class="bg-primary rounded-3xl p-2xl text-center text-on-primary shadow-2xl relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 pointer-events-none">
                        <svg height="100%" preserveaspectratio="none" viewbox="0 0 100 100" width="100%">
                            <path d="M0 100 C 20 0 50 0 100 100 Z" fill="white"></path>
                        </svg>
                    </div>
                    <div class="relative z-10 space-y-lg max-w-3xl mx-auto">
                        <h2 class="font-display-md text-display-md">Ready to take the next step?</h2>
                        <p class="font-body-lg text-body-lg opacity-90">Join over 2 million satisfied customers who have
                            built their future with FinancePro. Your smart loan is just minutes away.</p>
                        <div class="flex flex-col sm:flex-row gap-md justify-center pt-md">
                            <button
                                class="bg-surface-container-lowest text-primary font-label-lg text-label-lg px-2xl py-md rounded-xl shadow-lg active:scale-95 transition-all">Apply
                                Now</button>
                            <button
                                class="bg-primary-container text-on-primary-container border border-on-primary-container/30 font-label-lg text-label-lg px-2xl py-md rounded-xl active:scale-95 transition-all">Schedule
                                a Consultation</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <!-- Footer -->
    <footer
        class="w-full py-xl px-margin-mobile md:px-xl flex flex-col max-w-container-max mx-auto border-t border-outline-variant bg-surface-container-highest">
        <div class="flex flex-col md:flex-row justify-between items-start gap-2xl mb-xl">
            <div class="space-y-md max-w-sm">
                <span class="font-label-lg text-label-lg font-bold text-on-surface">FinancePro</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Global leader in digital lending and
                    financial asset management. Empowering businesses and individuals since 2015.</p>
                <div class="flex gap-md">
                    <a class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-primary hover:bg-primary hover:text-on-primary transition-all"
                        href="#">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">public</span>
                    </a>
                    <a class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-primary hover:bg-primary hover:text-on-primary transition-all"
                        href="#">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">share</span>
                    </a>
                    <a class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-primary hover:bg-primary hover:text-on-primary transition-all"
                        href="#">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">forum</span>
                    </a>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-xl w-full md:w-auto">
                <div class="space-y-sm">
                    <h5 class="font-label-lg text-label-lg text-on-surface font-bold">Solutions</h5>
                    <ul class="space-y-xs font-body-sm text-body-sm text-on-surface-variant">
                        <li><a class="hover:text-primary transition-colors" href="#">Personal Loans</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Business Capital</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Asset Management</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Tax Advisory</a></li>
                    </ul>
                </div>
                <div class="space-y-sm">
                    <h5 class="font-label-lg text-label-lg text-on-surface font-bold">Company</h5>
                    <ul class="space-y-xs font-body-sm text-body-sm text-on-surface-variant">
                        <li><a class="hover:text-primary transition-colors" href="#">About Us</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Careers</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Contact</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Newsroom</a></li>
                    </ul>
                </div>
                <div class="space-y-sm">
                    <h5 class="font-label-lg text-label-lg text-on-surface font-bold">Legal</h5>
                    <ul class="space-y-xs font-body-sm text-body-sm text-on-surface-variant">
                        <li><a class="hover:text-primary transition-colors" href="#">Privacy Policy</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Terms of Service</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Compliance</a></li>
                        <li><a class="hover:text-primary transition-colors" href="#">Security</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div
            class="pt-lg border-t border-outline-variant flex flex-col md:flex-row justify-between items-center gap-md">
            <p class="font-body-sm text-body-sm text-on-surface-variant">© 2024 FinancePro Solutions. All rights
                reserved.</p>
            <div class="flex gap-lg">
                <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary" href="#">English
                    (US)</a>
                <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary" href="#">EUR (€)</a>
            </div>
        </div>
    </footer>
    <script>
        // Simple Micro-interaction for FAQ
        document.querySelectorAll('.group').forEach(item => {
            item.addEventListener('click', () => {
                const icon = item.querySelector('.material-symbols-outlined');
                if (icon) {
                    icon.classList.toggle('rotate-180');
                }
            });
        });

        // Sticky Navbar effect on scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            if (window.scrollY > 20) {
                header.classList.add('shadow-md');
                header.classList.add('bg-white');
            } else {
                header.classList.remove('shadow-md');
                header.classList.remove('bg-white');
            }
        });
    </script>
</body>

</html>