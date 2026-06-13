@extends('layouts.app')
@section('title', 'Profile')
@section('page-title', 'Profile')
@section('content')
    <div class="p-margin-mobile md:p-xl space-y-xl max-w-5xl mx-auto">
        <!-- Welcome Banner (Bento Style) -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-lg">
            <div
                class="md:col-span-2 bg-primary rounded-xl p-xl text-on-primary flex items-center justify-between relative overflow-hidden">
                <div class="relative z-10">
                    <h3 class="font-display-md text-display-md mb-sm">{{ Auth::user()->fullname }}</h3>
                    <p class="font-body-lg text-body-lg opacity-90">Premium Portfolio Manager • Member since 2021
                    </p>
                </div>
                <div class="absolute right-0 top-0 bottom-0 w-1/2 opacity-20 pointer-events-none">

                </div>
            </div>
            <div
                class="bg-surface-container-highest rounded-xl p-xl flex flex-col justify-center items-center text-center border border-outline-variant">
                <p class="font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider mb-xs">
                    Profile Strength</p>
                <div class="text-display-md font-bold text-primary">85%</div>
                <div class="w-full bg-surface-container-low h-2 rounded-full mt-md overflow-hidden">
                    <div class="bg-tertiary-container h-full w-[85%] rounded-full"></div>
                </div>
                <p class="font-body-sm text-body-sm mt-sm text-on-surface-variant">Complete KYC to reach 100%</p>
            </div>
        </section>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-xl">
            <!-- Left Column: Personal Info & Security -->
            <div class="lg:col-span-2 space-y-xl">
                <!-- Personal Information Card -->
                <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-outline-variant">
                    <div class="flex items-center justify-between mb-xl">
                        <h4 class="font-headline-sm text-headline-sm text-on-surface">Personal Information</h4>
                        <button
                            class="flex items-center gap-xs px-md py-sm bg-surface-container-high rounded-lg text-primary font-bold transition-all active:scale-95">
                            <span class="material-symbols-outlined text-[20px]" data-icon="edit">edit</span>
                            <span class="font-label-lg">Edit Details</span>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                        <div class="space-y-xs">
                            <label class="font-label-md text-label-md text-on-surface-variant px-xs">Full
                                Name</label>
                            <input
                                class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all"
                                readonly="" type="text" value="Alex Thompson" />
                        </div>
                        <div class="space-y-xs">
                            <label class="font-label-md text-label-md text-on-surface-variant px-xs">Email
                                Address</label>
                            <input
                                class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all"
                                readonly="" type="email" value="alex.t@financepro.com" />
                        </div>
                        <div class="space-y-xs">
                            <label class="font-label-md text-label-md text-on-surface-variant px-xs">Phone
                                Number</label>
                            <input
                                class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all"
                                readonly="" type="tel" value="+1 (555) 012-3456" />
                        </div>
                        <div class="space-y-xs">
                            <label class="font-label-md text-label-md text-on-surface-variant px-xs">Date of
                                Birth</label>
                            <input
                                class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all"
                                readonly="" type="text" value="August 24, 1988" />
                        </div>
                        <div class="md:col-span-2 space-y-xs">
                            <label class="font-label-md text-label-md text-on-surface-variant px-xs">Residential
                                Address</label>
                            <textarea
                                class="w-full px-md py-sm bg-surface-container-low border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all"
                                readonly="" rows="2">221B Baker Street, NW1 6XE, London, United Kingdom</textarea>
                        </div>
                    </div>
                </div>
                <!-- Security & Authentication -->
                <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-outline-variant">
                    <div class="flex items-center gap-sm mb-xl">
                        <span class="material-symbols-outlined text-primary" data-icon="shield">shield</span>
                        <h4 class="font-headline-sm text-headline-sm text-on-surface">Security &amp; Privacy</h4>
                    </div>
                    <div class="space-y-lg">
                        <div
                            class="flex items-center justify-between p-md bg-surface rounded-lg border border-outline-variant/30">
                            <div class="flex items-center gap-md">
                                <div
                                    class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                    <span class="material-symbols-outlined" data-icon="lock_reset">lock_reset</span>
                                </div>
                                <div>
                                    <p class="font-label-lg text-label-lg text-on-surface">Password</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">Last changed 3
                                        months ago</p>
                                </div>
                            </div>
                            <button
                                class="px-md py-sm bg-surface-container-lowest border border-outline-variant text-primary rounded-lg font-label-lg hover:bg-primary/5 transition-colors">Change</button>
                        </div>
                        <div
                            class="flex items-center justify-between p-md bg-surface rounded-lg border border-outline-variant/30">
                            <div class="flex items-center gap-md">
                                <div
                                    class="w-10 h-10 rounded-full bg-tertiary-container/10 flex items-center justify-center text-tertiary">
                                    <span class="material-symbols-outlined"
                                        data-icon="phonelink_setup">phonelink_setup</span>
                                </div>
                                <div>
                                    <p class="font-label-lg text-label-lg text-on-surface">Two-Factor Authentication
                                    </p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">Keep your account
                                        secure with 2FA</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input checked="" class="sr-only peer" type="checkbox" />
                                <div
                                    class="w-11 h-6 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Right Column: Documents -->
            <div class="space-y-xl">
                <div
                    class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-outline-variant sticky top-24">
                    <div class="flex items-center justify-between mb-xl">
                        <h4 class="font-headline-sm text-headline-sm text-on-surface">Documents</h4>
                        <button
                            class="material-symbols-outlined text-primary hover:bg-primary/10 p-sm rounded-full transition-colors"
                            data-icon="upload_file">upload_file</button>
                    </div>
                    <div class="space-y-md">
                        <!-- Document Item: Verified -->
                        <div
                            class="group p-md rounded-xl border border-outline-variant bg-surface hover:border-primary transition-all">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-md">
                                    <div
                                        class="w-12 h-12 bg-surface-container-high rounded-lg flex items-center justify-center text-primary">
                                        <span class="material-symbols-outlined text-3xl" data-icon="badge">badge</span>
                                    </div>
                                    <div>
                                        <p class="font-label-lg text-label-lg text-on-surface">Government ID</p>
                                        <p class="font-body-sm text-body-sm text-on-surface-variant">Passport • Exp:
                                            2028</p>
                                    </div>
                                </div>
                                <span
                                    class="px-sm py-1 bg-tertiary-container/10 text-tertiary text-[10px] font-bold uppercase rounded-md flex items-center gap-xs">
                                    <span class="material-symbols-outlined text-[12px]"
                                        data-icon="check_circle">check_circle</span>
                                    Verified
                                </span>
                            </div>
                            <div class="mt-md flex gap-sm opacity-0 group-hover:opacity-100 transition-opacity">
                                <button class="text-primary font-label-md hover:underline">View</button>
                                <button class="text-on-surface-variant font-label-md hover:underline">Download</button>
                            </div>
                        </div>
                        <!-- Document Item: Pending -->
                        <div
                            class="group p-md rounded-xl border border-outline-variant bg-surface hover:border-primary transition-all">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-md">
                                    <div
                                        class="w-12 h-12 bg-surface-container-high rounded-lg flex items-center justify-center text-primary">
                                        <span class="material-symbols-outlined text-3xl"
                                            data-icon="home_pin">home_pin</span>
                                    </div>
                                    <div>
                                        <p class="font-label-lg text-label-lg text-on-surface">Address Proof</p>
                                        <p class="font-body-sm text-body-sm text-on-surface-variant">Utility Bill •
                                            Uploaded 2d ago</p>
                                    </div>
                                </div>
                                <span
                                    class="px-sm py-1 bg-secondary-container/20 text-on-secondary-container text-[10px] font-bold uppercase rounded-md flex items-center gap-xs">
                                    <span class="material-symbols-outlined text-[12px]" data-icon="schedule">schedule</span>
                                    Pending
                                </span>
                            </div>
                            <div class="mt-md flex gap-sm opacity-0 group-hover:opacity-100 transition-opacity">
                                <button class="text-primary font-label-md hover:underline">Review</button>
                            </div>
                        </div>
                        <!-- Document Item: Upload CTA -->
                        <div
                            class="p-md rounded-xl border border-dashed border-outline-variant bg-surface-container-low flex flex-col items-center justify-center text-center cursor-pointer hover:bg-surface-container-high transition-colors">
                            <span class="material-symbols-outlined text-outline-variant text-4xl mb-sm"
                                data-icon="add_circle">add_circle</span>
                            <p class="font-label-lg text-label-lg text-on-surface-variant">Add Tax Document</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant/70">W-9 or local tax ID
                                proof</p>
                        </div>
                    </div>
                    <div class="mt-xl p-md bg-error-container/20 rounded-xl border border-error/20 flex gap-md">
                        <span class="material-symbols-outlined text-error" data-icon="info">info</span>
                        <p class="font-body-sm text-body-sm text-error">Employment verification is required to
                            increase your loan limit.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav
        class="md:hidden fixed bottom-0 left-0 right-0 h-16 bg-surface-container-lowest border-t border-outline-variant z-50 flex items-center justify-around px-margin-mobile">
        <a class="flex flex-col items-center gap-1 text-on-surface-variant" href="#">
            <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
            <span class="text-[10px] font-bold">Home</span>
        </a>
        <a class="flex flex-col items-center gap-1 text-on-surface-variant" href="#">
            <span class="material-symbols-outlined" data-icon="payments">payments</span>
            <span class="text-[10px] font-bold">Loans</span>
        </a>
        <div class="relative -top-6">
            <button class="h-14 w-14 rounded-full bg-primary text-on-primary shadow-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-3xl" data-icon="add">add</span>
            </button>
        </div>
        <a class="flex flex-col items-center gap-1 text-on-surface-variant" href="#">
            <span class="material-symbols-outlined" data-icon="analytics">analytics</span>
            <span class="text-[10px] font-bold">Stats</span>
        </a>
        <a class="flex flex-col items-center gap-1 text-primary" href="#">
            <span class="material-symbols-outlined" data-icon="person">person</span>
            <span class="text-[10px] font-bold">Profile</span>
        </a>
    </nav>
    <script>
        // Micro-interactions and Tab Logic
        document.querySelectorAll('a, button').forEach(el => {
            el.addEventListener('mousedown', () => {
                el.classList.add('scale-95');
            });
            el.addEventListener('mouseup', () => {
                el.classList.remove('scale-95');
            });
            el.addEventListener('mouseleave', () => {
                el.classList.remove('scale-95');
            });
        });

        // Simulating form interactions
        const editBtn = document.querySelector('button:has([data-icon="edit"])');
        editBtn.addEventListener('click', () => {
            const inputs = document.querySelectorAll('input, textarea');
            const isReadonly = inputs[0].hasAttribute('readonly');

            inputs.forEach(input => {
                if (isReadonly) {
                    input.removeAttribute('readonly');
                    input.classList.remove('bg-surface-container-low');
                    input.classList.add('bg-white', 'border-primary');
                } else {
                    input.setAttribute('readonly', 'true');
                    input.classList.add('bg-surface-container-low');
                    input.classList.remove('bg-white', 'border-primary');
                }
            });

            editBtn.innerHTML = isReadonly ?
                '<span class="material-symbols-outlined text-[20px]" data-icon="save">save</span><span class="font-label-lg">Save Changes</span>' :
                '<span class="material-symbols-outlined text-[20px]" data-icon="edit">edit</span><span class="font-label-lg">Edit Details</span>';
        });
    </script>
@endsection