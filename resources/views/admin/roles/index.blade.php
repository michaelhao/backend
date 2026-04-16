@extends('layouts.admin')

@section('page-title', '角色管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">角色管理</h2>
        <x-permission name="Role.create">
            <a href="{{ route('roles.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                新增角色
            </a>
        </x-permission>
    </div>

    <div class="flash-area">
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 transition-opacity duration-500 flash-message">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 transition-opacity duration-500 flash-message">{{ session('error') }}</div>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">角色名稱</th>
                    <th class="px-6 py-3">說明</th>
                    <th class="px-6 py-3">預設頁面</th>
                    <th class="px-6 py-3">權限數</th>
                    <th class="px-6 py-3">使用者數</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($roles as $role)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $role->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $role->description }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $permissionDescriptions[$role->default_route] ?? $role->default_route }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $role->permissions_count }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $role->users_count }}</td>
                        <td class="px-6 py-4 space-x-2">
                            <x-permission name="Role.update">
                                <a href="{{ route('roles.edit', $role) }}"
                                   class="text-blue-600 hover:text-blue-800">編輯</a>
                            </x-permission>
                            <x-permission name="Role.delete">
                                <button type="button"
                                        class="delete-btn text-red-600 hover:text-red-800"
                                        data-url="{{ route('roles.destroy', $role) }}"
                                        data-name="{{ $role->name }}">
                                    刪除
                                </button>
                            </x-permission>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

<div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">確認刪除</h3>
        <p class="text-sm text-gray-600 mb-6">
            確定要刪除「<span id="delete-modal-name" class="font-medium text-gray-900"></span>」嗎？此操作無法復原。
        </p>
        <div class="flex justify-end gap-3">
            <button id="delete-modal-cancel"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                取消
            </button>
            <button id="delete-modal-confirm"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors">
                確認刪除
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.flash-message').forEach(el => {
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, 5000);
        });

        let deleteTargetUrl = null;

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                deleteTargetUrl = this.dataset.url;
                document.getElementById('delete-modal-name').textContent = this.dataset.name;
                document.getElementById('delete-modal').classList.remove('hidden');
            });
        });

        document.getElementById('delete-modal-cancel').addEventListener('click', () => {
            document.getElementById('delete-modal').classList.add('hidden');
            deleteTargetUrl = null;
        });

        document.getElementById('delete-modal-confirm').addEventListener('click', async function () {
            if (!deleteTargetUrl) return;

            this.disabled = true;
            this.textContent = '刪除中...';

            try {
                await axios.delete(deleteTargetUrl);

                document.querySelector(`[data-url="${deleteTargetUrl}"]`)
                    .closest('tr')
                    .remove();

                showFlash('success', '已成功刪除');
            } catch (err) {
                const message = err.response?.data?.message ?? '刪除失敗，請稍後再試';
                showFlash('error', message);
            } finally {
                document.getElementById('delete-modal').classList.add('hidden');
                this.disabled = false;
                this.textContent = '確認刪除';
                deleteTargetUrl = null;
            }
        });

        function showFlash(type, message) {
            const colors = {
                success: 'bg-green-50 text-green-700',
                error:   'bg-red-50 text-red-700',
            };
            const el = document.createElement('div');
            el.className = `mb-4 rounded-lg p-4 text-sm flash-message ${colors[type]}`;
            el.textContent = message;
            document.querySelector('.flash-area').prepend(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.5s';
                setTimeout(() => el.remove(), 500);
            }, 5000);
        }
    });
</script>
@endpush
