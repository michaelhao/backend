@extends('layouts.admin')

@section('page-title', '聊天')

@section('content')
    @php
        $chatProps = [
            'meId' => Auth::id(),
            'selectableUsers' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ];
    @endphp
    <div
        id="chat-app"
        data-props='@json($chatProps)'
    ></div>
@endsection

@push('scripts')
    @vite('resources/js/chats/index.js')
@endpush
