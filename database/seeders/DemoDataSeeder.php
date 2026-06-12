<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create Categories
        $categories = [
            ['name' => 'Salary', 'type' => 'income', 'color' => '#4CAF50'],
            ['name' => 'Food & Groceries', 'type' => 'expense', 'color' => '#FF9800'],
            ['name' => 'Transportation', 'type' => 'expense', 'color' => '#2196F3'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#E91E63'],
            ['name' => 'Utilities', 'type' => 'expense', 'color' => '#009688'],
            ['name' => 'Stocks', 'type' => 'investment', 'color' => '#673AB7'],
            ['name' => 'Bonds', 'type' => 'investment', 'color' => '#9C27B0'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }

        // Create Demo Users
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'user@example.com',
                'password' => Hash::make('password123'),
                'role' => 'user',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'loanoffier@example.com',
                'password' => Hash::make('password123'),
                'role' => 'loan_officer',
            ],
            [
                'name' => 'Robert Manager',
                'email' => 'manager@example.com',
                'password' => Hash::make('password123'),
                'role' => 'manager',
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(['email' => $user['email']], $user);
        }

        // Create sample loans for user
        $regularUser = User::where('email', 'user@example.com')->first();
        if ($regularUser) {
            $regularUser->loans()->createMany([
                [
                    'amount' => 100000,
                    'purpose' => 'Home Renovation',
                    'monthly_income' => 50000,
                    'tenure_months' => 24,
                    'status' => 'pending',
                    'description' => 'Planning to renovate kitchen and bathroom',
                ],
                [
                    'amount' => 50000,
                    'purpose' => 'Vehicle Purchase',
                    'monthly_income' => 50000,
                    'tenure_months' => 36,
                    'status' => 'approved',
                    'description' => 'Need funds for car purchase',
                    'approved_at' => now(),
                ],
            ]);
        }

        // Create sample portfolios
        if ($regularUser) {
            $portfolio = $regularUser->portfolios()->create([
                'name' => 'Retirement Fund',
                'description' => 'Long-term investment portfolio',
                'total_value' => 250000,
                'status' => 'active',
            ]);

            // Create sample transactions
            $salaryCategory = Category::where('name', 'Salary')->first();
            $regularUser->transactions()->createMany([
                [
                    'portfolio_id' => $portfolio->id,
                    'type' => 'income',
                    'amount' => 50000,
                    'description' => 'Monthly Salary',
                    'category_id' => $salaryCategory->id,
                    'transaction_date' => now()->subDays(5),
                ],
                [
                    'type' => 'expense',
                    'amount' => 5000,
                    'description' => 'Grocery Shopping',
                    'category_id' => Category::where('name', 'Food & Groceries')->first()->id,
                    'transaction_date' => now()->subDays(2),
                ],
                [
                    'type' => 'expense',
                    'amount' => 2000,
                    'description' => 'Gas & Transportation',
                    'category_id' => Category::where('name', 'Transportation')->first()->id,
                    'transaction_date' => now()->subDay(),
                ],
            ]);
        }

        // Create sample budgets
        if ($regularUser) {
            $regularUser->budgets()->createMany([
                [
                    'name' => 'Monthly Groceries',
                    'limit_amount' => 8000,
                    'spent_amount' => 5000,
                    'category_id' => Category::where('name', 'Food & Groceries')->first()->id,
                    'period' => 'monthly',
                    'start_date' => now()->startOfMonth(),
                    'end_date' => now()->endOfMonth(),
                    'status' => 'active',
                ],
                [
                    'name' => 'Transportation Budget',
                    'limit_amount' => 4000,
                    'spent_amount' => 2000,
                    'category_id' => Category::where('name', 'Transportation')->first()->id,
                    'period' => 'monthly',
                    'start_date' => now()->startOfMonth(),
                    'end_date' => now()->endOfMonth(),
                    'status' => 'active',
                ],
            ]);
        }
    }
}
