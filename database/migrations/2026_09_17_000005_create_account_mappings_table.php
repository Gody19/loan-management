<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('mapping_key', 50);
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->timestamps();

            $table->unique(['organization_id', 'mapping_key']);
            $table->index(['mapping_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
