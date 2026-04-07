# 後台頁面基礎框架實作方案

## 背景說明

目前專案已有登入/登出功能（`feat/login` 分支），但登入後只顯示 Laravel 預設歡迎頁，沒有後台管理介面的框架。需要建立一個包含側邊欄導覽、頂部工具列的後台 layout，作為未來所有後台頁面的基礎。

---

## 頁面結構

```
+------------------+--------------------------------------+
| Sidebar (w-64)   | Top Bar (使用者名稱 + 登出按鈕)       |
|                  +--------------------------------------+
| - Dashboard      |                                      |
| - Posts (預留)    |       主要內容區域                     |
| - Users (預留)   |       (@yield('content'))             |
|                  |                                      |
+------------------+--------------------------------------+
```

---

## 需新增的檔案（共 3 個）

### 版面配置

| 檔案 | 用途 |
|------|------|
| `resources/views/layouts/admin.blade.php` | 後台共用版面（側邊欄 + 頂部列 + 內容區域） |

### 視圖

| 檔案 | 用途 |
|------|------|
| `resources/views/admin/dashboard.blade.php` | Dashboard 頁面 |

### 控制器

| 檔案 | 用途 |
|------|------|
| `app/Http/Controllers/DashboardController.php` | Dashboard 頁面邏輯 |

## 需修改的檔案（共 1 個）

| 檔案 | 修改內容 |
|------|----------|
| `routes/web.php` | `/` 改為指向 `DashboardController@index` |

---

## 實作細節

### 步驟一：建立後台版面配置

**`resources/views/layouts/admin.blade.php`**

- 完整 HTML 骨架，沿用 `layouts/app.blade.php` 的 `<head>` 模式（`@vite` 載入）
- **側邊欄**：固定左側 `w-64`，包含 Logo/站名 + 導覽連結（Dashboard、Posts、Users 預留項目）
- **頂部列**：顯示頁面標題（`@yield('page-title')`）、使用者名稱（`Auth::user()->name`）、登出表單
- **內容區域**：`@yield('content')`
- 使用 `request()->routeIs()` 判斷當前路由，標示 active 導覽項目

### 步驟二：建立 Dashboard 視圖

**`resources/views/admin/dashboard.blade.php`**

- 繼承 `layouts.admin`
- 顯示歡迎訊息 + 幾個 placeholder 統計卡片（grid 排列），展示 layout 使用方式

### 步驟三：建立控制器

**`app/Http/Controllers/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard');
    }
}
```

### 步驟四：更新 `routes/web.php`

將原本的 `/` 路由從 welcome view 改為 DashboardController：

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// 訪客專用路由（已登入使用者會被導向 /）
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// 需登入路由（未登入使用者會被導向 /login）
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/test-db', [PostController::class, 'test']);
});
```

---

## 不需變動的檔案

- `resources/views/layouts/app.blade.php` — 保留給登入頁使用
- `bootstrap/app.php` — 現有 redirect 設定不需改動
- CSS/JS 設定 — Tailwind v4 `@source` 已自動掃描新 blade 檔案

---

## 驗證測試

1. 啟動開發環境，訪問 `/` 確認直接顯示 dashboard
2. 未登入時確認被導向 `/login`
3. 登入後確認看到完整的 sidebar + top bar + dashboard 內容
4. 點擊登出按鈕確認能正常登出並跳轉至登入頁
5. 側邊欄 Dashboard 連結應顯示 active 狀態
