{{-- Landing Page Navbar --}}
<nav class="landing-nav fixed-top" id="landingNav">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between py-3">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="d-flex align-items-center text-decoration-none">
                <div class="bg-primary rounded-lg d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px;">
                    <i class="bi bi-grid-1x2-fill text-white fs-5"></i>
                </div>
                <div>
                    <span class="fw-semibold fs-5 text-dark">FinancePro</span>
                    <small class="text-muted d-block" style="font-size: 0.65rem; line-height: 1;">VICOBA System</small>
                </div>
            </a>

            {{-- Desktop Nav --}}
            <div class="d-none d-lg-flex align-items-center gap-4">
                <a href="#features" class="text-dark text-decoration-none fw-medium" style="font-size: 0.9rem;">Features</a>
                <a href="#how-it-works" class="text-dark text-decoration-none fw-medium" style="font-size: 0.9rem;">How It Works</a>
                <a href="#about" class="text-dark text-decoration-none fw-medium" style="font-size: 0.9rem;">About</a>
                <a href="#testimonials" class="text-dark text-decoration-none fw-medium" style="font-size: 0.9rem;">Testimonials</a>
                <a href="#faq" class="text-dark text-decoration-none fw-medium" style="font-size: 0.9rem;">FAQ</a>
                <a href="#contact" class="text-dark text-decoration-none fw-medium" style="font-size: 0.9rem;">Contact</a>
            </div>

            {{-- Desktop Actions --}}
            <div class="d-none d-lg-flex align-items-center gap-2">
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm px-3">Login</a>
                <a href="{{ route('register-organization') }}" class="btn btn-primary btn-sm px-3">Register Your Organization</a>
            </div>

            {{-- Mobile Hamburger --}}
            <button class="btn btn-link text-dark d-lg-none p-1" onclick="toggleMobileNav()" aria-label="Toggle navigation">
                <i class="bi bi-list fs-4" id="navIcon"></i>
            </button>
        </div>

        {{-- Mobile Nav --}}
        <div class="d-lg-none d-none pb-3" id="mobileNav">
            <div class="border-top pt-3 mt-1">
                <a href="#features" class="d-block py-2 text-dark text-decoration-none" onclick="closeMobileNav()">Features</a>
                <a href="#how-it-works" class="d-block py-2 text-dark text-decoration-none" onclick="closeMobileNav()">How It Works</a>
                <a href="#about" class="d-block py-2 text-dark text-decoration-none" onclick="closeMobileNav()">About</a>
                <a href="#testimonials" class="d-block py-2 text-dark text-decoration-none" onclick="closeMobileNav()">Testimonials</a>
                <a href="#faq" class="d-block py-2 text-dark text-decoration-none" onclick="closeMobileNav()">FAQ</a>
                <a href="#contact" class="d-block py-2 text-dark text-decoration-none" onclick="closeMobileNav()">Contact</a>
                <hr class="my-2">
                <a href="{{ route('login') }}" class="btn btn-outline-primary w-100 mb-2">Login</a>
                <a href="{{ route('register-organization') }}" class="btn btn-primary w-100">Register Your Organization</a>
            </div>
        </div>
    </div>
</nav>

<script>
    function toggleMobileNav() {
        const nav = document.getElementById('mobileNav');
        const icon = document.getElementById('navIcon');
        nav.classList.toggle('d-none');
        icon.classList.toggle('bi-list');
        icon.classList.toggle('bi-x-lg');
    }

    function closeMobileNav() {
        const nav = document.getElementById('mobileNav');
        const icon = document.getElementById('navIcon');
        nav.classList.add('d-none');
        icon.classList.add('bi-list');
        icon.classList.remove('bi-x-lg');
    }

    window.addEventListener('scroll', function() {
        const nav = document.getElementById('landingNav');
        if (window.scrollY > 10) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });
</script>
