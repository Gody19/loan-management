@extends('layouts.app')
@section('styles')
@endsection
@section('title', 'Loan Review | FinancePro Admin')
@section('content')

    <div class="bg-surface-container-lowest px-margin-mobile md:px-xl py-lg">
        <div class="max-w-container-max mx-auto flex flex-col md:flex-row md:items-center justify-between gap-md">
            <div>
                <div class="flex items-center gap-sm mb-xs">
                    <span
                        class="bg-secondary-container text-on-secondary-container px-sm py-xs rounded-lg text-label-md font-bold">L-882910</span>
                    <span class="text-on-surface-variant font-label-md">• Received 2 hours ago</span>
                </div>
                <h2 class="font-display-md text-display-md text-on-surface tracking-tight">Review Application:
                    {{ Auth::user()->fullname ?? 'N/A' }}</h2>
            </div>
            <div class="flex items-center gap-sm">
                <button
                    class="bg-surface-container-high text-on-surface px-lg py-sm rounded-lg font-label-lg hover:bg-surface-container-highest transition-colors flex items-center gap-xs">
                    <span class="material-symbols-outlined">print</span> Print
                </button>
                <button
                    class="bg-primary text-on-primary px-lg py-sm rounded-lg font-label-lg shadow-sm hover:opacity-90 transition-all flex items-center gap-xs">
                    <span class="material-symbols-outlined">share</span> Share Report
                </button>
            </div>
        </div>
    </div>
    <!-- Two-Column Layout Content -->
    <section class="flex-1 px-margin-mobile md:px-xl py-xl overflow-y-auto custom-scrollbar">
        <div class="max-w-container-max mx-auto grid grid-cols-1 lg:grid-cols-12 gap-gutter">
            <!-- Left Column: Details -->
            <div class="lg:col-span-7 space-y-lg">
                <!-- Applicant Details Card -->
                <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm">
                    <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-md">
                        <div class="flex items-center gap-md">
                            <div
                                class="w-12 h-12 rounded-xl bg-primary-fixed-dim flex items-center justify-center text-on-primary-fixed">
                                <span class="material-symbols-outlined">person</span>
                            </div>
                            <div>
                                <h3 class="font-headline-sm text-headline-sm text-on-surface">Applicant Profile</h3>
                                <p class="text-on-surface-variant font-body-sm">Verified Identity &amp; Contact</p>
                            </div>
                        </div>
                        <span
                            class="bg-tertiary-container text-on-tertiary-container px-md py-xs rounded-full text-label-md font-bold flex items-center gap-xs">
                            <span class="material-symbols-outlined text-[16px]">verified</span> KYC Verified
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-xl">
                        <div class="space-y-md">
                            <div>
                                <label class="block text-on-surface-variant font-label-md mb-xs">Full Name</label>
                                <p class="font-body-md text-body-md text-on-surface font-semibold">Sarah Jane
                                    Mitchell</p>
                            </div>
                            <div>
                                <label class="block text-on-surface-variant font-label-md mb-xs">NIDA Number</label>
                                <p class="font-body-md text-body-md text-on-surface font-semibold tracking-widest">
                                    19880512-41223-00001-22</p>
                            </div>
                            <div>
                                <label class="block text-on-surface-variant font-label-md mb-xs">Date of
                                    Birth</label>
                                <p class="font-body-md text-body-md text-on-surface">12 May 1988 (35 yrs)</p>
                            </div>
                        </div>
                        <div class="space-y-md">
                            <div>
                                <label class="block text-on-surface-variant font-label-md mb-xs">Email
                                    Address</label>
                                <p class="font-body-md text-body-md text-on-surface">s.mitchell@enterprise.com</p>
                            </div>
                            <div>
                                <label class="block text-on-surface-variant font-label-md mb-xs">Phone
                                    Number</label>
                                <p class="font-body-md text-body-md text-on-surface">+255 784 992 001</p>
                            </div>
                            <div>
                                <label class="block text-on-surface-variant font-label-md mb-xs">Residential
                                    Address</label>
                                <p class="font-body-md text-body-md text-on-surface">Plot 44, Masaki Drive, Dar es
                                    Salaam</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Loan Specifics Card -->
                <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm">
                    <div class="flex items-center gap-md mb-lg border-b border-outline-variant pb-md">
                        <div
                            class="w-12 h-12 rounded-xl bg-secondary-container flex items-center justify-center text-on-secondary-container">
                            <span class="material-symbols-outlined">payments</span>
                        </div>
                        <div>
                            <h3 class="font-headline-sm text-headline-sm text-on-surface">Loan Details</h3>
                            <p class="text-on-surface-variant font-body-sm">Financial parameters &amp; purpose</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
                        <div class="bg-surface-container-low p-md rounded-lg">
                            <label class="block text-on-secondary-container font-label-md mb-xs">Requested
                                Amount</label>
                            <p class="text-headline-md font-bold text-primary">$45,000.00</p>
                        </div>
                        <div class="bg-surface-container-low p-md rounded-lg">
                            <label class="block text-on-secondary-container font-label-md mb-xs">Duration</label>
                            <p class="text-headline-md font-bold text-on-surface">36 Months</p>
                        </div>
                        <div class="bg-surface-container-low p-md rounded-lg">
                            <label class="block text-on-secondary-container font-label-md mb-xs">Estimated
                                APR</label>
                            <p class="text-headline-md font-bold text-tertiary">8.4%</p>
                        </div>
                    </div>
                    <div class="space-y-lg">
                        <div>
                            <label class="block text-on-surface-variant font-label-md mb-xs">Loan Purpose</label>
                            <p class="font-body-md text-body-md text-on-surface leading-relaxed">The applicant is
                                seeking funds for commercial expansion of their existing logistics business. The
                                capital will be utilized for purchasing two additional heavy-duty freight vehicles
                                and hiring licensed operators to meet increasing demand in the East African
                                corridor.</p>
                        </div>
                        <div>
                            <label class="block text-on-surface-variant font-label-md mb-xs">Security /
                                Collateral</label>
                            <div class="flex items-center gap-md p-md border border-outline-variant rounded-lg">
                                <span class="material-symbols-outlined text-primary">apartment</span>
                                <div>
                                    <p class="font-label-lg text-label-lg text-on-surface">Commercial Real Estate
                                        Title</p>
                                    <p class="text-body-sm text-on-surface-variant">Valued at $120,000.00 (LTV:
                                        37.5%)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Credit Analysis Widget -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter">
                    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm">
                        <h4 class="font-label-lg text-label-lg text-on-surface mb-md">Internal Risk Score</h4>
                        <div class="flex items-end gap-sm">
                            <span class="text-display-md text-tertiary font-bold">782</span>
                            <span class="text-on-surface-variant font-label-md mb-sm">/ 850</span>
                        </div>
                        <div class="w-full bg-surface-container-highest h-2 rounded-full mt-md overflow-hidden">
                            <div class="bg-tertiary h-full w-[92%]"></div>
                        </div>
                        <p class="mt-md text-body-sm text-on-surface-variant">Exceptional repayment history and low
                            debt-to-income ratio.</p>
                    </div>
                    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm">
                        <h4 class="font-label-lg text-label-lg text-on-surface mb-md">Affordability Index</h4>
                        <div class="flex items-end gap-sm">
                            <span class="text-display-md text-primary font-bold">2.4x</span>
                        </div>
                        <div class="w-full bg-surface-container-highest h-2 rounded-full mt-md overflow-hidden">
                            <div class="bg-primary h-full w-[65%]"></div>
                        </div>
                        <p class="mt-md text-body-sm text-on-surface-variant">Monthly income covers 240% of the
                            projected repayment amount.</p>
                    </div>
                </div>
            </div>
            <!-- Right Column: Documents & Decision -->
            <div class="lg:col-span-5 space-y-lg">
                <!-- Document Preview Area -->
                <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-lg shadow-sm">
                    <div class="flex items-center justify-between mb-lg">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Documents</h3>
                        <button class="text-primary font-label-lg hover:underline">View All (8)</button>
                    </div>
                    <div class="grid grid-cols-2 gap-md">
                        <!-- Doc Item 1 -->
                        <div class="group relative rounded-lg border border-outline-variant overflow-hidden cursor-pointer">
                            <div class="aspect-video bg-surface-container-low flex items-center justify-center">
                                <span class="material-symbols-outlined text-outline text-3xl">picture_as_pdf</span>
                            </div>
                            <div class="p-sm bg-surface-container-lowest border-t border-outline-variant">
                                <p class="text-label-md text-on-surface truncate">NIDA_ID_Front.pdf</p>
                                <p class="text-[10px] text-on-surface-variant">1.2 MB • Verified</p>
                            </div>
                            <div
                                class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="bg-primary text-on-primary p-xs rounded-full">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                </span>
                            </div>
                        </div>
                        <!-- Doc Item 2 -->
                        <div class="group relative rounded-lg border border-outline-variant overflow-hidden cursor-pointer">
                            <div class="aspect-video bg-surface-container-low flex items-center justify-center">
                                <span class="material-symbols-outlined text-outline text-3xl">image</span>
                            </div>
                            <div class="p-sm bg-surface-container-lowest border-t border-outline-variant">
                                <p class="text-label-md text-on-surface truncate">Business_License.jpg</p>
                                <p class="text-[10px] text-on-surface-variant">2.4 MB • Verified</p>
                            </div>
                            <div
                                class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="bg-primary text-on-primary p-xs rounded-full">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                </span>
                            </div>
                        </div>
                        <!-- Doc Item 3 -->
                        <div class="group relative rounded-lg border border-outline-variant overflow-hidden cursor-pointer">
                            <div class="aspect-video bg-surface-container-low flex items-center justify-center">
                                <span class="material-symbols-outlined text-outline text-3xl">description</span>
                            </div>
                            <div class="p-sm bg-surface-container-lowest border-t border-outline-variant">
                                <p class="text-label-md text-on-surface truncate">Bank_Statements_6M.pdf</p>
                                <p class="text-[10px] text-on-surface-variant">5.1 MB • Pending Review</p>
                            </div>
                            <div
                                class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="bg-primary text-on-primary p-xs rounded-full">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                </span>
                            </div>
                        </div>
                        <!-- Doc Item 4 -->
                        <div class="group relative rounded-lg border border-outline-variant overflow-hidden cursor-pointer">
                            <div class="aspect-video bg-surface-container-low flex items-center justify-center">
                                <span class="material-symbols-outlined text-outline text-3xl">description</span>
                            </div>
                            <div class="p-sm bg-surface-container-lowest border-t border-outline-variant">
                                <p class="text-label-md text-on-surface truncate">Tax_Clearance_2023.pdf</p>
                                <p class="text-[10px] text-on-surface-variant">0.8 MB • Verified</p>
                            </div>
                            <div
                                class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="bg-primary text-on-primary p-xs rounded-full">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Decision Panel -->
                <div
                    class="bg-surface-container-lowest rounded-xl border-2 border-primary shadow-lg p-lg sticky top-[5.5rem]">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-md">Review Decision</h3>
                    <p class="text-body-sm text-on-surface-variant mb-xl">Submit your final verdict for this
                        application. This action is recorded and audited.</p>
                    <div class="space-y-lg">
                        <!-- Comments Field -->
                        <div>
                            <label class="block font-label-lg text-label-lg text-on-surface mb-sm"
                                for="decision-comments">Internal Reviewer Comments</label>
                            <textarea
                                class="w-full bg-surface-container-low border-outline-variant rounded-lg p-md text-body-md focus:ring-primary focus:border-primary placeholder:text-outline"
                                id="decision-comments" placeholder="Enter detailed reasoning for your decision..."
                                rows="4"></textarea>
                        </div>
                        <!-- Quick Tags -->
                        <div class="flex flex-wrap gap-xs">
                            <button
                                class="px-sm py-1 border border-outline-variant rounded-full text-label-md text-on-surface-variant hover:border-primary hover:text-primary transition-all">Strong
                                Credit</button>
                            <button
                                class="px-sm py-1 border border-outline-variant rounded-full text-label-md text-on-surface-variant hover:border-primary hover:text-primary transition-all">Verified
                                Income</button>
                            <button
                                class="px-sm py-1 border border-outline-variant rounded-full text-label-md text-on-surface-variant hover:border-primary hover:text-primary transition-all">Collateral
                                Approved</button>
                        </div>
                        <!-- Decision Actions -->
                        <div class="grid grid-cols-1 gap-sm">
                            <button
                                class="w-full bg-primary text-on-primary py-lg rounded-xl font-headline-sm shadow-md hover:scale-[1.01] active:scale-95 transition-all flex items-center justify-center gap-md">
                                <span class="material-symbols-outlined">check_circle</span> Approve Application
                            </button>
                            <div class="grid grid-cols-2 gap-sm">
                                <button
                                    class="bg-surface-container-highest text-on-surface py-md rounded-xl font-label-lg hover:bg-outline-variant transition-colors flex items-center justify-center gap-xs">
                                    <span class="material-symbols-outlined text-[20px]">info</span> Request Info
                                </button>
                                <button
                                    class="bg-error-container text-on-error-container py-md rounded-xl font-label-lg hover:bg-red-200 transition-colors flex items-center justify-center gap-xs">
                                    <span class="material-symbols-outlined text-[20px]">cancel</span> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-xl pt-lg border-t border-outline-variant flex items-center gap-sm">
                        <span class="material-symbols-outlined text-outline">history</span>
                        <p class="text-[12px] text-on-surface-variant italic">Last updated: Today at 2:45 PM by J.
                            Doe</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script>
        // Micro-interactions for the decision panel
        document.querySelectorAll('textarea').forEach(el => {
            el.addEventListener('focus', () => {
                el.parentElement.parentElement.classList.add('ring-2', 'ring-primary/20');
            });
            el.addEventListener('blur', () => {
                el.parentElement.parentElement.classList.remove('ring-2', 'ring-primary/20');
            });
        });

        // Quick tag click handling
        document.querySelectorAll('.flex-wrap button').forEach(btn => {
            btn.addEventListener('click', () => {
                const textarea = document.getElementById('decision-comments');
                const tagText = btn.textContent;
                if (!textarea.value.includes(tagText)) {
                    textarea.value += (textarea.value ? ', ' : '') + tagText;
                }
                btn.classList.add('bg-primary', 'text-white', 'border-primary');
            });
        });
    </script>
@endsection