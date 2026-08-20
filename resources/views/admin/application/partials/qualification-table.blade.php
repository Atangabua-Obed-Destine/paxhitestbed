{{--
    Read-only qualification summary for staff.

    Shared by the application review screen and the printable preview so the two
    always agree. Each row leads with the qualification and carries the evidence
    collected for it, which is the whole point of the card design: a reviewer
    sees the certificate next to the qualification it proves.

    Expects:
      $row         Application
      $pdf         bool  — suppress links, since a printed page cannot follow them
      $tableClass  string — the print stylesheet uses its own table class
      $wrap        bool  — Bootstrap needs a responsive wrapper, dompdf does not
--}}
@php
    $pdf = $pdf ?? false;
    $tableClass = $tableClass ?? 'table table-striped table-sm';
    $wrap = $wrap ?? true;
    $documentConfig = \App\Services\DegreeTypeFormConfig::documents($row->degreeType);
    $cards = \App\Services\DegreeTypeFormConfig::qualifications($row->degreeType);
    $uploaded = $row->documents ? $row->documents->keyBy('document_type') : collect();

    // Which document keys evidence which card.
    $evidence = [];
    foreach ($documentConfig as $docKey => $document) {
        if (!empty($document['qualification_group'])) {
            $evidence[$document['qualification_group']][$docKey] = $document;
        }
    }

    $period = function ($history) {
        $from = $history->start_year ?: ($history->date_from ? date('Y', strtotime((string) $history->date_from)) : null);
        $to = $history->end_year ?: ($history->date_to ? date('Y', strtotime((string) $history->date_to)) : null);
        if (!$from && !$to) {
            return __('N/A');
        }
        return trim(($from ?: '—') . ' – ' . ($to ?: __('Present')));
    };
@endphp

@if($wrap)<div class="table-responsive">@endif
    <table class="{{ $tableClass }}">
        <thead>
            <tr>
                <th>{{ __('Qualification') }}</th>
                <th>{{ __('Awarding body') }}</th>
                <th>{{ __('School / Institution') }}</th>
                <th>{{ __('Location') }}</th>
                <th>{{ __('Years') }}</th>
                <th>{{ __('Evidence') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($row->academicHistories as $history)
                @php
                    $location = array_filter([$history->city, $history->country]);
                    $cardKey = $history->qualification_key;
                    $cardLabel = $cardKey && isset($cards[$cardKey]) ? $cards[$cardKey]['label'] : null;
                    $slots = $cardKey ? ($evidence[$cardKey] ?? []) : [];
                @endphp
                <tr>
                    <td>
                        {{ $history->certificate_obtained ?: ($cardLabel ?: __('N/A')) }}
                        @if($cardLabel && $history->certificate_obtained && $history->certificate_obtained !== $cardLabel)
                            <small class="d-block text-muted">{{ __('for') }} {{ $cardLabel }}</small>
                        @endif
                        @unless($cardKey)
                            <small class="d-block text-muted">{{ __('Added by applicant') }}</small>
                        @endunless
                    </td>
                    <td>{{ $history->awarding_body ?: __('N/A') }}</td>
                    <td>{{ $history->institution_name ?: __('N/A') }}</td>
                    <td>{{ count($location) ? implode(', ', $location) : __('N/A') }}</td>
                    <td>{{ $period($history) }}</td>
                    <td>
                        @forelse($slots as $docKey => $document)
                            @php $file = optional($uploaded->get($docKey))->file_path; @endphp
                            <div class="small">
                                @if($file && !$pdf)
                                    <a href="{{ asset('uploads/student/'.$file) }}" target="_blank">{{ $document['label'] }}</a>
                                @elseif($file)
                                    {{ $document['label'] }} — {{ __('provided') }}
                                @else
                                    <span class="text-muted">{{ $document['label'] }} — {{ $document['required'] ? __('missing') : __('optional') }}</span>
                                @endif
                            </div>
                        @empty
                            {{-- An applicant-added qualification keeps its file on the row itself. --}}
                            @if($history->certificate_file)
                                @if($pdf)
                                    <span class="small">{{ __('Certificate provided') }}</span>
                                @else
                                    <a class="small" href="{{ asset('uploads/student/'.$history->certificate_file) }}" target="_blank">{{ __('Certificate') }}</a>
                                @endif
                            @else
                                <span class="text-muted small">{{ __('N/A') }}</span>
                            @endif
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">{{ __('No qualifications recorded.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@if($wrap)</div>@endif

@php
    // Anything the applicant typed into the legacy subject fields still needs
    // to reach the reviewer, even though the form no longer collects it.
    $legacyNotes = $row->academicHistories->flatMap(fn ($h) => array_filter([
        $h->gce_ol_detail, $h->gce_al_detail, $h->probatoire_detail, $h->baccalaureate_detail, $h->notes,
    ]))->all();
@endphp
@if(count($legacyNotes))
    <p class="small text-muted mb-0"><strong>{{ __('Additional notes') }}:</strong> {{ implode(' | ', $legacyNotes) }}</p>
@endif
