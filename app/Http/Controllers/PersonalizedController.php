<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonalizedController extends Controller
{
    public function __construct(private readonly PersonalizedService $personalized)
    {
    }

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'all' ? 'all' : 'pending';
        $campId = $request->integer('camp') ?: null;
        $page = max(1, $request->integer('page', 1));
        $value = mb_substr(trim((string) $request->query('value', '')), 0, 200) ?: null;
        $field = $value && isset(PersonalizedService::FIELDS[$request->query('field')]) ? $request->query('field') : ($value ? 'id' : null);
        $scopeId = $this->scopeId();

        $campaigns = $this->personalized->campaigns($scopeId);
        $messages = $tab === 'all' || $campId
            ? $this->personalized->messages($scopeId, $tab === 'pending', $campId, $field, $value, $page)
            : null;

        return view('personalized.index', [
            'tab'       => $tab,
            'campId'    => $campId,
            'campaign'  => $campId ? collect($campaigns)->firstWhere('camp_id', $campId) : null,
            'campaigns' => $campaigns,
            'messages'  => $messages,
            'page'      => $page,
            'field'     => $field ?? 'id',
            'value'     => $value,
        ]);
    }

    public function action(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action'  => ['required', 'in:approve,suspend'],
            'camp_id' => ['nullable', 'integer', 'min:1', 'required_without:ids'],
            'ids'     => ['nullable', 'array', 'max:1000', 'required_without:camp_id'],
            'ids.*'   => ['integer', 'min:1'],
        ]);

        $message = $this->personalized->act(
            $this->scopeId(),
            $data['action'],
            isset($data['camp_id']) ? (int) $data['camp_id'] : null,
            array_map('intval', $data['ids'] ?? []),
        );

        return back()->with('success', $message);
    }

    /** Campaign approvers only see their own users' messages (legacy key-account scope). */
    private function scopeId(): ?int
    {
        $user = session('auth_user');

        return strtolower((string) ($user['user_type'] ?? '')) === 'campapprover' ? (int) $user['id'] : null;
    }
}