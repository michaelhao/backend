@extends('layouts.admin')

@section('page-title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">歡迎回來，{{ Auth::user()->name }}！</h2>
        <p class="text-gray-500 mt-1">以下是系統概覽。</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm font-medium text-gray-500">文章數</p>
            <p class="text-3xl font-bold text-gray-800 mt-2">128</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm font-medium text-gray-500">使用者數</p>
            <p class="text-3xl font-bold text-gray-800 mt-2">64</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm font-medium text-gray-500">今日瀏覽量</p>
            <p class="text-3xl font-bold text-gray-800 mt-2">1,024</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm font-medium text-gray-500">待審留言</p>
            <p class="text-3xl font-bold text-gray-800 mt-2">12</p>
        </div>
    </div>
@endsection
