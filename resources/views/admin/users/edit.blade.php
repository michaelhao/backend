@extends('layouts.admin')

@section('page-title', '編輯使用者')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">編輯使用者：{{ $user->name }}</h2>
    </div>

    @include('admin.users._form', [
        'action' => route('users.update', $user),
        'method' => 'PUT',
        'submitLabel' => '儲存變更',
        'user' => $user,
    ])
@endsection
