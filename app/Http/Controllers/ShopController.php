<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\ShopCertifyRequest;
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
    public function edit(int $id)
    {
        $shop = Shop::find($id);
        if (! $shop) {
            return redirect()->route('shops.index')->with('error', '找不到該商店');
        }

        return view('admin.shops.edit', $this->shopService->getEditData($shop));
    }

    #[RequiresPermission('Shop.update')]
    public function update(ShopUpdateRequest $request, int $id)
    {
        $shop = Shop::find($id);
        if (! $shop) {
            return redirect()->route('shops.index')->with('error', '找不到該商店');
        }

        $shopData = $request->only(['name', 'email', 'grade_id', 'status']);
        $adminData = $request->input('admin');

        $this->shopService->updateShop($shop, $shopData, $adminData);

        return redirect()->route('shops.index')->with('success', '商店已更新');
    }

    #[RequiresPermission('Shop.update')]
    public function certify(ShopCertifyRequest $request, int $id)
    {
        $shop = Shop::find($id);
        if (! $shop) {
            return redirect()->route('shops.index')->with('error', '找不到該商店');
        }

        $result = $this->shopService->verifyCertification($request->business_number);

        return response()->json($result);
    }
}
