<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1e1b4b">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/icon-192.png" type="image/png">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'GeoAsistencia') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="bg-gray-100 font-sans text-gray-800 antialiased" x-data="{ sidebar: false }">
        <div class="min-h-screen lg:flex">
            <div x-show="sidebar" x-cloak x-transition.opacity class="no-print fixed inset-0 z-30 bg-gray-900/50 lg:hidden" @click="sidebar = false"></div>

            <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
                   class="no-print fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full transform flex-col bg-indigo-950 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
                @include('layouts.navigation')
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="no-print sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-gray-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                    <button type="button" class="-ml-1 rounded p-1 text-gray-600 hover:bg-gray-100 lg:hidden" @click="sidebar = true" aria-label="Abrir menú">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>

                    <div class="min-w-0 flex-1 truncate text-base font-semibold text-gray-900 sm:text-lg">
                        {{ $header ?? '' }}
                    </div>

                    @php($unread = auth()->user()->unreadNotifications()->count())
                    <a href="{{ route('notifications.index') }}" class="relative rounded-full p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700" title="Notificaciones">
                        <x-icon name="bell" class="h-6 w-6" />
                        @if ($unread)
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 rounded-full p-0.5 pr-2 text-sm text-gray-700 hover:bg-gray-100">
                                <x-avatar :user="auth()->user()" />
                                <span class="hidden max-w-[10rem] truncate font-medium sm:inline">{{ auth()->user()->name }}</span>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500">
                                {{ auth()->user()->role?->name ?? 'Sin rol' }}
                            </div>
                            <x-dropdown-link :href="route('profile.edit')">Mi perfil</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    Cerrar sesión
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <main class="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-6">
                    <x-flash />
                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
