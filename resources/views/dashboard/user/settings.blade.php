@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="max-w-4xl mx-auto space-y-xl">
    @if(session('success'))
        <div class="p-md bg-tertiary-container/20 text-tertiary rounded-lg border border-tertiary-container/30">{{ session('success') }}</div>
    @endif

    <div class="mb-2xl">
        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-xs">Settings</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Manage your account preferences and security</p>
    </div>

    <!-- Change Password -->
    <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-outline-variant">
        <div class="flex items-center gap-sm mb-lg">
            <span class="material-symbols-outlined text-primary">lock_reset</span>
            <h4 class="font-headline-sm text-headline-sm text-on-surface">Change Password</h4>
        </div>

        <form action="{{ route('settings.password') }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-3 gap-lg">
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Current Password</label>
                    <input name="current_password" type="password" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" required />
                    @error('current_password')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">New Password</label>
                    <input name="password" type="password" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" required />
                    @error('password')<p class="text-error text-body-sm mt-xs">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-xs">
                    <label class="font-label-md text-on-surface-variant">Confirm Password</label>
                    <input name="password_confirmation" type="password" class="w-full h-12 px-md bg-surface border border-outline-variant rounded-lg text-on-surface font-body-md focus:ring-primary focus:border-primary outline-none transition-all" required />
                </div>
            </div>

            <div class="mt-lg flex justify-end">
                <button type="submit" class="px-xl py-sm bg-primary text-on-primary rounded-lg font-label-lg hover:opacity-90 transition-all flex items-center gap-sm">
                    <span class="material-symbols-outlined text-sm">lock</span> Update Password
                </button>
            </div>
        </form>
    </div>

    <!-- Notification Preferences -->
    <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-outline-variant">
        <div class="flex items-center gap-sm mb-lg">
            <span class="material-symbols-outlined text-primary">notifications</span>
            <h4 class="font-headline-sm text-headline-sm text-on-surface">Notification Preferences</h4>
        </div>

        <form action="{{ route('settings.notifications') }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="space-y-lg">
                <p class="font-label-lg text-on-surface-variant">Email Notifications</p>
                <div class="space-y-md">
                    <label class="flex items-center justify-between p-md bg-surface rounded-lg border border-outline-variant/30 cursor-pointer">
                        <div>
                            <p class="font-label-lg text-on-surface">Loan Status Updates</p>
                            <p class="text-body-sm text-on-surface-variant">Receive emails when your loan status changes</p>
                        </div>
                        <input type="hidden" name="email_loan_updates" value="0">
                        <input type="checkbox" name="email_loan_updates" value="1" @checked($settings['notifications']['email_loan_updates'] ?? true) class="w-5 h-5 text-primary border-outline-variant rounded focus:ring-primary/20" />
                    </label>
                    <label class="flex items-center justify-between p-md bg-surface rounded-lg border border-outline-variant/30 cursor-pointer">
                        <div>
                            <p class="font-label-lg text-on-surface">Payment Reminders</p>
                            <p class="text-body-sm text-on-surface-variant">Get reminded before repayment due dates</p>
                        </div>
                        <input type="hidden" name="email_payment_reminders" value="0">
                        <input type="checkbox" name="email_payment_reminders" value="1" @checked($settings['notifications']['email_payment_reminders'] ?? true) class="w-5 h-5 text-primary border-outline-variant rounded focus:ring-primary/20" />
                    </label>
                    <label class="flex items-center justify-between p-md bg-surface rounded-lg border border-outline-variant/30 cursor-pointer">
                        <div>
                            <p class="font-label-lg text-on-surface">Promotions & Offers</p>
                            <p class="text-body-sm text-on-surface-variant">Receive promotional offers and new loan products</p>
                        </div>
                        <input type="hidden" name="email_promotions" value="0">
                        <input type="checkbox" name="email_promotions" value="1" @checked($settings['notifications']['email_promotions'] ?? false) class="w-5 h-5 text-primary border-outline-variant rounded focus:ring-primary/20" />
                    </label>
                </div>

                <p class="font-label-lg text-on-surface-variant pt-lg border-t border-outline-variant">SMS Notifications</p>
                <div class="space-y-md">
                    <label class="flex items-center justify-between p-md bg-surface rounded-lg border border-outline-variant/30 cursor-pointer">
                        <div>
                            <p class="font-label-lg text-on-surface">Loan Status via SMS</p>
                            <p class="text-body-sm text-on-surface-variant">Receive SMS when your loan status changes</p>
                        </div>
                        <input type="hidden" name="sms_loan_updates" value="0">
                        <input type="checkbox" name="sms_loan_updates" value="1" @checked($settings['notifications']['sms_loan_updates'] ?? false) class="w-5 h-5 text-primary border-outline-variant rounded focus:ring-primary/20" />
                    </label>
                    <label class="flex items-center justify-between p-md bg-surface rounded-lg border border-outline-variant/30 cursor-pointer">
                        <div>
                            <p class="font-label-lg text-on-surface">SMS Payment Reminders</p>
                            <p class="text-body-sm text-on-surface-variant">Get SMS reminders before repayment due dates</p>
                        </div>
                        <input type="hidden" name="sms_payment_reminders" value="0">
                        <input type="checkbox" name="sms_payment_reminders" value="1" @checked($settings['notifications']['sms_payment_reminders'] ?? true) class="w-5 h-5 text-primary border-outline-variant rounded focus:ring-primary/20" />
                    </label>
                </div>
            </div>

            <div class="mt-lg pt-lg border-t border-outline-variant flex justify-end">
                <button type="submit" class="px-xl py-sm bg-primary text-on-primary rounded-lg font-label-lg hover:opacity-90 transition-all flex items-center gap-sm">
                    <span class="material-symbols-outlined text-sm">notifications</span> Save Preferences
                </button>
            </div>
        </form>
    </div>

    <!-- Danger Zone -->
    @if(auth()->user()->role === 'admin')
    <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-error/30">
        <div class="flex items-center gap-sm mb-lg">
            <span class="material-symbols-outlined text-error">warning</span>
            <h4 class="font-headline-sm text-headline-sm text-error">Danger Zone</h4>
        </div>
        <p class="text-body-sm text-on-surface-variant mb-lg">Admin-only destructive actions. Use with caution.</p>
        <div class="flex gap-md">
            <form action="#" method="POST" onsubmit="return confirm('Are you sure you want to clear all data?')">
                @csrf
                <button type="submit" class="px-xl py-sm bg-error text-on-error rounded-lg font-label-lg hover:opacity-90 transition-all">Clear System Data</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
