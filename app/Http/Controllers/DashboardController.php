<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;

class DashboardController extends Controller
{
    #[RequiresPermission('Dashboard.index')]
    public function index()
    {
        return view('admin.dashboard');
    }
}
