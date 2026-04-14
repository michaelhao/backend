<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserController;
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
        Route::get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');

        // 等級管理
        Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
        Route::get('/grades/create', [GradeController::class, 'create'])->name('grades.create');
        Route::post('/grades', [GradeController::class, 'store'])->name('grades.store');
        Route::get('/grades/{id}/edit', [GradeController::class, 'edit'])->name('grades.edit');
        Route::put('/grades/{id}', [GradeController::class, 'update'])->name('grades.update');
        Route::patch('/grades/{id}/toggle', [GradeController::class, 'toggleStatus'])->name('grades.toggle');

        // 使用者管理
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

        // 商店管理
        Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
        Route::get('/shops/{id}/edit', [ShopController::class, 'edit'])->name('shops.edit');
        Route::put('/shops/{id}', [ShopController::class, 'update'])->name('shops.update');
        Route::post('/shops/{id}/certify', [ShopController::class, 'certify'])->name('shops.certify');
    });
});
