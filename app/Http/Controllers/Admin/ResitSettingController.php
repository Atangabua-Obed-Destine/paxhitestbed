<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ResitSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:resit-request-view');
        $this->middleware('permission:resit-request-transition')->only('update');
    }

    public function edit()
    {
        $currentFee = Config::get('resit.default_fee');

        return view('admin.resit.settings', [
            'currentFee' => $currentFee,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_fee' => ['required', 'numeric', 'min:0'],
        ]);

        $envPath = base_path('.env');
        $envContent = File::exists($envPath) ? File::get($envPath) : '';
        $key = 'RESIT_DEFAULT_FEE';
        $value = number_format((float) $data['default_fee'], 2, '.', '');

        if (Str::contains($envContent, $key . '=')) {
            $envContent = preg_replace(
                '/^' . $key . '=.*/m',
                $key . '=' . $value,
                $envContent
            );
        } else {
            $envContent .= PHP_EOL . $key . '=' . $value . PHP_EOL;
        }

        File::put($envPath, $envContent);

        Artisan::call('config:clear');

        return redirect()
            ->route('admin.resit-settings.edit')
            ->with('success', __('Default resit fee updated.'));
    }
}
