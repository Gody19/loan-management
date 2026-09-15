{{-- Sidebar Navigation --}}
<aside class="vicoba-sidebar" id="sidebar">

    {{-- Sidebar Header --}}
    <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom border-secondary border-opacity-25">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
            <div class="bg-primary rounded-2 d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px;">
                <i class="bi bi-grid-1x2-fill text-white fs-5"></i>
            </div>
            <div class="sidebar-brand">
                <span class="text-white fw-semibold fs-6">FinancePro</span>
                <small class="text-white-50 d-block" style="font-size: 0.65rem;">VICOBA System</small>
            </div>
        </a>
        <button class="btn btn-sm btn-link text-white-50 d-lg-none" onclick="toggleSidebar()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="mt-2">

        {{-- Main Section --}}
        <div class="sidebar-section">Main</div>

        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        {{-- Members Section --}}
        <div class="sidebar-section mt-3">Members</div>

        <a href="#" class="nav-link">
            <i class="bi bi-people"></i>
            <span>Members</span>
        </a>

        <a href="#" class="nav-link">
            <i class="bi bi-person-plus"></i>
            <span>Register Member</span>
        </a>

        {{-- VICOBA Groups --}}
        <div class="sidebar-section mt-3">Groups</div>

        <a href="#" class="nav-link">
            <i class="bi bi-diagram-3"></i>
            <span>VICOBA Groups</span>
        </a>

        <a href="#" class="nav-link">
            <i class="bi bi-building"></i>
            <span>Branches</span>
        </a>

        {{-- Financial Section --}}
        <div class="sidebar-section mt-3">Finance</div>

        <a href="#" class="nav-link">
            <i class="bi bi-wallet2"></i>
            <span>Savings</span>
        </a>

        <a href="#" class="nav-link">
            <i class="bi bi-cash-stack"></i>
            <span>Shares</span>
        </a>

        <a href="#" class="nav-link">
            <i class="bi bi-cash-coin"></i>
            <span>Loans</span>
        </a>

        <a href="#" class="nav-link">
            <i class="bi bi-calculator"></i>
            <span>Accounting</span>
        </a>

        {{-- Operations Section --}}
        <div class="sidebar-section mt-3">Operations</div>

        <a href="#" class="nav-link">
            <i class="bi bi-calendar-check"></i>
            <span>Meetings</span>
        </a>

        <a href="#" class="nav-link">
            <i class="bi bi-clipboard-data"></i>
            <span>Reports</span>
        </a>

        {{-- Administration Section --}}
        <div class="sidebar-section mt-3">Administration</div>

        <a href="#" class="nav-link">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>

        @if(auth()->check() && auth()->user()->can('user.view'))
        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i>
            <span>Users</span>
        </a>
        @endif

        @if(auth()->check() && auth()->user()->can('role.view'))
        <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock"></i>
            <span>Roles</span>
        </a>
        @endif

        @if(auth()->check() && auth()->user()->can('permission.view'))
        <a href="{{ route('permissions.index') }}" class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
            <i class="bi bi-key"></i>
            <span>Permissions</span>
        </a>
        @endif

    </nav>

    {{-- Sidebar Footer --}}
    <div class="mt-auto p-3 border-top border-secondary border-opacity-25">
        <div class="d-flex align-items-center">
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                <span class="text-white fw-semibold" style="font-size: 0.75rem;">
                    {{ substr(auth()->user()->fullname ?? 'U', 0, 1) }}
                </span>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="text-white fw-medium small text-truncate">{{ auth()->user()->fullname ?? 'User' }}</div>
                <div class="text-white-50" style="font-size: 0.65rem;">
                    {{ auth()->user()->roles->pluck('name')->first() ?? 'No Role' }}
                </div>
            </div>
        </div>
    </div>

</aside>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const overlay = document.getElementById('sidebarOverlay');

        if (window.innerWidth < 992) {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('d-none');
        } else {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    }
</script>
