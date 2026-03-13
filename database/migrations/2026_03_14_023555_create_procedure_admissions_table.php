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
        Schema::create('procedure_admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('package_name');
            $table->integer('full_price');
            $table->foreignId('operation_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('operation_date')->nullable();
            $table->string('room')->nullable();
            $table->string('bed')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedure_admissions');
    }
};
