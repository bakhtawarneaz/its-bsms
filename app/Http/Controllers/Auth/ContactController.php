<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ContactUpdateRequest;
use App\Repositories\AuthRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(
        private readonly AuthRepository $repository,
    ) {}

    /**
     * Show the contact confirmation form for first-time users.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->session()->get('contact_user');

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.contact-update', ['user' => $user]);
    }

    /**
     * Save the confirmed contact details and continue to OTP.
     */
    public function update(ContactUpdateRequest $request): RedirectResponse
    {
        $user = $request->session()->get('contact_user');

        if (! $user) {
            return redirect()->route('login');
        }

        $mobiles = implode(',', $request->mobiles());
        $emails  = implode(',', $request->emails());

        $updated = $this->repository->updateContact(
            (int) $user['id'],
            $request->validated('fullname'),
            $mobiles,
            $emails,
        );

        if (! $updated) {
            return back()->withInput()->withErrors(['fullname' => 'Contact info could not be updated. Please try again.']);
        }

        Log::channel('auth')->info('Contact info updated', ['user_id' => $user['id']]);

        // Carry the new details forward so OTP goes to the confirmed recipients
        $user = array_merge($user, [
            'fullname'        => $request->validated('fullname'),
            'user_mobile'     => $mobiles,
            'email'           => $emails,
            'contact_updated' => 1,
        ]);

        $request->session()->forget('contact_user');
        $request->session()->put('otp_user', $user);

        return redirect()->route('otp.recipients')->with('status', 'Contact info updated successfully.');
    }
}