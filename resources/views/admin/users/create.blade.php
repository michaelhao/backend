@extends('layouts.admin')

@section('page-title', '新增使用者')

@section('content')
    <div class="mb-6">
        <h2 class="page-title">新增使用者</h2>
    </div>

    @include('admin.users._form', [
        'action' => route('users.store'),
        'method' => 'POST',
        'submitLabel' => '建立使用者',
        'user' => null,
    ])
@endsection
