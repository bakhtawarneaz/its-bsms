<?php

namespace App\Http\Controllers;

use App\Http\Requests\Templates\SaveTemplateRequest;
use App\Http\Requests\Templates\StoreOtpTemplateRequest;
use App\Services\TemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function __construct(private readonly TemplateService $templates)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', '')) ?: null;

        return view('templates.index', [
            'templates' => $this->templates->page($this->userId(), $search ? mb_substr($search, 0, 200) : null, max(1, $request->integer('page', 1))),
            'search'    => $search,
        ]);
    }

    public function store(SaveTemplateRequest $request): RedirectResponse
    {
        $this->templates->create($this->userId(), $request->validated('name'), $request->validated('message'));

        return redirect()->route('templates.index')->with('success', 'Template added.');
    }

    public function update(SaveTemplateRequest $request, int $template): RedirectResponse
    {
        $this->templates->update($this->userId(), $template, $request->validated('message'));

        return back()->with('success', 'Template updated.');
    }

    public function destroy(int $template): RedirectResponse
    {
        $name = $this->templates->delete($this->userId(), $template);

        return back()->with('success', "Template \"{$name}\" deleted.");
    }

    public function otp(Request $request): View
    {
        $userId = $request->integer('user_id') ?: null;

        return view('templates.otp', [
            'templates' => $this->templates->otpPage($userId, max(1, $request->integer('page', 1))),
            'users'     => $this->templates->users(),
            'userId'    => $userId,
        ]);
    }

    public function otpStore(StoreOtpTemplateRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $tempId = $this->templates->addOtp($payload);

        return redirect()
            ->route('templates.otp', ['user_id' => $payload['user_id']])
            ->with('success', "OTP template added as Temp ID {$tempId}.");
    }

    private function userId(): int
    {
        return (int) session('auth_user')['id'];
    }
}