<?php

namespace App\Http\Controllers;

use App\Http\Requests\Groups\SaveGroupRequest;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct(private readonly GroupService $groups)
    {
    }

    public function index(Request $request): View
    {
        $search = $this->search($request);

        return view('groups.index', [
            'groups' => $this->groups->page($this->userId(), $search, $this->page($request)),
            'search' => $search,
        ]);
    }

    public function store(SaveGroupRequest $request): RedirectResponse
    {
        $this->groups->save($this->userId(), null, $request->name(), $request->description());

        return redirect()->route('groups.index')->with('success', 'Group added.');
    }

    public function update(SaveGroupRequest $request, int $group): RedirectResponse
    {
        $this->groups->save($this->userId(), $group, $request->name(), $request->description());

        return back()->with('success', 'Group updated.');
    }

    public function destroy(int $group): RedirectResponse
    {
        ['name' => $name, 'contacts' => $contacts] = $this->groups->delete($this->userId(), $group);

        return redirect()->route('groups.index')->with('success', "Group \"{$name}\" deleted with {$contacts} contact(s).");
    }

    public function show(Request $request, int $group): View
    {
        $search = $this->search($request);

        return view('groups.show', [
            'group'    => $this->groups->find($this->userId(), $group),
            'search'   => $search,
            'contacts' => $this->groups->members($this->userId(), $group, $search, $this->page($request)),
        ]);
    }


    private function search(Request $request): ?string
    {
        $search = trim((string) $request->query('search', ''));

        return $search !== '' ? mb_substr($search, 0, 100) : null;
    }

    private function page(Request $request): int
    {
        return max(1, $request->integer('page', 1));
    }

    private function userId(): int
    {
        return (int) session('auth_user')['id'];
    }
}