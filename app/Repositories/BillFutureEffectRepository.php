<?php

namespace App\Repositories;

use App\Models\BillDetail;
use App\Models\BillFutureEffect;
use Illuminate\Database\Eloquent\Collection;

class BillFutureEffectRepository
{
    public function createFromDetail(BillDetail $detail): BillFutureEffect
    {
        return BillFutureEffect::create([
            'bill_id' => $detail->bill_id,
            'bill_detail_id' => $detail->id,
            'execute_at' => $detail->start_at->toDateString(),
            'finished_at' => null,
        ]);
    }

    public function getPendingUpToToday(): Collection
    {
        return BillFutureEffect::with(['detail.bill'])
            ->whereDate('execute_at', '<=', today())
            ->whereNull('finished_at')
            ->orderBy('execute_at')
            ->orderBy('id')
            ->get();
    }

    public function markFinished(BillFutureEffect $effect): void
    {
        $effect->update(['finished_at' => today()->toDateString()]);
    }
}
