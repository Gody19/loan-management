<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_application_guarantors', function (Blueprint $table) {
            $table->string('nida_number')->nullable()->after('guarantor_member_id');
        });
    }

    public function down(): void
    {
        Schema::table('loan_application_guarantors', function (Blueprint $table) {
            $table->dropColumn('nida_number');
        });
    }
};
