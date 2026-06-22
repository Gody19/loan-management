<!-- Sidebar Overlay (mobile) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed top-0 left-0 z-50 h-screen w-64 bg-primary text-on-primary transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto">
    <div class="p-6 border-b border-white/20">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
                <span class="font-headline-md text-lg font-bold">FinancePro</span>
            </div>
            <button id="sidebar-close" class="md:hidden p-1 hover:bg-white/10 rounded-lg transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
    </div>

    <nav class="p-4 space-y-2">
        @if(auth()->user()->role === 'user')
            <a href="{{ route('dashboard.user') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('dashboard.user') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('loans.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('loans.index') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">assignment</span>
                <span>Loans</span>
            </a>
            <a href="{{ route('loans.user-history') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('loans.user-history') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">history</span>
                <span>My History</span>
            </a>
            <a href="{{ route('portfolio.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('portfolio.*') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">trending_up</span>
                <span>Portfolio</span>
            </a>
            <a href="{{ route('budget.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('budget.*') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">wallet</span>
                <span>Budget</span>
            </a>
            <hr class="border-white/20 my-2">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('profile.*') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">person</span>
                <span>Profile</span>
            </a>
            <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('settings.*') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
        @endif

        @if(in_array(auth()->user()->role, ['admin', 'manager', 'loan_officer']))
            <a href="{{ route('dashboard.admin') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('dashboard.admin') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">admin_panel_settings</span>
                <span>Admin Panel</span>
            </a>

            @if(in_array(auth()->user()->role, ['admin', 'manager', 'loan_officer']))
            <a href="{{ route('loans.pending') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('loans.pending') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">approval</span>
                <span>Loan Approvals</span>
            </a>
            <a href="{{ route('loans.my-history') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('loans.my-history') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">history</span>
                <span>My History</span>
            </a>
            @endif

            @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('reports.*') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">bar_chart</span>
                <span>Reports</span>
            </a>
            @endif

            @if(auth()->user()->role === 'admin')
            <a href="{{ route('admin.users') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('admin.users') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">group</span>
                <span>Users</span>
            </a>
            <a href="{{ route('admin.all-processed') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('admin.all-processed') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">fact_check</span>
                <span>All Processed Loans</span>
            </a>
            <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition-colors {{ request()->routeIs('settings.*') ? 'bg-white/20' : '' }}">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
            @endif
        @endif
    </nav>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const toggleBtn = document.getElementById('sidebar-toggle');
        const closeBtn = document.getElementById('sidebar-close');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.remove('hidden');
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
        }

        if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);

        // Close sidebar on nav link click (mobile)
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth < 768) closeSidebar();
            });
        });
    });
</script>
