<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('type', 10);
            $table->string('calc_type', 10)->default('fixed');
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('applies_to_all')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_pay_component', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'pay_component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_pay_component');
        Schema::dropIfExists('pay_components');
    }
};
