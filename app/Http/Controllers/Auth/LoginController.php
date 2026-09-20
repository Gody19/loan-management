<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Handle a login request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $user = Auth::user();

        // Check if user is deactivated
        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account has been deactivated. Please contact support.',
            ])->onlyInput('email');
        }

        // Check if user is suspended
        if ($user->status === UserStatus::Suspended) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account has been suspended. Please contact support.',
            ])->onlyInput('email');
        }

        // Check if user is inactive
        if ($user->status === UserStatus::Inactive) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account is inactive. Please contact support.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Record login
        app(UserService::class)->recordLogin($user);

        // Redirect members to member portal, others to admin dashboard
        if ($user->member && $user->member->membership_status->value === 'active') {
            return redirect()->intended(route('member.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request): RedirectResponse
    {
        app(AuditService::class)->logLogout(auth()->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
