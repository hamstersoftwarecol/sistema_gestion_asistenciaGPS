<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla única de asistencia: cada fila es un empleado en un día.
 * La lista de asistencia, los informes, la nómina y las facturas de
 * contratistas se calculan exclusivamente a partir de estas filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('day_type', 10)->default('workday');
            $table->string('status', 14)->default('present');

            $table->dateTime('check_in_at')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy')->nullable();
            $table->unsignedInteger('check_in_distance_m')->nullable();
            $table->boolean('check_in_out_of_zone')->default(false);
            $table->string('check_in_selfie')->nullable();

            $table->dateTime('check_out_at')->nullable();
            $table->foreignId('check_out_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy')->nullable();
            $table->unsignedInteger('check_out_distance_m')->nullable();
            $table->boolean('check_out_out_of_zone')->default(false);
            $table->string('check_out_selfie')->nullable();

            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('regular_minutes')->default(0);
            $table->unsignedInteger('holiday_minutes')->default(0);
            $table->unsignedInteger('night_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('approved_overtime_minutes')->default(0);
            $table->string('overtime_status', 10)->default('none');
            $table->foreignId('overtime_reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained()->nullOnDelete();

            $table->string('source', 12)->default('app');
            $table->boolean('is_offline')->default(false);
            $table->string('device_id')->nullable();
            $table->boolean('regularized')->default(false);
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->dateTime('requested_check_in')->nullable();
            $table->dateTime('requested_check_out')->nullable();
            $table->text('reason');
            $table->string('status', 12)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendances');
    }
};
