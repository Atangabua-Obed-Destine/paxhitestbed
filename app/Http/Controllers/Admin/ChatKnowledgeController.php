<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatKnowledgeEntry;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Curated answers the assistant may quote.
 *
 * This is what makes the public widget useful: an anonymous visitor has no
 * personal-data capabilities at all, so published guidance is the only thing
 * the assistant can draw on for procedural questions.
 */
class ChatKnowledgeController extends Controller
{
    protected $title, $route, $view, $access;

    public function __construct()
    {
        $this->title = __('Chat Knowledge Base');
        $this->route = 'admin.chat-knowledge';
        $this->view = 'admin.chat-knowledge';
        $this->access = 'chat-knowledge';

        $this->middleware('permission:' . $this->access . '-view')->only(['index', 'edit']);
        $this->middleware('permission:' . $this->access . '-create')->only('store');
        $this->middleware('permission:' . $this->access . '-edit')->only('update');
        $this->middleware('permission:' . $this->access . '-delete')->only('destroy');
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['rows'] = ChatKnowledgeEntry::orderBy('sort_order')->orderBy('id')->paginate(25);
        $data['row'] = null;

        return view($this->view . '.index', $data);
    }

    public function edit(ChatKnowledgeEntry $chatKnowledge)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['rows'] = ChatKnowledgeEntry::orderBy('sort_order')->orderBy('id')->paginate(25);
        $data['row'] = $chatKnowledge;

        return view($this->view . '.index', $data);
    }

    public function store(Request $request)
    {
        $entry = new ChatKnowledgeEntry($this->validated($request));
        $entry->created_by = Auth::guard('web')->id();
        $entry->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    public function update(Request $request, ChatKnowledgeEntry $chatKnowledge)
    {
        $chatKnowledge->fill($this->validated($request));
        $chatKnowledge->updated_by = Auth::guard('web')->id();
        $chatKnowledge->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    public function destroy(ChatKnowledgeEntry $chatKnowledge)
    {
        $chatKnowledge->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:4000'],
            'tags' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'for_web' => ['nullable', 'boolean'],
            'for_application' => ['nullable', 'boolean'],
            'for_student' => ['nullable', 'boolean'],
            'for_admin' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        // Unchecked boxes are absent from the payload.
        foreach (['for_web', 'for_application', 'for_student', 'for_admin', 'status'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
