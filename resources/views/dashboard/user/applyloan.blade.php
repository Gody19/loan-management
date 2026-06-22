@extends('layouts.app')
@section('page-title', 'Apply Loan')
@section('title', 'FinancePro | Loan Application')
@section('content')
    <div class="flex-1 max-w-3xl mx-auto w-full">
        <div class="mb-xl">
            <div class="flex justify-between items-center mb-md">
                <h1 class="font-headline-md text-headline-md text-on-surface">New Loan Application</h1>
                <span class="text-label-lg text-on-surface-variant" id="step-counter">Step 1 of 3</span>
            </div>
            <div class="relative h-2 w-full bg-surface-container-highest rounded-full overflow-hidden">
                <div class="active-step-bar absolute top-0 left-0 h-full bg-primary" id="progress-indicator" style="width: 33.33%;"></div>
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

        <form action="{{ route('loans.store') }}" method="POST" id="loan-form">
            @csrf
            <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-sm p-lg md:p-xl relative overflow-hidden" id="wizard-form">
                @if ($errors->any())
                    <div class="mb-lg p-md bg-error-container text-error rounded-lg">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li class="text-body-sm">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="step-content space-y-lg" id="step-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Desired Loan Amount (TZS)</label>
                            <div class="relative">
                                <span class="absolute left-md top-1/2 -translate-y-1/2 text-on-surface-variant font-bold">TZS</span>
                                <input name="amount" class="w-full pl-xl pr-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all" id="loan-amount" placeholder="Enter amount" type="number" value="{{ old('amount', 25000) }}" required />
                            </div>
                        </div>
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Repayment Period</label>
                            <select name="tenure_months" class="w-full px-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all appearance-none" id="loan-period" required>
                                <option value="12">12 Months (1 Year)</option>
                                <option selected value="24">24 Months (2 Years)</option>
                                <option value="36">36 Months (3 Years)</option>
                                <option value="60">60 Months (5 Years)</option>
                            </select>
                        </div>
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Loan Purpose</label>
                            <input name="purpose" class="w-full px-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all" id="loan-purpose" placeholder="e.g. Inventory purchase" type="text" value="{{ old('purpose') }}" required />
                        </div>
                        <div class="space-y-sm">
                            <label class="font-label-lg text-on-surface-variant">Monthly Income (TZS)</label>
                            <input name="monthly_income" class="w-full px-md py-sm bg-surface border border-outline-variant rounded-lg font-body-md focus:border-primary transition-all" placeholder="Enter monthly income" type="number" value="{{ old('monthly_income') }}" />
                        </div>
                    </div>
                    <div class="p-lg bg-surface-container rounded-xl border border-outline-variant/50">
                        <div class="flex items-start gap-md">
                            <span class="material-symbols-outlined text-primary">info</span>
                            <div class="space-y-xs">
                                <p class="font-label-lg text-on-surface">Interest Rate Estimate</p>
                                <p class="text-body-sm text-on-surface-variant">Based on your current profile, your starting rate is estimated at <span class="font-bold text-primary">8.5% APR</span>. Final rates are determined after document review.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-content hidden space-y-lg" id="step-2">
                    <div class="space-y-sm">
                        <h3 class="font-headline-sm text-headline-sm">Verify Identity</h3>
                        <p class="text-body-md text-on-surface-variant">Please upload clear copies of the following documents to proceed with your application.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-md">
                        <div class="group border-2 border-dashed border-outline-variant rounded-xl p-xl flex flex-col items-center justify-center gap-sm hover:border-primary hover:bg-primary/5 transition-all cursor-pointer">
                            <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined">badge</span>
                            </div>
                            <div class="text-center">
                                <p class="font-label-lg text-on-surface">Government Issued ID</p>
                                <p class="text-body-sm text-on-surface-variant">Upload Passport or Driver's License (Max 5MB)</p>
                            </div>
                        </div>
                        <div class="group border-2 border-dashed border-outline-variant rounded-xl p-xl flex flex-col items-center justify-center gap-sm hover:border-primary hover:bg-primary/5 transition-all cursor-pointer">
                            <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined">description</span>
                            </div>
                            <div class="text-center">
                                <p class="font-label-lg text-on-surface">Proof of Income</p>
                                <p class="text-body-sm text-on-surface-variant">Last 3 months payslips or bank statements</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-md p-md bg-tertiary-container/10 border border-tertiary-container/20 rounded-lg">
                        <span class="material-symbols-outlined text-tertiary">lock</span>
                        <p class="text-body-sm text-tertiary">All documents are encrypted and stored securely following PCI-DSS standards.</p>
                    </div>
                </div>

                <div class="step-content hidden space-y-lg" id="step-3">
                    <div class="bg-primary text-on-primary p-xl rounded-2xl relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-label-lg opacity-80 mb-xs">Estimated Monthly Payment</p>
                            <h2 class="font-display-md text-display-md" id="summary-monthly">TZS 0</h2>
                            <div class="flex gap-xl mt-lg">
                                <div>
                                    <p class="text-label-md opacity-80">Principal</p>
                                    <p class="font-headline-sm" id="summary-principal">TZS 0</p>
                                </div>
                                <div>
                                    <p class="text-label-md opacity-80">Interest Rate</p>
                                    <p class="font-headline-sm">8.5% APR</p>
                                </div>
                                <div>
                                    <p class="text-label-md opacity-80">Term</p>
                                    <p class="font-headline-sm" id="summary-term">24 Months</p>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                        <div class="absolute -left-16 -top-16 w-48 h-48 bg-white/5 rounded-full blur-2xl"></div>
                    </div>
                    <div class="space-y-md">
                        <h4 class="font-label-lg text-on-surface-variant border-b border-outline-variant pb-xs">Application Details</h4>
                        <div class="grid grid-cols-2 gap-y-md">
                            <div>
                                <p class="text-label-md text-on-surface-variant">Full Name</p>
                                <p class="font-body-md text-on-surface">{{ auth()->user()->fullname }}</p>
                            </div>
                            <div>
                                <p class="text-label-md text-on-surface-variant">Email Address</p>
                                <p class="font-body-md text-on-surface">{{ auth()->user()->email }}</p>
                            </div>
                            <div>
                                <p class="text-label-md text-on-surface-variant">Loan Type</p>
                                <p class="font-body-md text-on-surface" id="summary-type">Personal Loan</p>
                            </div>
                            <div>
                                <p class="text-label-md text-on-surface-variant">Application ID</p>
                                <p class="font-body-md text-on-surface">#AUTO-GENERATED</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-md mt-lg">
                        <input class="mt-1 w-5 h-5 text-primary border-outline-variant rounded focus:ring-primary/20" id="terms" type="checkbox" required />
                        <label class="text-body-sm text-on-surface-variant" for="terms">
                            I confirm that the information provided is accurate and I agree to the <a class="text-primary font-bold" href="#">Terms of Service</a> and <a class="text-primary font-bold" href="#">Privacy Policy</a>.
                        </label>
                    </div>
                </div>

                <div class="flex justify-between items-center mt-xl pt-lg border-t border-outline-variant">
                    <button type="button" class="invisible px-lg py-sm border border-outline-variant text-on-surface-variant font-label-lg rounded-lg hover:bg-surface-container transition-all flex items-center gap-sm" id="prev-btn">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back
                    </button>
                    <button type="button" class="px-xl py-sm bg-primary text-on-primary font-label-lg rounded-lg hover:shadow-lg hover:shadow-primary/20 active:scale-95 transition-all flex items-center gap-sm" id="next-btn">
                        Next <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                    <button type="submit" class="hidden px-xl py-sm bg-tertiary text-on-tertiary font-label-lg rounded-lg hover:shadow-lg hover:shadow-tertiary/20 active:scale-95 transition-all flex items-center gap-sm" id="submit-btn">
                        Submit Application <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-xl grid grid-cols-1 md:grid-cols-3 gap-md">
            <div class="p-md bg-surface-container-low rounded-xl border border-outline-variant flex gap-md items-center">
                <span class="material-symbols-outlined text-primary">speed</span>
                <div class="flex-1">
                    <p class="font-label-md text-on-surface">Fast Approval</p>
                    <p class="text-body-sm text-on-surface-variant leading-tight">Decisions in < 24h</p>
                </div>
            </div>
            <div class="p-md bg-surface-container-low rounded-xl border border-outline-variant flex gap-md items-center">
                <span class="material-symbols-outlined text-primary">verified_user</span>
                <div class="flex-1">
                    <p class="font-label-md text-on-surface">Safe & Secure</p>
                    <p class="text-body-sm text-on-surface-variant leading-tight">Bank-grade encryption</p>
                </div>
            </div>
            <div class="p-md bg-surface-container-low rounded-xl border border-outline-variant flex gap-md items-center">
                <span class="material-symbols-outlined text-primary">payments</span>
                <div class="flex-1">
                    <p class="font-label-md text-on-surface">Flexible Terms</p>
                    <p class="text-body-sm text-on-surface-variant leading-tight">Customized for you</p>
                </div>
            </div>
        </div>
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
            document.querySelectorAll('.step-content').forEach((el, index) => {
                el.classList.toggle('hidden', index + 1 !== currentStep);
            });

            progressBar.style.width = (currentStep / totalSteps) * 100 + '%';
            stepCounter.innerText = `Step ${currentStep} of ${totalSteps}`;

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
            const rate = 0.085 / 12;

            const monthlyPayment = amount * (rate * Math.pow(1 + rate, months)) / (Math.pow(1 + rate, months) - 1);

            document.getElementById('summary-monthly').innerText = amount > 0
                ? 'TZS ' + Math.round(monthlyPayment).toLocaleString()
                : 'TZS 0';

            document.getElementById('summary-principal').innerText = 'TZS ' + Math.round(amount).toLocaleString();
            document.getElementById('summary-term').innerText = months + ' Months';
            document.getElementById('summary-type').innerText = 'Personal Loan';
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

        document.getElementById('loan-amount').addEventListener('input', calculateSummary);
        document.getElementById('loan-period').addEventListener('change', calculateSummary);
    </script>
@endsection
