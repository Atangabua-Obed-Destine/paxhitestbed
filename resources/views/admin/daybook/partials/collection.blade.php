{{--
  Who has paid, for this budget year.

  Everything above this on the page is a FLOW — money that moved in a month.
  This is a POSITION: where the year's billing stands right now. The two are
  not interchangeable, which is why the figures come from their own service
  (App\Services\FeeCollection) rather than from the daybook rows.

  Three things here are deliberate and will look odd if you do not know why:

    * STUDENTS lead and applicants are reported beside them, never pooled. On
      this install the 2026/2027 window holds 11 students and 26 applicants,
      and the applicants have nearly all paid a 21,000 admission fee. Pooled,
      the page reports "25 of 37 fully paid" — a healthy-looking year. The
      students alone are 8.5% collected. Pooling does not blur the answer here,
      it inverts it.

    * Colour is the three status roles at bar weight (--paid-full / -part /
      -none), validated as an ordered triple. Every one travels with its word,
      so the split is never colour alone.

    * Fees that belong to nobody are shown rather than dropped, the same way
      the page already shows money on no budget line.

  Expects $collection, plus $budget for the drill-through links.
--}}

@php
    $c = $collection;
    $st = $c['students'];
    $ap = $c['applicants'];

    // The bar is drawn from the student counts, so it cannot disagree with the
    // three figures printed beside it.
    $barTotal = max(1, $st['total']);
    $pct = fn ($n) => round($n / $barTotal * 100, 2);

    $owing = $st['part'] + $st['none'];
@endphp

