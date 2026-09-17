@extends('layouts.landing')

@section('title', 'VICOBA & Microfinance Management Platform')
@section('meta-description', 'Manage VICOBA groups, members, savings, shares, welfare, loans, repayments and financial operations from one centralized platform.')

@section('content')
<x-landing.navbar />

{{-- Hero Section --}}
<section class="hero-section" id="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="trust-badge">
                    <i class="bi bi-shield-check"></i>
                    Trusted by VICOBA & Microfinance Organizations
                </div>
                <h1 class="display-5 fw-bold mb-3" style="letter-spacing: -0.03em; line-height: 1.15;">
                    Manage Your VICOBA.<br>Simplify Your Financial Operations.
                </h1>
                <p class="lead mb-4" style="font-size: 1.1rem; opacity: 0.9; max-width: 600px;">
                    A secure and modern platform for organizations to manage VICOBA groups, members, savings, shares, welfare funds, loans, repayments and financial operations from one centralized system.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ route('register-organization') }}" class="btn btn-landing-primary btn-lg">
                        <i class="bi bi-building me-2"></i>Register Your Organization
                    </a>
                    <a href="{{ route('login') }}" class="btn btn-landing-outline btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <div class="p-4">
                    <div class="bg-white bg-opacity-10 rounded-4 p-4" style="backdrop-filter: blur(10px);">
                        <div class="row g-3 text-center">
                            <div class="col-4">
                                <div class="bg-white bg-opacity-10 rounded-3 p-3"><i class="bi bi-people fs-3 text-white"></i></div>
                                <small class="text-white-50 mt-2 d-block">Members</small>
                            </div>
                            <div class="col-4">
                                <div class="bg-white bg-opacity-10 rounded-3 p-3"><i class="bi bi-wallet2 fs-3 text-white"></i></div>
                                <small class="text-white-50 mt-2 d-block">Savings</small>
                            </div>
                            <div class="col-4">
                                <div class="bg-white bg-opacity-10 rounded-3 p-3"><i class="bi bi-cash-coin fs-3 text-white"></i></div>
                                <small class="text-white-50 mt-2 d-block">Loans</small>
                            </div>
                            <div class="col-4">
                                <div class="bg-white bg-opacity-10 rounded-3 p-3"><i class="bi bi-cash-stack fs-3 text-white"></i></div>
                                <small class="text-white-50 mt-2 d-block">Shares</small>
                            </div>
                            <div class="col-4">
                                <div class="bg-white bg-opacity-10 rounded-3 p-3"><i class="bi bi-heart fs-3 text-white"></i></div>
                                <small class="text-white-50 mt-2 d-block">Welfare</small>
                            </div>
                            <div class="col-4">
                                <div class="bg-white bg-opacity-10 rounded-3 p-3"><i class="bi bi-journal-text fs-3 text-white"></i></div>
                                <small class="text-white-50 mt-2 d-block">Accounting</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Value Proposition --}}
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="d-flex align-items-start">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-folder2-open"></i></div>
                    <div>
                        <h5 class="fw-bold mb-2">Organized</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage members, groups, branches and financial activities from one platform.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start">
                    <div class="feature-icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-eye"></i></div>
                    <div>
                        <h5 class="fw-bold mb-2">Transparent</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Maintain clear transaction records, loan schedules, repayments and financial reports.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start">
                    <div class="feature-icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-shield-lock"></i></div>
                    <div>
                        <h5 class="fw-bold mb-2">Secure</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Role-based access control and tenant isolation help protect organizational data.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Features Section --}}
