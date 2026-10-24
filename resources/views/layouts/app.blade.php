<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CompliTrack') | USTP Claveria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @guest
        <link rel="stylesheet" href="{{ asset('css/auth-login.css') }}">
    @endguest
    @auth
        @php
            $portalStyles = [
                0 => 'css/super-admin-portal.css',
                1 => 'css/admin-portal.css',
                2 => 'css/auditor-portal.css',
                3 => 'css/auditee-portal.css',
            ];
            $portalRole = (int) auth()->user()->roleId;
        @endphp
        @if (isset($portalStyles[$portalRole]))
            <link rel="stylesheet" href="{{ asset($portalStyles[$portalRole]) }}">
        @endif
    @endauth
</head>
<body class="min-h-screen" data-user-id="{{ auth()->id() }}">
    @auth
        <div id="server-offline-banner" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-950/95 p-6 text-center text-white" role="alert" aria-live="assertive">
            <div class="max-w-lg rounded-xl border border-white/15 bg-slate-900 p-8 shadow-2xl">
                <h2 class="mb-3 text-2xl font-bold">Server connection lost</h2>
                <p class="text-base text-slate-200">CompliTrack is offline. You cannot use the dashboard while the server is unavailable. Reconnecting…</p>
            </div>
        </div>
        @php($portalLayout = $portalRole === 3 ? 'app-layout' : 'app-container')
        @php($mainLayout = $portalRole === 2 ? 'main-content' : 'main-wrapper')
        @php($contentLayout = match ($portalRole) {
            0 => 'view-content-container',
            1 => 'content-area',
            2 => 'content-body',
            3 => 'content-viewport',
            default => '',
        })
        <div class="{{ $portalLayout }}" x-data="{ sidebarOpen: false }">
            <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-ustp-dark/50 lg:hidden" @click="sidebarOpen = false"></div>
            @include('partials.sidebar')
            <div class="{{ $mainLayout }}">
                @include('partials.navbar')
                <main id="main-content" class="{{ $contentLayout }}">
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        @yield('content')
    @endauth
    @stack('scripts')
</body>
</html>
