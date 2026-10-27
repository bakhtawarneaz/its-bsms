<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contacts\StoreContactRequest;
use App\Services\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests\Contacts\ImportContactsRequest;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contacts)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $value = mb_substr(trim((string) $request->query('value', '')), 0, 200) ?: null;
        $field = $request->query('field');
        $field = $value ? (isset(ContactService::FIELDS[$field]) ? $field : 'id') : null;

        try {
            $page = $this->contacts->page($field, $value, max(1, $request->integer('page', 1)));
        } catch (ValidationException $e) {
            return redirect()->route('contacts.index')->withErrors($e->errors());
        }

        return view('contacts.index', [
            ...$page,
            'groups' => $this->contacts->groups($this->userId()),
            'field'  => $field ?? 'id',
            'value'  => $value,
            'userId' => $this->userId(),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $this->contacts->add($this->userId(), $request->payload());

        return back()->with('success', 'Contact added.');
    }

    public function update(StoreContactRequest $request, int $contact): RedirectResponse
    {
        $this->contacts->update($contact, $request->payload());

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(int $contact): RedirectResponse
    {
        $number = $this->contacts->delete($contact);

        return back()->with('success', "Contact {$number} deleted.");
    }

    public function groups(Request $request, int $contact): RedirectResponse
    {
        $ids = $request->validate([
            'groups'   => ['nullable', 'array', 'max:200'],
            'groups.*' => ['integer', 'min:1'],
        ])['groups'] ?? [];

        $this->contacts->setGroups($this->userId(), $contact, array_values(array_unique(array_map('intval', $ids))));

        return back()->with('success', 'Groups updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $scope = (string) $request->query('scope', 'all');
        $scope = in_array($scope, ['all', 'mine'], true) || preg_match('/^group:\d+$/', $scope) ? $scope : 'all';

        $value = mb_substr(trim((string) $request->query('value', '')), 0, 200) ?: null;
        $field = $request->query('field');
        $field = $value ? (isset(ContactService::FIELDS[$field]) ? $field : 'id') : null;

        return $this->contacts->export($scope, $field, $value, $this->userId());
    }

    private function userId(): int
    {
        return (int) session('auth_user')['id'];
    }

        public function import(ImportContactsRequest $request): RedirectResponse
    {
        $groupId = $request->validated('group_id') ? (int) $request->validated('group_id') : null;
        $stats = $this->contacts->import($this->userId(), $request->file('file'), $groupId);

        $message = number_format($stats['inserted']) . ' contacts imported. Total rows ' . number_format($stats['total'])
            . ', rejected ' . number_format($stats['rejected'])
            . ($stats['duplicates'] ? ', repeated in file ' . number_format($stats['duplicates']) : '') . '.';

        return back()->with($stats['inserted'] > 0 ? 'success' : 'error', $message);
    }
}