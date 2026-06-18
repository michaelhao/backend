@extends('layouts.admin')

@section('page-title', '版本管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="page-title">版本管理</h2>
        <x-permission name="Grade.create">
            <a href="{{ route('grades.create') }}"
               class="btn-primary">
                新增版本
            </a>
        </x-permission>
    </div>

    @if (session('success'))
        <div class="flash flash-success flash-message">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="flash flash-error flash-message">{{ session('error') }}</div>
    @endif

    <div class="card">
        <table class="table">
            <thead class="table-head">
                <tr>
                    <th class="px-6 py-3">代碼</th>
                    <th class="px-6 py-3">名稱</th>
                    <th class="px-6 py-3">權重</th>
                    <th class="px-6 py-3">價格</th>
                    <th class="px-6 py-3">狀態</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($grades as $grade)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-mono text-gray-600">{{ $grade->code }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $grade->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $grade->weight }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ number_format($grade->price) }}</td>
                        <td class="px-6 py-4">
                            @if(auth()->user()->hasPermissionTo('Grade.update'))
                                <button type="button"
                                        class="toggle-btn relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none
                                               {{ $grade->status === \App\Enums\GradeStatus::Active ? 'bg-green-500' : 'bg-gray-300' }}"
                                        data-url="{{ route('grades.toggle', $grade) }}"
                                        data-name="{{ $grade->name }}"
                                        data-active="{{ $grade->status === \App\Enums\GradeStatus::Active ? '1' : '0' }}"
                                        title="{{ $grade->status === \App\Enums\GradeStatus::Active ? '點擊關閉' : '點擊啟用' }}">
                                    <span class="inline-block w-4 h-4 transform bg-white rounded-full shadow transition-transform
                                                 {{ $grade->status === \App\Enums\GradeStatus::Active ? 'translate-x-6' : 'translate-x-1' }}">
                                    </span>
                                </button>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                             {{ $grade->status === \App\Enums\GradeStatus::Active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $grade->status === \App\Enums\GradeStatus::Active ? '啟用' : '關閉' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 space-x-2">
                            <x-permission name="Grade.update">
                                <a href="{{ route('grades.edit', $grade) }}"
                                   class="text-blue-600 hover:text-blue-800">編輯</a>
                            </x-permission>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div id="grade-toggle"></div>
@endsection

@push('scripts')
    @vite('resources/js/grades/index.js')
@endpush
