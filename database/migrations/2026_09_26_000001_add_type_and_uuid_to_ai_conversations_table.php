<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('type')->default('private')->index()->after('status');
            $table->uuid('uuid')->nullable()->unique()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['type', 'uuid']);
        });
    }
};
