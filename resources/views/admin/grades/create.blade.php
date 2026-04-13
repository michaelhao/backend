@extends('layouts.admin')

@section('page-title', '新增版本')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">新增版本</h2>
    </div>

    @include('admin.grades._form', [
        'action'      => route('grades.store'),
        'method'      => 'POST',
        'submitLabel' => '建立版本',
        'grade'       => null,
    ])
@endsection
