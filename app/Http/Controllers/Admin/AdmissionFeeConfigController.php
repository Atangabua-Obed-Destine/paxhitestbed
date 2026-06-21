<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeesCategory;
use App\Models\Setting;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

class AdmissionFeeConfigController extends Controller
{
    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = 'Admission Fee Configuration';
        $this->route = 'admin.admission-fee-config';
        $this->view = 'admin.admission-fee-config';
        $this->path = 'admission-fee';
        $this->access = 'application';

        $this->middleware('permission:'.$this->access.'-view', ['only' => ['index']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['update']]);
    }

    /**
     * Display admission fee configuration
     */
    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get admission fee category (is_admission = 1)
        $data['admissionCategory'] = FeesCategory::where('is_admission', 1)
            ->where('status', 1)
            ->first();

        // Get all active fee categories for dropdown
        $data['feeCategories'] = FeesCategory::where('status', 1)->orderBy('title', 'asc')->get();

        // Get system setting for currency
        $data['setting'] = Setting::where('status', '1')->first();

        // Get configuration from .env or default
        $data['currentFeeAmount'] = env('ADMISSION_FEE_AMOUNT', 15000);
        $data['currentDueDays'] = env('ADMISSION_FEE_DUE_DAYS', 30);

        return view($this->view.'.index', $data);
    }

    /**
     * Update admission fee configuration
     */
    public function update(Request $request)
    {
        $request->validate([
            'fee_category_id' => 'required|exists:fees_categories,id',
            'fee_amount' => 'required|numeric|min:0',
            'due_days' => 'required|integer|min:1|max:365',
            'payment_instructions' => 'nullable|string',
            'admission_fee_enabled' => 'nullable|boolean',
        ]);

        try {
            // Update the selected category to be the admission fee category
            // First, remove is_admission flag from all categories
            FeesCategory::where('is_admission', 1)->update(['is_admission' => 0]);

            // Set the new category as admission category
            $category = FeesCategory::findOrFail($request->fee_category_id);
            $category->is_admission = 1;
            $category->save();

            // Determine enabled status
            $admissionFeeEnabled = $request->has('admission_fee_enabled') ? 'true' : 'false';

            // Update .env file
            $this->updateEnvFile([
                'ADMISSION_FEE_ENABLED' => $admissionFeeEnabled,
                'ADMISSION_FEE_AMOUNT' => $request->fee_amount,
                'ADMISSION_FEE_DUE_DAYS' => $request->due_days,
                'ADMISSION_FEE_INSTRUCTIONS' => $request->payment_instructions ?? '',
            ]);

            // Clear all caches to ensure changes take effect
            \Artisan::call('config:clear');
            \Artisan::call('cache:clear');

            if ($admissionFeeEnabled === 'true') {
                Flasher::addSuccess('Admission fee requirement enabled and configuration updated successfully!');
            } else {
                Flasher::addSuccess('Admission fee requirement disabled. Applications can now be reviewed without payment.');
            }
            
            return redirect()->route($this->route.'.index');
        } catch (\Exception $e) {
            Flasher::addError('Error updating configuration: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Update .env file with new values
     */
    protected function updateEnvFile(array $data)
    {
        $envFile = app()->environmentFilePath();
        $envContent = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            // Escape special characters in value
            $value = str_replace(['"', "\n", "\r"], ['\"', '', ''], $value);
            
            // Check if key exists
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                // Update existing key
                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}=\"{$value}\"",
                    $envContent
                );
            } else {
                // Append new key
                $envContent .= "\n{$key}=\"{$value}\"";
            }
        }

        file_put_contents($envFile, $envContent);

        // Clear config cache
        \Artisan::call('config:clear');
    }
}
