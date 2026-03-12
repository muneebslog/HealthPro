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
            $table->bigInteger('queue_id');
            $table->foreign('queue_id')->references('id')->on('queues');
            $table->bigInteger('visit_id');
            $table->foreign('visit_id')->references('id')->on('visits');
            $table->bigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients');
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
