<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('js/tab-session.js') }}?v={{ filemtime(public_path('js/tab-session.js')) }}"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CompliTrack') | USTP Claveria</title>
    <link rel="preload" href="{{ asset('fonts/inter/inter-3100e775e861.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/plusjakartasans/plusjakartasans-153fc85b7029.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ filemtime(public_path('css/fonts.css')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/page-notices.css') }}?v={{ filemtime(public_path('css/page-notices.css')) }}">
    @guest
        <link rel="stylesheet" href="{{ asset('css/auth-login.css') }}">
    @endguest
    @auth
        @php
            $portalStyles = [
                0 => 'css/super-admin-portal.css',
                1 => 'css/admin-portal.css',
                2 => 'css/super-admin-portal.css',
                3 => 'css/super-admin-portal.css',
            ];
            $portalRole = (int) auth()->user()->roleId;
            $profilePath = match ($portalRole) {
                0 => '/super-admin/profile',
                1 => '/admin/profile',
                2 => '/auditor/profile',
                3 => '/auditee/profile',
                default => '/profile',
            };
        @endphp
        @if (isset($portalStyles[$portalRole]))
            <link rel="stylesheet" href="{{ asset($portalStyles[$portalRole]) }}">
        @endif
    @endauth
    @auth @if (in_array($portalRole, [2, 3], true))<link rel="stylesheet" href="{{ asset('css/auditor-ui.css') }}?v={{ filemtime(public_path('css/auditor-ui.css')) }}">@endif @endauth
    @auth @if ($portalRole === 1)<link rel="stylesheet" href="{{ asset('css/admin-ui.css') }}?v={{ filemtime(public_path('css/admin-ui.css')) }}">@endif @endauth
    @auth
        <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}?v={{ filemtime(public_path('css/sidebar.css')) }}">
    @endauth
    @stack('styles')
    @auth
        <link rel="stylesheet" href="{{ asset('css/portal-header.css') }}?v={{ filemtime(public_path('css/portal-header.css')) }}">
    @endauth
</head>
<body class="min-h-screen" data-user-id="{{ auth()->id() }}" data-portal-role="{{ auth()->user()?->roleId }}">
    @auth
        @php($portalLayout = 'app-container')
        @php($mainLayout = 'main-wrapper')
        @php($contentLayout = match ($portalRole) {
            0 => 'view-content-container',
            1 => 'content-area',
            2 => 'view-content-container',
            3 => 'view-content-container',
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
    @auth @if ($portalRole === 1)<x-atl-review-modal />@endif @endauth
</body>
</html>
