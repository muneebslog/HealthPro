<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change payout_duration from string (weekly/biweekly/monthly) to integer (number of days).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('doctors', 'payout_interval_days')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->unsignedSmallInteger('payout_interval_days')->nullable()->after('is_on_payroll');
            });
        }

        if (Schema::hasColumn('doctors', 'payout_duration')) {
            DB::table('doctors')->whereNotNull('payout_duration')->update([
                'payout_interval_days' => DB::raw("CASE payout_duration WHEN 'weekly' THEN 7 WHEN 'biweekly' THEN 15 WHEN 'monthly' THEN 30 END"),
            ]);
        }

        if (Schema::hasColumn('doctors', 'payout_duration')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->dropColumn('payout_duration');
            });
        }

        if (Schema::hasColumn('doctors', 'payout_interval_days')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->renameColumn('payout_interval_days', 'payout_duration');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('payout_duration_str', 20)->nullable()->after('is_on_payroll');
        });

        DB::table('doctors')->whereNotNull('payout_duration')->update([
            'payout_duration_str' => DB::raw("CASE payout_duration WHEN 7 THEN 'weekly' WHEN 15 THEN 'biweekly' WHEN 30 THEN 'monthly' END"),
        ]);

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('payout_duration');
        });
        Schema::table('doctors', function (Blueprint $table) {
            $table->renameColumn('payout_duration_str', 'payout_duration');
        });
    }
};
