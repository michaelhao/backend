@extends('layouts.admin')

@section('page-title', '新增版本')

@section('content')
    <div class="mb-6">
        <h2 class="page-title">新增版本</h2>
    </div>

    @include('admin.grades._form', [
        'action'      => route('grades.store'),
        'method'      => 'POST',
        'submitLabel' => '建立版本',
        'grade'       => null,
    ])
@endsection

@push('scripts')
    @vite('resources/js/grades/form.js')
@endpush
