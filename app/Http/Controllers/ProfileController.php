<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profile,
    ) {}

    public function edit(Request $request): View
    {
        $userId = (int) $request->session()->get('auth_user.id');
        $errors = $request->session()->get('errors');

        return view('profile.edit', [
            'profile'   => $this->profile->find($userId),
            'activity'  => $this->profile->recentActivity($userId),
            // Reopen the Security tab when a password change failed validation
            'activeTab' => $errors?->hasAny(['current_password', 'password']) ? 'security' : null,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $limit = $request->validated('low_balance_limit');

        $this->profile->updateDetails(
            $request->session()->get('auth_user'),
            $request->validated('fullname'),
            $request->mobiles(),
            $request->emails(),
            $limit === null ? null : (int) $limit,
        );

        return back()->with('success', 'Profile updated.');
    }

    public function password(ChangePasswordRequest $request): RedirectResponse
    {
        $this->profile->changePassword(
            $request->session()->get('auth_user'),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return redirect()->to(route('profile.edit') . '#security')->with('success', 'Password changed.');
    }
}