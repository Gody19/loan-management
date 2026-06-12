<!-- Header / Top Nav Context -->
<header class="sticky top-0 w-full z-30 flex items-center justify-between px-margin-mobile md:px-xl h-16 bg-surface-container-lowest shadow-sm border-b border-outline-variant">
    <div class="flex items-center gap-md">
        <button class="md:hidden p-base rounded-full hover:bg-surface-container-high transition-colors" id="mobileMenuButton">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="hidden md:block">
            <span class="font-headline-md text-headline-md font-bold text-primary">@yield('page-title', 'Dashboard')</span>
        </div>
    </div>
    
    <div class="flex items-center gap-lg">
        <div class="hidden lg:flex items-center bg-surface-container-low px-md py-xs rounded-full border border-outline-variant">
            <span class="material-symbols-outlined text-outline">search</span>
            <input class="bg-transparent border-none focus:ring-0 text-sm w-48" 
                   placeholder="Search transactions..." 
                   type="text" 
                   id="searchInput">
        </div>
        
        <div class="flex items-center gap-md">
            <button class="relative material-symbols-outlined text-on-surface-variant p-base hover:bg-surface-container-high rounded-full cursor-pointer transition-colors" 
                    id="notificationButton">
                notifications
                <span class="absolute top-1 right-1 w-2 h-2 bg-error rounded-full"></span>
            </button>
            
            <div class="flex items-center gap-sm">
                <img alt="User profile" 
                     class="w-8 h-8 rounded-full border border-primary/20 cursor-pointer hover:opacity-80 transition-opacity" 
                     src="{{ Auth::user()->avatar ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuAvbB8s4zZHELNowvIovmtcnec0LxWksHSRwRTVrrRY5Z9LYTjTBX-o08ovZJKv_04G6XHnUOfh2svGJMrs9yBJujKmq7fQWQqpjOx0oCi0yN6WjSQHPESA4TeqNWotaHbtyQdhCt6Qnle8j7aIdqxeXFPD1V_AcPVOrW3JNU5lBeOAg99wkjHjNYNpZpvNImr2cSrd-jEp1fu6Ek3xjnWpEboiBBiNUY8giNUNw5ZfRGMFjYazBS8KJKpMyUHuY0McVQNPCRoEG1Y' }}">
                <span class="hidden sm:inline font-label-lg text-label-lg text-on-surface">{{ Auth::user()->fullname ?? 'Alex Sterling' }}</span>
            </div>
        </div>
    </div>
</header>

@push('scripts')
<script>
    // Mobile menu toggle
    document.getElementById('mobileMenuButton')?.addEventListener('click', function() {
        const sidebar = document.querySelector('aside');
        sidebar.classList.toggle('hidden');
        sidebar.classList.toggle('fixed');
        sidebar.classList.toggle('inset-0');
        sidebar.classList.toggle('z-50');
    });
    
    // Search functionality
    document.getElementById('searchInput')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        // Implement search logic here
        console.log('Searching for:', searchTerm);
    });
    
    // Notifications
    document.getElementById('notificationButton')?.addEventListener('click', function() {
        // Implement notification panel toggle
        console.log('Notifications clicked');
    });
</script>
@endpush