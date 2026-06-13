@extends('layouts.app')
@section('scripts')
@section('page-title','Apply Loan')
@section('title','FinancePro|Loan Application')

@section('content')
        <!-- Main Content Area -->
        <div class="flex-1 max-w-3xl mx-auto w-full">
            <!-- Progress Bar Section -->
            <div class="mb-xl">
                <div class="flex justify-between items-center mb-md">
                    <h1 class="font-headline-md text-headline-md text-on-surface">New Loan Application</h1>
                    <span class="text-label-lg text-on-surface-variant" id="step-counter">Step 1 of 3</span>
                </div>
                <div class="relative h-2 w-full bg-surface-container-highest rounded-full overflow-hidden">
                    <div class="active-step-bar absolute top-0 left-0 h-full bg-primary" id="progress-indicator"
                        style="width: 33.33%;"></div>
                </div>
                <div class="flex justify-between mt-sm">
                    <div class="flex flex-col items-center">
                        <span class="text-label-md font-bold text-primary">Loan Info</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-label-md text-outline" id="step-label-2">Documents</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-label-md text-outline" id="step-label-3">Review</span>
                    </div>
                </div>
            </div>
            <!-- Form Container -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-sm p-lg md:p-xl relative overflow-hidden"
                id="wizard-form">
                <!-- Step 1: Loan Information -->
                <div class="step-content space-y-lg" id="step-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Desired Loan Amount (USD)</label>
                            <div class="relative">
                                <span class="absolute left-md top-1/2 -translate-y-1/2 text-on-surface-variant">$</span>
                                <input
                                    class="w-full pl-xl pr-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all"
                                    id="loan-amount" placeholder="Enter amount" type="number" value="25000" />
                            </div>
                        </div>
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Loan Type</label>
                            <select
                                class="w-full px-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all appearance-none"
                                id="loan-type">
                                <option value="personal">Personal Loan</option>
                                <option value="business">Business Expansion</option>
                                <option value="mortgage">Real Estate / Mortgage</option>
                                <option value="auto">Auto Financing</option>
                            </select>
                        </div>
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Repayment Period</label>
                            <select
                                class="w-full px-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all"
                                id="loan-period">
                                <option value="12">12 Months (1 Year)</option>
                                <option selected="" value="24">24 Months (2 Years)</option>
                                <option value="36">36 Months (3 Years)</option>
                                <option value="60">60 Months (5 Years)</option>
                            </select>
                        </div>
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Loan Purpose</label>
                            <input
                                class="w-full px-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all"
                                placeholder="e.g. Inventory purchase" type="text" />
                        </div>
                    </div>
                    <div class="p-lg bg-surface-container rounded-xl border border-outline-variant/50">
                        <div class="flex items-start gap-md">
                            <span class="material-symbols-outlined text-primary">info</span>
                            <div class="space-y-xs">
                                <p class="font-label-lg text-on-surface">Interest Rate Estimate</p>
                                <p class="text-body-sm text-on-surface-variant">Based on your current profile, your
                                    starting rate is estimated at <span class="font-bold text-primary">4.5% APR</span>.
                                    Final rates are determined after document review.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Step 2: Document Upload (Hidden initially) -->
                <div class="step-content hidden space-y-lg" id="step-2">
                    <div class="space-y-sm">
                        <h3 class="font-headline-sm text-headline-sm">Verify Identity</h3>
                        <p class="text-body-md text-on-surface-variant">Please upload clear copies of the following
                            documents to proceed with your application.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-md">
                        <!-- Upload Slot 1 -->
                        <div
                            class="group border-2 border-dashed border-outline-variant rounded-xl p-xl flex flex-col items-center justify-center gap-sm hover:border-primary hover:bg-primary/5 transition-all cursor-pointer">
                            <div
                                class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined">badge</span>
                            </div>
                            <div class="text-center">
                                <p class="font-label-lg text-on-surface">Government Issued ID</p>
                                <p class="text-body-sm text-on-surface-variant">Upload Passport or Driver's License (Max
                                    5MB)</p>
                            </div>
                        </div>
                        <!-- Upload Slot 2 -->
                        <div
                            class="group border-2 border-dashed border-outline-variant rounded-xl p-xl flex flex-col items-center justify-center gap-sm hover:border-primary hover:bg-primary/5 transition-all cursor-pointer">
                            <div
                                class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined">description</span>
                            </div>
                            <div class="text-center">
                                <p class="font-label-lg text-on-surface">Proof of Income</p>
                                <p class="text-body-sm text-on-surface-variant">Last 3 months payslips or bank
                                    statements</p>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-md p-md bg-tertiary-container/10 border border-tertiary-container/20 rounded-lg">
                        <span class="material-symbols-outlined text-tertiary">lock</span>
                        <p class="text-body-sm text-tertiary">All documents are encrypted and stored securely following
                            PCI-DSS standards.</p>
                    </div>
                </div>
                <!-- Step 3: Summary Review (Hidden initially) -->
                <div class="step-content hidden space-y-lg" id="step-3">
                    <div class="bg-primary text-on-primary p-xl rounded-2xl relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-label-lg opacity-80 mb-xs">Estimated Monthly Payment</p>
                            <h2 class="font-display-md text-display-md" id="summary-monthly">$1,091.24</h2>
                            <div class="flex gap-xl mt-lg">
                                <div>
                                    <p class="text-label-md opacity-80">Principal</p>
                                    <p class="font-headline-sm" id="summary-principal">$25,000</p>
                                </div>
                                <div>
                                    <p class="text-label-md opacity-80">Interest Rate</p>
                                    <p class="font-headline-sm">4.5% APR</p>
                                </div>
                                <div>
                                    <p class="text-label-md opacity-80">Term</p>
                                    <p class="font-headline-sm" id="summary-term">24 Months</p>
                                </div>
                            </div>
                        </div>
                        <!-- Abstract Visual Pattern -->
                        <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                        <div class="absolute -left-16 -top-16 w-48 h-48 bg-white/5 rounded-full blur-2xl"></div>
                    </div>
                    <div class="space-y-md">
                        <h4 class="font-label-lg text-on-surface-variant border-b border-outline-variant pb-xs">
                            Application Details</h4>
                        <div class="grid grid-cols-2 gap-y-md">
                            <div>
                                <p class="text-label-md text-on-surface-variant">Full Name</p>
                                <p class="font-body-md text-on-surface">Alex Carter</p>
                            </div>
                            <div>
                                <p class="text-label-md text-on-surface-variant">Email Address</p>
                                <p class="font-body-md text-on-surface">alex.c@financepro.com</p>
                            </div>
                            <div>
                                <p class="text-label-md text-on-surface-variant">Loan Type</p>
                                <p class="font-body-md text-on-surface" id="summary-type">Personal Loan</p>
                            </div>
                            <div>
                                <p class="text-label-md text-on-surface-variant">Application ID</p>
                                <p class="font-body-md text-on-surface">#FP-99201-B</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-md mt-lg">
                        <input class="mt-1 w-5 h-5 text-primary border-outline-variant rounded focus:ring-primary/20"
                            id="terms" type="checkbox" />
                        <label class="text-body-sm text-on-surface-variant" for="terms">
                            I confirm that the information provided is accurate and I agree to the <a
                                class="text-primary font-bold" href="#">Terms of Service</a> and <a
                                class="text-primary font-bold" href="#">Privacy Policy</a>.
                        </label>
                    </div>
                </div>
                <!-- Control Buttons -->
                <div class="flex justify-between items-center mt-xl pt-lg border-t border-outline-variant">
                    <button
                        class="invisible px-lg py-sm border border-outline-variant text-on-surface-variant font-label-lg rounded-lg hover:bg-surface-container transition-all flex items-center gap-sm"
                        id="prev-btn">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Back
                    </button>
                    <button
                        class="px-xl py-sm bg-primary text-on-primary font-label-lg rounded-lg hover:shadow-lg hover:shadow-primary/20 active:scale-95 transition-all flex items-center gap-sm"
                        id="next-btn">
                        Next
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                    <button
                        class="hidden px-xl py-sm bg-tertiary text-on-tertiary font-label-lg rounded-lg hover:shadow-lg hover:shadow-tertiary/20 active:scale-95 transition-all flex items-center gap-sm"
                        id="submit-btn">
                        Submit Application
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    </button>
                </div>
            </div>
            <!-- Contextual Information Card -->
            <div class="mt-xl grid grid-cols-1 md:grid-cols-3 gap-md">
                <div
                    class="p-md bg-surface-container-low rounded-xl border border-outline-variant flex gap-md items-center">
                    <span class="material-symbols-outlined text-primary">speed</span>
                    <div class="flex-1">
                        <p class="font-label-md text-on-surface">Fast Approval</p>
                        <p class="text-body-sm text-on-surface-variant leading-tight">Decisions in < 24h</p>
                    </div>
                </div>
                <div
                    class="p-md bg-surface-container-low rounded-xl border border-outline-variant flex gap-md items-center">
                    <span class="material-symbols-outlined text-primary">verified_user</span>
                    <div class="flex-1">
                        <p class="font-label-md text-on-surface">Safe & Secure</p>
                        <p class="text-body-sm text-on-surface-variant leading-tight">Bank-grade encryption</p>
                    </div>
                </div>
                <div
                    class="p-md bg-surface-container-low rounded-xl border border-outline-variant flex gap-md items-center">
                    <span class="material-symbols-outlined text-primary">payments</span>
                    <div class="flex-1">
                        <p class="font-label-md text-on-surface">Flexible Terms</p>
                        <p class="text-body-sm text-on-surface-variant leading-tight">Customized for you</p>
                    </div>
                </div>
            </div>
        </div>
    <!-- Image for Hero Background or Reference (Hidden) -->
    <div class="hidden">
        <img data-alt="A clean, professional workspace with a sleek laptop displaying a modern financial dashboard. The background is a soft-focus corporate office with bright, natural morning light coming through large windows. The overall aesthetic is minimalist and sophisticated, utilizing a color palette of whites, soft greys, and deep business blues to convey trust and technological advancement in finance."
            src="https://lh3.googleusercontent.com/aida-public/AB6AXuAlA4YzzbSC-L3ZAxHuwVZby9go_CQz89iH762LXT_1JrKr5072msPwmQXouB9bG4DMC5xAU5-_COmuUBEcwhhSk8pmjvTOADYTW10LzkbsitUGdfmlaFjwBYckMiGHT4nOInr8JS6GbIvgUS49OPjzXXnD7f4MwrtsMs53wHSPMP-bHT3kMyMGypeL8fdenxfd59RSrt-l4xSLh_CjQvo0xPy0ylqEdy8tg9GnT1pkxJhoG6zjlkfnlbEP-auxXNlMRsW291oP9WU" />
    </div>
    <script>
        let currentStep = 1;
        const totalSteps = 3;

        const nextBtn = document.getElementById('next-btn');
        const prevBtn = document.getElementById('prev-btn');
        const submitBtn = document.getElementById('submit-btn');
        const progressBar = document.getElementById('progress-indicator');
        const stepCounter = document.getElementById('step-counter');

        function updateWizard() {
            // Update Visibility
            document.querySelectorAll('.step-content').forEach((el, index) => {
                if (index + 1 === currentStep) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });

            // Update Progress Bar
            progressBar.style.width = (currentStep / totalSteps) * 100 + '%';
            stepCounter.innerText = `Step ${currentStep} of ${totalSteps}`;

            // Button Logic
            if (currentStep === 1) {
                prevBtn.classList.add('invisible');
                nextBtn.classList.remove('hidden');
                submitBtn.classList.add('hidden');
            } else if (currentStep === 2) {
                prevBtn.classList.remove('invisible');
                nextBtn.classList.remove('hidden');
                submitBtn.classList.add('hidden');
                document.getElementById('step-label-2').classList.add('text-primary', 'font-bold');
                document.getElementById('step-label-2').classList.remove('text-outline');
            } else if (currentStep === 3) {
                prevBtn.classList.remove('invisible');
                nextBtn.classList.add('hidden');
                submitBtn.classList.remove('hidden');
                document.getElementById('step-label-3').classList.add('text-primary', 'font-bold');
                document.getElementById('step-label-3').classList.remove('text-outline');
                calculateSummary();
            }
        }

        function calculateSummary() {
            const amount = parseFloat(document.getElementById('loan-amount').value) || 0;
            const months = parseInt(document.getElementById('loan-period').value) || 12;
            const rate = 0.045 / 12; // Monthly rate for 4.5% APR

            // Simple monthly payment formula: P [ i(1 + i)^n ] / [ (1 + i)^n – 1 ]
            const monthlyPayment = amount * (rate * Math.pow(1 + rate, months)) / (Math.pow(1 + rate, months) - 1);

            document.getElementById('summary-monthly').innerText = amount > 0
                ? '$' + monthlyPayment.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : '$0.00';

            document.getElementById('summary-principal').innerText = '$' + amount.toLocaleString();
            document.getElementById('summary-term').innerText = months + ' Months';

            const typeSelect = document.getElementById('loan-type');
            document.getElementById('summary-type').innerText = typeSelect.options[typeSelect.selectedIndex].text;
        }

        nextBtn.addEventListener('click', () => {
            if (currentStep < totalSteps) {
                currentStep++;
                updateWizard();
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                updateWizard();
            }
        });

        submitBtn.addEventListener('click', () => {
            const terms = document.getElementById('terms').checked;
            if (!terms) {
                alert('Please agree to the Terms of Service to continue.');
                return;
            }
            submitBtn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span> Processing...';
            setTimeout(() => {
                alert('Application submitted successfully! Redirecting to dashboard...');
                window.location.reload();
            }, 2000);
        });

        // Initialize calculations on step 1 input change
        document.getElementById('loan-amount').addEventListener('input', calculateSummary);
        document.getElementById('loan-period').addEventListener('change', calculateSummary);
    </script>
@endsection