<?php

namespace App\Http\Controllers;

use App\Http\Requests\Compose\QuickSmsRequest;
use App\Services\ComposeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Exceptions\OtpDeliveryFailed;
use App\Http\Requests\Compose\CampaignRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Compose\PersonalizeRequest;
use App\Services\PersonalizeService;

class ComposeController extends Controller
{
    public function __construct(private readonly ComposeService $compose)
    {
    }

    public function quick(): View
    {
        return view('compose.quick', [
            ...$this->compose->context($this->userId()),
            'limit' => ComposeService::QUICK_LIMIT,
        ]);
    }

    public function quickSend(QuickSmsRequest $request): RedirectResponse
    {
        $result = $this->compose->quick(
            $this->userId(),
            $request->validated('sender'),
            $request->validated('receivers'),
            $request->validated('message'),
            (string) $request->ip(),
        );

        // Keep the header/dashboard balance in step with the deduction.
        session()->put('auth_user.balance', $result['balance']);

        return redirect()->route('compose.index')->with(
            'success',
            "{$result['sent']} messages sent, {$result['rejected']} rejected, {$result['duplicates']} duplicate.",
        );
    }

    private function userId(): int
    {
        return (int) session('auth_user')['id'];
    }

        public function campaign(): View
    {
        return view('compose.campaign', [
            ...$this->compose->context($this->userId()),
            'groups' => $this->compose->groups($this->userId()),
            'review' => session('campaign_review'),
        ]);
    }

    public function campaignPrepare(CampaignRequest $request): RedirectResponse
    {
        $review = $this->compose->prepareCampaign(
            $this->userId(),
            $request->validated(),
            $request->file('file'),
            $request->scheduledAt(),
        );

        session()->put('campaign_review', $review);

        return redirect()->route('compose.campaign')->withInput($request->except('file'));
    }

    public function campaignConfirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:40'],
            'code'  => ['required', 'digits:5'],
        ]);

        try {
            $result = $this->compose->confirmCampaign($this->userId(), $data['token'], $data['code'], (string) $request->ip());
        } catch (ValidationException $e) {
            // Keep the review open on a wrong code; close it when the review is no longer usable.
            if (str_contains(implode(' ', $e->errors()['code'] ?? []), 'Tries left') === false) {
                session()->forget('campaign_review');
            }

            throw $e;
        }

        session()->forget('campaign_review');
        session()->put('auth_user.balance', $result['balance']);

        $message = "Campaign #{$result['campaignId']} created: {$result['sent']} messages, {$result['rejected']} rejected."
            . ($result['approval'] ? ' It is waiting for approval.' : '');

        return redirect()->route('compose.campaign')->with('success', $message);
    }

    public function campaignCancel(Request $request): RedirectResponse
    {
        $this->compose->cancelCampaign($this->userId(), session('campaign_review.token'));
        session()->forget('campaign_review');

        return redirect()->route('compose.campaign');
    }

    public function campaignResend(): RedirectResponse
    {
        try {
            $sent = $this->compose->resendCampaignOtp();
        } catch (OtpDeliveryFailed) {
            return back()->with('error', 'Campaign OTP could not be sent (SMS and email both failed).');
        }

        return back()->with($sent ? 'success' : 'error', $sent ? 'A new campaign OTP has been sent.' : 'Please wait a minute before requesting a new code.');
    }

        public function personalize(): View
    {
        return view('compose.personalize', $this->compose->context($this->userId()));
    }

    public function personalizeSend(PersonalizeRequest $request, PersonalizeService $personalize): RedirectResponse
    {
        $result = $personalize->send(
            $this->userId(),
            $request->validated(),
            $request->file('file'),
            $request->scheduledAt(),
            (string) $request->ip(),
        );

        session()->put('auth_user.balance', $result['balance']);

        $message = "Campaign #{$result['campaignId']} created: {$result['sent']} messages sent, {$result['rejected']} rejected."
            . ($result['approval'] ? ' It is waiting for approval.' : '');

        return redirect()->route('compose.personalize')->with('success', $message);
    }
}