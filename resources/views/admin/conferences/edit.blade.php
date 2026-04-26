@extends('layouts.admin')

@section('page-title', '編輯說明會')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">編輯說明會</h2>
    </div>

    @include('admin.conferences._form', [
        'action' => route('conferences.update', $conference),
        'method' => 'PUT',
        'submitLabel' => '儲存變更',
    ])
@endsection
