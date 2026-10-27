<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gateway\ListGatewayUsersRequest;
use App\Http\Requests\Gateway\StoreGatewayUserRequest;
use App\Services\GatewayUserService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Requests\Gateway\UpdateGatewayUserRequest;
use App\Http\Requests\Users\AdjustBalanceRequest;
use App\Http\Requests\Gateway\AddGatewayMasksRequest;
use App\Http\Requests\Gateway\DeleteGatewayMasksRequest;
use App\Http\Requests\Gateway\AddGatewayTwoFactorRequest;
use App\Http\Requests\Gateway\UpdateGatewayMaskRoutesRequest;
use Illuminate\Http\Request;


class GatewayUserController extends Controller
{
    public function __construct(
        private readonly GatewayUserService $gateway,
    ) {}

    public function index(ListGatewayUsersRequest $request, Permissions $permissions): View
    {
        $filters = $request->filters();

        return view('gateway-users.index', [
            ...$this->gateway->list($filters),
            'filters'   => $filters,
            'canManage' => $permissions->has(Permissions::GW_USER_CREATE),
        ]);
    }

    public function create(): View
    {
        return view('gateway-users.create', [
            'options'  => $this->gateway->formOptions(),
            'networks' => GatewayUserService::NETWORKS,
            'creator'  => session('auth_user'),
        ]);
    }

    public function store(StoreGatewayUserRequest $request): RedirectResponse
    {
        $id = $this->gateway->create($request->payload());

        return redirect()
            ->route('gateway.users')
            ->with('success', "Gateway user {$request->validated('username')} created (ID {$id}).");
    }

    public function edit(int $gatewayUser): View
    {
        return view('gateway-users.edit', [
            ...$this->gateway->editForm($gatewayUser),
            'networks'         => GatewayUserService::NETWORKS,
            'maskRouteOptions' => $this->gateway->maskRouteOptions(),
        ]);
    }

    public function update(UpdateGatewayUserRequest $request, int $gatewayUser): RedirectResponse
    {
        $this->gateway->update($gatewayUser, $request->payload());

        return redirect()->route('gateway.users')->with('success', 'Gateway user details saved.');
    }

    public function balance(AdjustBalanceRequest $request, int $gatewayUser): RedirectResponse
    {
        $amount = (int) $request->validated('amount');
        $action = $request->validated('action');

        $result = $this->gateway->adjustBalance(session('auth_user'), $gatewayUser, $action, $amount);

        $message = $action === 'add'
            ? number_format($amount) . " credits added to {$result->username}."
            : number_format($amount) . " credits deducted from {$result->username}.";

        return back()->with('success', $message . ' New balance: ' . number_format((int) $result->balance) . '.');
    }
    
        public function addMasks(AddGatewayMasksRequest $request, int $gatewayUser): RedirectResponse
    {
        ['added' => $added, 'skipped' => $skipped] = $this->gateway->addMasks($gatewayUser, $request->payload());

        $message = count($added) . ' mask(s) added: ' . implode(', ', $added) . '.';

        if ($skipped) {
            $message .= ' Already existed: ' . implode(', ', $skipped) . '.';
        }

        return back()->with('success', $message);
    }

    public function deleteMasks(DeleteGatewayMasksRequest $request, int $gatewayUser): RedirectResponse
    {
        $count = $this->gateway->deleteMasks($gatewayUser, $request->validated('masks'));

        return back()->with('success', "{$count} mask(s) deleted.");
    }
    
        public function addTwoFactor(AddGatewayTwoFactorRequest $request, int $gatewayUser): RedirectResponse
    {
        ['added' => $added, 'rejected' => $rejected] = $this->gateway->addTwoFactor(session('auth_user'), $gatewayUser, $request->payload());

        $message = "{$added} two-factor contact(s) added.";

        if ($rejected) {
            $message .= ' Already registered: ' . implode(', ', $rejected) . '.';
        }

        return back()->with('success', $message);
    }

    public function maskRoutes(Request $request): View
    {
        // The mask travels hex-encoded so spaces inside it are kept exactly.
        $key = (string) $request->query('m', '');
        $mask = $key !== '' && strlen($key) % 2 === 0 && ctype_xdigit($key) ? hex2bin($key) : null;

        return view('gateway-users.mask-routes', $this->gateway->maskRoutesPage(
            $mask !== '' ? $mask : null,
            $request->integer('user') ?: null,
        ));
    }

    public function updateMaskRoutes(UpdateGatewayMaskRoutesRequest $request): RedirectResponse
    {
        $mask = $request->mask();
        $userId = (int) $request->validated('user_id');

        $this->gateway->updateMaskRoutes($mask, $userId, $request->boolean('promotional'), $request->routes());

        return redirect()
            ->route('gateway.mask-routes', ['m' => bin2hex($mask), 'user' => $userId])
            ->with('success', 'Routes for "' . trim($mask) . '" updated.');
    }
}