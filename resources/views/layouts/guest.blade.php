<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Build, publish, and collect responses with AI Form Builder.">

        <title>{{ $title ?? config('app.name') }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div class="text-center">
                <a href="{{ route('login') }}" wire:navigate class="inline-flex flex-col items-center gap-2">
                    <x-application-logo class="w-16 h-16 fill-current text-indigo-600" />
                    <span class="text-xl font-semibold text-gray-800 tracking-tight">{{ config('app.name') }}</span>
                </a>
                <p class="mt-2 text-sm text-gray-500 max-w-xs mx-auto">
                    Build, publish, and collect responses — manually, with AI, or from Word &amp; Excel.
                </p>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>

            <footer class="mt-8 mb-6 text-center text-xs text-gray-400 space-y-1">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                <p>v1.0.0</p>
            </footer>
        </div>
    </body>
</html>
