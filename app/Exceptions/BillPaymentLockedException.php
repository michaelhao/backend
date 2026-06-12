<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * 帳單付款流程已持有鎖、重複觸發付款時拋出。
 */
class BillPaymentLockedException extends Exception
{
    public function __construct(string $message = '付款處理中，請勿重複操作')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 429);
    }
}
