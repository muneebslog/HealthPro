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
        Schema::disableForeignKeyConstraints();

        Schema::create('invoice_services', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('serviceprice_id');
            $table->foreignId('service_price_id')->constrained('service_prices');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->integer('price');
            $table->integer('discount');
            $table->integer('final_amount');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_services');
    }
};
