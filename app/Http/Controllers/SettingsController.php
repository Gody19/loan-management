<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $settings = $user->settings ?? [];

        return view('dashboard.user.settings', compact('settings'));
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password changed successfully.');
    }

    public function updateNotifications(Request $request)
    {
        $user = auth()->user();

        $settings = array_merge($user->settings ?? [], [
            'notifications' => [
                'email_loan_updates' => $request->boolean('email_loan_updates', true),
                'email_payment_reminders' => $request->boolean('email_payment_reminders', true),
                'email_promotions' => $request->boolean('email_promotions', false),
                'sms_loan_updates' => $request->boolean('sms_loan_updates', false),
                'sms_payment_reminders' => $request->boolean('sms_payment_reminders', true),
            ],
        ]);

        $user->update(['settings' => $settings]);

        return back()->with('success', 'Notification preferences updated.');
    }
}
