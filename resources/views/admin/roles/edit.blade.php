@extends('layouts.admin')

@section('page-title', '編輯角色')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">編輯角色：{{ $role->name }}</h2>
    </div>

    @include('admin.roles._form', [
        'action' => route('roles.update', $role),
        'method' => 'PUT',
        'submitLabel' => '更新',
        'role' => $role,
        'permissions' => $permissions,
        'rolePermissionIds' => $rolePermissionIds,
    ])
@endsection
