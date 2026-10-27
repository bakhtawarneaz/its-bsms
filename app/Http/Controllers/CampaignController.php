<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns)
    {
    }

    public function index(Request $request): View
    {
        $value = mb_substr(trim((string) $request->query('value', '')), 0, 200) ?: null;
        $field = $value ? (isset(CampaignService::FIELDS[$request->query('field')]) ? $request->query('field') : 'id') : null;
        $state = isset(CampaignService::STATES[$request->query('state')]) ? $request->query('state') : null;

        if (in_array($field, ['id', 'user_id'], true) && ! ctype_digit((string) $value)) {
            $value = '0'; // non-numeric id matches nothing, as in the legacy screen
        }

        return view('campaigns.index', [
            ...$this->campaigns->page($field, $value, $state, max(1, $request->integer('page', 1)), $this->scopeId()),
            'field'     => $field ?? 'id',
            'value'     => $value,
            'state'     => $state,
            'canRevert' => $this->scopeId() === null,
        ]);
    }

    public function action(Request $request, int $campaign): RedirectResponse
    {
        $data = $request->validate([
            'action'    => ['required', 'in:' . implode(',', CampaignService::ACTIONS)],
            'send_date' => ['required_if:action,reschedule', 'nullable', 'date_format:Y-m-d'],
            'send_time' => ['required_if:action,reschedule', 'nullable', 'date_format:H:i'],
        ]);

        $at = $data['action'] === 'reschedule'
            ? Carbon::createFromFormat('Y-m-d H:i', $data['send_date'] . ' ' . $data['send_time'])
            : null;

        return back()->with('success', $this->campaigns->act($campaign, $data['action'], $at, $this->scopeId()));
    }

    public function logs(int $campaign): StreamedResponse
    {
        return $this->campaigns->logs($campaign, $this->scopeId());
    }

    public function revert(int $campaign): RedirectResponse
    {
        return back()->with('success', $this->campaigns->revert($campaign, (int) session('auth_user')['id']));
    }

    /** Campaign approvers only see the campaigns of their own users (legacy key-account scope). */
    private function scopeId(): ?int
    {
        $user = session('auth_user');

        return strtolower((string) ($user['user_type'] ?? '')) === 'campapprover' ? (int) $user['id'] : null;
    }
}