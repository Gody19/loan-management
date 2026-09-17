<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicRegistrationRequest;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->check()) {
            if (auth()->user()->hasRole('VICOBA Member') && auth()->user()->member) {
                return redirect()->route('member.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return view('landing.index');
    }

    public function showRegistration(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('landing.register-organization');
    }

    public function storeRegistration(
        StorePublicRegistrationRequest $request,
        OrganizationService $organizationService
    ): RedirectResponse {
        $organization = $organizationService->createWithAdmin(
            $request->only([
                'name',
                'registration_number',
                'phone',
                'email',
                'address',
                'region',
                'district',
            ]),
            $request->only([
                'admin_name',
                'admin_email',
                'admin_phone',
                'admin_password',
            ]),
        );

        app(AuditService::class)->log(
            'organization_registration_completed',
            $organization,
            [],
            $organization->toArray()
        );

        return redirect()->route('login')
            ->with('success', 'Your organization has been registered successfully. Please sign in with your administrator credentials.');
    }
}
