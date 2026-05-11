<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    #[RequiresPermission('Dashboard.index')]
    public function index(): View
    {
        $overview = $this->service->getOverview(auth()->id(), auth()->user());
        $today = Carbon::today('Asia/Taipei')->isoFormat('Y年M月D日（dd）');

        return view('admin.dashboard', compact('overview', 'today'));
    }
}
