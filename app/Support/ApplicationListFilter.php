<?php

namespace App\Support;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The filters on the applications list, in one place.
 *
 * The list screen and the admissions board report both answer "which
 * applications match this form?". Written twice, the two answers drift apart
 * the first time somebody adds a filter to one of them, and a board would be
 * handed figures that do not match the screen the registrar is looking at. So
 * both read from here.
 *
 * Every rule below is the list's own, carried over unchanged — including the
 * slightly unusual "was this given" test, and the fallback to created_at for
 * applications that carry no apply_date.
 */
class ApplicationListFilter
{
    /** What the list loads for each row. */
    public const LIST_RELATIONS = ['admissionFee.paymentReceipts', 'degreeType', 'session', 'applicant', 'program', 'approvals'];

    /** The form values, under the names the list view reads. */
    public array $selected = [];

    protected Request $request;

    protected function __construct(Request $request)
    {
        $this->request = $request;
        $r = $request;

        $this->selected = [
            'selected_batch' => $this->given('batch') ? $r->batch : '0',
            'selected_program' => $this->given('program') ? $r->program : '0',
            'selected_degree_type' => $this->given('degree_type') ? $r->degree_type : '0',
            'selected_session' => $this->given('session') ? $r->session : '0',
            'selected_status' => $this->given('status') ? $r->status : '99',
            'selected_start_date' => $this->given('start_date')
                ? $r->start_date
                : date('Y-m-d', strtotime(Carbon::now()->subYear())),
            'selected_end_date' => $this->given('end_date')
                ? $r->end_date
                : date('Y-m-d', strtotime(Carbon::today())),
            'selected_registration_no' => $this->given('registration_no') ? $r->registration_no : null,
            'selected_applicant' => !empty($r->applicant) ? trim($r->applicant) : null,
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request);
    }

    /**
     * The list's own test for whether a value was given: not empty, or "0".
     * "0" is a real choice for status — it means rejected.
     */
    protected function given(string $key): bool
    {
        $value = $this->request->{$key};

        return !empty($value) || $value != null;
    }

    /** The list stays empty until a search has been made. */
    public function isSearching(): bool
    {
        $r = $this->request;

        return isset($r->program)
            || isset($r->status)
            || isset($r->registration_no)
            || isset($r->degree_type)
            || isset($r->session)
            || !empty($this->selected['selected_applicant']);
    }

    /** Is the form asking for unfinished drafts rather than submitted applications? */
    public function reportingOnDrafts(): bool
    {
        return $this->selected['selected_status'] === 'draft';
    }

    public function degreeTypeId(): ?int
    {
        return !empty($this->request->degree_type) ? (int) $this->request->degree_type : null;
    }

    /**
     * The matching applications.
     *
     * @param string|null $status  force a status in place of the form's — the
     *                             report uses 'draft' to count unfinished
     *                             drafts under the same filters
     * @param array|null  $with    relations to load; the list's by default
     */
    public function query(?string $status = null, ?array $with = null): Builder
    {
        $r = $this->request;

        $query = Application::with($with ?? self::LIST_RELATIONS)
            // Falls back to created_at: an application with no apply_date used
            // to match no date range at all and vanish from the list entirely,
            // which is a worse failure than showing it against the day it was
            // begun.
            ->whereRaw('DATE(COALESCE(apply_date, created_at)) >= ?', [$this->selected['selected_start_date']])
            ->whereRaw('DATE(COALESCE(apply_date, created_at)) <= ?', [$this->selected['selected_end_date']]);

        if (!empty($r->batch)) {
            $query->where('batch_id', $r->batch);
        }
        if (!empty($r->program)) {
            $query->where('program_id', $r->program);
        }
        if (!empty($r->degree_type)) {
            $query->where('degree_type_id', $r->degree_type);
        }
        if (!empty($r->session)) {
            $query->where('session_id', $r->session);
        }
        if (!empty($r->registration_no)) {
            $query->where('registration_no', 'LIKE', '%' . $r->registration_no . '%');
        }

        // Free-text search across applicant name, email and phone.
        if (!empty($this->selected['selected_applicant'])) {
            $like = '%' . $this->selected['selected_applicant'] . '%';

            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like])
                  ->orWhere('email', 'LIKE', $like)
                  ->orWhere('phone', 'LIKE', $like)
                  ->orWhereHas('applicant', function ($sub) use ($like) {
                      $sub->where('first_name', 'LIKE', $like)
                          ->orWhere('last_name', 'LIKE', $like)
                          ->orWhere('email', 'LIKE', $like)
                          ->orWhere('phone', 'LIKE', $like);
                  });
            });
        }

        // An unsubmitted draft is not an application yet — it is a form
        // somebody may still be filling in. Admissions acts on what has been
        // submitted, so drafts stay out unless asked for.
        $selectedStatus = $status ?? $this->selected['selected_status'];
        $requestedStatus = $status ?? $r->status;

        if ($selectedStatus === 'draft') {
            $query->where('stage', 'draft');
        } elseif (!empty($requestedStatus) || $requestedStatus != null) {
            $query->where('status', $selectedStatus)->where('stage', '!=', 'draft');
        } else {
            $query->where('stage', '!=', 'draft');
        }

        return $query;
    }

    /**
     * The filters in force, in words, for the cover of a report — so a printed
     * copy says what it is a report of.
     *
     * @return array<string, string>
     */
    public function describe(): array
    {
        $r = $this->request;
        $out = [];

        $format = function ($date) {
            try {
                return Carbon::parse($date)->format('d M Y');
            } catch (\Throwable $e) {
                return (string) $date;
            }
        };

        $out[__('Applied between')] = $format($this->selected['selected_start_date'])
            . ' – ' . $format($this->selected['selected_end_date']);

        if (!empty($r->degree_type)) {
            $out[__('Degree type')] = (string) optional(\App\Models\DegreeType::find($r->degree_type))->title;
        }
        if (!empty($r->session)) {
            $out[__('Intake')] = (string) optional(\App\Models\Session::find($r->session))->title;
        }
        if (!empty($r->program)) {
            $out[__('Programme')] = (string) optional(\App\Models\Program::find($r->program))->title;
        }
        if (!empty($r->batch)) {
            $out[__('Batch')] = (string) optional(\App\Models\Batch::find($r->batch))->title;
        }

        $out[__('Applications')] = match ((string) $this->selected['selected_status']) {
            '1' => __('status_pending'),
            '2' => __('status_approved'),
            '0' => __('status_rejected'),
            'draft' => __('Not yet submitted'),
            default => __('All submitted'),
        };

        if (!empty($r->registration_no)) {
            $out[__('Registration no. contains')] = (string) $r->registration_no;
        }
        if (!empty($this->selected['selected_applicant'])) {
            $out[__('Applicant matches')] = $this->selected['selected_applicant'];
        }

        return $out;
    }
}
