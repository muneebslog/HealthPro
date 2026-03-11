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
            $table->bigInteger('visit_id');
            $table->foreign('visit_id')->references('id')->on('Visit');
            $table->bigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('Service');
            $table->bigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('Doctor');
            $table->enum('status', ["assigned","waiting","inprogress","completed"]);
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
