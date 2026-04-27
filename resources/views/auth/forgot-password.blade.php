@extends('layouts.app')

@section('content')
<div class="w-full max-w-md">
    <div class="bg-white shadow-md rounded-lg px-8 py-8">
        <h2 class="text-2xl font-bold text-center mb-6">忘記密碼</h2>

        <p class="mb-4 text-sm text-gray-600">
            請輸入您的電子郵件，我們會寄送密碼重設連結給您。
        </p>

        @if (session('status'))
            <div class="mb-4 text-sm text-green-600">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">電子郵件</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">返回登入</a>
                <button type="submit"
                        class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                    寄送重設連結
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
