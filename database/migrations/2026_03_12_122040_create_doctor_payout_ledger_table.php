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
        Schema::create('doctor_payout_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_payout_id')->constrained('doctor_payouts')->cascadeOnDelete();
            $table->foreignId('invoice_service_id')->constrained('invoice_services')->cascadeOnDelete();
            $table->unsignedBigInteger('share_amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_payout_ledger');
    }
};
