<!-- Top Navbar -->
<div class="bg-white border-b border-gray-200 px-4 md:px-6 py-4 flex items-center justify-between sticky top-0 z-30">
    <!-- Mobile Sidebar Toggle -->
    <button id="sidebar-toggle" class="md:hidden p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors mr-2">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <!-- Search Bar -->
    <div class="flex-1 max-w-md">
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
            <input type="text" placeholder="Search..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm" />
        </div>
    </div>

    <!-- Right Side Actions -->
    <div class="flex items-center gap-2 md:gap-4 ml-auto">
        <button class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
            <span class="material-symbols-outlined">notifications</span>
            <span class="absolute top-1 right-1 w-2 h-2 bg-error rounded-full"></span>
        </button>

        <div class="flex items-center gap-3 pl-4 border-l border-gray-200">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->fullname ?? auth()->user()->name }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ auth()->user()->role }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-on-primary font-semibold text-sm">
                {{ strtoupper(substr(auth()->user()->fullname ?? auth()->user()->name, 0, 1)) }}
            </div>

            <div class="relative group">
                <button class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                    <span class="material-symbols-outlined">expand_more</span>
                </button>
                <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 first:rounded-t-lg">
                        <span class="material-symbols-outlined inline text-sm mr-2">person</span>
                        Profile
                    </a>
                    <a href="{{ route('settings.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                        <span class="material-symbols-outlined inline text-sm mr-2">settings</span>
                        Settings
                    </a>
                    <hr class="my-2">
                    <form action="{{ route('logout') }}" method="POST" class="block">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100 last:rounded-b-lg">
                            <span class="material-symbols-outlined inline text-sm mr-2">logout</span>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
