{{--
  The measured part of the ledger-grade language, shared by the money screens.

  Only the TOKENS live here, not the components. The account screens draw an
  eight-column ledger and the fees screens a sixteen-column report; their
  markup differs enough that sharing component rules would mean parameterising
  every selector, which makes the CSS unreadable for no gain. What is worth
  sharing is the part that was measured — and the part that would be quietly
  wrong if it drifted.

  Every value here was computed against white rather than chosen by eye:

    ink      #15233b  15.73:1   body text
    body     #4a5870   7.19:1   secondary text
    muted    #64748b   4.76:1   labels; the lighter grey it replaced was
                                3.06:1 and failed as text
    faint    #8794ab   3.06:1   rules and icons ONLY, never text

  The accents are per-section, so each screen's money is told apart before its
  heading is read. All three pass the categorical palette validator together
  (worst all-pairs CVD ΔE 10.4, normal-vision 20.8):

    income   flow #2a78d6  4.42:1  marks only   ink #1856a4  7.23:1
    expense  flow #eb6834  3.20:1  marks only   ink #9a3b12  6.98:1
    fees     flow #047857  5.48:1  text or mark ink #035c43  8.03:1

  Expects $scope (the selector list these tokens attach to) and $accent (one
  of income, expense, fees).
--}}
@php
    $ledgerAccents = [
        'income'  => ['#2a78d6', '#1856a4', '#eef4fc'],
        'expense' => ['#eb6834', '#9a3b12', '#fdf1ec'],
        'fees'    => ['#047857', '#035c43', '#e8f5f0'],
    ];

    [$ledgerFlow, $ledgerFlowInk, $ledgerFlowWash] =
        $ledgerAccents[$accent ?? 'income'] ?? $ledgerAccents['income'];
@endphp    {{ $scope }} {
        --ink:       #15233b;
        --body:      #4a5870;
        --muted:     #64748b;
        --faint:     #8794ab;
        --rule:      #e4e8f0;
        --rule-soft: #eef1f6;
        --surface:   #ffffff;
        --ground:    #f6f8fb;

        --flow:      {{ $ledgerFlow }};
        --flow-ink:  {{ $ledgerFlowInk }};
        --flow-wash: {{ $ledgerFlowWash }};

        --good:      #0f7a3d;
        --warn:      #9a6a06;
        --bad:       #b3261e;
    }
