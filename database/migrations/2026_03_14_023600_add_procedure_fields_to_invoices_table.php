<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('paid_amount')->default(0)->after('total_amount');
            $table->foreignId('procedure_admission_id')->nullable()->after('visit_id')->constrained('procedure_admissions')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procedure_admission_id');
            $table->dropColumn('paid_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->nullable(false)->change();
        });
    }
};
