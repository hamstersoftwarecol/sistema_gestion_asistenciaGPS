@php
    $user = auth()->user();
    $team = null;
    if ($user->can('attendance.approve') || $user->can('leave.approve')) {
        $team = \App\Models\User::visibleTo($user)->whereKeyNot($user->id)->pluck('id');
    }
    $pendingCorrections = $team && $user->can('attendance.approve') ? \App\Models\AttendanceCorrection::where('status', 'pending')->whereIn('user_id', $team)->count() : 0;
    $pendingOvertime = $team && $user->can('attendance.approve') ? \App\Models\Attendance::where('overtime_status', 'pending')->whereIn('user_id', $team)->count() : 0;
    $pendingLeave = $team && $user->can('leave.approve') ? \App\Models\LeaveRequest::where('status', 'pending')->whereIn('user_id', $team)->count() : 0;
    $section = 'mt-5 mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-indigo-400';
@endphp

<div class="flex h-14 shrink-0 items-center gap-2 border-b border-indigo-900 px-4">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-white">
        <x-application-logo class="h-8 w-8" />
        <span class="text-base font-bold leading-tight">GeoAsistencia<span class="block text-[11px] font-medium text-indigo-300">RR. HH. de campo</span></span>
    </a>
    <button type="button" class="ml-auto rounded p-1 text-indigo-300 hover:text-white lg:hidden" @click="sidebar = false" aria-label="Cerrar menú">
        <x-icon name="x" class="h-5 w-5" />
    </button>
</div>

<nav class="flex-1 space-y-0.5 overflow-y-auto px-2 pb-6 pt-3">
    <x-side-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Panel</x-side-link>

    @canany(['attendance.self', 'leave.request', 'payroll.view_own'])
        <div class="{{ $section }}">Mi espacio</div>
        @can('attendance.self')
            <x-side-link :href="route('my-attendance.index')" :active="request()->routeIs('my-attendance.index')" icon="camera">Mi asistencia</x-side-link>
            <x-side-link :href="route('my-attendance.history')" :active="request()->routeIs('my-attendance.history')" icon="clock">Mi historial</x-side-link>
            <x-side-link :href="route('corrections.index')" :active="request()->routeIs('corrections.index', 'corrections.create')" icon="pencil">Mis correcciones</x-side-link>
        @endcan
        @can('leave.request')
            <x-side-link :href="route('leave-requests.index')" :active="request()->routeIs('leave-requests.index', 'leave-requests.create')" icon="sun">Mis permisos</x-side-link>
        @endcan
        @can('payroll.view_own')
            <x-side-link :href="route('payslips.index')" :active="request()->routeIs('payslips.index')" icon="receipt">Mis recibos</x-side-link>
        @endcan
    @endcanany

    @canany(['attendance.view', 'attendance.approve', 'leave.approve', 'shifts.assign', 'live_map.view'])
        <div class="{{ $section }}">Operación</div>
        @can('live_map.view')
            <x-side-link :href="route('live-map.index')" :active="request()->routeIs('live-map.*')" icon="map">Mapa en vivo</x-side-link>
        @endcan
        @can('attendance.view')
            <x-side-link :href="route('register.index')" :active="request()->routeIs('register.*')" icon="list">Registro de asistencia</x-side-link>
        @endcan
        @can('shifts.assign')
            <x-side-link :href="route('shift-calendar.index')" :active="request()->routeIs('shift-calendar.*')" icon="calendar">Calendario de turnos</x-side-link>
        @endcan
        @can('attendance.approve')
            <x-side-link :href="route('corrections.approvals')" :active="request()->routeIs('corrections.approvals')" icon="check-circle" :badge="$pendingCorrections ?: null">Correcciones</x-side-link>
            <x-side-link :href="route('overtime.index')" :active="request()->routeIs('overtime.*')" icon="clock" :badge="$pendingOvertime ?: null">Horas extra</x-side-link>
        @endcan
        @can('leave.approve')
            <x-side-link :href="route('leave-requests.approvals')" :active="request()->routeIs('leave-requests.approvals')" icon="inbox" :badge="$pendingLeave ?: null">Aprobar permisos</x-side-link>
        @endcan
    @endcanany

    @canany(['employees.manage', 'org.manage', 'documents.manage', 'leave.manage'])
        <div class="{{ $section }}">Organización</div>
        @can('employees.manage')
            <x-side-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" icon="users">Empleados</x-side-link>
        @endcan
        @can('org.manage')
            <x-side-link :href="route('departments.index')" :active="request()->routeIs('departments.*')" icon="building">Departamentos</x-side-link>
            <x-side-link :href="route('sites.index')" :active="request()->routeIs('sites.*')" icon="pin">Sitios y geocercas</x-side-link>
            <x-side-link :href="route('shifts.index')" :active="request()->routeIs('shifts.*')" icon="clock">Turnos</x-side-link>
            <x-side-link :href="route('holidays.index')" :active="request()->routeIs('holidays.*')" icon="calendar">Festivos</x-side-link>
        @endcan
        @can('leave.manage')
            <x-side-link :href="route('leave-types.index')" :active="request()->routeIs('leave-types.*')" icon="sun">Tipos de permiso</x-side-link>
            <x-side-link :href="route('leave-balances.index')" :active="request()->routeIs('leave-balances.*')" icon="scale">Saldos de permisos</x-side-link>
        @endcan
        @can('documents.manage')
            <x-side-link :href="route('documents.index')" :active="request()->routeIs('documents.*')" icon="document">Documentos</x-side-link>
        @endcan
    @endcanany

    @canany(['payroll.manage', 'advances.manage', 'contractors.manage', 'settlements.manage'])
        <div class="{{ $section }}">Nómina</div>
        @can('payroll.manage')
            <x-side-link :href="route('payroll.index')" :active="request()->routeIs('payroll.*')" icon="banknotes">Períodos de nómina</x-side-link>
        @endcan
        @can('advances.manage')
            <x-side-link :href="route('advances.index')" :active="request()->routeIs('advances.*')" icon="banknotes">Anticipos y préstamos</x-side-link>
        @endcan
        @can('contractors.manage')
            <x-side-link :href="route('pay-components.index')" :active="request()->routeIs('pay-components.*')" icon="adjustments">Componentes de pago</x-side-link>
            <x-side-link :href="route('contractors.index')" :active="request()->routeIs('contractors.*')" icon="truck">Contratistas</x-side-link>
            <x-side-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')" icon="receipt">Facturas de contratistas</x-side-link>
        @endcan
        @can('settlements.manage')
            <x-side-link :href="route('settlements.index')" :active="request()->routeIs('settlements.*')" icon="scale">Liquidaciones finales</x-side-link>
        @endcan
    @endcanany

    @canany(['reports.view', 'settings.manage', 'roles.manage'])
        <div class="{{ $section }}">Administración</div>
        @can('reports.view')
            <x-side-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="chart">Informes y CSV</x-side-link>
        @endcan
        @can('settings.manage')
            <x-side-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')" icon="cog">Reglas de RR. HH.</x-side-link>
        @endcan
        @can('roles.manage')
            <x-side-link :href="route('roles.index')" :active="request()->routeIs('roles.*')" icon="shield">Roles y permisos</x-side-link>
        @endcan
    @endcanany
</nav>
