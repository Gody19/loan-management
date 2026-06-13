<!-- Sidebar Component -->
<aside
    class="hidden md:flex flex-col h-screen fixed left-0 top-0 p-md space-y-md bg-surface-container-low border-r border-outline-variant w-64 z-40">
    <div class="flex items-center gap-md px-md py-sm">
        <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-symbols-outlined text-on-primary">account_balance</span>
        </div>
        <div>
            <h1 class="font-headline-sm text-headline-sm font-bold text-on-surface">FinancePro</h1>
            <p class="text-xs text-on-surface-variant uppercase tracking-wider">Enterprise Finance</p>
        </div>
    </div>

    <div class="mt-xl px-md">
        <button
            class="w-full bg-primary text-on-primary rounded-lg py-md px-lg font-bold flex items-center justify-center gap-sm hover:opacity-90 transition-all active:scale-95 duration-150">
            <!-- <span class="material-symbols-outlined">add</span> -->
            <a href="{{route('loans.apply')}}"> New Application</a>
        </button>
    </div>

    <nav class="flex-1 mt-md space-y-1">
        <a class="flex items-center gap-md {{ request()->routeIs('dashboard') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-high' }} rounded-lg px-md py-sm font-bold transition-all duration-100"
            href="{{ route('dashboard') }}">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="font-label-lg text-label-lg">Dashboard</span>
        </a>

        <a class="flex items-center gap-md {{ request()->routeIs('loans.apply') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-high' }} rounded-lg px-md py-sm transition-all duration-100"
            href="{{ route('loans.apply') }}">
            <span class="material-symbols-outlined">add_box</span>
            <span class="font-label-lg text-label-lg">Apply Loan</span>
        </a>

        <a class="flex items-center gap-md {{ request()->routeIs('loans.index') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-high' }} rounded-lg px-md py-sm transition-all duration-100"
            href="{{ route('loans.index') }}">
            <span class="material-symbols-outlined">history_edu</span>
            <span class="font-label-lg text-label-lg">My Loan History</span>
        </a>

        <a class="flex items-center gap-md {{ request()->routeIs('loans.payments') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-high' }} rounded-lg px-md py-sm transition-all duration-100"
            href="{{ route('loans.payments') }}">
            <span class="material-symbols-outlined">payments</span>
            <span class="font-label-lg text-label-lg">Repayments</span>
        </a>

        <a class="flex items-center gap-md {{ request()->routeIs('reports.index') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-high' }} rounded-lg px-md py-sm transition-all duration-100"
            href="{{-- route('reports.index') --}}">
            <span class="material-symbols-outlined">analytics</span>
            <span class="font-label-lg text-label-lg">Reports</span>
        </a>

        <a class="flex items-center gap-md {{ request()->routeIs('settings.index') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-high' }} rounded-lg px-md py-sm transition-all duration-100"
            href="{{-- route('settings.index') --}}">
            <span class="material-symbols-outlined">settings</span>
            <span class="font-label-lg text-label-lg">Settings</span>
        </a>
        <a class="flex items-center gap-md {{ request()->routeIs('user.profile') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-high' }} rounded-lg px-md py-sm transition-all duration-100"
            href="{{ route('user.profile') }}">

            <span class="material-symbols-outlined">person</span>
            <span class="font-label-lg text-label-lg">Profile</span>
        </a>
    </nav>

    <div class="pt-lg border-t border-outline-variant space-y-1">
        <a class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all"
            href="{{-- route('support') --}}">
            <span class="material-symbols-outlined">help</span>
            <span class="font-label-lg text-label-lg">Support</span>
        </a>

        <form method="POST" action="{{-- route('logout') --}}" class="w-full">
            @csrf
            <button type="submit"
                class="flex items-center gap-md text-on-surface-variant px-md py-sm hover:bg-surface-container-high rounded-lg transition-all w-full">
                <span class="material-symbols-outlined">logout</span>
                <span class="font-label-lg text-label-lg">Logout</span>
            </button>
        </form>
    </div>
</aside>