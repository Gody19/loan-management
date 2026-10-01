{{-- Top Navigation Bar --}}
<header class="vicoba-navbar d-flex align-items-center justify-content-between px-4">

    {{-- Left: Hamburger + Search --}}
    <div class="d-flex align-items-center">
        <button class="btn btn-link text-dark d-lg-none me-2 p-1" onclick="toggleSidebar()">
            <i class="bi bi-list fs-4"></i>
        </button>

        <div class="d-none d-md-block">
            <div class="input-group input-group-sm" style="width: 280px;">
                <span class="input-group-text bg-light border-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control bg-light border-0" placeholder="Search...">
            </div>
        </div>
    </div>

    {{-- Right: Actions --}}
    <div class="d-flex align-items-center gap-2">

        {{-- Notifications --}}
        @php
            $recentNotifications = auth()->user()->notifications()->latest()->limit(5)->get();
            $unreadNotifications = $recentNotifications->filter(fn ($n) => $n->read_at === null)->count();
        @endphp
        <div class="dropdown">
            <button class="btn btn-link text-dark position-relative p-2" data-bs-toggle="dropdown">
                <i class="bi bi-bell fs-5"></i>
                @if($unreadNotifications > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                    {{ $unreadNotifications }}
                </span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end" style="width: 320px;">
                <h6 class="dropdown-header fw-semibold">Notifications</h6>
                @forelse($recentNotifications as $recentNotification)
                    <a href="{{ data_get($recentNotification->data, 'url', route('notifications.index')) }}" class="dropdown-item">
                        <div class="d-flex flex-column">
                            <span class="small fw-semibold text-truncate">{{ data_get($recentNotification->data, 'title', 'Notification') }}</span>
                            <span class="small text-muted text-truncate">{{ \Illuminate\Support\Str::limit(data_get($recentNotification->data, 'summary', ''), 60) }}</span>
                        </div>
                    </a>
                    @if(! $loop->last)
                    <div class="dropdown-divider"></div>
                    @endif
                @empty
                    <div class="dropdown-item-text text-muted text-center py-3" style="font-size: 0.85rem;">
                        No new notifications
                    </div>
                @endforelse
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-center small" href="{{ route('notifications.index') }}">View all notifications</a>
            </div>
        </div>

        {{-- User Dropdown --}}
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
                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
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