<div class="viz-card" id="feeCollection">
    <div class="viz-head">
        <h5>{{ __('Who has paid, and who has not') }}</h5>
        <p class="sub">
            {{ __('Students billed a :scope in this budget year, counted as people rather than as fees: a student with both instalments and one paid is one student who is partly paid.', [
                'scope' => implode(' ' . __('or') . ' ', $c['scope']['names'])]) }}
            @if(!$c['scope']['matched'])
                {{-- Only shown when the catalogue has no instalment category to
                     match. Silence here would mean the section reported on
                     every fee while its headings said instalments. --}}
                <strong>{{ __('No instalment fee category was found in the catalogue, so this section is counting every fee instead.') }}</strong>
            @endif
        </p>
    </div>

    @if($st['total'] === 0 && $ap['total'] === 0)
        <div class="viz-body">
            <p class="sub mb-0">
                {{ __('No fees have been assigned in this budget year yet, so there is nobody to report on. This section fills in as fees are assigned.') }}
            </p>
        </div>
    @else

    <div class="viz-body">
        {{-- The three figures that were asked for. --}}
        <div class="fc-split">
            <div class="fc-stat fc-full">
                <div class="label">{{ __('Paid in full') }}</div>
                <div class="value">{{ number_format($st['full']) }}</div>
                <div class="of">{{ __('of :total students', ['total' => number_format($st['total'])]) }}</div>
            </div>
            <div class="fc-stat fc-part">
                <div class="label">{{ __('Paid in part') }}</div>
                <div class="value">{{ number_format($st['part']) }}</div>
                <div class="of">{{ __('some money received, a balance left') }}</div>
            </div>
            <div class="fc-stat fc-none">
                <div class="label">{{ __('Paid nothing at all') }}</div>
                <div class="value">{{ number_format($st['none']) }}</div>
                <div class="of">{{ __('not one franc received') }}</div>
            </div>
        </div>

        {{-- role="img" with the figures in the label: the bar is the only place
             the proportion is drawn, so a screen reader has to get it here. --}}
        <div class="fc-bar" role="img"
             aria-label="{{ __(':full paid in full, :part in part, :none nothing, of :total students', [
                'full' => $st['full'], 'part' => $st['part'], 'none' => $st['none'], 'total' => $st['total']]) }}">
            @if($st['full'] > 0)<span class="s-full" style="width: {{ $pct($st['full']) }}%"></span>@endif
            @if($st['part'] > 0)<span class="s-part" style="width: {{ $pct($st['part']) }}%"></span>@endif
            @if($st['none'] > 0)<span class="s-none" style="width: {{ $pct($st['none']) }}%"></span>@endif
        </div>

        {{-- Identity is never colour alone. --}}
        <div class="key px-0 pt-2">
            <span><i style="background: var(--paid-full)"></i>{{ __('Paid in full') }}</span>
            <span><i style="background: var(--paid-part)"></i>{{ __('Paid in part') }}</span>
            <span><i style="background: var(--paid-none)"></i>{{ __('Paid nothing') }}</span>
        </div>
    </div>

    {{-- The money behind those counts. --}}
    <div class="tiles fc-tiles">
        <div class="tile">
            <div class="label">{{ __('Billed this year') }}</div>
            <div class="value">{{ number_format($st['billed'] + $ap['billed']) }}</div>
            <div class="note">{{ __('Fees assigned between :from and :to, after discounts and including fines.', [
                'from' => \Carbon\Carbon::parse($c['window']['from'])->format('j M Y'),
                'to' => \Carbon\Carbon::parse($c['window']['to'])->format('j M Y')]) }}</div>
        </div>
        <div class="tile in">
            <div class="label">{{ __('Collected') }}</div>
            <div class="value">{{ number_format($st['collected'] + $ap['collected']) }}</div>
            <div class="note">{{ __('Received and still belonging to these fees. Credit moved to settle another fee is counted there, not twice.') }}</div>
        </div>
        <div class="tile {{ $st['outstanding'] > 0 ? 'deficit' : '' }}">
            <div class="label">{{ __('Still outstanding') }}</div>
            <div class="value">{{ number_format($st['outstanding'] + $ap['outstanding']) }}</div>
            <div class="note">
                {{ __('What this year\'s billing has yet to bring in.') }}
                @if($owing > 0)
                    <span class="status-chip chip-warning">
                        <i class="fas fa-user-clock"></i>
                        {{ trans_choice(':count student to chase|:count students to chase', $owing, ['count' => $owing]) }}
                    </span>
                @endif
            </div>
        </div>
        <div class="tile">
            <div class="label">{{ __('Collection rate, students') }}</div>
            <div class="value">{{ number_format($st['rate'], 1) }}%</div>
            <div class="note">
                {{ __('Of what students were billed this year.') }}
                @if($ap['total'] > 0)
                    <br><span class="fc-aside">{{ __('Applicants, counted apart: :rate% of :billed across :n.', [
                        'rate' => number_format($ap['rate'], 1),
                        'billed' => number_format($ap['billed']),
                        'n' => $ap['total']]) }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- What the scope leaves out. Stated, not dropped: resit and admission
         fees are real money, they are simply not what "has the student paid
         their fees" asks, and a section that omitted them without a word
         would be the kind of quiet omission this page exists to prevent. --}}
    @if($c['scope']['matched'] && $c['scope']['excluded']['fees'] > 0)
    <div class="fc-note">
        <i class="fas fa-filter"></i>
        <div>
            <strong>{{ __('Not counted above: :amount', ['amount' => number_format($c['scope']['excluded']['billed'])]) }}</strong>
            {{ trans_choice('across :count other fee|across :count other fees', $c['scope']['excluded']['fees'], ['count' => $c['scope']['excluded']['fees']]) }}
            — {{ __('resit fees, admission fees and anything else that is not an instalment, of which :owed is still owed.', [
                'owed' => number_format($c['scope']['excluded']['owed'])]) }}
            <span class="fc-aside">{{ __('They are broken out in full under "Collection by fee" below. A student who has settled a small resit fee but neither instalment belongs in "paid nothing at all", which is why they are kept out of the counts.') }}</span>
        </div>
    </div>
    @endif

    {{-- Brought forward. Without this the page would show a clean year while
         last session's debts sat uncollected and invisible. --}}
    @if($c['arrears']['people'] > 0)
    <div class="fc-note fc-note-warn">
        <i class="fas fa-history"></i>
        <div>
            <strong>{{ __('Owed from earlier years: :amount', ['amount' => number_format($c['arrears']['amount'])]) }}</strong>
            {{ trans_choice('across :count person|across :count people', $c['arrears']['people'], ['count' => $c['arrears']['people']]) }}{{ $c['arrears']['oldest'] ? ', ' . __('oldest assigned :date', ['date' => \Carbon\Carbon::parse($c['arrears']['oldest'])->format('j M Y')]) : '' }}.
            <span class="fc-aside">{{ __('Not in the figures above, which cover this year\'s billing only — but it is money the institution is still owed.') }}</span>
        </div>
    </div>
    @endif

    @if($c['plans']['people'] > 0)
    <div class="fc-note">
        <i class="fas fa-file-signature"></i>
        <div>
            {{ trans_choice(':count student is on an agreed payment plan|:count students are on agreed payment plans', $c['plans']['people'], ['count' => $c['plans']['people']]) }}
            ({{ number_format($c['plans']['amount']) }}).
            <span class="fc-aside">{{ __('They appear as partly paid above, which is what they are — but they are working to a schedule rather than simply behind.') }}</span>
        </div>
    </div>
    @endif

    <div class="viz-read">
        <div>
            <h6>{{ __('How to read it') }}</h6>
            <p>{{ __('Each student is counted once, however many instalments they carry. Paid in full means every fee assigned to them this year is settled; paid nothing means no money has been received against any of them. The rate is money, not people, so one large unpaid fee moves it more than several small ones.') }}</p>
        </div>
        <div>
            <h6>{{ __('What to do with it') }}</h6>
            <p>
                @if($st['none'] > 0)
                    {{ trans_choice('Start with the :count student who has paid nothing — that is a conversation, not a reminder.|Start with the :count students who have paid nothing — those are conversations, not reminders.', $st['none'], ['count' => $st['none']]) }}
                @elseif($owing > 0)
                    {{ __('Everybody has paid something. The balances below are reminders rather than conversations.') }}
                @else
                    {{ __('Every student billed this year is settled. Nothing to chase.') }}
                @endif
                {{ __('The ranking by programme shows where to spend the effort.') }}
            </p>
        </div>
    </div>

    @endif
</div>

{{-- ---- Admission fees, counted apart --------------------------------------
     Its own card, deliberately, and placed AFTER the student figures so it
     can never be mistaken for part of them. An admission fee is paid by
     somebody who is not a student yet; adding the two was measured to turn
     "11 students, 8.5% collected" into "37 people, 25 fully paid".
--}}
@if($c['admissions']['has_data'])
@php $ad = $c['admissions']; @endphp
<div class="viz-card">
    <div class="viz-head">
        <h5>{{ __('Admission fees, counted on their own') }}</h5>
        <p class="sub">
            {{ __('People billed a :scope in this budget year. These are mostly applicants, who are not students yet — so they are counted here and nowhere else on this page. None of these figures are part of the student figures above.', [
                'scope' => implode(' ' . __('or') . ' ', $ad['names'])]) }}
        </p>
    </div>

    <div class="viz-body">
        <div class="fc-split">
            <div class="fc-stat fc-full">
                <div class="value">{{ number_format($ad['full']) }}</div>
                <div class="label">{{ __('Paid in full') }}</div>
                <div class="of">{{ __('of :total people billed', ['total' => number_format($ad['total'])]) }}</div>
            </div>
            <div class="fc-stat fc-part">
                <div class="value">{{ number_format($ad['part']) }}</div>
                <div class="label">{{ __('Paid in part') }}</div>
                <div class="of">{{ __('some money received, a balance left') }}</div>
            </div>
            <div class="fc-stat fc-none">
                <div class="value">{{ number_format($ad['none']) }}</div>
                <div class="label">{{ __('Paid nothing at all') }}</div>
                <div class="of">{{ __('not one franc received') }}</div>
            </div>
        </div>
    </div>

    <div class="tiles fc-tiles">
        <div class="tile">
            <div class="label">{{ __('Billed this year') }}</div>
            <div class="value">{{ number_format($ad['billed']) }}</div>
        </div>
        <div class="tile in">
            <div class="label">{{ __('Collected') }}</div>
            <div class="value">{{ number_format($ad['collected']) }}</div>
        </div>
        <div class="tile {{ $ad['outstanding'] > 0 ? 'deficit' : '' }}">
            <div class="label">{{ __('Still outstanding') }}</div>
            <div class="value">{{ number_format($ad['outstanding']) }}</div>
        </div>
        <div class="tile">
            <div class="label">{{ __('Collection rate, admissions') }}</div>
            <div class="value">{{ number_format($ad['rate'], 1) }}%</div>
        </div>
    </div>

    <div class="viz-read">
        <div>
            <h6>{{ __('How to read it') }}</h6>
            <p>{{ __('One line per person, exactly as above, but over admission fees only. An unpaid admission fee is an admission that has not completed rather than a student in arrears, which is why it is never added to the student counts.') }}</p>
        </div>
        <div>
            <h6>{{ __('What to do with it') }}</h6>
            <p>{{ __('Read this with the admissions office rather than with the chasing list: somebody who has not paid to come in is a place that is still open, not a debt.') }}</p>
        </div>
    </div>
</div>
@endif

{{-- ---- Collection month by month ------------------------------------------
     The same arithmetic as the daybook's fee rows — cash received, dated by
     pay_date — so this curve and the money-in bars above it cannot disagree.
--}}
@if(count($c['by_month']))
<div class="viz-card">
    <div class="viz-head">
        <h5>{{ __('Fee collection, month by month') }}</h5>
        <p class="sub">
            {{ __('Instalment cash received in this budget year, and the running total.') }}
            {{ __('This is a wider figure than "Collected" above, deliberately: it counts every franc of instalment money that came in during the year, including instalments billed in earlier years, while the figures above cover only what was billed this year.') }}
            {{ __('The table below carries a second column for all fee cash, instalments and everything else together — that is the figure that ties to the money-in bars on this page.') }}
        </p>
    </div>
    <div class="key">
        <span><i style="background: var(--series-in)"></i>{{ __('Instalments received in the month') }}</span>
        <span><i style="background: var(--text-secondary)"></i>{{ __('Running total') }}</span>
    </div>
    <div class="viz-body"><div class="viz-canvas"><canvas id="chartFeeCollection"></canvas></div></div>

    <details class="numbers">
        <summary>{{ __('The numbers behind this chart') }}</summary>
        <table>
            <thead><tr>
                <th>{{ __('Month') }}</th>
                <th class="n">{{ __('Instalments received') }}</th>
                <th class="n">{{ __('Running total') }}</th>
                <th class="n">{{ __('All fee cash') }}</th>
            </tr></thead>
            <tbody>
            @foreach($c['by_month'] as $m)
                <tr>
                    <td>{{ $m['label'] }}</td>
                    <td class="n">{{ number_format($m['cash']) }}</td>
                    <td class="n">{{ number_format($m['cumulative']) }}</td>
                    <td class="n">{{ number_format($m['all_cash']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </details>

    <div class="viz-read">
        <div>
            <h6>{{ __('How to read it') }}</h6>
            <p>{{ __('Fee income arrives in bursts around registration rather than evenly, so a flat month is normal and a flat registration month is not. The running total is what to compare against the year gone.') }}</p>
        </div>
        <div>
            <h6>{{ __('What to do with it') }}</h6>
            <p>{{ __('A fee paid in instalments is dated here by its last payment, because the fee carries one payment date — the daybook dates it the same way, which is why the two agree. Read the shape of the year from this, and a single student\'s history from their receipts.') }}</p>
        </div>
    </div>
</div>
@endif

{{-- ---- By programme ------------------------------------------------------- --}}
@if(count($c['by_programme']))
@php $worstDue = max(array_column($c['by_programme'], 'owed')) ?: 1; @endphp
<div class="viz-card">
    <div class="viz-head">
        <h5>{{ __('Collection by programme') }}</h5>
        <p class="sub">{{ __('Worst collection rate first — where chasing is worth the most.') }}</p>
    </div>
    <div class="viz-body">
        <table class="rank-table fc-rank mb-0">
            <thead><tr>
                <th>{{ trans_choice('module_program', 1) }}</th>
                <th class="rank-num">{{ __('People') }}</th>
                <th class="rank-num">{{ __('Billed') }}</th>
                <th class="rank-num">{{ __('Collected') }}</th>
                <th class="rank-num">{{ __('Outstanding') }}</th>
                <th style="width: 22%">{{ __('Rate') }}</th>
            </tr></thead>
            <tbody>
            @foreach($c['by_programme'] as $row)
                <tr>
                    <td>
                        {{ $row['programme'] }}
                        @if($row['faculty'])<div class="fc-aside">{{ $row['faculty'] }}</div>@endif
                    </td>
                    <td class="rank-num">{{ number_format($row['people']) }}</td>
                    <td class="rank-num">{{ number_format($row['due']) }}</td>
                    <td class="rank-num">{{ number_format($row['collected']) }}</td>
                    <td class="rank-num">{{ number_format($row['owed']) }}</td>
                    <td>
                        <div class="rank-track">
                            <div class="rank-bar {{ $row['rate'] >= 90 ? 'fc-b-full' : ($row['rate'] >= 50 ? 'fc-b-part' : 'fc-b-none') }}"
                                 style="width: {{ max(2, $row['rate']) }}%"></div>
                        </div>
                        <span class="rank-share">{{ number_format($row['rate'], 1) }}%</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="viz-read">
        <div>
            <h6>{{ __('How to read it') }}</h6>
            <p>{{ __('Rate is money collected against money billed for that programme, so a programme with one large unpaid fee can sit below one with many small ones. People is everybody billed under the programme, students and applicants alike.') }}</p>
        </div>
        <div>
            <h6>{{ __('What to do with it') }}</h6>
            <p>{{ __('The top row is where a morning of telephone calls recovers the most. Read it with the outstanding column, not the rate alone — a small programme can show a terrible rate over very little money.') }}</p>
        </div>
    </div>
</div>
@endif

{{-- ---- By fee category --------------------------------------------------- --}}
@if(count($c['by_category']))
<div class="viz-card">
    <div class="viz-head">
        <h5>{{ __('Collection by fee') }}</h5>
        <p class="sub">
            {{ __('Every fee category billed this year, not only the instalments — this is where the money the counts above leave out stays visible.') }}
            {{ __('The counted ones come first and are marked; the rest are shown so nothing disappears.') }}
        </p>
    </div>
    <div class="viz-body">
        <table class="rank-table fc-rank mb-0">
            <thead><tr>
                <th>{{ __('field_fees_category') }}</th>
                <th>{{ __('Counted above') }}</th>
                <th class="rank-num">{{ __('Fees') }}</th>
                <th class="rank-num">{{ __('Billed') }}</th>
                <th class="rank-num">{{ __('Collected') }}</th>
                <th class="rank-num">{{ __('Outstanding') }}</th>
                <th style="width: 22%">{{ __('Rate') }}</th>
            </tr></thead>
            <tbody>
            @foreach($c['by_category'] as $row)
                <tr>
                    <td>{{ $row['category'] }}</td>
                    <td>
                        {{-- A word, not a tick: identity is never carried by a
                             mark the reader has to go and decode. --}}
                        @if($row['in_scope'])
                            <span class="fc-tag">{{ __('Counted') }}</span>
                        @else
                            <span class="fc-aside">{{ __('Not counted') }}</span>
                        @endif
                    </td>
                    <td class="rank-num">{{ number_format($row['fees']) }}</td>
                    <td class="rank-num">{{ number_format($row['due']) }}</td>
                    <td class="rank-num">{{ number_format($row['collected']) }}</td>
                    <td class="rank-num">{{ number_format($row['owed']) }}</td>
                    <td>
                        <div class="rank-track">
                            <div class="rank-bar {{ $row['rate'] >= 90 ? 'fc-b-full' : ($row['rate'] >= 50 ? 'fc-b-part' : 'fc-b-none') }}"
                                 style="width: {{ max(2, $row['rate']) }}%"></div>
                        </div>
                        <span class="rank-share">{{ number_format($row['rate'], 1) }}%</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="viz-read">
        <div>
            <h6>{{ __('How to read it') }}</h6>
            <p>{{ __('This counts fees, not people, because a fee category is a property of the fee. A low rate on the second instalment with a high one on the first is the ordinary shape of a year in progress, not a problem.') }}</p>
            <p>{{ __('Only the rows marked "Counted" feed the three figures at the top of this section. The others are shown because they are money the institution is owed, but a resit fee is a separate transaction and an admission fee belongs to somebody who is not a student yet — neither answers "has this student paid their fees".') }}</p>
        </div>
        <div>
            <h6>{{ __('What to do with it') }}</h6>
            <p>{{ __('Compare each rate against how far through the year that fee is due. An instalment due in January reading 10% in February is behind; the same figure in October is not.') }}</p>
        </div>
    </div>
</div>
@endif

{{-- ---- Who owes the most ------------------------------------------------- --}}
@if(count($c['top_debtors']))
<div class="viz-card">
    <div class="viz-head">
        <h5>{{ __('The largest outstanding balances') }}</h5>
        <p class="sub">{{ __('Every fee assigned to this person this year, totalled.') }}</p>
    </div>
    <div class="viz-body">
        <table class="rank-table fc-rank mb-0">
            <thead><tr>
                <th>{{ __('field_student_name') }}</th>
                <th>{{ trans_choice('module_program', 1) }}</th>
                <th class="rank-num">{{ __('Billed') }}</th>
                <th class="rank-num">{{ __('Paid') }}</th>
                <th class="rank-num">{{ __('Outstanding') }}</th>
                <th class="rank-num">{{ __('Unpaid share') }}</th>
            </tr></thead>
            <tbody>
            @foreach($c['top_debtors'] as $row)
                <tr>
                    <td>
                        <strong>{{ $row['name'] }}</strong>
                        <div class="fc-aside">
                            {{ $row['reference'] }}
                            @if($row['kind'] === 'applicant')
                                <span class="fc-tag">{{ __('Applicant') }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $row['programme'] ?: '—' }}</td>
                    <td class="rank-num">{{ number_format($row['due']) }}</td>
                    <td class="rank-num">{{ number_format($row['paid']) }}</td>
                    <td class="rank-num"><strong>{{ number_format($row['owed']) }}</strong></td>
                    <td class="rank-num">{{ number_format($row['share'], 1) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="viz-read">
        <div>
            <h6>{{ __('How to read it') }}</h6>
            <p>{{ __('An applicant is marked as one: they have not enrolled yet, so an unpaid admission fee here is an admission that has not completed rather than a student in arrears.') }}</p>
        </div>
        <div>
            <h6>{{ __('What to do with it') }}</h6>
            <p>{{ __('Open the fees report filtered to the student to see which instalment is outstanding and what has been receipted, before any of this is raised with them.') }}</p>
        </div>
    </div>
</div>
@endif

{{-- ---- Records to repair ---------------------------------------------------
     Shown for the same reason the page shows money on no budget line: a
     figure the totals cannot include is a question for the Bursar, and
     leaving it out would make the page quietly wrong rather than visibly
     incomplete.

     It is kept OFF the printed pack. This is a repair task for whoever keeps
     the records, not a figure for a board meeting, and the pack carries a
     single sentence so its totals are still honest. --}}
@if($c['unattributed']['count'] > 0)
<div class="viz-card fc-repair">
    <div class="viz-head">
        <h5>{{ __('Records to repair: fees with no student attached') }}</h5>
        <p class="sub">
            {{ __('Not a collection figure. Each of these is a fee whose enrolment record was deleted while the fee itself was left behind, so the system no longer knows whose fee it is.') }}
            {{ __('They are in none of the figures above, and they are left out of the printed pack — but they are money on the books, so they are shown here for correction rather than dropped.') }}
        </p>
    </div>
    <div class="viz-body">
        <table class="rank-table fc-rank mb-0">
            <thead><tr>
                <th>{{ __('Fee') }}</th>
                <th>{{ __('field_fees_category') }}</th>
                <th>{{ __('field_assign_date') }}</th>
                <th class="rank-num">{{ __('Billed') }}</th>
                <th class="rank-num">{{ __('Outstanding') }}</th>
                <th>{{ __('Why') }}</th>
            </tr></thead>
            <tbody>
            @foreach($c['unattributed']['rows'] as $row)
                <tr>
                    <td class="rank-num">#{{ $row['id'] }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td>{{ $row['assign_date'] ? \Carbon\Carbon::parse($row['assign_date'])->format('j M Y') : '—' }}</td>
                    <td class="rank-num">{{ number_format($row['due']) }}</td>
                    <td class="rank-num">{{ number_format($row['owed']) }}</td>
                    <td class="fc-aside">
                        @if($row['reason'] === 'missing_enrolment')
                            {{ __('Its enrolment (:id) no longer exists.', ['id' => $row['enroll_id']]) }}
                        @else
                            {{ __('Its enrolment carries neither a student nor an applicant.') }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="viz-read">
        <div>
            <h6>{{ __('Why it matters') }}</h6>
            <p>{{ __('A fee with no person cannot be chased, cannot be receipted and cannot appear on anybody\'s statement, yet :amount of it is still outstanding. It is also a sign that an enrolment was deleted while its fees were left behind.', ['amount' => number_format($c['unattributed']['owed'])]) }}</p>
        </div>
        <div>
            <h6>{{ __('What to do with it') }}</h6>
            <p>{{ __('Each row is either a fee that should be re-attached to the right enrolment, or one that should be cancelled. Cancelling it is what takes it out of this list honestly; deleting the row would also take it out of the ledger.') }}</p>
        </div>
    </div>
</div>
@endif
