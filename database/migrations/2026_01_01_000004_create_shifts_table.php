<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->string('night_start', 5)->default('21:00');
            $table->string('night_end', 5)->default('06:00');
            $table->unsignedSmallInteger('overtime_cap_minutes')->default(240);
            $table->decimal('overtime_rate', 5, 2)->default(1.25);
            $table->decimal('night_rate', 5, 2)->default(1.35);
            $table->decimal('weekend_rate', 5, 2)->default(1.75);
            $table->decimal('holiday_rate', 5, 2)->default(1.75);
            $table->string('color', 7)->default('#4f46e5');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
