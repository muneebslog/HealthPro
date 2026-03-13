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

        Schema::create('visit_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('doctor_id')->constrained('doctors');
            $table->enum('status', ['assigned', 'waiting', 'inprogress', 'completed']);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_services');
    }
};
