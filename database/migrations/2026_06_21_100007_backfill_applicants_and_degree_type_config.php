<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\DegreeType;
use App\Models\Field;
use App\Models\ApplicationSetting;
use App\Support\ApplicationDocumentRequirements;

/**
 * One-time data migration:
 *  (1) Create an Applicant login account for every existing application (dedup
 *      by email) and link applications.applicant_id; backfill degree_type_id
 *      from the chosen program (default HND) and session_id where present.
 *  (2) Seed each degree type's form configuration from the CURRENT live form
 *      (global Field statuses + ApplicationDocumentRequirements + env fee). The
 *      form on the system today represents the HND degree type, so HND mirrors
 *      it exactly and every other degree type starts from the same baseline for
 *      admins to tailor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillApplicants();
        $this->seedDegreeTypeConfig();
    }

    private function backfillApplicants(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('applicants')) {
            return;
        }

        // Resolve the default (HND) degree type id used as a fallback.
        $hndId = optional(
            DegreeType::where('shortcode', 'HND')->orWhere('is_hnd', 1)->first()
        )->id;

        $apps = DB::table('applications')->orderBy('id')->get();

        $applicantByEmail = []; // email => applicant id

        foreach ($apps as $app) {
            // --- degree type backfill (from program) ---
            $degreeTypeId = $app->degree_type_id ?? null;
            if (!$degreeTypeId && $app->program_id) {
                $degreeTypeId = DB::table('programs')->where('id', $app->program_id)->value('degree_type_id');
            }
            $degreeTypeId = $degreeTypeId ?: $hndId;

            // --- applicant account (one per email) ---
            $applicantId = null;
            $email = $app->email ? strtolower(trim($app->email)) : null;

            if ($email) {
                if (isset($applicantByEmail[$email])) {
                    $applicantId = $applicantByEmail[$email];
                } else {
                    $existing = DB::table('applicants')->where('email', $email)->first();
                    if ($existing) {
                        $applicantId = $existing->id;
                    } else {
                        $applicantId = DB::table('applicants')->insertGetId([
                            'first_name' => $app->first_name ?? null,
                            'last_name' => $app->last_name ?? null,
                            'email' => $email,
                            'phone' => $app->phone ?? null,
                            'password' => $app->password ?? Hash::make(Str::random(16)),
                            'remember_token' => $app->remember_token ?? null,
                            'email_verified_at' => $app->email_verified_at ?? null,
                            'portal_last_login_at' => $app->portal_last_login_at ?? null,
                            'created_at' => $app->created_at ?? now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $applicantByEmail[$email] = $applicantId;
                }
            }

            DB::table('applications')->where('id', $app->id)->update([
                'applicant_id' => $applicantId,
                'degree_type_id' => $degreeTypeId,
            ]);
        }
    }

    private function seedDegreeTypeConfig(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('degree_type_documents')) {
            return;
        }

        $degreeTypes = DegreeType::all();
        if ($degreeTypes->isEmpty()) {
            return;
        }

        // Current global field statuses (the live HND form).
        $fieldStatuses = Field::pluck('status', 'slug')->toArray();

        // Current global document catalog.
        $docs = ApplicationDocumentRequirements::all();

        // Current global fee + intro.
        $feeEnabled = filter_var(env('ADMISSION_FEE_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $feeAmount = (float) env('ADMISSION_FEE_AMOUNT', 15000);
        $feeDueDays = (int) env('ADMISSION_FEE_DUE_DAYS', 30);
        $feeInstructions = env('ADMISSION_FEE_INSTRUCTIONS', null);
        $intro = optional(ApplicationSetting::where('slug', 'admission')->first())->body;

        foreach ($degreeTypes as $dt) {
            // Field overrides (copy of current global).
            foreach ($fieldStatuses as $slug => $status) {
                DB::table('degree_type_field_settings')->updateOrInsert(
                    ['degree_type_id' => $dt->id, 'slug' => $slug],
                    ['status' => $status, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            // Documents (copy of current global catalog).
            $order = 0;
            foreach ($docs as $key => $doc) {
                DB::table('degree_type_documents')->updateOrInsert(
                    ['degree_type_id' => $dt->id, 'doc_key' => $key],
                    [
                        'label' => $doc['label'] ?? $key,
                        'description' => $doc['description'] ?? null,
                        'required' => !empty($doc['required']),
                        'assign_to_column' => $doc['assign_to_column'] ?? null,
                        'sort_order' => $order++,
                        'status' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // Application settings (fee + intro).
            DB::table('degree_type_application_settings')->updateOrInsert(
                ['degree_type_id' => $dt->id],
                [
                    'intro_html' => $intro,
                    'requirements_html' => $dt->requirements ?? null,
                    'fee_enabled' => $feeEnabled,
                    'fee_amount' => $feeAmount,
                    'fee_due_days' => $feeDueDays,
                    'fee_instructions' => $feeInstructions,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Non-destructive: leave seeded config and applicant links in place.
    }
};
