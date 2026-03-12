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
        $tables = [
            'visits',
            'invoices',
            'queue_tokens',
            'visit_services',
            'invoice_services',
            'queues',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->after('id')->constrained('shifts')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->after('shift_id')->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'visits',
            'invoices',
            'queue_tokens',
            'visit_services',
            'invoice_services',
            'queues',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->dropForeign(['created_by']);
            });
        }
    }
};
