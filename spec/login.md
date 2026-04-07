# 登入功能實作方案

## 背景說明

這是一個全新的 Laravel 13 專案（Blade + Tailwind CSS v4 + Vite）。目前尚未安裝任何認證套件，但 Laravel 內建的認證基礎設施已足夠使用：`Auth` facade、`auth`/`guest` middleware，且 `User` model 已繼承 `Authenticatable` 並使用 `Notifiable` trait。`users` 資料表的 migration 已存在。

目標：新增登入/登出功能，並將未登入的使用者統一導向 `/login`。

---

## 需新增的檔案（共 3 個）

### 控制器

| 檔案 | 用途 |
|------|------|
| `app/Http/Controllers/Auth/LoginController.php` | 登入表單、驗證身份、登出 |

### 視圖

| 檔案 | 用途 |
|------|------|
| `resources/views/layouts/app.blade.php` | 共用版面配置（Vite 資源、Tailwind、置中容器） |
| `resources/views/auth/login.blade.php` | 登入表單：email、密碼、記住我 |

## 需修改的檔案（共 2 個）

| 檔案 | 修改內容 |
|------|----------|
| `bootstrap/app.php` | 在 `withMiddleware` 中加入 `redirectGuestsTo('/login')` 與 `redirectUsersTo('/')` |
| `routes/web.php` | 新增認證路由；將現有路由包裝在 `auth` middleware 中 |

---

## 實作細節

### 步驟一：建立版面配置與視圖

**`resources/views/layouts/app.blade.php`** - 包含 `@vite`、CSRF meta 標籤、置中 body 的精簡版面。

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    @yield('content')
</body>
</html>
```

**`resources/views/auth/login.blade.php`** - 表單欄位：email、密碼、記住我勾選框。顯示 `@error('email')` 用於登入失敗錯誤。

```blade
@extends('layouts.app')

@section('content')
<div class="w-full max-w-md">
    <div class="bg-white shadow-md rounded-lg px-8 py-8">
        <h2 class="text-2xl font-bold text-center mb-6">登入</h2>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">電子郵件</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密碼</label>
                <input id="password" type="password" name="password" required
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600">
                    <span class="ml-2 text-sm text-gray-600">記住我</span>
                </label>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                登入
            </button>
        </form>
    </div>
</div>
@endsection
```

### 步驟二：建立控制器

**`app/Http/Controllers/Auth/LoginController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
```

### 步驟三：更新 `bootstrap/app.php`

新增 middleware 導向設定：

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo('/login');
    $middleware->redirectUsersTo('/');
})
```

### 步驟四：更新 `routes/web.php`

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PostController; // 既有控制器
use Illuminate\Support\Facades\Route;

// 訪客專用路由（已登入使用者會被導向 /）
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// 需登入路由（未登入使用者會被導向 /login）
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return view('welcome');
    });

    Route::get('/test-db', [PostController::class, 'test']);
});
```

---

## 驗證測試

1. 未登入狀態訪問 `/` -> 被導向 `/login`
2. 提交錯誤的帳號密碼 -> 顯示錯誤訊息
3. 提交正確的帳號密碼（seeder 預設：`test@example.com` / `password`）-> 導向 `/`
4. 已登入狀態訪問 `/login` -> 被導向 `/`
5. 登出 -> 被導向 `/login`
