@extends('layouts.admin')

@section('page-title', '聊天')

@section('content')
    <div id="chat-app"
         data-user-id="{{ Auth::id() }}"
         class="card flex h-[calc(100vh-8rem)] overflow-hidden p-0">

        {{-- 左欄：對話列表 --}}
        <div class="w-72 border-r border-slate-200 flex flex-col flex-shrink-0">
            <div class="p-3 border-b border-slate-200">
                <select id="start-user-select" class="form-control">
                    <option value="">＋ 開新對話…</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 列表載入骨架 --}}
            <div id="convo-skeleton" class="p-3 space-y-3">
                @for ($i = 0; $i < 6; $i++)
                    <div class="flex items-center gap-2.5">
                        <div class="chat-skeleton w-9 h-9 rounded-full"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="chat-skeleton h-3 w-2/3"></div>
                            <div class="chat-skeleton h-2.5 w-1/2"></div>
                        </div>
                    </div>
                @endfor
            </div>

            {{-- 列表空狀態 --}}
            <div id="convo-empty" class="hidden flex-1 flex flex-col items-center justify-center px-6 text-center">
                <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                </svg>
                <p class="mt-2 text-sm text-slate-400">還沒有任何對話</p>
                <p class="text-xs text-slate-400">從上方「開新對話」開始</p>
            </div>

            {{-- 列表載入失敗狀態 --}}
            <div id="convo-error" class="hidden flex-1 flex flex-col items-center justify-center px-6 text-center">
                <p class="text-sm text-slate-500">對話載入失敗</p>
                <button id="convo-retry" type="button" class="btn-primary mt-3">重新載入</button>
            </div>

            <ul id="conversation-list" class="hidden flex-1 overflow-y-auto"></ul>
        </div>

        {{-- 右欄：訊息串 --}}
        <div class="flex-1 flex flex-col min-w-0">
            <div id="chat-header" class="hidden h-14 border-b border-slate-200 flex items-center px-4 gap-3">
                <span class="relative">
                    <span id="chat-header-avatar" class="chat-avatar chat-avatar-sm"></span>
                    <span id="chat-online-dot" class="hidden chat-online-dot absolute -bottom-0.5 -right-0.5"></span>
                </span>
                <span id="chat-title" class="font-medium text-slate-700 truncate"></span>
            </div>

            {{-- 未選對話空狀態（預設顯示） --}}
            <div id="chat-empty-none" class="flex-1 flex flex-col items-center justify-center text-center px-6">
                <svg class="w-14 h-14 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
                <p class="mt-3 text-sm text-slate-400">選擇一個對話開始聊天</p>
            </div>

            {{-- 訊息載入骨架 --}}
            <div id="thread-skeleton" class="hidden flex-1 p-4 space-y-4 bg-slate-50">
                <div class="flex justify-start"><div class="chat-skeleton h-10 w-48 rounded-2xl"></div></div>
                <div class="flex justify-end"><div class="chat-skeleton h-8 w-40 rounded-2xl"></div></div>
                <div class="flex justify-start"><div class="chat-skeleton h-12 w-56 rounded-2xl"></div></div>
                <div class="flex justify-end"><div class="chat-skeleton h-8 w-32 rounded-2xl"></div></div>
            </div>

            {{-- 訊息載入失敗狀態 --}}
            <div id="thread-error" class="hidden flex-1 flex flex-col items-center justify-center text-center px-6">
                <p class="text-sm text-slate-500">訊息載入失敗</p>
                <button id="thread-retry" type="button" class="btn-primary mt-3">重新載入</button>
            </div>

            {{-- 訊息串（含跳到最新 pill） --}}
            <div id="thread-wrap" class="hidden relative flex-1 min-h-0">
                <div id="message-thread" class="absolute inset-0 overflow-y-auto p-4 bg-slate-50"></div>

                {{-- 對話無訊息空狀態 --}}
                <div id="chat-empty-messages" class="hidden absolute inset-0 flex flex-col items-center justify-center text-center px-6 pointer-events-none">
                    <p class="text-sm text-slate-400">尚無訊息</p>
                    <p class="text-xs text-slate-400">送出第一則訊息開始對話</p>
                </div>

                <button id="scroll-to-latest" type="button" class="chat-scroll-pill hidden">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                    新訊息
                </button>
            </div>

            <div id="typing-indicator" class="hidden px-4 py-1.5 flex items-center gap-2 text-xs text-slate-400">
                <span class="chat-typing-dots"><span></span><span></span><span></span></span>
                對方正在輸入…
            </div>

            <form id="message-form" class="hidden border-t border-slate-200 p-3 flex gap-2">
                <input id="message-input" type="text" autocomplete="off"
                       class="form-control flex-1" placeholder="輸入訊息…">
                <button type="submit" class="btn-primary">送出</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/chats/index.js')
@endpush
