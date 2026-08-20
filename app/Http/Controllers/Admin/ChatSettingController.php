<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSetting;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

/**
 * Configuration for the context-aware chat assistant.
 *
 * Follows the same single-row settings shape as ApplicationSettingController.
 */
class ChatSettingController extends Controller
{
    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = __('Chat Assistant Settings');
        $this->route = 'admin.chat-setting';
        $this->view = 'admin.chat-setting';
        $this->path = 'chat-setting';
        $this->access = 'chat-setting';

        $this->middleware('permission:' . $this->access . '-view')->only('index');
        $this->middleware('permission:' . $this->access . '-edit')->only('update');
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['row'] = ChatSetting::current();
        $data['surfaces'] = ChatSetting::SURFACES;

        return view($this->view . '.index', $data);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'is_enabled' => ['nullable', 'boolean'],
            'enabled_web' => ['nullable', 'boolean'],
            'enabled_application' => ['nullable', 'boolean'],
            'enabled_student' => ['nullable', 'boolean'],
            'enabled_admin' => ['nullable', 'boolean'],
            'model' => ['required', 'string', 'max:100'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_output_tokens' => ['required', 'integer', 'min:128', 'max:8192'],
            'max_tool_calls' => ['required', 'integer', 'min:1', 'max:8'],
            'system_prompt' => ['nullable', 'string', 'max:4000'],
            'greeting_web' => ['nullable', 'string', 'max:500'],
            'greeting_application' => ['nullable', 'string', 'max:500'],
            'greeting_student' => ['nullable', 'string', 'max:500'],
            'greeting_admin' => ['nullable', 'string', 'max:500'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'history_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'escalation_enabled' => ['nullable', 'boolean'],
        ]);

        $setting = ChatSetting::current();

        // Unchecked boxes are absent from the payload, so normalise them here.
        foreach ([
            'is_enabled', 'enabled_web', 'enabled_application', 'enabled_student',
            'enabled_admin', 'escalation_enabled',
        ] as $flag) {
            $validated[$flag] = $request->boolean($flag);
        }

        $setting->update($validated);

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }
}
