<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'QA Agent') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-50" x-data="{ sidebarOpen: false }">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen"
         @click="sidebarOpen = false"
         x-cloak
         x-transition:enter="transition-opacity duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-20 bg-slate-900/50 backdrop-blur-sm lg:hidden"></div>

    {{-- Sidebar --}}
    <aside x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-30 w-60 bg-slate-900 flex flex-col
                  transition-transform duration-300 ease-in-out
                  lg:translate-x-0 lg:w-16">

        {{-- Logo --}}
        <div class="flex items-center h-14 px-3 shrink-0 border-b border-slate-800">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 bg-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                    </svg>
                </div>
                <span class="text-white font-semibold text-sm truncate lg:hidden">Sales QA</span>
            </a>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto">
            @php
                $nav = [
                    ['route'=>'dashboard','match'=>'dashboard','label'=>'Dashboard'],
                    ['route'=>'calls.index','match'=>'calls.*','label'=>'Calls'],
                    ['route'=>'agents.index','match'=>'agents.*','label'=>'Agents'],
                    ['route'=>'templates.index','match'=>'templates.*','label'=>'Templates'],
                ];
                $icons = [
                    'Dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
                    'Calls'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>',
                    'Agents'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
                    'Templates' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                ];
            @endphp
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}"
                   title="{{ $item['label'] }}"
                   class="flex items-center gap-3 px-2 py-2.5 rounded-xl transition-colors duration-150
                          {{ $active
                              ? 'bg-indigo-600 text-white'
                              : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <div class="w-8 h-8 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $icons[$item['label']] !!}
                        </svg>
                    </div>
                    <span class="text-sm font-medium lg:hidden">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- User --}}
        <div class="px-2 py-3 border-t border-slate-800 shrink-0" x-data="{ userOpen: false }">
            <button @click="userOpen = !userOpen"
                    class="flex items-center gap-3 w-full px-2 py-2 rounded-xl
                           text-slate-400 hover:text-white hover:bg-slate-800 transition-colors duration-150">
                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center
                            text-white text-xs font-bold shrink-0">
                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                </div>
                <div class="text-left lg:hidden min-w-0">
                    <p class="text-xs font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ Auth::user()->role }}</p>
                </div>
            </button>

            <div x-show="userOpen"
                 @click.away="userOpen = false"
                 x-cloak
                 class="absolute bottom-20 left-2 w-52 bg-white rounded-xl shadow-xl
                        border border-slate-100 py-1.5 z-50">
                <div class="px-4 py-2 border-b border-slate-100">
                    <p class="text-sm font-semibold text-slate-800 truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</p>
                </div>
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Profile
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-2 w-full px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="lg:pl-16 flex flex-col min-h-screen">

        {{-- Topbar --}}
        <header class="sticky top-0 z-10 h-14 bg-white border-b border-slate-200 flex items-center gap-3 px-4 shrink-0">
            <button @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
                @isset($header)
                    {{ $header }}
                @endisset
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 min-h-0">
            {{ $slot }}
        </main>
    </div>

</body>
</html>
