@extends('layouts.admin')

@section('page-title', '編輯附加功能')

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <h2 class="text-2xl font-bold text-gray-800">編輯附加功能</h2>
        @if ($addon->syncing === \App\Enums\AddonSyncing::Syncing)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                同步中...
            </span>
        @endif
    </div>

    @include('admin.addons._form', [
        'action'      => route('addons.update', $addon),
        'method'      => 'PUT',
        'submitLabel' => '儲存變更',
    ])
@endsection
