<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vicoba_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('savings_product_id')->constrained()->restrictOnDelete();
            $table->string('account_number', 20)->unique();
            $table->date('opening_date');
            $table->string('status', 20)->default('active');
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('member_id');
            $table->index('organization_id');
            $table->index('branch_id');
            $table->index('vicoba_group_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_accounts');
    }
};
