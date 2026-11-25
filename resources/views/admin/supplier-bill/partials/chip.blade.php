@php
    $chipLabels = [
        'draft' => __('Draft'),
        'unpaid' => __('Unpaid'),
        'part_paid' => __('Part paid'),
        'paid' => __('Paid'),
        'void' => __('Void'),
    ];
@endphp
<span class="ap-chip {{ $state }}">{{ $chipLabels[$state] ?? $state }}</span>
@if(!empty($overdue))
    <span class="ap-chip overdue">{{ __('Overdue') }}</span>
@endif