<section class="py-5" id="features">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading display-6 fw-bold mb-3">Platform Features</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Everything you need to manage VICOBA groups, financial operations and member activities in one platform.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-diagram-3"></i></div>
                    <h5 class="fw-bold mb-2">VICOBA Management</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Create and manage VICOBA groups, branches and organizational structures.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people"></i></div>
                    <h5 class="fw-bold mb-2">Member Management</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Register members, manage membership information and monitor member status.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-wallet2"></i></div>
                    <h5 class="fw-bold mb-2">Savings Management</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Track member savings, deposits, withdrawals and balances.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-info bg-opacity-10 text-info"><i class="bi bi-cash-stack"></i></div>
                    <h5 class="fw-bold mb-2">Shares Management</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage shares, share ownership and share transactions.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-heart"></i></div>
                    <h5 class="fw-bold mb-2">Welfare Management</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage welfare contributions and welfare-related financial activities.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-coin"></i></div>
                    <h5 class="fw-bold mb-2">Loan Management</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Create loan products, manage applications, approvals, guarantors, disbursements and repayment schedules.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-success bg-opacity-10 text-success"><i class="bi bi-calendar-check"></i></div>
                    <h5 class="fw-bold mb-2">Repayment &amp; Collections</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Track repayments, allocations, outstanding balances and delinquency.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-journal-text"></i></div>
                    <h5 class="fw-bold mb-2">Financial Accounting</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Double-entry accounting with journals, ledgers, trial balance and financial statements.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-info bg-opacity-10 text-info"><i class="bi bi-bar-chart-line"></i></div>
                    <h5 class="fw-bold mb-2">Reports</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Financial and operational information for management and decision-making.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-building"></i></div>
                    <h5 class="fw-bold mb-2">Multi-Branch Organizations</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Support organizations with multiple branches and VICOBA groups.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- How It Works --}}
<section class="py-5 bg-light" id="how-it-works">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading display-6 fw-bold mb-3">How It Works</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Get started in six simple steps.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="step-number">1</div>
                    <div>
                        <h5 class="fw-bold mb-1">Register Your Organization</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Create your organization account and administrator profile.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="step-number">2</div>
                    <div>
                        <h5 class="fw-bold mb-1">Set Up Your Organization</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Create branches and VICOBA groups.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="step-number">3</div>
                    <div>
                        <h5 class="fw-bold mb-1">Add Members</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Register and manage members belonging to your VICOBA groups.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="step-number">4</div>
                    <div>
                        <h5 class="fw-bold mb-1">Configure Financial Products</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Configure savings, shares, welfare and loan plans.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="step-number">5</div>
                    <div>
                        <h5 class="fw-bold mb-1">Manage Financial Activities</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage savings, loans, repayments, collections and other financial activities.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="step-number">6</div>
                    <div>
                        <h5 class="fw-bold mb-1">Monitor Your Organization</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Use dashboards, accounting and reports to understand your financial position.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- About Section --}}
