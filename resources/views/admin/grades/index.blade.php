@extends('layouts.admin')

@section('page-title', '版本管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">版本管理</h2>
        <x-permission name="Grade.create">
            <a href="{{ route('grades.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                新增版本
            </a>
        </x-permission>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 transition-opacity duration-500 flash-message">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 transition-opacity duration-500 flash-message">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">代碼</th>
                    <th class="px-6 py-3">名稱</th>
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
    <div id="toggle-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">確認狀態切換</h3>
            <p class="text-sm text-gray-600 mb-6">
                確定要<span id="toggle-modal-action" class="font-medium text-gray-900"></span>「<span id="toggle-modal-name" class="font-medium text-gray-900"></span>」嗎？
            </p>
            <div class="flex justify-end gap-3">
                <button id="toggle-modal-cancel" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">取消</button>
                <button id="toggle-modal-confirm" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">確認</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/grades/index.js')
@endpush
