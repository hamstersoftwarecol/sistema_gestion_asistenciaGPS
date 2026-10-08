<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->nullable()->unique();
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pay_type', 10)->default('monthly');
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->string('bank_account')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('status', 12)->default('active');
            $table->string('google_id')->nullable()->index();
            $table->string('avatar')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('device_bound_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('contractor_id');
            $table->dropUnique(['employee_code']);
            $table->dropIndex(['google_id']);
            $table->dropColumn([
                'employee_code', 'national_id', 'phone', 'position', 'pay_type', 'monthly_salary',
                'hourly_rate', 'bank_account', 'hire_date', 'termination_date', 'status',
                'google_id', 'avatar', 'device_id', 'device_bound_at',
            ]);
        });
    }
};
