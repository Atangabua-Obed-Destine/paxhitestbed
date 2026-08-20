<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

/**
 * The audit trail for the chat assistant.
 *
 * A feature that reads student records has to be reviewable after the fact:
 * who asked, what was asked, which capability ran, with what arguments and
 * what came back.
 */
class ChatConversationController extends Controller
{
    protected $title, $route, $view, $access;

    public function __construct()
    {
        $this->title = __('Chat Conversations');
        $this->route = 'admin.chat-conversation';
        $this->view = 'admin.chat-conversation';
        $this->access = 'chat-conversation';

        $this->middleware('permission:' . $this->access . '-view')->only(['index', 'show']);
        $this->middleware('permission:' . $this->access . '-delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['rows'] = ChatConversation::query()
            ->when($request->filled('actor_type'), fn ($q) => $q->where('actor_type', $request->actor_type))
            ->when($request->filled('surface'), fn ($q) => $q->where('surface', $request->surface))
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $data['filters'] = $request->only(['actor_type', 'surface']);

        return view($this->view . '.index', $data);
    }

    public function show(ChatConversation $chatConversation)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['row'] = $chatConversation;
        $data['messages'] = $chatConversation->messages()->get();

        return view($this->view . '.show', $data);
    }

    public function destroy(ChatConversation $chatConversation)
    {
        // Messages cascade via the foreign key.
        $chatConversation->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }
}
