# maskString / maskEmail 重構為 Support 類別

## Context

原本 `maskString()` 和 `maskEmail()` 以**全域函式**定義於 `app/Helpers/MaskHelpers.php`，並透過 `composer.json` 的 `files` autoload 載入。

問題：
- Blade 樣板中呼叫 `maskString(...)` 時，IDE 無法做 "go to definition"（全域函式不在 PSR-4 namespace 內）
- `maskEmail` 在內部呼叫 `maskString`，兩函式間的依賴藏在全域空間，靜態分析工具無法建立呼叫圖

改為 `App\Support\Mask` 靜態類別，利用 PSR-4 autoload 讓 IDE 可追蹤，邏輯也完整封裝在類別內。

---

## 變更範圍

### `app/Support/Mask.php`（新建）

```php
namespace App\Support;

class Mask
{
    // Mask odd-index characters of a string with *.
    // e.g. 12345678 → 1*3*5*7*
    public static function string(string $value): string { ... }

    // Mask odd-index characters in the local part of an email address.
    // e.g. admin@test.com → a*m*n@test.com
    public static function email(string $email): string { ... }
}
```

`email()` 內部呼叫 `static::string()`，依賴明確可見。PSR-4 autoload，不需異動 `composer.json`。

### `app/Helpers/MaskHelpers.php`（更新）

全域函式保留，改為 delegate 至 `Mask` class，維持向後相容：

```php
if (! function_exists('maskEmail')) {
    function maskEmail(string $email): string
    {
        return \App\Support\Mask::email($email);
    }
}

if (! function_exists('maskString')) {
    function maskString(string $value): string
    {
        return \App\Support\Mask::string($value);
    }
}
```

### `resources/views/admin/shops/edit.blade.php`（2 處）

| 行 | 原本 | 改為 |
|----|------|------|
| 113 | `maskEmail(...)` | `\App\Support\Mask::email(...)` |
| 146 | `maskString(...)` | `\App\Support\Mask::string(...)` |

### `tests/Feature/ShopRuTest.php`（1 處）

| 行 | 原本 | 改為 |
|----|------|------|
| 78 | `maskEmail(...)` | `\App\Support\Mask::email(...)` |

---

## 修改檔案

| 檔案 | 變更 |
|------|------|
| `app/Support/Mask.php` | 新建 |
| `app/Helpers/MaskHelpers.php` | 全域函式改為 delegate |
| `resources/views/admin/shops/edit.blade.php` | 改用 `Mask::` 靜態呼叫 |
| `tests/Feature/ShopRuTest.php` | 改用 `Mask::` 靜態呼叫 |

---

## 驗證方式

```bash
php artisan test --filter=ShopRuTest
```

全部 19 個測試通過即完成。
