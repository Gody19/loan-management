{{-- Member Portal Sidebar --}}
<aside class="vicoba-sidebar" id="sidebar">

    {{-- Sidebar Header --}}
    <div class="flex items-center justify-between px-3 py-3 border-b border-white/10 shrink-0">
        <a href="{{ route('member.dashboard') }}" class="flex items-center no-underline">
            <div class="bg-primary rounded-lg flex items-center justify-content-center me-2" style="width: 36px; height: 36px;">
                <i class="bi bi-grid-1x2-fill text-white fs-5"></i>
            </div>
            <div class="sidebar-brand">
                <span class="text-white fw-semibold fs-6">FinancePro</span>
                <small class="text-white-50 d-block" style="font-size: 0.65rem;">Member Portal</small>
            </div>
        </a>
        <button class="btn btn-sm btn-link text-white-50 d-lg-none" onclick="toggleSidebar()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Scrollable nav area --}}
    <div class="flex-1 overflow-y-auto no-scrollbar py-2 aside-content">

        {{-- Dashboard --}}
        <a href="{{ route('member.dashboard') }}" class="nav-link {{ request()->routeIs('member.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        {{-- My Finance --}}
        <div class="sidebar-section mt-3">My Finance</div>

        <a href="{{ route('member.savings') }}" class="nav-link {{ request()->routeIs('member.savings') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i>
            <span>My Savings</span>
        </a>

        <a href="{{ route('member.shares') }}" class="nav-link {{ request()->routeIs('member.shares') ? 'active' : '' }}">
            <i class="bi bi-cash-stack"></i>
            <span>My Shares</span>
        </a>

        <a href="{{ route('member.welfare') }}" class="nav-link {{ request()->routeIs('member.welfare') ? 'active' : '' }}">
            <i class="bi bi-heart"></i>
            <span>My Welfare</span>
        </a>

        <a href="{{ route('member.transactions') }}" class="nav-link {{ request()->routeIs('member.transactions') ? 'active' : '' }}">
            <i class="bi bi-list-ul"></i>
            <span>Transactions</span>
        </a>

        {{-- Loans --}}
        <div class="sidebar-section mt-3">Loans</div>

        <a href="{{ route('member.loans') }}" class="nav-link {{ request()->routeIs('member.loans') && !request()->routeIs('member.loans.*') ? 'active' : '' }}">
            <i class="bi bi-cash-coin"></i>
            <span>My Loans</span>
        </a>

        <a href="{{ route('member.loans.applications') }}" class="nav-link {{ request()->routeIs('member.loans.applications*') ? 'active' : '' }}">
            <i class="bi bi-file-text"></i>
            <span>Loan Applications</span>
        </a>

        <a href="{{ route('member.repayment-schedule') }}" class="nav-link {{ request()->routeIs('member.repayment-schedule') ? 'active' : '' }}">
            <i class="bi bi-calendar3"></i>
            <span>Repayment Schedule</span>
        </a>

        <a href="{{ route('member.repayments') }}" class="nav-link {{ request()->routeIs('member.repayments*') ? 'active' : '' }}">
            <i class="bi bi-credit-card"></i>
            <span>Repayment History</span>
        </a>

        @php
            $activeLoan = null;
            if (auth()->check() && auth()->user()->member) {
                $activeLoan = \App\Models\Loan::where('member_id', auth()->user()->member->id)
                    ->whereIn('status', [\App\Enums\LoanStatus::Active, \App\Enums\LoanStatus::Disbursed, \App\Enums\LoanStatus::PendingDisbursement])
                    ->where('deleted_at', null)
                    ->first();
            }
        @endphp
        @if($activeLoan)
            <a href="{{ route('member.loans.repay', $activeLoan) }}" class="nav-link {{ request()->routeIs('member.loans.repay*') ? 'active' : '' }}">
                <i class="bi bi-cash"></i>
                <span>Make Payment</span>
            </a>
        @endif

        {{-- Guarantor Requests --}}
        <div class="sidebar-section mt-3">Guarantor</div>

        <a href="{{ route('member.guarantor.requests') }}" class="nav-link {{ request()->routeIs('member.guarantor*') ? 'active' : '' }}">
            <i class="bi bi-shield-check"></i>
            <span>Guarantor Requests</span>
            @php
                $guarantorCount = 0;
                if (auth()->check() && auth()->user()->member) {
                    $guarantorCount = \App\Models\LoanApplicationGuarantor::where('guarantor_member_id', auth()->user()->member->id)
                        ->where('status', \App\Enums\GuarantorStatus::Pending)
                        ->count();
                }
            @endphp
            @if($guarantorCount > 0)
                <span class="badge bg-danger ms-auto" style="font-size: 0.65rem;">{{ $guarantorCount }}</span>
            @endif
        </a>

        {{-- Statements --}}
        <div class="sidebar-section mt-3">Statements</div>

        <a href="{{ route('member.statements') }}" class="nav-link {{ request()->routeIs('member.statements') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-text"></i>
            <span>Financial Statement</span>
        </a>

        {{-- Activity --}}
        <div class="sidebar-section mt-3">Activity</div>

        <a href="{{ route('member.notifications') }}" class="nav-link {{ request()->routeIs('member.notifications') ? 'active' : '' }}">
            <i class="bi bi-bell"></i>
            <span>Notifications</span>
        </a>

        {{-- Account --}}
        <div class="sidebar-section mt-3">Account</div>

        <a href="{{ route('member.profile') }}" class="nav-link {{ request()->routeIs('member.profile*') ? 'active' : '' }}">
            <i class="bi bi-person"></i>
            <span>My Profile</span>
        </a>

        <a href="{{ route('member.settings') }}" class="nav-link {{ request()->routeIs('member.settings') ? 'active' : '' }}">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>

        <a href="{{ route('member.help') }}" class="nav-link {{ request()->routeIs('member.help') ? 'active' : '' }}">
            <i class="bi bi-question-circle"></i>
            <span>Help & Support</span>
        </a>

        {{-- Logout --}}
        <div class="mt-3 px-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm w-100 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.8rem;">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>

    </div>

    {{-- Sidebar Footer --}}
    <div class="shrink-0 p-3 border-t border-white/10">
        <div class="flex items-center">
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                <span class="text-white fw-semibold" style="font-size: 0.75rem;">
                    {{ substr(auth()->user()->fullname ?? 'U', 0, 1) }}
                </span>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="text-white fw-medium small text-truncate">{{ auth()->user()->fullname ?? 'User' }}</div>
                <div class="text-white-50" style="font-size: 0.65rem;">
                    {{ auth()->user()->member->member_number ?? '' }}
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
