<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mailbox\Office;
use App\User;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

/**
 * The offices students can write to (Bursary, Registry, ...). Each office is
 * staffed by named members of staff, who all see its conversations.
 */
class MailboxOfficeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:mailbox-office');
    }

    public function index()
    {
        return view('admin.mailbox.offices', [
            'title' => __('Mailbox offices'),
            'offices' => Office::with('members:id,first_name,last_name')->orderBy('title')->get(),
            'staff' => User::where('status', '1')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'staff_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $office = Office::create(['title' => $data['title'], 'description' => $data['description'] ?? null, 'status' => true]);
        $office->members()->sync($data['members']);
        Flasher::addSuccess(__('Office saved. Students can now write to it.'), __('msg_success'));

        return redirect()->route('admin.mailbox.offices');
    }

    public function update(Request $request, Office $office)
    {
        $data = $this->validated($request);
        $office->update(['title' => $data['title'], 'description' => $data['description'] ?? null, 'status' => $request->boolean('status')]);
        $office->members()->sync($data['members']);
        Flasher::addSuccess(__('Office saved.'), __('msg_success'));

        return redirect()->route('admin.mailbox.offices');
    }

    public function destroy(Office $office)
    {
        // Conversations already held with the office stay with their people.
        $office->delete();
        Flasher::addSuccess(__('Office removed. Its conversations stay in the mailboxes of the people in them.'), __('msg_success'));

        return redirect()->route('admin.mailbox.offices');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'members' => 'required|array|min:1',
            'members.*' => 'integer|exists:users,id',
        ]);
    }
}
