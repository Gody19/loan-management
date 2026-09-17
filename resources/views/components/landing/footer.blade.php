{{-- Landing Page Footer --}}
<footer class="landing-footer pt-5 pb-4">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 mb-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary rounded-lg d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px;">
                        <i class="bi bi-grid-1x2-fill text-white fs-5"></i>
                    </div>
                    <div>
                        <span class="fw-semibold fs-5 text-white">FinancePro</span>
                        <small class="d-block" style="font-size: 0.65rem; line-height: 1; color: rgba(255,255,255,0.5);">VICOBA System</small>
                    </div>
                </div>
                <p class="mb-0" style="font-size: 0.9rem; max-width: 320px;">
                    A modern platform for managing VICOBA groups, members, savings, shares, welfare, loans and financial operations.
                </p>
            </div>

            <div class="col-6 col-md-2">
                <h6 class="text-white fw-semibold mb-3" style="font-size: 0.85rem;">Platform</h6>
                <ul class="list-unstyled" style="font-size: 0.85rem;">
                    <li class="mb-2"><a href="{{ route('home') }}">Home</a></li>
                    <li class="mb-2"><a href="#about">About</a></li>
                    <li class="mb-2"><a href="#features">Features</a></li>
                    <li class="mb-2"><a href="#how-it-works">How It Works</a></li>
                </ul>
            </div>

            <div class="col-6 col-md-2">
                <h6 class="text-white fw-semibold mb-3" style="font-size: 0.85rem;">Account</h6>
                <ul class="list-unstyled" style="font-size: 0.85rem;">
                    <li class="mb-2"><a href="{{ route('login') }}">Login</a></li>
                    <li class="mb-2"><a href="{{ route('register-organization') }}">Register Organization</a></li>
                </ul>
            </div>

            <div class="col-6 col-md-2">
                <h6 class="text-white fw-semibold mb-3" style="font-size: 0.85rem;">Support</h6>
                <ul class="list-unstyled" style="font-size: 0.85rem;">
                    <li class="mb-2"><a href="#contact">Contact</a></li>
                </ul>
            </div>
        </div>

        <hr style="border-color: rgba(255,255,255,0.1);">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center" style="font-size: 0.8rem;">
            <div>&copy; {{ date('Y') }} FinancePro VICOBA System. All rights reserved.</div>
            <div class="mt-2 mt-md-0">
                Built with care for VICOBA & Microfinance organizations.
            </div>
        </div>
    </div>
</footer>
