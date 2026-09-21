<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_application_guarantors', function (Blueprint $table) {
            $table->string('guarantor_name')->nullable()->after('guarantor_member_id');
            $table->string('guarantor_phone')->nullable()->after('guarantor_name');
            $table->string('guarantor_email')->nullable()->after('guarantor_phone');
            $table->string('guarantor_relationship')->nullable()->after('guarantor_email');
            $table->string('guarantor_occupation')->nullable()->after('guarantor_relationship');
            $table->string('guarantor_address')->nullable()->after('guarantor_occupation');
        });

        DB::statement('ALTER TABLE loan_application_guarantors MODIFY guarantor_member_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE loan_application_guarantors MODIFY guarantor_member_id BIGINT UNSIGNED NOT NULL');

        Schema::table('loan_application_guarantors', function (Blueprint $table) {
            $table->dropColumn([
                'guarantor_name', 'guarantor_phone', 'guarantor_email',
                'guarantor_relationship', 'guarantor_occupation', 'guarantor_address',
            ]);
        });
    }
};
