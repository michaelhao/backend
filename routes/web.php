<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

// 訪客專用路由（已登入使用者會被導向 /）
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// 需登入路由（未登入使用者會被導向 /login）
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // 無角色提示頁（不受 permission middleware 保護）
    Route::get('/no-role', fn () => view('no-role'))->name('no-role');

    Route::get('/test-db', [PostController::class, 'test']);

    // 需要權限檢查的 routes — middleware 自動推斷權限
    Route::middleware('permission')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // 角色管理
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});
