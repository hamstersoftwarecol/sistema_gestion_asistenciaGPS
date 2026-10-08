<?php

use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\AttendanceRegisterController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ContractorInvoiceController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LiveMapController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\PayComponentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SelfieController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ShiftCalendarController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Mi asistencia: entrada/salida con selfie + GPS, historial y correcciones.
    Route::middleware('can:attendance.self')->group(function () {
        Route::get('my-attendance', [MyAttendanceController::class, 'index'])->name('my-attendance.index');
        Route::post('my-attendance/punch', [MyAttendanceController::class, 'punch'])->name('my-attendance.punch');
        Route::post('my-attendance/sync', [MyAttendanceController::class, 'sync'])->name('my-attendance.sync');
        Route::get('my-attendance/token', [MyAttendanceController::class, 'token'])->name('my-attendance.token');
        Route::get('my-attendance/history', [MyAttendanceController::class, 'history'])->name('my-attendance.history');

        Route::get('corrections', [CorrectionController::class, 'index'])->name('corrections.index');
        Route::get('corrections/create', [CorrectionController::class, 'create'])->name('corrections.create');
        Route::post('corrections', [CorrectionController::class, 'store'])->name('corrections.store');
    });

    Route::get('attendance/{attendance}/selfie/{type}', SelfieController::class)
        ->whereIn('type', ['in', 'out'])
        ->name('attendance.selfie');

    // Permisos del empleado.
    Route::middleware('can:leave.request')->group(function () {
        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::get('leave-requests/create', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    });
    Route::post('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
    Route::get('leave-requests/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])->name('leave-requests.attachment');

    // Recibos de pago.
    Route::get('my-payslips', [PayslipController::class, 'index'])->middleware('can:payroll.view_own')->name('payslips.index');
    Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');

    // Aprobaciones.
    Route::middleware('can:attendance.approve')->group(function () {
        Route::get('approvals/corrections', [CorrectionController::class, 'approvals'])->name('corrections.approvals');
        Route::post('approvals/corrections/{correction}/approve', [CorrectionController::class, 'approve'])->name('corrections.approve');
        Route::post('approvals/corrections/{correction}/reject', [CorrectionController::class, 'reject'])->name('corrections.reject');
        Route::get('approvals/overtime', [OvertimeController::class, 'index'])->name('overtime.index');
        Route::post('approvals/overtime', [OvertimeController::class, 'review'])->name('overtime.review');
    });

    Route::middleware('can:leave.approve')->group(function () {
        Route::get('approvals/leave', [LeaveRequestController::class, 'approvals'])->name('leave-requests.approvals');
        Route::post('approvals/leave/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('approvals/leave/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    });

    // Registro de asistencia (masivo e individual).
    Route::get('register', [AttendanceRegisterController::class, 'index'])->middleware('can:attendance.view')->name('register.index');
    Route::middleware('can:attendance.mark')->group(function () {
        Route::post('register', [AttendanceRegisterController::class, 'store'])->name('register.store');
        Route::get('register/{user}/{date}', [AttendanceRegisterController::class, 'edit'])->name('register.edit');
        Route::put('register/{user}/{date}', [AttendanceRegisterController::class, 'update'])->name('register.update');
        Route::delete('register/{user}/{date}', [AttendanceRegisterController::class, 'destroy'])->name('register.destroy');
    });

    Route::middleware('can:shifts.assign')->group(function () {
        Route::get('shift-calendar', [ShiftCalendarController::class, 'index'])->name('shift-calendar.index');
        Route::post('shift-calendar', [ShiftCalendarController::class, 'assign'])->name('shift-calendar.assign');
    });

    Route::middleware('can:live_map.view')->group(function () {
        Route::get('live-map', [LiveMapController::class, 'index'])->name('live-map.index');
        Route::get('live-map/data', [LiveMapController::class, 'data'])->name('live-map.data');
    });

    // Organización.
    Route::resource('employees', EmployeeController::class)->middleware('can:employees.manage');
    Route::post('employees/{employee}/reset-device', [EmployeeController::class, 'resetDevice'])->middleware('can:employees.manage')->name('employees.reset-device');

    Route::middleware('can:org.manage')->group(function () {
        Route::resource('departments', DepartmentController::class)->except('show');
        Route::resource('sites', SiteController::class)->except('show');
        Route::resource('shifts', ShiftController::class)->except('show');
        Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('can:leave.manage')->group(function () {
        Route::resource('leave-types', LeaveTypeController::class)->except('show');
        Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::post('leave-balances', [LeaveBalanceController::class, 'store'])->name('leave-balances.store');
        Route::put('leave-balances/{balance}', [LeaveBalanceController::class, 'update'])->name('leave-balances.update');
        Route::post('leave-balances/allocate', [LeaveBalanceController::class, 'allocate'])->name('leave-balances.allocate');
    });

    Route::middleware('can:contractors.manage')->group(function () {
        Route::resource('contractors', ContractorController::class)->except('show');
        Route::resource('pay-components', PayComponentController::class)->except('show');
        Route::resource('invoices', ContractorInvoiceController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    });

    Route::resource('advances', AdvanceController::class)->only(['index', 'store', 'destroy'])->middleware('can:advances.manage');

    Route::middleware('can:documents.manage')->group(function () {
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // Nómina.
    Route::middleware('can:payroll.manage')->group(function () {
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('payroll/{period}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{period}/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('payroll/{period}/lock', [PayrollController::class, 'lock'])->name('payroll.lock');
        Route::post('payroll/{period}/unlock', [PayrollController::class, 'unlock'])->name('payroll.unlock');
        Route::get('payroll/{period}/export', [PayrollController::class, 'export'])->name('payroll.export');
        Route::delete('payroll/{period}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
    });

    Route::middleware('can:settlements.manage')->group(function () {
        Route::resource('settlements', SettlementController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::post('settlements/{settlement}/recalculate', [SettlementController::class, 'recalculate'])->name('settlements.recalculate');
        Route::post('settlements/{settlement}/approve', [SettlementController::class, 'approve'])->name('settlements.approve');
        Route::post('settlements/{settlement}/paid', [SettlementController::class, 'markPaid'])->name('settlements.paid');
    });

    // Informes y administración.
    Route::get('reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->middleware('can:reports.view')->name('reports.show');

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/backup', [SettingController::class, 'backup'])->name('settings.backup');
    });

    Route::middleware('can:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});

require __DIR__.'/auth.php';
