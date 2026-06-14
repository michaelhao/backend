@extends('layouts.app')

@section('content')
<div class="w-full max-w-md">
    <div class="bg-white shadow-md rounded-lg px-8 py-8">
        <h2 class="text-2xl font-bold text-center mb-6">重設密碼</h2>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-4">
                <label for="email" class="form-label">電子郵件</label>
                <x-form-input type="email" name="email" :value="old('email', $email)" required autofocus class="w-full" />
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">新密碼</label>
                <x-password-input name="password" :required="true" />
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="form-label">確認新密碼</label>
                <x-password-input name="password_confirmation" :required="true" />
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                重設密碼
            </button>
        </form>
    </div>
</div>
@endsection
