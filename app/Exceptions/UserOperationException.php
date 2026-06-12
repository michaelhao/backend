<?php

namespace App\Exceptions;

use Exception;

/**
 * 使用者管理操作違反業務規則（如刪除自己、修改自己的角色）時拋出。
 */
class UserOperationException extends Exception
{
    //
}
