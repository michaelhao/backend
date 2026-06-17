<?php

namespace App\Exceptions;

use Exception;

/**
 * 聊天操作違反業務規則（如存取非自己參與的對話、與自己建立對話）時拋出。
 */
class ChatOperationException extends Exception
{
    //
}
