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
            $table->foreign('queue_id')->references('id')->on('Queue');
            $table->bigInteger('visit_id');
            $table->foreign('visit_id')->references('id')->on('Visit');
            $table->bigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('Patient');
            $table->integer('token_number');
            $table->enum('status', ["waiting","called","completed","skipped","cancelled"]);
            $table->timestamp('reserved_at');
            $table->timestamp('paid_at');
            $table->timestamp('called_at');
            $table->timestamp('completed_at');
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
