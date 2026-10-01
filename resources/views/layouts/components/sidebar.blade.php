{{-- Sidebar Navigation --}}
<aside class="vicoba-sidebar" id="sidebar">

    {{-- Sidebar Header — fixed --}}
    <div class="flex items-center justify-between px-3 py-3 border-b border-white/10 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center no-underline">
            <div class="bg-primary rounded-lg flex items-center justify-center me-2" style="width: 36px; height: 36px;">
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

    {{-- Scrollable nav area — no visible scrollbar --}}
    <div class="flex-1 overflow-y-auto no-scrollbar py-2 aside-content">

        {{-- Main Section --}}
        <div class="sidebar-section">Main</div>

        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        @if(auth()->check() && auth()->user()->can('ai.use'))
        <a href="{{ route('ai.index') }}" class="nav-link {{ request()->routeIs('ai.index', 'ai.chat', 'ai.conversations.*', 'ai.tool') ? 'active' : '' }}">
            <i class="bi bi-stars"></i>
            <span>AI Assistant</span>
        </a>
        @endif

        @if(auth()->check() && auth()->user()->can('ai.feedback.review'))
        <a href="{{ route('ai.evaluations.queue') }}" class="nav-link {{ request()->routeIs('ai.evaluations.*', 'ai.dataset.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard2-check"></i>
            <span>AI Feedback Review</span>
        </a>
        @endif

        @php
            $hasIntelligence = auth()->check() && (auth()->user()->can('ai.portfolio.view') || auth()->user()->can('ai.delinquency.view') || auth()->user()->can('ai.collection.view') || auth()->user()->can('ai.trend.view') || auth()->user()->can('ai.accounting.view') || auth()->user()->can('ai.anomaly.view') || auth()->user()->can('ai.predictive.view') || auth()->user()->can('ai.insights.view'));
        @endphp
        @if($hasIntelligence)
        <a href="{{ route('ai.intelligence.index') }}" class="nav-link {{ request()->routeIs('ai.intelligence.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Financial Intelligence</span>
        </a>
        @endif

        @if(auth()->check())
        <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
            <i class="bi bi-bell"></i>
            <span>Notifications</span>
            @if($unreadNotifications = auth()->user()->unreadNotifications()->count())
                <span class="badge rounded-pill bg-danger ms-auto">{{ $unreadNotifications }}</span>
            @endif
        </a>
        @endif

        {{-- Organization Section --}}
        @php
            $hasOrgSection = auth()->check() && (auth()->user()->can('organization.view') || auth()->user()->can('branch.view') || auth()->user()->can('group.view'));
        @endphp
        @if($hasOrgSection)
        <div class="sidebar-section mt-3">Organization</div>

        @if(auth()->user()->can('organization.view'))
        <a href="{{ route('organizations.index') }}" class="nav-link {{ request()->routeIs('organizations.*') ? 'active' : '' }}">
            <i class="bi bi-building"></i>
            <span>Organizations</span>
        </a>
        @endif

        @if(auth()->user()->can('branch.view'))
        <a href="{{ route('branches.index') }}" class="nav-link {{ request()->routeIs('branches.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3"></i>
            <span>Branches</span>
        </a>
        @endif

        @if(auth()->user()->can('group.view'))
        <a href="{{ route('vicoba-groups.index') }}" class="nav-link {{ request()->routeIs('vicoba-groups.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>VICOBA Groups</span>
        </a>
        @endif
        @endif

        {{-- Members Section --}}
        @if(auth()->check() && auth()->user()->can('member.view'))
        <div class="sidebar-section mt-3">Members</div>

        <a href="{{ route('members.index') }}" class="nav-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
            <i class="bi bi-person-plus"></i>
            <span>Members</span>
        </a>
        @endif

        {{-- Financial Section --}}
        @php
            $hasSavingsMenu = auth()->check() && (auth()->user()->can('savings_product.view') || auth()->user()->can('savings_account.view') || auth()->user()->can('savings_transaction.view'));
            $hasSharesMenu = auth()->check() && (auth()->user()->can('share_product.view') || auth()->user()->can('share_account.view') || auth()->user()->can('share_transaction.view'));
            $hasWelfareMenu = auth()->check() && (auth()->user()->can('welfare_fund.view') || auth()->user()->can('welfare_account.view') || auth()->user()->can('welfare_transaction.view'));
        @endphp
        @if($hasSavingsMenu || $hasSharesMenu || $hasWelfareMenu)
        <div class="sidebar-section mt-3">Finance</div>

        {{-- Savings Sub-menu --}}
        @if($hasSavingsMenu)
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
        @if($hasSharesMenu)
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
        @if($hasWelfareMenu)
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
        @endif

        {{-- Loans Section --}}
        @php
            $hasLoansMenu = auth()->check() && (auth()->user()->can('loan_plan.view') || auth()->user()->can('loan_eligibility.view') || auth()->user()->can('loan_application.view') || auth()->user()->can('loan_approval_level.view') || auth()->user()->can('loans.view'));
        @endphp
        @if($hasLoansMenu)
        <div class="sidebar-section mt-3">Loans</div>

        <div class="nav-parent">
            <a href="#" class="nav-link nav-toggle {{ request()->routeIs('loan-*') || request()->routeIs('loans.*') ? 'active' : '' }}" onclick="toggleSubmenu(event, 'submenu-loans')">
                <i class="bi bi-cash-coin"></i>
                <span>Loans</span>
                <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
            </a>
            <div class="nav-submenu" id="submenu-loans" style="{{ request()->routeIs('loan-*') || request()->routeIs('loans.*') ? 'display:block;' : '' }}">
                @if(auth()->user()->can('loan_plan.view'))
                <a href="{{ route('loan-plans.index') }}" class="nav-link sub-link {{ request()->routeIs('loan-plans.*') ? 'active' : '' }}">
                    <span>Loan Plans</span>
                </a>
                @endif
                @if(auth()->user()->can('loan_eligibility.view'))
                <a href="{{ route('loan-eligibility.index') }}" class="nav-link sub-link {{ request()->routeIs('loan-eligibility.*') ? 'active' : '' }}">
                    <span>Eligibility Check</span>
                </a>
                @endif
                @if(auth()->user()->can('loan_application.view'))
                <a href="{{ route('loan-applications.index') }}" class="nav-link sub-link {{ request()->routeIs('loan-applications.*') ? 'active' : '' }}">
                    <span>Applications</span>
                </a>
                <a href="{{ route('guarantor-reviews.index') }}" class="nav-link sub-link {{ request()->routeIs('guarantor-reviews.*') ? 'active' : '' }}">
                    <span>Guarantor Reviews</span>
                </a>
                @endif
                @if(auth()->user()->can('loan_approval_level.view'))
                <a href="{{ route('loan-approval-levels.index') }}" class="nav-link sub-link {{ request()->routeIs('loan-approval-levels.*') ? 'active' : '' }}">
                    <span>Approval Levels</span>
                </a>
                @endif
                @if(auth()->user()->can('loans.view'))
                <a href="{{ route('loans.index') }}" class="nav-link sub-link {{ request()->routeIs('loans.*') ? 'active' : '' }}">
                    <span>Loan Accounts</span>
                </a>
                <a href="{{ route('loan-disbursements.index') }}" class="nav-link sub-link {{ request()->routeIs('loan-disbursements.*') ? 'active' : '' }}">
                    <span>Disbursements</span>
                </a>
                @endif
                @if(auth()->user()->can('loan-repayments.view'))
                <a href="{{ route('loan-collections.index') }}" class="nav-link sub-link {{ request()->routeIs('loan-collections.*') ? 'active' : '' }}">
                    <span>Collections & Delinquency</span>
                </a>
                @endif
                @if(auth()->user()->can('loan-repayments.create'))
                <a href="{{ route('loan-repayments-collection.index') }}" class="nav-link sub-link {{ request()->routeIs('loan-repayments-collection.*') ? 'active' : '' }}">
                    <span>Record Payment</span>
                </a>
                @endif
            </div>
        </div>
        @endif

        {{-- Reports --}}
        @if(auth()->check() && auth()->user()->can('reports.view'))
        <div class="sidebar-section mt-3">Reports</div>

        <a href="#" class="nav-link">
            <i class="bi bi-clipboard-data"></i>
            <span>Reports</span>
        </a>
        @endif

        {{-- Accounting --}}
        @php
            $hasAccountingMenu = auth()->check() && auth()->user()->can('accounting.view');
        @endphp
        @if($hasAccountingMenu)
        <div class="sidebar-section mt-3">Accounting</div>

        <div class="nav-item">
            <a class="nav-link {{ request()->routeIs('accounting.accounts.*') ? 'active' : '' }}" data-bs-toggle="collapse" href="#accountingAccounts">
                <i class="bi bi-journal-bookmark"></i>
                <span>Chart of Accounts</span>
            </a>
            <div class="collapse {{ request()->routeIs('accounting.accounts.*') ? 'show' : '' }}" id="accountingAccounts">
                <div class="no-scrollbar">
                    <a href="{{ route('accounting.accounts.index') }}" class="nav-link sub-link {{ request()->routeIs('accounting.accounts.index') ? 'active' : '' }}">All Accounts</a>
                    <a href="{{ route('accounting.accounts.create') }}" class="nav-link sub-link {{ request()->routeIs('accounting.accounts.create') ? 'active' : '' }}">New Account</a>
                </div>
            </div>
        </div>

        <div class="nav-item">
            <a class="nav-link {{ request()->routeIs('accounting.periods.*') ? 'active' : '' }}" data-bs-toggle="collapse" href="#accountingPeriods">
                <i class="bi bi-calendar3"></i>
                <span>Accounting Periods</span>
            </a>
            <div class="collapse {{ request()->routeIs('accounting.periods.*') ? 'show' : '' }}" id="accountingPeriods">
                <div class="no-scrollbar">
                    <a href="{{ route('accounting.periods.index') }}" class="nav-link sub-link {{ request()->routeIs('accounting.periods.index') ? 'active' : '' }}">All Periods</a>
                    <a href="{{ route('accounting.periods.create') }}" class="nav-link sub-link {{ request()->routeIs('accounting.periods.create') ? 'active' : '' }}">New Period</a>
                </div>
            </div>
        </div>

        <div class="nav-item">
            <a class="nav-link {{ request()->routeIs('accounting.journals.*') ? 'active' : '' }}" data-bs-toggle="collapse" href="#accountingJournals">
                <i class="bi bi-journal-text"></i>
                <span>Journal Entries</span>
            </a>
            <div class="collapse {{ request()->routeIs('accounting.journals.*') ? 'show' : '' }}" id="accountingJournals">
                <div class="no-scrollbar">
                    <a href="{{ route('accounting.journals.index') }}" class="nav-link sub-link {{ request()->routeIs('accounting.journals.index') ? 'active' : '' }}">All Journals</a>
                    <a href="{{ route('accounting.journals.create') }}" class="nav-link sub-link {{ request()->routeIs('accounting.journals.create') ? 'active' : '' }}">New Journal</a>
                </div>
            </div>
        </div>

        <div class="nav-item">
            <a class="nav-link {{ request()->routeIs('accounting.reports.*') ? 'active' : '' }}" data-bs-toggle="collapse" href="#accountingReports">
                <i class="bi bi-bar-chart-line"></i>
                <span>Financial Reports</span>
            </a>
            <div class="collapse {{ request()->routeIs('accounting.reports.*') ? 'show' : '' }}" id="accountingReports">
                <div class="no-scrollbar">
                    <a href="{{ route('accounting.reports.general-ledger') }}" class="nav-link sub-link {{ request()->routeIs('accounting.reports.general-ledger') ? 'active' : '' }}">General Ledger</a>
                    <a href="{{ route('accounting.reports.trial-balance') }}" class="nav-link sub-link {{ request()->routeIs('accounting.reports.trial-balance') ? 'active' : '' }}">Trial Balance</a>
                    <a href="{{ route('accounting.reports.balance-sheet') }}" class="nav-link sub-link {{ request()->routeIs('accounting.reports.balance-sheet') ? 'active' : '' }}">Balance Sheet</a>
                    <a href="{{ route('accounting.reports.income-statement') }}" class="nav-link sub-link {{ request()->routeIs('accounting.reports.income-statement') ? 'active' : '' }}">Income Statement</a>
                    <a href="{{ route('accounting.reports.cash-ledger') }}" class="nav-link sub-link {{ request()->routeIs('accounting.reports.cash-ledger') ? 'active' : '' }}">Cash/Bank Ledger</a>
                </div>
            </div>
        </div>

        <a href="{{ route('accounting.configuration.index') }}" class="nav-link {{ request()->routeIs('accounting.configuration.*') ? 'active' : '' }}">
            <i class="bi bi-gear"></i>
            <span>Configuration</span>
        </a>
        @endif

        {{-- Administration Section --}}
        @php
            $hasAdminMenu = auth()->check() && (auth()->user()->can('user.view') || auth()->user()->can('role.view') || auth()->user()->can('permission.view') || auth()->user()->can('audit.view') || auth()->user()->can('contact_message.view'));
        @endphp
        @if($hasAdminMenu)
        <div class="sidebar-section mt-3">Administration</div>

        @if(auth()->user()->can('contact_message.view'))
        <a href="{{ route('contact-messages.index') }}" class="nav-link {{ request()->routeIs('contact-messages.*') ? 'active' : '' }}">
            <i class="bi bi-envelope"></i>
            <span>Contact Messages</span>
        </a>
        @endif

        @if(auth()->user()->can('user.view'))
        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i>
            <span>Users</span>
        </a>
        @endif

        @if(auth()->user()->can('role.view'))
        <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock"></i>
            <span>Roles</span>
        </a>
        @endif

        @if(auth()->user()->can('permission.view'))
        <a href="{{ route('permissions.index') }}" class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
            <i class="bi bi-key"></i>
            <span>Permissions</span>
        </a>
        @endif
        @endif

    </div>

    {{-- Sidebar Footer — fixed --}}
    <div class="shrink-0 p-3 border-t border-white/10">
        <div class="flex items-center">
            <div class="bg-primary rounded-circle flex items-center justify-center me-2" style="width: 32px; height: 32px;">
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

    document.addEventListener('DOMContentLoaded', function() {
        const aside = document.querySelector('.aside-content');
        const key = 'admin_sidebar_scroll';

        const saved = sessionStorage.getItem(key);
        if (saved) {
            aside.scrollTop = parseInt(saved, 10);
        }

        aside.addEventListener('scroll', function() {
            sessionStorage.setItem(key, aside.scrollTop);
        });

        document.querySelectorAll('.aside-content a').forEach(function(link) {
            link.addEventListener('click', function() {
                sessionStorage.setItem(key, aside.scrollTop);
            });
        });
    });
</script>