<section class="py-5" id="about">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h2 class="section-heading display-6 fw-bold mb-3">About the Platform</h2>
                <p class="text-muted mb-3" style="font-size: 1rem; line-height: 1.7;">
                    Our platform is designed to help VICOBA and financial organizations move from fragmented manual processes to a centralized digital management system.
                </p>
                <p class="text-muted mb-4" style="font-size: 1rem; line-height: 1.7;">
                    It brings member management, savings, shares, welfare, loans, repayments, collections and accounting together in one platform.
                </p>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-primary"></i>
                            <span style="font-size: 0.9rem;">Digitization</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-primary"></i>
                            <span style="font-size: 0.9rem;">Transparency</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-primary"></i>
                            <span style="font-size: 0.9rem;">Centralized Information</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-primary"></i>
                            <span style="font-size: 0.9rem;">Operational Efficiency</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-light rounded-4 p-4">
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 shadow-sm"><i class="bi bi-diagram-3 fs-2 text-primary"></i></div>
                            <small class="text-muted mt-2 d-block">Groups</small>
                        </div>
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 shadow-sm"><i class="bi bi-people fs-2 text-success"></i></div>
                            <small class="text-muted mt-2 d-block">Members</small>
                        </div>
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 shadow-sm"><i class="bi bi-wallet2 fs-2 text-warning"></i></div>
                            <small class="text-muted mt-2 d-block">Finance</small>
                        </div>
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 shadow-sm"><i class="bi bi-cash-coin fs-2 text-danger"></i></div>
                            <small class="text-muted mt-2 d-block">Loans</small>
                        </div>
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 shadow-sm"><i class="bi bi-journal-text fs-2 text-info"></i></div>
                            <small class="text-muted mt-2 d-block">Accounting</small>
                        </div>
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 shadow-sm"><i class="bi bi-bar-chart-line fs-2 text-secondary"></i></div>
                            <small class="text-muted mt-2 d-block">Reports</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Who Is It For --}}
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading display-6 fw-bold mb-3">Who Is It For?</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Built for organizations that manage group-based financial operations.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-bold mb-2">VICOBA Organizations</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage groups, members and financial activities.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto"><i class="bi bi-bank"></i></div>
                    <h5 class="fw-bold mb-2">Microfinance Organizations</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage loans, repayments, collections and accounting.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-warning bg-opacity-10 text-warning mx-auto"><i class="bi bi-piggy-bank"></i></div>
                    <h5 class="fw-bold mb-2">Cooperatives / Savings Groups</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Organize member savings, shares and financial records.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-info bg-opacity-10 text-info mx-auto"><i class="bi bi-building"></i></div>
                    <h5 class="fw-bold mb-2">Multi-Branch Organizations</h5>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage branches and groups under one organization.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Security / Trust --}}
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading display-6 fw-bold mb-3">Built with Trust</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Security and integrity are fundamental to how the platform operates.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary flex-shrink-0"><i class="bi bi-shield-lock"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Role-Based Access</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Users only receive functionality appropriate to their assigned roles.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-icon bg-success bg-opacity-10 text-success flex-shrink-0"><i class="bi bi-building"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Organization Isolation</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Organizations operate within isolated tenant boundaries.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-icon bg-warning bg-opacity-10 text-warning flex-shrink-0"><i class="bi bi-journal-check"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Financial Integrity</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Financial operations use controlled transaction workflows and double-entry accounting.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-icon bg-info bg-opacity-10 text-info flex-shrink-0"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Auditability</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Important activities can be tracked through audit records.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-icon bg-danger bg-opacity-10 text-danger flex-shrink-0"><i class="bi bi-lock"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Controlled Accounting</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Posted accounting records are protected from direct editing and use reversal workflows.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CTA Section --}}
<section class="cta-section py-5">
    <div class="container text-center">
        <h2 class="display-6 fw-bold mb-3">Ready to Digitize Your VICOBA Operations?</h2>
        <p class="mb-4 mx-auto" style="max-width: 600px; opacity: 0.9;">
            Create your organization and start managing your VICOBA groups, members and financial activities from one centralized platform.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="{{ route('register-organization') }}" class="btn btn-landing-primary btn-lg">
                <i class="bi bi-building me-2"></i>Register Your Organization
            </a>
            <a href="{{ route('login') }}" class="btn btn-landing-outline btn-lg">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </a>
        </div>
    </div>
</section>

{{-- Contact Section --}}
<section class="py-5" id="contact">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <h2 class="section-heading display-6 fw-bold mb-3">Get in Touch</h2>
                <p class="text-muted mb-4" style="font-size: 1rem; line-height: 1.7;">
                    Have questions about the platform? We would love to hear from you. Send us a message and we will respond as soon as possible.
                </p>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary flex-shrink-0"><i class="bi bi-envelope"></i></div>
                    <div>
                        <div class="fw-semibold" style="font-size: 0.9rem;">Email</div>
                        <div class="text-muted" style="font-size: 0.9rem;">info@financepro.co.tz</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="feature-icon bg-success bg-opacity-10 text-success flex-shrink-0"><i class="bi bi-telephone"></i></div>
                    <div>
                        <div class="fw-semibold" style="font-size: 0.9rem;">Phone</div>
                        <div class="text-muted" style="font-size: 0.9rem;">+255 000 000 000</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="bg-light rounded-4 p-4">
                    <form method="POST" action="#" id="contactForm">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contact_name" class="form-label fw-medium">Name</label>
                                <input type="text" class="form-control" id="contact_name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_email" class="form-label fw-medium">Email</label>
                                <input type="email" class="form-control" id="contact_email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_phone" class="form-label fw-medium">Phone</label>
                                <input type="tel" class="form-control" id="contact_phone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="contact_subject" class="form-label fw-medium">Subject</label>
                                <input type="text" class="form-control" id="contact_subject" name="subject">
                            </div>
                            <div class="col-12">
                                <label for="contact_message" class="form-label fw-medium">Message</label>
                                <textarea class="form-control" id="contact_message" name="message" rows="4" required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-cta-primary">
                                    <i class="bi bi-send me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<x-landing.footer />

@endsection
