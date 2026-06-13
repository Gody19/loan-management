@extends('layouts.app')
@section('page-title','Loan History')
@section('FinancePro|Loan History')
@section('content')

    <!-- Content Area -->
    <section class="p-margin-mobile md:p-xl space-y-lg max-w-container-max mx-auto w-full">
        <!-- Welcome/Stats Banner (Bento Style) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-lg">
            <div
                class="md:col-span-2 bg-primary-container text-on-primary-container p-xl rounded-xl relative overflow-hidden flex flex-col justify-between min-h-[160px]">
                <div class="relative z-10">
                    <h3 class="font-headline-md text-headline-md mb-xs">Track Your Growth</h3>
                    <p class="font-body-md text-body-md opacity-90 max-w-md">Review your borrowing history and
                        manage repayments effectively with our integrated loan management suite.</p>
                </div>
                <div class="absolute right-0 bottom-0 opacity-20 transform translate-x-1/4 translate-y-1/4">
                    <span class="material-symbols-outlined text-[120px]">analytics</span>
                </div>
            </div>
            <div
                class="bg-surface-container-lowest p-xl rounded-xl border border-outline-variant shadow-sm flex flex-col justify-center">
                <p class="text-on-surface-variant font-label-md text-label-md uppercase tracking-widest mb-sm">Total
                    Active Loans</p>
                <div class="flex items-baseline gap-sm">
                    <span class="font-display-md text-display-md text-primary">$42,500.00</span>
                    <span class="text-tertiary font-label-lg text-label-lg flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">trending_up</span> 12%
                    </span>
                </div>
            </div>
        </div>
        <!-- Table Actions Filter Bar -->
        <div
            class="bg-surface-container-lowest p-md rounded-xl border border-outline-variant shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-md">
            <div class="flex-1 flex flex-col md:flex-row gap-md">
                <!-- Search Bar -->
                <div class="relative flex-1 max-w-md">
                    <span
                        class="material-symbols-outlined absolute left-md top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                    <input
                        class="w-full h-12 pl-12 pr-md bg-surface-container-low border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-body-sm"
                        placeholder="Search by Loan ID or Type..." type="text" />
                </div>
                <!-- Status Filter -->
                <div class="relative w-full md:w-48">
                    <select
                        class="w-full h-12 px-md bg-surface-container-low border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-body-sm appearance-none cursor-pointer">
                        <option value="">Filter by Status</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="overdue">Overdue</option>
                    </select>
                    <span
                        class="material-symbols-outlined absolute right-md top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant">expand_more</span>
                </div>
            </div>
            <div class="flex items-center gap-sm">
                <button
                    class="h-12 px-lg flex items-center gap-sm bg-surface-container border border-outline-variant text-on-surface rounded-lg hover:bg-surface-variant transition-colors">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <span class="font-label-lg text-label-lg">Custom Range</span>
                </button>
                <button
                    class="h-12 w-12 flex items-center justify-center bg-primary text-on-primary rounded-lg shadow-md hover:scale-95 transition-transform active:scale-90">
                    <span class="material-symbols-outlined">download</span>
                </button>
            </div>
        </div>
        <!-- Data Table Container -->
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-sm overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th
                                class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">
                                Loan ID</th>
                            <th
                                class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">
                                Type</th>
                            <th
                                class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-right">
                                Amount</th>
                            <th
                                class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">
                                Duration</th>
                            <th
                                class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider">
                                Date</th>
                            <th
                                class="px-xl py-md font-label-lg text-label-lg text-on-surface-variant uppercase tracking-wider text-center">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <!-- Row 1 -->
                        <tr class="hover:bg-surface-container transition-colors group">
                            <td class="px-xl py-lg font-code text-primary font-semibold">FP-90234</td>
                            <td class="px-xl py-lg">
                                <div class="flex items-center gap-sm">
                                    <span class="material-symbols-outlined text-secondary">business_center</span>
                                    <span class="font-body-md text-on-surface">Business Growth</span>
                                </div>
                            </td>
                            <td class="px-xl py-lg text-right font-semibold text-on-surface">$25,000.00</td>
                            <td class="px-xl py-lg text-on-surface-variant">24 Months</td>
                            <td class="px-xl py-lg">
                                <span
                                    class="px-sm py-xs bg-tertiary-fixed-dim/20 text-tertiary font-label-md text-[10px] rounded uppercase tracking-widest font-bold">Active</span>
                            </td>
                            <td class="px-xl py-lg text-on-surface-variant text-body-sm">Oct 12, 2023</td>
                            <td class="px-xl py-lg text-center">
                                <div
                                    class="flex items-center justify-center gap-sm opacity-60 group-hover:opacity-100 transition-opacity">
                                    <button class="p-xs text-primary hover:bg-primary/10 rounded" title="View Details"><span
                                            class="material-symbols-outlined">visibility</span></button>
                                    <button class="p-xs text-secondary hover:bg-secondary/10 rounded"
                                        title="Download PDF"><span
                                            class="material-symbols-outlined">file_download</span></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 2 -->
                        <tr class="hover:bg-surface-container transition-colors group">
                            <td class="px-xl py-lg font-code text-primary font-semibold">FP-88412</td>
                            <td class="px-xl py-lg">
                                <div class="flex items-center gap-sm">
                                    <span class="material-symbols-outlined text-secondary">home</span>
                                    <span class="font-body-md text-on-surface">Mortgage Refinance</span>
                                </div>
                            </td>
                            <td class="px-xl py-lg text-right font-semibold text-on-surface">$120,000.00</td>
                            <td class="px-xl py-lg text-on-surface-variant">180 Months</td>
                            <td class="px-xl py-lg">
                                <span
                                    class="px-sm py-xs bg-secondary-fixed/50 text-secondary font-label-md text-[10px] rounded uppercase tracking-widest font-bold">Completed</span>
                            </td>
                            <td class="px-xl py-lg text-on-surface-variant text-body-sm">Jan 05, 2024</td>
                            <td class="px-xl py-lg text-center">
                                <div
                                    class="flex items-center justify-center gap-sm opacity-60 group-hover:opacity-100 transition-opacity">
                                    <button class="p-xs text-primary hover:bg-primary/10 rounded"><span
                                            class="material-symbols-outlined">visibility</span></button>
                                    <button class="p-xs text-secondary hover:bg-secondary/10 rounded"><span
                                            class="material-symbols-outlined">file_download</span></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 3 -->
                        <tr class="hover:bg-surface-container transition-colors group">
                            <td class="px-xl py-lg font-code text-primary font-semibold">FP-91002</td>
                            <td class="px-xl py-lg">
                                <div class="flex items-center gap-sm">
                                    <span class="material-symbols-outlined text-secondary">speed</span>
                                    <span class="font-body-md text-on-surface">Quick Credit</span>
                                </div>
                            </td>
                            <td class="px-xl py-lg text-right font-semibold text-on-surface">$5,500.00</td>
                            <td class="px-xl py-lg text-on-surface-variant">6 Months</td>
                            <td class="px-xl py-lg">
                                <span
                                    class="px-sm py-xs bg-error-container text-error font-label-md text-[10px] rounded uppercase tracking-widest font-bold">Overdue</span>
                            </td>
                            <td class="px-xl py-lg text-on-surface-variant text-body-sm">Feb 20, 2024</td>
                            <td class="px-xl py-lg text-center">
                                <div
                                    class="flex items-center justify-center gap-sm opacity-60 group-hover:opacity-100 transition-opacity">
                                    <button class="p-xs text-primary hover:bg-primary/10 rounded"><span
                                            class="material-symbols-outlined">visibility</span></button>
                                    <button class="p-xs text-secondary hover:bg-secondary/10 rounded"><span
                                            class="material-symbols-outlined">file_download</span></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 4 -->
                        <tr class="hover:bg-surface-container transition-colors group">
                            <td class="px-xl py-lg font-code text-primary font-semibold">FP-92110</td>
                            <td class="px-xl py-lg">
                                <div class="flex items-center gap-sm">
                                    <span class="material-symbols-outlined text-secondary">school</span>
                                    <span class="font-body-md text-on-surface">Education Fund</span>
                                </div>
                            </td>
                            <td class="px-xl py-lg text-right font-semibold text-on-surface">$12,000.00</td>
                            <td class="px-xl py-lg text-on-surface-variant">36 Months</td>
                            <td class="px-xl py-lg">
                                <span
                                    class="px-sm py-xs bg-surface-container-high text-on-surface-variant font-label-md text-[10px] rounded uppercase tracking-widest font-bold">Pending</span>
                            </td>
                            <td class="px-xl py-lg text-on-surface-variant text-body-sm">Mar 15, 2024</td>
                            <td class="px-xl py-lg text-center">
                                <div
                                    class="flex items-center justify-center gap-sm opacity-60 group-hover:opacity-100 transition-opacity">
                                    <button class="p-xs text-primary hover:bg-primary/10 rounded"><span
                                            class="material-symbols-outlined">visibility</span></button>
                                    <button class="p-xs text-secondary hover:bg-secondary/10 rounded"><span
                                            class="material-symbols-outlined">file_download</span></button>
                                </div>
                            </td>
                        </tr>
                        <!-- Row 5 -->
                        <tr class="hover:bg-surface-container transition-colors group">
                            <td class="px-xl py-lg font-code text-primary font-semibold">FP-92850</td>
                            <td class="px-xl py-lg">
                                <div class="flex items-center gap-sm">
                                    <span class="material-symbols-outlined text-secondary">directions_car</span>
                                    <span class="font-body-md text-on-surface">Auto Loan</span>
                                </div>
                            </td>
                            <td class="px-xl py-lg text-right font-semibold text-on-surface">$32,000.00</td>
                            <td class="px-xl py-lg text-on-surface-variant">60 Months</td>
                            <td class="px-xl py-lg">
                                <span
                                    class="px-sm py-xs bg-tertiary-fixed-dim/20 text-tertiary font-label-md text-[10px] rounded uppercase tracking-widest font-bold">Active</span>
                            </td>
                            <td class="px-xl py-lg text-on-surface-variant text-body-sm">Apr 02, 2024</td>
                            <td class="px-xl py-lg text-center">
                                <div
                                    class="flex items-center justify-center gap-sm opacity-60 group-hover:opacity-100 transition-opacity">
                                    <button class="p-xs text-primary hover:bg-primary/10 rounded"><span
                                            class="material-symbols-outlined">visibility</span></button>
                                    <button class="p-xs text-secondary hover:bg-secondary/10 rounded"><span
                                            class="material-symbols-outlined">file_download</span></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Footer -->
            <div
                class="px-xl py-md bg-surface-container-low border-t border-outline-variant flex flex-col sm:flex-row items-center justify-between gap-md">
                <p class="text-body-sm text-on-surface-variant">Showing <span class="font-bold text-on-surface">1 to
                        5</span> of <span class="font-bold text-on-surface">24</span> records</p>
                <div class="flex items-center gap-xs">
                    <button
                        class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-high disabled:opacity-50"
                        disabled="">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <button
                        class="w-10 h-10 flex items-center justify-center rounded-lg bg-primary text-on-primary font-label-lg">1</button>
                    <button
                        class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-high">2</button>
                    <button
                        class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-high">3</button>
                    <span class="px-sm text-on-surface-variant">...</span>
                    <button
                        class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-high">5</button>
                    <button
                        class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-high">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>
        </div>
    </section>
    <!-- Footer (Authority Source: Shared Components JSON) -->
    <footer
        class="w-full mt-auto py-xl px-margin-mobile md:px-xl border-t border-outline-variant bg-surface-container-highest">
        <div class="max-w-container-max mx-auto flex flex-col md:flex-row justify-between items-center gap-lg">
            <div class="flex flex-col items-center md:items-start">
                <span class="font-label-lg text-label-lg font-bold text-on-surface">FinancePro</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-sm">© 2024 FinancePro Solutions. All
                    rights reserved.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-xl">
                <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                    href="#">Privacy Policy</a>
                <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                    href="#">Terms of Service</a>
                <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                    href="#">Compliance</a>
                <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors"
                    href="#">Security</a>
            </div>
        </div>
    </footer>
    </main>
    <!-- Floating Action Button (Only on Mobile for loan context) -->
    <button
        class="md:hidden fixed bottom-margin-mobile right-margin-mobile w-14 h-14 bg-primary text-on-primary rounded-full shadow-2xl flex items-center justify-center z-50">
        <span class="material-symbols-outlined">add</span>
    </button>
    <script>
        // Simple Interaction: Mock row highlight or filter logging
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.addEventListener('click', (e) => {
                    // Prevent trigger if clicking action buttons
                    if (e.target.closest('button')) return;
                    console.log('Viewing details for:', row.cells[0].innerText);
                });
            });

            // Mock Search
            const searchInput = document.querySelector('input[type="text"]');
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        });
@endsection