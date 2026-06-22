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

        $users = [
            [
                'fullname' => 'John Doe', 'username' => 'john', 'email' => 'user@example.com',
                'phone' => '255712345678', 'role' => 'user', 'is_active' => 'active',
                'password' => Hash::make('password123'),
            ],
            [
                'fullname' => 'Jane Smith', 'username' => 'jane', 'email' => 'officer@example.com',
                'phone' => '255723456789', 'role' => 'loan_officer', 'is_active' => 'active',
                'password' => Hash::make('password123'),
            ],
            [
                'fullname' => 'Robert Manager', 'username' => 'robert', 'email' => 'manager@example.com',
                'phone' => '255734567890', 'role' => 'manager', 'is_active' => 'active',
                'password' => Hash::make('password123'),
            ],
            [
                'fullname' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com',
                'phone' => '255745678901', 'role' => 'admin', 'is_active' => 'active',
                'password' => Hash::make('password123'),
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                array_merge($user, [
                    'nida_number' => fake()->unique()->numerify('########-#####-#####-##'),
                    'date_of_birth' => fake()->date('Y-m-d', '2000-01-01'),
                    'gender' => fake()->randomElement(['male', 'female']),
                ])
            );
        }

        $regularUser = User::where('email', 'user@example.com')->first();
        if ($regularUser) {
            $regularUser->loans()->createMany([
                [
                    'amount' => 100000, 'purpose' => 'Home Renovation',
                    'monthly_income' => 50000, 'tenure_months' => 24,
                    'status' => 'pending', 'description' => 'Planning to renovate kitchen and bathroom',
                ],
                [
                    'amount' => 50000, 'purpose' => 'Vehicle Purchase',
                    'monthly_income' => 50000, 'tenure_months' => 36,
                    'status' => 'approved', 'approved_at' => now(),
                    'description' => 'Need funds for car purchase',
                ],
            ]);

            $portfolio = $regularUser->portfolios()->create([
                'name' => 'Retirement Fund',
                'description' => 'Long-term investment portfolio',
                'total_value' => 250000,
                'status' => 'active',
            ]);

            $salaryCategory = Category::where('name', 'Salary')->first();
            $regularUser->transactions()->createMany([
                [
                    'portfolio_id' => $portfolio->id, 'type' => 'income',
                    'amount' => 50000, 'description' => 'Monthly Salary',
                    'category_id' => $salaryCategory->id,
                    'transaction_date' => now()->subDays(5),
                ],
                [
                    'type' => 'expense', 'amount' => 5000,
                    'description' => 'Grocery Shopping',
                    'category_id' => Category::where('name', 'Food & Groceries')->first()->id,
                    'transaction_date' => now()->subDays(2),
                ],
                [
                    'type' => 'expense', 'amount' => 2000,
                    'description' => 'Gas & Transportation',
                    'category_id' => Category::where('name', 'Transportation')->first()->id,
                    'transaction_date' => now()->subDay(),
                ],
            ]);

            $regularUser->budgets()->createMany([
                [
                    'name' => 'Monthly Groceries', 'limit_amount' => 8000,
                    'spent_amount' => 5000,
                    'category_id' => Category::where('name', 'Food & Groceries')->first()->id,
                    'period' => 'monthly', 'start_date' => now()->startOfMonth(),
                    'end_date' => now()->endOfMonth(), 'status' => 'active',
                ],
                [
                    'name' => 'Transportation Budget', 'limit_amount' => 4000,
                    'spent_amount' => 2000,
                    'category_id' => Category::where('name', 'Transportation')->first()->id,
                    'period' => 'monthly', 'start_date' => now()->startOfMonth(),
                    'end_date' => now()->endOfMonth(), 'status' => 'active',
                ],
            ]);
        }
    }
}
