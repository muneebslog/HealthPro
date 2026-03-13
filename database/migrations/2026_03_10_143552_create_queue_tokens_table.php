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

        Schema::create('queue_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained('queues');
            $table->foreignId('visit_id')->constrained('visits');
            $table->foreignId('patient_id')->constrained('patients');
            $table->integer('token_number');
            $table->enum('status', ['reserved', 'waiting', 'called', 'completed', 'skipped', 'cancelled']);
            $table->timestamp('reserved_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_tokens');
    }
};
