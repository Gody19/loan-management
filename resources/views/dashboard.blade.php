<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>FinancePro Admin Dashboard</title>
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
                        "tertiary-fixed-dim": "#4edea3",
                        "on-secondary-container": "#586377",
                        "surface-container-low": "#eff4ff",
                        "surface-container-highest": "#d3e4fe",
                        "secondary-fixed-dim": "#bcc7de",
                        "on-primary-fixed-variant": "#003ea8",
                        "on-background": "#0b1c30",
                        "primary-fixed-dim": "#b4c5ff",
                        "on-primary-fixed": "#00174b",
                        "surface-dim": "#cbdbf5",
                        "surface-tint": "#0053db",
                        "on-primary": "#ffffff",
                        "tertiary": "#006242",
                        "on-tertiary-container": "#bdffdb",
                        "on-surface-variant": "#434655",
                        "on-tertiary-fixed-variant": "#005236",
                        "primary-fixed": "#dbe1ff",
                        "inverse-surface": "#213145",
                        "outline": "#737686",
                        "surface": "#f8f9ff",
                        "inverse-primary": "#b4c5ff",
                        "primary": "#004ac6",
                        "on-error": "#ffffff",
                        "on-secondary-fixed-variant": "#3c475a",
                        "surface-bright": "#f8f9ff",
                        "on-secondary-fixed": "#111c2d",
                        "background": "#f8f9ff",
                        "primary-container": "#2563eb",
                        "error": "#ba1a1a",
                        "secondary": "#545f73",
                        "outline-variant": "#c3c6d7",
                        "on-error-container": "#93000a",
                        "tertiary-fixed": "#6ffbbe",
                        "tertiary-container": "#007d55",
                        "surface-container-high": "#dce9ff",
                        "on-surface": "#0b1c30",
                        "surface-container": "#e5eeff",
                        "on-tertiary": "#ffffff",
                        "secondary-fixed": "#d8e3fb",
                        "secondary-container": "#d5e0f8",
                        "on-tertiary-fixed": "#002113",
                        "on-secondary": "#ffffff",
                        "surface-variant": "#d3e4fe",
                        "error-container": "#ffdad6",
                        "inverse-on-surface": "#eaf1ff",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-container": "#eeefff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "3xl": "64px",
                        "margin-mobile": "16px",
                        "gutter": "24px",
                        "md": "16px",
                        "lg": "24px",
                        "base": "4px",
                        "2xl": "48px",
                        "container-max": "1280px",
                        "sm": "8px",
                        "xs": "4px",
                        "xl": "32px"
                    },
                    "fontFamily": {
                        "label-lg": ["Inter"],
                        "headline-lg": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "body-lg": ["Inter"],
                        "code": ["Inter"],
                        "display-lg": ["Inter"],
                        "headline-sm": ["Inter"],
                        "display-md": ["Inter"],
                        "body-md": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-md": ["Inter"],
                        "body-sm": ["Inter"]
                    },
                    "fontSize": {
                        "label-lg": ["14px", { "lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "600" }],
                        "headline-lg": ["30px", { "lineHeight": "38px", "fontWeight": "600" }],
                        "headline-lg-mobile": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
                        "body-lg": ["18px", { "lineHeight": "28px", "fontWeight": "400" }],
                        "code": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
                        "display-lg": ["48px", { "lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "headline-sm": ["20px", { "lineHeight": "28px", "fontWeight": "600" }],
                        "display-md": ["36px", { "lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                        "label-md": ["12px", { "lineHeight": "16px", "fontWeight": "500" }],
                        "headline-md": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
                        "body-sm": ["14px", { "lineHeight": "20px", "fontWeight": "400" }]
                    }
                },
            },
        }
    </script>
    <style>
        body {
            background-color: #f8f9ff;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
        }

        .card-shadow {
            box-shadow: 0 2px 4px rgba(30, 41, 59, 0.05);
            border: 1px solid #E2E8F0;
        }
    </style>
</head>

<body class="font-body-md text-on-background">
    <!-- Sidebar Component (Mapped from JSON) -->
    <aside
        class="hidden md:flex flex-col h-screen fixed left-0 top-0 p-md space-y-md bg-surface-container-low border-r border-outline-variant w-64 z-50">
        <div class="flex items-center gap-md px-md py-sm mb-lg">
            <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center text-on-primary">
                <span class="material-symbols-outlined"
                    style="font-variation-settings: 'FILL' 1;">account_balance</span>
            </div>
            <div>
                <h1 class="font-headline-sm text-headline-sm font-bold text-on-surface">FinancePro</h1>
                <p class="font-label-md text-label-md text-on-surface-variant">Enterprise Finance</p>
            </div>
        </div>
        <nav class="flex-1 space-y-base">
            <a class="flex items-center gap-md bg-secondary-container text-on-secondary-container rounded-lg px-md py-sm font-bold"
                href="#">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="font-label-lg text-label-lg">Dashboard</span>
            </a>
            <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all"
                href="#">
                <span class="material-symbols-outlined">add_box</span>
                <span class="font-label-lg text-label-lg">Apply Loan</span>
            </a>
            <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all"
                href="#">
                <span class="material-symbols-outlined">history_edu</span>
                <span class="font-label-lg text-label-lg">My Loans</span>
            </a>
            <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all"
                href="#">
                <span class="material-symbols-outlined">payments</span>
                <span class="font-label-lg text-label-lg">Repayments</span>
            </a>
            <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all"
                href="#">
                <span class="material-symbols-outlined">analytics</span>
                <span class="font-label-lg text-label-lg">Reports</span>
            </a>
            <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all"
                href="#">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-label-lg text-label-lg">Settings</span>
            </a>
        </nav>
        <div class="pt-lg border-t border-outline-variant space-y-base">
            <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all"
                href="#">
                <span class="material-symbols-outlined">help</span>
                <span class="font-label-lg text-label-lg">Support</span>
            </a>
            <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all text-error"
                href="#">
                <span class="material-symbols-outlined">logout</span>
                <span class="font-label-lg text-label-lg">Logout</span>
            </a>
        </div>
    </aside>
    <!-- Main Content Area -->
    <main class="md:ml-64 min-h-screen">
        <!-- Top Header Component (Mapped from JSON) -->
        <header
            class="sticky top-0 w-full z-40 flex items-center justify-between px-margin-mobile md:px-xl h-16 bg-surface-container-lowest border-b border-outline-variant shadow-sm max-w-container-max mx-auto">
            <div class="flex items-center gap-md">
                <h2 class="font-headline-md text-headline-md font-bold text-primary">Overview</h2>
            </div>
            <div class="flex items-center gap-lg">
                <div
                    class="hidden md:flex items-center bg-surface-container px-md py-xs rounded-full border border-outline-variant">
                    <span class="material-symbols-outlined text-outline">search</span>
                    <input
                        class="bg-transparent border-none focus:ring-0 font-body-sm text-body-sm placeholder:text-outline-variant w-48"
                        placeholder="Search records..." type="text" />
                </div>
                <div class="flex items-center gap-sm">
                    <div class="text-right hidden sm:block">
                        <p class="font-label-lg text-label-lg text-on-surface leading-tight">Administrator</p>
                        <p class="font-label-md text-label-md text-on-surface-variant leading-tight">Super Admin</p>
                    </div>
                    <img alt="Administrator profile"
                        class="w-10 h-10 rounded-full object-cover border-2 border-primary-fixed"
                        data-alt="A professional headshot of a middle-aged male administrator in a sharp navy blue business suit. He is standing against a soft-focus corporate office background with cool blue and white tones. The lighting is crisp and even, conveying a sense of authority, trustworthiness, and high-stakes financial leadership. The overall aesthetic is clean, modern, and high-fidelity."
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBu7jJLSX-nOu2nWFgm0S_MxwLqnxOAsFdQ0VL9RF9IyVt7HfGabf5VFNKbHdrzdPpxO-7MiUhAnAmFee4m51-WUIohLQfk23wO3nv1gT8TfuR9yv-J-0Q6LzvUEBIldZZqTjqboUd_T-AZE6SAFWvPks7bjl5irUA3Ry7V5X0lDsMnYgY6LS_SN0DbbNIJxgCihBYjgUhh9MfRdpr-_Fywsq8f5pYf-r-zNQ4mbMvr74d6AOO3MabqVS0NiO1qYw9KKDGUXA25Q3s" />
                </div>
            </div>
        </header>
        <!-- Dashboard Canvas -->
        <div class="p-margin-mobile md:p-xl max-w-container-max mx-auto space-y-xl">
            <!-- KPI Section -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-lg">
                <!-- Total Portfolio Value -->
                <div
                    class="bg-surface-container-lowest p-lg rounded-xl card-shadow group hover:border-primary transition-all">
                    <div class="flex justify-between items-start mb-md">
                        <div
                            class="w-12 h-12 bg-primary-fixed rounded-lg flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined">account_balance_wallet</span>
                        </div>
                        <span class="text-tertiary font-label-lg text-label-lg flex items-center gap-xs">
                            <span class="material-symbols-outlined text-sm">trending_up</span> +12.5%
                        </span>
                    </div>
                    <h3 class="font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Total
                        Portfolio Value</h3>
                    <p class="font-display-md text-display-md text-on-surface mt-base">$42,850,000</p>
                </div>
                <!-- Pending Approvals -->
                <div
                    class="bg-surface-container-lowest p-lg rounded-xl card-shadow group hover:border-error transition-all">
                    <div class="flex justify-between items-start mb-md">
                        <div
                            class="w-12 h-12 bg-error-container rounded-lg flex items-center justify-center text-error">
                            <span class="material-symbols-outlined">pending_actions</span>
                        </div>
                        <span
                            class="bg-error text-on-error px-sm py-xs rounded-full font-label-md text-label-md animate-pulse">Urgent</span>
                    </div>
                    <h3 class="font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Pending
                        Approvals</h3>
                    <p class="font-display-md text-display-md text-on-surface mt-base">14</p>
                </div>
                <!-- Approval Rate -->
                <div
                    class="bg-surface-container-lowest p-lg rounded-xl card-shadow group hover:border-tertiary transition-all">
                    <div class="flex justify-between items-start mb-md">
                        <div
                            class="w-12 h-12 bg-tertiary-fixed rounded-lg flex items-center justify-center text-tertiary">
                            <span class="material-symbols-outlined">verified</span>
                        </div>
                        <span class="text-tertiary font-label-lg text-label-lg flex items-center gap-xs">
                            <span class="material-symbols-outlined text-sm">check_circle</span> Stable
                        </span>
                    </div>
                    <h3 class="font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Approval
                        Rate</h3>
                    <p class="font-display-md text-display-md text-on-surface mt-base">88.4%</p>
                </div>
                <!-- Total Active Users -->
                <div
                    class="bg-surface-container-lowest p-lg rounded-xl card-shadow group hover:border-primary transition-all">
                    <div class="flex justify-between items-start mb-md">
                        <div
                            class="w-12 h-12 bg-secondary-fixed rounded-lg flex items-center justify-center text-secondary">
                            <span class="material-symbols-outlined">group</span>
                        </div>
                        <span class="text-primary font-label-lg text-label-lg flex items-center gap-xs">
                            <span class="material-symbols-outlined text-sm">person_add</span> +430
                        </span>
                    </div>
                    <h3 class="font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">Total
                        Active Users</h3>
                    <p class="font-display-md text-display-md text-on-surface mt-base">2,105</p>
                </div>
            </section>
            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-xl">
                <!-- Loan Applications Queue -->
                <section class="lg:col-span-2 space-y-lg">
                    <div class="flex items-center justify-between">
                        <h2 class="font-headline-sm text-headline-sm font-bold">Loan Applications Queue</h2>
                        <button class="text-primary font-label-lg text-label-lg hover:underline transition-all">View All
                            Applications</button>
                    </div>
                    <div class="bg-surface-container-lowest rounded-xl card-shadow overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface-container-low">
                                    <th
                                        class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant uppercase">
                                        Applicant</th>
                                    <th
                                        class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant uppercase">
                                        Loan Type</th>
                                    <th
                                        class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant uppercase">
                                        Amount</th>
                                    <th
                                        class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant uppercase">
                                        Status</th>
                                    <th
                                        class="px-lg py-md font-label-lg text-label-lg text-on-surface-variant uppercase text-right">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                <tr class="hover:bg-surface-container/50 transition-colors">
                                    <td class="px-lg py-lg">
                                        <div class="flex items-center gap-md">
                                            <div
                                                class="w-10 h-10 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center font-bold">
                                                JD</div>
                                            <div>
                                                <p class="font-label-lg text-label-lg">John Doe</p>
                                                <p class="font-body-sm text-body-sm text-on-surface-variant">
                                                    john.d@email.com</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-lg py-lg font-body-md text-body-md">Commercial Real Estate</td>
                                    <td class="px-lg py-lg font-body-md text-body-md font-semibold">$1,200,000</td>
                                    <td class="px-lg py-lg">
                                        <span
                                            class="bg-secondary-container text-on-secondary-container px-sm py-1 rounded-full text-xs font-bold">UNDER
                                            REVIEW</span>
                                    </td>
                                    <td class="px-lg py-lg text-right">
                                        <button
                                            class="bg-primary text-on-primary px-lg py-sm rounded-lg font-label-lg text-label-lg hover:opacity-90 transition-all active:scale-95">Review</button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-surface-container/50 transition-colors">
                                    <td class="px-lg py-lg">
                                        <div class="flex items-center gap-md">
                                            <div
                                                class="w-10 h-10 rounded-full bg-tertiary-container text-on-tertiary-container flex items-center justify-center font-bold">
                                                SM</div>
                                            <div>
                                                <p class="font-label-lg text-label-lg">Sarah Miller</p>
                                                <p class="font-body-sm text-body-sm text-on-surface-variant">
                                                    s.miller@corp.net</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-lg py-lg font-body-md text-body-md">Business Expansion</td>
                                    <td class="px-lg py-lg font-body-md text-body-md font-semibold">$450,000</td>
                                    <td class="px-lg py-lg">
                                        <span
                                            class="bg-error-container text-on-error-container px-sm py-1 rounded-full text-xs font-bold uppercase">Urgent
                                            Review</span>
                                    </td>
                                    <td class="px-lg py-lg text-right">
                                        <button
                                            class="bg-primary text-on-primary px-lg py-sm rounded-lg font-label-lg text-label-lg hover:opacity-90 transition-all active:scale-95">Review</button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-surface-container/50 transition-colors">
                                    <td class="px-lg py-lg">
                                        <div class="flex items-center gap-md">
                                            <div
                                                class="w-10 h-10 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold">
                                                RW</div>
                                            <div>
                                                <p class="font-label-lg text-label-lg">Robert Wong</p>
                                                <p class="font-body-sm text-body-sm text-on-surface-variant">
                                                    robert.w@tech.io</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-lg py-lg font-body-md text-body-md">Equipment Finance</td>
                                    <td class="px-lg py-lg font-body-md text-body-md font-semibold">$85,000</td>
                                    <td class="px-lg py-lg">
                                        <span
                                            class="bg-surface-container-highest text-on-surface-variant px-sm py-1 rounded-full text-xs font-bold uppercase">Queued</span>
                                    </td>
                                    <td class="px-lg py-lg text-right">
                                        <button
                                            class="bg-primary text-on-primary px-lg py-sm rounded-lg font-label-lg text-label-lg hover:opacity-90 transition-all active:scale-95">Review</button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-surface-container/50 transition-colors">
                                    <td class="px-lg py-lg">
                                        <div class="flex items-center gap-md">
                                            <div
                                                class="w-10 h-10 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-bold">
                                                AL</div>
                                            <div>
                                                <p class="font-label-lg text-label-lg">Alice Lee</p>
                                                <p class="font-body-sm text-body-sm text-on-surface-variant">
                                                    alice.lee@mail.com</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-lg py-lg font-body-md text-body-md">Personal Premium</td>
                                    <td class="px-lg py-lg font-body-md text-body-md font-semibold">$120,000</td>
                                    <td class="px-lg py-lg">
                                        <span
                                            class="bg-secondary-container text-on-secondary-container px-sm py-1 rounded-full text-xs font-bold uppercase">Pending
                                            Docs</span>
                                    </td>
                                    <td class="px-lg py-lg text-right">
                                        <button
                                            class="bg-primary text-on-primary px-lg py-sm rounded-lg font-label-lg text-label-lg hover:opacity-90 transition-all active:scale-95">Review</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <!-- Portfolio Distribution -->
                <section class="space-y-lg">
                    <div class="flex items-center justify-between">
                        <h2 class="font-headline-sm text-headline-sm font-bold">Portfolio Distribution</h2>
                        <span
                            class="material-symbols-outlined text-outline cursor-pointer hover:text-primary">more_vert</span>
                    </div>
                    <div
                        class="bg-surface-container-lowest rounded-xl card-shadow p-lg h-[440px] flex flex-col items-center justify-center relative overflow-hidden">
                        <div class="relative w-64 h-64">
                            <!-- SVG Chart Placeholder -->
                            <svg class="w-full h-full transform -rotate-90" viewbox="0 0 36 36">
                                <path class="text-primary-container"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none" stroke="currentColor" stroke-dasharray="30, 100" stroke-width="3">
                                </path>
                                <path class="text-tertiary-container"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none" stroke="currentColor" stroke-dasharray="20, 100" stroke-dashoffset="-30"
                                    stroke-width="3"></path>
                                <path class="text-secondary"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none" stroke="currentColor" stroke-dasharray="25, 100" stroke-dashoffset="-50"
                                    stroke-width="3"></path>
                                <path class="text-outline-variant"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none" stroke="currentColor" stroke-dasharray="25, 100" stroke-dashoffset="-75"
                                    stroke-width="3"></path>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <p class="font-display-md text-display-md leading-tight">72%</p>
                                <p class="font-label-md text-label-md text-on-surface-variant uppercase">Liquid</p>
                            </div>
                        </div>
                        <div class="w-full mt-xl space-y-sm">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-sm">
                                    <div class="w-3 h-3 rounded-full bg-primary-container"></div>
                                    <span class="font-body-sm text-body-sm">Real Estate</span>
                                </div>
                                <span class="font-label-lg text-label-lg">30%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-sm">
                                    <div class="w-3 h-3 rounded-full bg-tertiary-container"></div>
                                    <span class="font-body-sm text-body-sm">Corporate Loans</span>
                                </div>
                                <span class="font-label-lg text-label-lg">20%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-sm">
                                    <div class="w-3 h-3 rounded-full bg-secondary"></div>
                                    <span class="font-body-sm text-body-sm">Equities</span>
                                </div>
                                <span class="font-label-lg text-label-lg">25%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-sm">
                                    <div class="w-3 h-3 rounded-full bg-outline-variant"></div>
                                    <span class="font-body-sm text-body-sm">Cash Reserve</span>
                                </div>
                                <span class="font-label-lg text-label-lg">25%</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <!-- Footer Component (Mapped from JSON) -->
            <footer
                class="w-full py-xl px-margin-mobile md:px-xl flex flex-col md:flex-row justify-between items-center gap-lg max-w-container-max mx-auto border-t border-outline-variant mt-2xl bg-surface-container-highest">
                <div class="flex flex-col items-center md:items-start">
                    <h4 class="font-label-lg text-label-lg font-bold text-on-surface">FinancePro</h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">© 2024 FinancePro Solutions. All rights
                        reserved.</p>
                </div>
                <nav class="flex gap-lg flex-wrap justify-center">
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                        href="#">Privacy Policy</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                        href="#">Terms of Service</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                        href="#">Compliance</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                        href="#">Security</a>
                </nav>
            </footer>
        </div>
    </main>
    <script>
        // Simple interactivity for the Review buttons
        document.querySelectorAll('button').forEach(btn => {
            if (btn.innerText === 'Review') {
                btn.addEventListener('click', () => {
                    btn.classList.add('scale-95');
                    setTimeout(() => {
                        btn.classList.remove('scale-95');
                        alert('Navigating to loan details review...');
                    }, 150);
                });
            }
        });

        // Atmospheric fade-in for cards
        window.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.card-shadow');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = `all 0.4s ease-out ${index * 0.1}s`;
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 50);
            });
        });
    </script>
</body>

</html>