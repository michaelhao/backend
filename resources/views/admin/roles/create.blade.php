@extends('layouts.admin')

@section('page-title', '新增角色')

@section('content')
    <div class="mb-6">
        <h2 class="page-title">新增角色</h2>
    </div>

    @include('admin.roles._form', [
        'action' => route('roles.store'),
        'method' => 'POST',
        'submitLabel' => '建立',
        'role' => null,
        'permissions' => $permissions,
        'rolePermissionIds' => [],
    ])
@endsection
