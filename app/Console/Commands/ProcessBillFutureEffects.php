<?php

namespace App\Console\Commands;

use App\Services\BillPaymentService;
use Illuminate\Console\Command;

class ProcessBillFutureEffects extends Command
{
    protected $signature = 'bills:process-future-effects';

    protected $description = 'Process bills_future_effect records where execute_at <= today';

    public function handle(BillPaymentService $billPaymentService): int
    {
        $this->info('Processing bill future effects…');

        $billPaymentService->processFutureEffects();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
