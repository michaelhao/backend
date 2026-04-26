<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\ConferenceRequest;
use App\Models\Conference;
use App\Services\ConferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConferenceController extends Controller
{
    public function __construct(private ConferenceService $conferenceService) {}

    #[RequiresPermission('Conference.index')]
    public function index(Request $request): View
    {
        return view('admin.conferences.index', $this->conferenceService->getIndexData($request));
    }

    #[RequiresPermission('Conference.create')]
    public function create(): View
    {
        return view('admin.conferences.create', $this->conferenceService->getCreateData());
    }

    #[RequiresPermission('Conference.create')]
    public function store(ConferenceRequest $request): RedirectResponse
    {
        $this->conferenceService->createConference($request->validated());

        return redirect()->route('conferences.index')->with('success', '說明會已建立');
    }

    #[RequiresPermission('Conference.update')]
    public function edit(int $id): View|RedirectResponse
    {
        $conference = Conference::find($id);
        if (! $conference) {
            return redirect()->route('conferences.index')->with('error', '找不到該說明會');
        }

        return view('admin.conferences.edit', $this->conferenceService->getEditData($conference));
    }

    #[RequiresPermission('Conference.update')]
    public function update(ConferenceRequest $request, int $id): RedirectResponse
    {
        $conference = Conference::find($id);
        if (! $conference) {
            return redirect()->route('conferences.index')->with('error', '找不到該說明會');
        }

        $this->conferenceService->updateConference($conference, $request->validated());

        return redirect()->route('conferences.index')->with('success', '說明會已更新');
    }
}
