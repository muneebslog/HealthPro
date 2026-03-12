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
        Schema::create('receipt_prints', function (Blueprint $table) {
            $table->id();
            $table->string('print_type'); // invoice, shift_close, doctor_payout
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('doctor_payout_id')->nullable()->constrained('doctor_payouts')->nullOnDelete();
            $table->timestamp('printed_at');
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('printer_identifier')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_prints');
    }
};
