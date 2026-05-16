@extends('layouts.app')

@section('content')
<div class="w-full max-w-md">
    <div class="bg-white shadow-md rounded-lg px-8 py-8">
        <h2 class="text-2xl font-bold text-center mb-6">登入</h2>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">電子郵件</label>
                <x-form-input type="email" name="email" :value="old('email')" required autofocus class="w-full" />
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密碼</label>
                <x-password-input name="password" :required="true" />
            </div>

            <div class="mb-6 flex justify-end">
                <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">忘記密碼？</a>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                登入
            </button>
        </form>
    </div>
</div>
@endsection
