@extends('layouts.admin')

@section('page-title', '新增附加功能')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">新增附加功能</h2>
    </div>

    @include('admin.addons._form', [
        'action'           => route('addons.store'),
        'method'           => 'POST',
        'submitLabel'      => '建立附加功能',
        'addon'            => null,
        'selectedGradeIds' => [],
    ])
@endsection
