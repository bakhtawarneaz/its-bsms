<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maskings\ListMaskingsRequest;
use App\Http\Requests\Maskings\StoreMaskingRequest;
use App\Http\Requests\Maskings\UpdateMaskingRoutesRequest;
use App\Http\Requests\Maskings\UpdateMaskingStatusRequest;
use App\Services\MaskingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaskingController extends Controller
{
    public function __construct(
        private readonly MaskingService $maskings,
    ) {}

    public function index(ListMaskingsRequest $request): View
    {
        $filters = $request->filters();

        return view('maskings.index', [
            ...$this->maskings->list($filters),
            'filters'       => $filters,
            'networks'      => MaskingService::NETWORKS,
            'defaultStatus' => MaskingService::DEFAULT_STATUS,
        ]);
    }

    public function create(): View
    {
        return view('maskings.create', [
            'networks'     => MaskingService::NETWORKS,
            'routeOptions' => $this->maskings->routeOptions(),
        ]);
    }

    public function store(StoreMaskingRequest $request): RedirectResponse
    {
        ['added' => $added, 'duplicates' => $duplicates] = $this->maskings->create($request->payload());

        $message = count($added) . ' masking(s) added: ' . implode(', ', $added) . '.';

        if ($duplicates) {
            $message .= ' Skipped existing: ' . implode(', ', $duplicates) . '.';
        }

        return redirect()->route('maskings.index')->with('success', $message);
    }

    public function edit(int $masking): View
    {
        $form = $this->maskings->editForm($masking);

        return view('maskings.edit', [
            'masking'      => $form['masking'],
            'current'      => $form['current'],
            'promotional'  => $form['promotional'],
            'routeOptions' => $form['options'],
            'networks'     => MaskingService::NETWORKS,
        ]);
    }

    public function update(UpdateMaskingRoutesRequest $request, int $masking): RedirectResponse
    {
        $name = $this->maskings->saveRoutes($request->session()->get('auth_user'), $masking, $request->payload());

        return redirect()
            ->route('maskings.index')
            ->with('success', "Routes saved for {$name}. All networks are off until you turn them on.");
    }

    public function status(UpdateMaskingStatusRequest $request): RedirectResponse
    {
        ['updated' => $updated, 'skipped' => $skipped] = $this->maskings->setStatus(
            $request->validated('maskings'),
            (bool) $request->validated('promotional'),
            $request->validated('networks'),
        );

        $message = 'Status updated for ' . count($updated) . ' masking(s).';

        if ($skipped) {
            $message .= ' Skipped (no routes): ' . implode(', ', $skipped) . '.';
        }

        return back()->with('success', $message);
    }

        public function requests(Request $request): View
    {
        return view('maskings.requests', [
            'requests' => $this->maskings->requests((int) $request->query('page', 1)),
        ]);
    }
}