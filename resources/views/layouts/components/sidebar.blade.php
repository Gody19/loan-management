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
    <nav class="mt-2 flex-grow-1 overflow-auto">

        {{-- Main Section --}}
        <div class="sidebar-section">Main</div>

        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        {{-- Organization Section --}}
        <div class="sidebar-section mt-3">Organization</div>

        @if(auth()->check() && auth()->user()->can('organization.view'))
        <a href="{{ route('organizations.index') }}" class="nav-link {{ request()->routeIs('organizations.*') ? 'active' : '' }}">
            <i class="bi bi-building"></i>
            <span>Organizations</span>
        </a>
        @endif

        @if(auth()->check() && auth()->user()->can('branch.view'))
        <a href="{{ route('branches.index') }}" class="nav-link {{ request()->routeIs('branches.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3"></i>
            <span>Branches</span>
        </a>
        @endif

        @if(auth()->check() && auth()->user()->can('group.view'))
        <a href="{{ route('vicoba-groups.index') }}" class="nav-link {{ request()->routeIs('vicoba-groups.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>VICOBA Groups</span>
        </a>
        @endif

        {{-- Members Section --}}
        <div class="sidebar-section mt-3">Members</div>

        @if(auth()->check() && auth()->user()->can('member.view'))
        <a href="{{ route('members.index') }}" class="nav-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
            <i class="bi bi-person-plus"></i>
            <span>Members</span>
        </a>
        @endif

        {{-- Financial Section --}}
        <div class="sidebar-section mt-3">Finance</div>

        {{-- Savings Sub-menu --}}
        @if(auth()->check() && (auth()->user()->can('savings_product.view') || auth()->user()->can('savings_account.view') || auth()->user()->can('savings_transaction.view')))
        <div class="nav-parent">
            <a href="#" class="nav-link nav-toggle {{ request()->routeIs('savings-*') ? 'active' : '' }}" onclick="toggleSubmenu(event, 'submenu-savings')">
                <i class="bi bi-wallet2"></i>
                <span>Savings</span>
                <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
            </a>
            <div class="nav-submenu" id="submenu-savings" style="{{ request()->routeIs('savings-*') ? 'display:block;' : '' }}">
                @if(auth()->user()->can('savings_product.view'))
                <a href="{{ route('savings-products.index') }}" class="nav-link sub-link {{ request()->routeIs('savings-products.*') ? 'active' : '' }}">
                    <span>Savings Plans</span>
                </a>
                @endif
                @if(auth()->user()->can('savings_account.view'))
                <a href="{{ route('savings-accounts.index') }}" class="nav-link sub-link {{ request()->routeIs('savings-accounts.*') ? 'active' : '' }}">
                    <span>Savings Accounts</span>
                </a>
                @endif
                @if(auth()->user()->can('savings_transaction.view'))
                <a href="{{ route('savings-transactions.index') }}" class="nav-link sub-link {{ request()->routeIs('savings-transactions.*') ? 'active' : '' }}">
                    <span>Savings Transactions</span>
                </a>
                @endif
            </div>
        </div>
        @endif

        {{-- Shares Sub-menu --}}
        @if(auth()->check() && (auth()->user()->can('share_product.view') || auth()->user()->can('share_account.view') || auth()->user()->can('share_transaction.view')))
        <div class="nav-parent">
            <a href="#" class="nav-link nav-toggle {{ request()->routeIs('share-*') ? 'active' : '' }}" onclick="toggleSubmenu(event, 'submenu-shares')">
                <i class="bi bi-cash-stack"></i>
                <span>Shares</span>
                <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
            </a>
            <div class="nav-submenu" id="submenu-shares" style="{{ request()->routeIs('share-*') ? 'display:block;' : '' }}">
                @if(auth()->user()->can('share_product.view'))
                <a href="{{ route('share-products.index') }}" class="nav-link sub-link {{ request()->routeIs('share-products.*') ? 'active' : '' }}">
                    <span>Share Plans</span>
                </a>
                @endif
                @if(auth()->user()->can('share_account.view'))
                <a href="{{ route('share-accounts.index') }}" class="nav-link sub-link {{ request()->routeIs('share-accounts.*') ? 'active' : '' }}">
                    <span>Share Accounts</span>
                </a>
                @endif
                @if(auth()->user()->can('share_transaction.view'))
                <a href="{{ route('share-transactions.index') }}" class="nav-link sub-link {{ request()->routeIs('share-transactions.*') ? 'active' : '' }}">
                    <span>Share Transactions</span>
                </a>
                @endif
            </div>
        </div>
        @endif

        {{-- Welfare Sub-menu --}}
        @if(auth()->check() && (auth()->user()->can('welfare_fund.view') || auth()->user()->can('welfare_account.view') || auth()->user()->can('welfare_transaction.view')))
        <div class="nav-parent">
            <a href="#" class="nav-link nav-toggle {{ request()->routeIs('welfare-*') ? 'active' : '' }}" onclick="toggleSubmenu(event, 'submenu-welfare')">
                <i class="bi bi-heart"></i>
                <span>Welfare</span>
                <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
            </a>
            <div class="nav-submenu" id="submenu-welfare" style="{{ request()->routeIs('welfare-*') ? 'display:block;' : '' }}">
                @if(auth()->user()->can('welfare_fund.view'))
                <a href="{{ route('welfare-funds.index') }}" class="nav-link sub-link {{ request()->routeIs('welfare-funds.*') ? 'active' : '' }}">
                    <span>Welfare Funds</span>
                </a>
                @endif
                @if(auth()->user()->can('welfare_account.view'))
                <a href="{{ route('welfare-accounts.index') }}" class="nav-link sub-link {{ request()->routeIs('welfare-accounts.*') ? 'active' : '' }}">
                    <span>Welfare Accounts</span>
                </a>
                @endif
                @if(auth()->user()->can('welfare_transaction.view'))
                <a href="{{ route('welfare-transactions.index') }}" class="nav-link sub-link {{ request()->routeIs('welfare-transactions.*') ? 'active' : '' }}">
                    <span>Welfare Transactions</span>
                </a>
                @endif
            </div>
        </div>
        @endif

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

    function toggleSubmenu(event, id) {
        event.preventDefault();
        const submenu = document.getElementById(id);
        const parent = submenu.parentElement;
        parent.classList.toggle('open');
        submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
    }
</script>
