<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\ShopUpdateRequest;
use App\Models\Shop;
use App\Services\ShopService;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(private ShopService $shopService) {}

    #[RequiresPermission('Shop.index')]
    public function index(Request $request)
    {
        return view('admin.shops.index', $this->shopService->getIndexData($request));
    }

    #[RequiresPermission('Shop.update')]
    public function edit(Shop $shop)
    {
        return view('admin.shops.edit', $this->shopService->getEditData($shop));
    }

    #[RequiresPermission('Shop.update')]
    public function update(ShopUpdateRequest $request, Shop $shop)
    {
        $shopData  = $request->only(['name', 'email', 'grade_id', 'status']);
        $adminData = $request->input('admin');

        $this->shopService->updateShop($shop, $shopData, $adminData);

        return redirect()->route('shops.index')->with('success', '商店已更新');
    }

    #[RequiresPermission('Shop.update')]
    public function certify(Request $request, Shop $shop)
    {
        $request->validate([
            'business_number' => ['required', 'string', 'regex:/^\d{8}$/'],
        ]);

        $result = $this->shopService->verifyCertification($request->business_number);

        return response()->json($result);
    }
}
