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
            <ul id="conversation-list" class="flex-1 overflow-y-auto"></ul>
        </div>

        {{-- 右欄：訊息串 --}}
        <div class="flex-1 flex flex-col">
            <div class="h-14 border-b border-slate-200 flex items-center px-4 gap-2">
                <span id="chat-online-dot" class="hidden w-2.5 h-2.5 rounded-full bg-green-500"></span>
                <span id="chat-title" class="font-medium text-slate-700">選擇一個對話開始聊天</span>
            </div>
            <div id="message-thread" class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50"></div>
            <div id="typing-indicator" class="hidden px-4 py-1 text-xs text-slate-400">對方正在輸入…</div>
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
