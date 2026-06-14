@extends('layouts.admin')

@section('page-title', '編輯版本')

@section('content')
    <div class="mb-6">
        <h2 class="page-title">編輯版本</h2>
    </div>

    @include('admin.grades._form', [
        'action'      => route('grades.update', $grade),
        'method'      => 'PUT',
        'submitLabel' => '儲存變更',
    ])
@endsection

@push('scripts')
    @vite('resources/js/grades/form.js')
@endpush
