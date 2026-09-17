{{-- Member Portal Top Navbar --}}
<header class="vicoba-navbar d-flex align-items-center justify-content-between px-4">

    <div class="d-flex align-items-center">
        <button class="btn btn-link text-dark d-lg-none me-2 p-1" onclick="toggleSidebar()">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="d-none d-md-block">
            <h6 class="mb-0 text-muted fw-medium" style="font-size: 0.85rem;">
                @yield('page-title', 'Member Portal')
            </h6>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="dropdown">
            <button class="btn btn-link text-dark d-flex align-items-center p-1" data-bs-toggle="dropdown">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                    <span class="text-white fw-semibold" style="font-size: 0.75rem;">
                        {{ substr(auth()->user()->fullname ?? 'U', 0, 1) }}
                    </span>
                </div>
                <span class="d-none d-md-inline text-dark fw-medium" style="font-size: 0.85rem;">
                    {{ auth()->user()->fullname ?? 'User' }}
                </span>
                <i class="bi bi-chevron-down ms-1 text-muted" style="font-size: 0.7rem;"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <span class="dropdown-item-text">
                        <div class="fw-semibold">{{ auth()->user()->fullname ?? 'User' }}</div>
                        <small class="text-muted">{{ auth()->user()->email ?? '' }}</small>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="{{ route('member.profile') }}">
                        <i class="bi bi-person me-2"></i>My Profile
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

</header>
