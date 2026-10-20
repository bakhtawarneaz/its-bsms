<?php

namespace App\Http\Controllers;

use App\Http\Requests\Resellers\ListResellersRequest;
use App\Http\Requests\Resellers\StoreResellerRequest;
use App\Services\ResellerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Http\Requests\Resellers\UpdateResellerBrandingRequest;

class ResellerController extends Controller
{
    public function __construct(
        private readonly ResellerService $resellers,
    ) {}

    public function index(ListResellersRequest $request): View
    {
        $filters = $request->filters();

        return view('resellers.index', [
            ...$this->resellers->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        return view('resellers.create', [
            'options'       => $this->resellers->formOptions(),
            'creator'       => $request->session()->get('auth_user'),
            'defaultColors' => StoreResellerRequest::DEFAULT_COLORS,
        ]);
    }

    public function store(StoreResellerRequest $request): RedirectResponse
    {
        $id = $this->resellers->create($request->session()->get('auth_user'), $request->payload());

        return redirect()
            ->route('resellers.index')
            ->with('success', "Reseller {$request->validated('username')} created (ID {$id}).");
    }

        public function edit(int $reseller): View
    {
        return view('resellers.edit', $this->resellers->editForm($reseller));
    }

    public function update(UpdateResellerBrandingRequest $request, int $reseller): RedirectResponse
    {
        $username = $this->resellers->updateBranding($reseller, $request->validated());

        return redirect()->route('resellers.index')->with('success', "Branding saved for {$username}.");
    }
    
}