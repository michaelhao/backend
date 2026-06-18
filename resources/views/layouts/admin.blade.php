<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="session-lifetime" content="{{ config('session.lifetime') * 60 }}">
    <meta name="login-url" content="{{ route('login') }}">
    <meta name="user-id" content="{{ Auth::id() }}">
    <title>@yield('page-title', 'Dashboard') - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/layouts/admin.js'])
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="flex min-h-screen">
        {{-- 側邊欄 --}}
        <aside class="w-64 bg-white text-slate-700 border-r border-slate-200 flex flex-col flex-shrink-0">
            {{-- Logo / 站名 --}}
            <div class="h-16 flex items-center px-6">
                <span class="text-xl font-bold tracking-wide text-blue-600">{{ config('app.name', 'Laravel') }}</span>
            </div>

            {{-- 導覽連結 --}}
            <nav class="flex-1 px-4 py-6 space-y-1">
                @env('local')
                    <a href="{{ route('docs.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('docs.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        系統文件
                    </a>
                @endenv
                <x-permission name="Dashboard.index">
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        儀表板
                    </a>
                </x-permission>
                <x-permission name="User.index">
                    <a href="{{ route('users.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('users.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        使用者管理
                    </a>
                </x-permission>
                <x-permission name="Role.index">
                    <a href="{{ route('roles.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('roles.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        角色管理
                    </a>
                </x-permission>
                <x-permission name="Grade.index">
                    <a href="{{ route('grades.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('grades.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        版本管理
                    </a>
                </x-permission>
                <x-permission name="Shop.index">
                    <a href="{{ route('shops.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('shops.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        商店管理
                    </a>
                </x-permission>
                <x-permission name="Addon.index">
                    <a href="{{ route('addons.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('addons.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        加購功能管理
                    </a>
                </x-permission>
                <x-permission name="Bill.index">
                    <a href="{{ route('bills.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('bills.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        帳務管理
                    </a>
                </x-permission>
                <x-permission name="Conference.index">
                    <a href="{{ route('conferences.index') }}"
                       class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('conferences.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        說明會管理
                    </a>
                </x-permission>
                <x-permission name="Chat.index">
                    <a href="{{ route('chats.index') }}"
                       class="flex items-center justify-between px-4 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('chats.*') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        <span>聊天</span>
                        <span id="chat-unread-badge"
                              class="hidden inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-xs"></span>
                    </a>
                </x-permission>
            </nav>
        </aside>

        {{-- 右側主區域 --}}
        <div class="flex-1 flex flex-col">
            {{-- 頂部列 --}}
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 flex-shrink-0">
                <h1 class="text-lg font-semibold text-slate-800">@yield('page-title', 'Dashboard')</h1>

                <div class="flex items-center gap-4">
                    <span id="session-timer"></span>
                    <span class="text-sm text-slate-600">{{ Auth::user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
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
