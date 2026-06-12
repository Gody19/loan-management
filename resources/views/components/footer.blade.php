<!-- Footer Component -->
<footer class="w-full py-xl px-margin-mobile md:px-xl flex flex-col md:flex-row justify-between items-center gap-lg max-w-container-max mx-auto bg-surface-container-highest border-t border-outline-variant mt-2xl">
    <div class="flex flex-col md:items-start items-center gap-base">
        <span class="font-label-lg text-label-lg font-bold text-on-surface">FinancePro</span>
        <p class="font-body-sm text-body-sm text-on-surface-variant text-center md:text-left">
            © {{ date('Y') }} FinancePro Solutions. All rights reserved.
        </p>
    </div>
    
    <div class="flex gap-lg flex-wrap justify-center">
        <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors" 
           href="{{-- route('privacy-policy') --}}">
            Privacy Policy
        </a>
        <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors" 
           href="{{-- route('terms-of-service') --}}">
            Terms of Service
        </a>
        <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors" 
           href="{{-- route('compliance') --}}">
            Compliance
        </a>
        <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors" 
           href="{{-- route('security') --}}">
            Security
        </a>
    </div>
</footer>