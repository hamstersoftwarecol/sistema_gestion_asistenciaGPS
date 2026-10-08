<x-app-layout>
    <x-slot name="header">Mi perfil</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="card p-4 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card p-4 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <p class="text-xs text-gray-500">Las cuentas de empleados se dan de baja desde RR. HH. para conservar el historial de asistencia y nómina.</p>
    </div>
</x-app-layout>
