@extends('layouts.admin')

@section('page-title', '新增說明會')

@section('content')
    <div class="mb-6">
        <h2 class="page-title">新增說明會</h2>
    </div>

    @include('admin.conferences._form', [
        'action' => route('conferences.store'),
        'method' => 'POST',
        'submitLabel' => '建立說明會',
        'conference' => null,
    ])
@endsection
