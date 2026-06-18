@extends('layouts.admin')

@section('page-title', '聊天')

@section('content')
    <div
        id="chat-app"
        data-props="@json(['meId' => Auth::id(), 'selectableUsers' => $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()])"
    ></div>
@endsection

@push('scripts')
    @vite('resources/js/chats/index.js')
@endpush
