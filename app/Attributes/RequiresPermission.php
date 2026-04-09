<?php

namespace App\Attributes;

/**
 * 宣告存取此 controller method 所需的權限名稱。
 *
 * permission 字串必須對應 permissions 表中的 name 欄位。
 * 需同步在 PermissionSeeder::$modules 新增後執行 seeder。
 *
 * @example #[RequiresPermission('Role.create')]
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class RequiresPermission
{
    public function __construct(public readonly string $permission) {}
}
