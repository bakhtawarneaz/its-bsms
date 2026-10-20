<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\ListUsersRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Services\UserService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Http\Requests\Users\AdjustBalanceRequest;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    /** Paginated user directory with search, filters and state tabs. */
    public function index(ListUsersRequest $request): View
    {
        $filters = $request->filters();

        return view('users.index', [
            ...$this->users->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        return view('users.create', [
            'options' => $this->users->formOptions(),
            'creator' => $request->session()->get('auth_user'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $userId = $this->users->create($request->session()->get('auth_user'), $request->payload());

        return redirect()
            ->route('users.index')
            ->with('success', "User {$request->validated('username')} created (ID {$userId}).");
    }

    public function edit(int $user): View
    {
        $record = $this->users->find($user);

        return view('users.edit', [
            'user'     => $record['user'],
            'maskings' => $record['maskings'],
            'options'  => $this->users->editOptions($record['user']),
            'balanceHistory' => $this->users->balanceHistory($user),
        ]);
    }

    public function update(UpdateUserRequest $request, int $user): RedirectResponse
    {
        $this->users->update($request->session()->get('auth_user'), $user, $request->payload());

        return redirect()
            ->route('users.index')
            ->with('success', 'User details saved.');
    }

    public function status(Request $request, int $user): RedirectResponse
    {
        $active = $request->boolean('status');
        $username = $this->users->setStatus($user, $active);

        return back()->with('success', $active ? "{$username} can sign in again." : "{$username} can no longer sign in.");
    }

    public function unblock(int $user): RedirectResponse
    {
        $username = $this->users->unblock($user);

        return back()->with('success', "{$username} has been unblocked.");
    }

    public function destroy(int $user): RedirectResponse
    {
        try {
            $username = $this->users->delete($user);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$username} has been deleted.");
    }

    public function balance(AdjustBalanceRequest $request, int $user): RedirectResponse
    {
        $amount = (int) $request->validated('amount');
        $action = $request->validated('action');

        $result = $this->users->adjustBalance($request->session()->get('auth_user'), $user, $action, $amount);

        $message = $action === 'add'
            ? number_format($amount) . " credits added to {$result->username}."
            : number_format($amount) . " credits deducted from {$result->username}.";

        return back()->with('success', $message . ' New balance: ' . number_format((int) $result->balance) . '.');
    }
}