<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1e1b4b">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/icon-192.png" type="image/png">

        <title>{{ config('app.name', 'GeoAsistencia') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen">
            <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-indigo-950 p-10 text-white lg:flex">
                <div class="flex items-center gap-3">
                    <x-application-logo class="h-10 w-10" />
                    <span class="text-xl font-bold">GeoAsistencia RR. HH.</span>
                </div>
                <div class="relative z-10 max-w-md">
                    <h1 class="text-3xl font-bold leading-tight">Asistencia con selfie y GPS para personal de campo.</h1>
                    <ul class="mt-6 space-y-3 text-indigo-200">
                        <li class="flex gap-2"><x-icon name="pin" class="h-5 w-5 text-amber-300" /> Geocerca del sitio más cercano (Haversine) y marcación fuera de zona.</li>
                        <li class="flex gap-2"><x-icon name="offline" class="h-5 w-5 text-amber-300" /> Cola sin conexión que se sincroniza al recuperar señal.</li>
                        <li class="flex gap-2"><x-icon name="banknotes" class="h-5 w-5 text-amber-300" /> Turnos, permisos, horas extra y nómina desde una única tabla de asistencia.</li>
                    </ul>
                </div>
                <div class="text-xs text-indigo-400">© {{ date('Y') }} {{ setting('company_name') }}</div>
                <svg class="absolute -bottom-24 -right-24 h-96 w-96 text-indigo-900" viewBox="0 0 200 200" fill="currentColor" aria-hidden="true"><circle cx="100" cy="100" r="100"/></svg>
            </div>

            <div class="flex flex-1 flex-col items-center justify-center bg-gray-50 px-6 py-12">
                <div class="mb-6 flex items-center gap-2 lg:hidden">
                    <x-application-logo class="h-10 w-10" />
                    <span class="text-lg font-bold text-indigo-950">GeoAsistencia RR. HH.</span>
                </div>
                <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white px-6 py-8 shadow-sm sm:px-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
