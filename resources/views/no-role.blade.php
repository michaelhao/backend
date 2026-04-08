@extends('layouts.admin')

@section('page-title', '無角色')

@section('content')
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="bg-white rounded-lg shadow p-8 max-w-md text-center">
            <h2 class="text-xl font-bold text-gray-800 mb-2">目前沒有角色</h2>
            <p class="text-gray-500 mb-6">您的帳號尚未被指派角色，請聯絡管理員協助處理。</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="bg-gray-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                    登出
                </button>
            </form>
        </div>
    </div>
@endsection
