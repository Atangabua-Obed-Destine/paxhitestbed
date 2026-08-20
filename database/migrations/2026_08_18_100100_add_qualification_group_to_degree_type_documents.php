<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attaches a document requirement to a qualification card.
 *
 * A document with a qualification_group renders as an upload slot inside that
 * card on the Academic Qualifications step instead of as a separate item on the
 * Documents step — which is what removes the duplicate collection.
 *
 * Nothing about storage changes: the file still lands in application_documents
 * under the same doc_key, so existing uploads, the resubmission flow and the
 * assign_to_column mapping onto the student record are all untouched.
 */
return new class extends Migration
{
    /** Which of the seeded document keys belong to which qualification card. */
    protected array $groups = [
        'gce_al_certificate' => 'gce_al',
        'gce_al_transcript' => 'gce_al',
        'gce_ol_certificate' => 'gce_ol',
        'gce_ol_transcript' => 'gce_ol',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('degree_type_documents', 'qualification_group')) {
            Schema::table('degree_type_documents', function (Blueprint $table) {
                $table->string('qualification_group')->nullable()->after('assign_to_column');
            });
        }

        // Backfill the degree types already configured, so existing forms pick
        // up the cards without an administrator having to redo their checklist.
        foreach ($this->groups as $docKey => $group) {
            DB::table('degree_type_documents')
                ->where('doc_key', $docKey)
                ->whereNull('qualification_group')
                ->update(['qualification_group' => $group]);
        }

        $this->seedQualifications();
    }

    /**
     * Give every degree type that already has a checklist the matching cards.
     * Without this such a degree type would fall back to the global catalog and
     * could show a card whose documents it has not configured.
     */
    protected function seedQualifications(): void
    {
        $cards = [
            'gce_al' => ['GCE A-Level or equivalent', 'Enter your A Level, Baccalaureate, or equivalent qualification and upload its certificate or result slip.', 1],
            'gce_ol' => ['GCE O-Level or equivalent', 'Enter your O Level, BEPC, or equivalent qualification and upload its certificate or result slip.', 2],
        ];

        $degreeTypeIds = DB::table('degree_type_documents')
            ->whereIn('qualification_group', array_keys($cards))
            ->distinct()->pluck('degree_type_id');

        foreach ($degreeTypeIds as $degreeTypeId) {
            foreach ($cards as $key => [$label, $description, $sort]) {
                $exists = DB::table('degree_type_qualifications')
                    ->where('degree_type_id', $degreeTypeId)->where('qual_key', $key)->exists();

                // Only create a card the degree type actually has documents for.
                $hasDocs = DB::table('degree_type_documents')
                    ->where('degree_type_id', $degreeTypeId)->where('qualification_group', $key)->exists();

                if ($exists || !$hasDocs) {
                    continue;
                }

                DB::table('degree_type_qualifications')->insert([
                    'degree_type_id' => $degreeTypeId,
                    'qual_key' => $key,
                    'label' => $label,
                    'description' => $description,
                    'required' => 1,
                    'sort_order' => $sort,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('degree_type_documents', 'qualification_group')) {
            Schema::table('degree_type_documents', function (Blueprint $table) {
                $table->dropColumn('qualification_group');
            });
        }
    }
};
