<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\AddonStatus;
use App\Http\Requests\AddonRequest;
use App\Models\Addon;
use App\Services\AddonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddonController extends Controller
{
    public function __construct(private AddonService $addonService) {}

    #[RequiresPermission('Addon.index')]
    public function index(Request $request): View
    {
        return view('admin.addons.index', $this->addonService->getIndexData($request));
    }

    #[RequiresPermission('Addon.create')]
    public function create(): View
    {
        return view('admin.addons.create', $this->addonService->getCreateData());
    }

    #[RequiresPermission('Addon.create')]
    public function store(AddonRequest $request): RedirectResponse
    {
        $this->addonService->createAddon(
            $request->safe()->except('image'),
            $request->file('image'),
        );

        return redirect()->route('addons.index')->with('success', '附加功能已建立');
    }

    #[RequiresPermission('Addon.update')]
    public function edit(int $id): View|RedirectResponse
    {
        $addon = Addon::find($id);
        if (! $addon || $addon->status === AddonStatus::Deleted) {
            return redirect()->route('addons.index')->with('error', '找不到該附加功能');
        }

        return view('admin.addons.edit', $this->addonService->getEditData($addon));
    }

    #[RequiresPermission('Addon.update')]
    public function update(AddonRequest $request, int $id): RedirectResponse
    {
        $addon = Addon::find($id);
        if (! $addon || $addon->status === AddonStatus::Deleted) {
            return redirect()->route('addons.index')->with('error', '找不到該附加功能');
        }

        $this->addonService->updateAddon(
            $addon,
            $request->safe()->except('image'),
            $request->file('image'),
        );

        return redirect()->route('addons.index')->with('success', '附加功能已更新');
    }

    #[RequiresPermission('Addon.delete')]
    public function destroy(int $id): RedirectResponse
    {
        $addon = Addon::find($id);
        if (! $addon || $addon->status === AddonStatus::Deleted) {
            return redirect()->route('addons.index')->with('error', '找不到該附加功能');
        }

        $this->addonService->deleteAddon($addon);

        return redirect()->route('addons.index')->with('success', '附加功能已刪除');
    }
}
