<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="session-lifetime" content="{{ config('session.lifetime') * 60 }}">
    <title>@yield('page-title', 'Dashboard') - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/layouts/admin.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex min-h-screen">
        {{-- 側邊欄 --}}
        <aside class="w-64 bg-gray-900 text-gray-100 flex flex-col flex-shrink-0">
            {{-- Logo / 站名 --}}
            <div class="h-16 flex items-center px-6 border-b border-gray-800">
                <span class="text-xl font-bold tracking-wide">{{ config('app.name', 'Laravel') }}</span>
            </div>

            {{-- 導覽連結 --}}
            <nav class="flex-1 px-4 py-6 space-y-1">
                <x-permission name="Dashboard.index">
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('dashboard') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        儀表板
                    </a>
                </x-permission>
                <x-permission name="Post.index">
                    <a href="#"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors">
                        文章管理
                    </a>
                </x-permission>
                <x-permission name="User.index">
                    <a href="{{ route('users.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('users.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        使用者管理
                    </a>
                </x-permission>
                <x-permission name="Role.index">
                    <a href="{{ route('roles.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('roles.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        角色管理
                    </a>
                </x-permission>
                <x-permission name="Grade.index">
                    <a href="{{ route('grades.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('grades.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        版本管理
                    </a>
                </x-permission>
                <x-permission name="Shop.index">
                    <a href="{{ route('shops.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('shops.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        商店管理
                    </a>
                </x-permission>
                <x-permission name="Addon.index">
                    <a href="{{ route('addons.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('addons.*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        加購功能管理
                    </a>
                </x-permission>
            </nav>
        </aside>

        {{-- 右側主區域 --}}
        <div class="flex-1 flex flex-col">
            {{-- 頂部列 --}}
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
                <h1 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h1>

                <div class="flex items-center gap-4">
                    <span id="session-timer" class="text-sm text-gray-400 font-mono" title="Session 剩餘時間"></span>
                    <span class="text-sm text-gray-600">{{ Auth::user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                            登出
                        </button>
                    </form>
                </div>
            </header>

            {{-- 內容區域 --}}
            <main class="flex-1 p-6">
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
