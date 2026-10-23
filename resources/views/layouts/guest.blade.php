<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-10 bg-slate-100">
            <a href="/" class="flex flex-col items-center gap-3 text-center">
                <x-application-logo class="w-14 h-14" />
                <span>
                    <span class="block text-2xl font-extrabold tracking-[0.2em] text-slate-900">{{ config('qa.company_name') }}</span>
                    <span class="block text-sm text-slate-500 mt-0.5">Sales Call Quality Assurance</span>
                </span>
            </a>

            <div class="w-full sm:max-w-md mt-8 px-6 py-6 bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
