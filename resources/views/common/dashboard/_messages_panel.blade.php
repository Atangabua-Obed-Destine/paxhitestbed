{{-- The Messages panel on a dashboard. Needs: $mbRoute ('admin.mailbox' | 'student.mailbox'), $mbMember, optional $mbGroups (staff). --}}
@php
    $mbService = app(\App\Services\Mailbox\Mailbox::class);
    $mbMembers = app(\App\Services\Mailbox\Members::class);
    $mbUnread = $mbService->unreadCount($mbMember);
    $mbThreads = $mbService->folder($mbMember, 'inbox')->limit(5)->get();
    $mbMembers->load($mbThreads->flatMap(fn ($t) => $t->participants->map(fn ($p) => [$p->member_type, (int) $p->member_id]))->all());
    $mbInitials = function ($name) { $p = preg_split('/\s+/', trim($name)); return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[count($p) - 1] ?? '', 0, 1)); };
    // Group messages: replies not yet read, across this person's group sends.
    $mbGroupReplies = 0;
    if (!empty($mbGroups)) {
        $mbGroupReplies = (int) \Illuminate\Support\Facades\DB::table('mailbox_threads as t')
            ->join('mailbox_participants as me', function ($j) use ($mbMember) {
                $j->on('me.thread_id', '=', 't.id')->where('me.member_type', $mbMember[0])->where('me.member_id', $mbMember[1]);
            })
            ->whereNotNull('t.broadcast_id')
            ->whereExists(fn ($e) => $e->from('mailbox_messages as m')->whereColumn('m.thread_id', 't.id')
                ->where(fn ($w) => $w->where('m.sender_type', '<>', $mbMember[0])->orWhere('m.sender_id', '<>', $mbMember[1]))
                ->whereRaw("m.created_at > COALESCE(me.last_read_at, '1970-01-01')"))
            ->count();
    }
@endphp
<div class="et-panel">
    <div class="et-panel-head">
        <h3 class="et-panel-title"><i class="fas fa-envelope" aria-hidden="true"></i> {{ __('Messages') }}</h3>
        <span class="et-section-note">
            @if($mbUnread){{ trans_choice('{1} One conversation unread|[2,*] :n conversations unread', $mbUnread, ['n' => $mbUnread]) }}@else{{ __('All read') }}@endif
        </span>
    </div>
    @if($mbThreads->count())
        <ul class="et-msgs">
            @foreach($mbThreads as $t)
                @php
                    $other = $t->participants->first(fn ($p) => !($p->member_type === $mbMember[0] && (int) $p->member_id === (int) $mbMember[1]));
                    $om = $other ? [$other->member_type, (int) $other->member_id] : $mbMember;
                    $others = $t->participants->count() - 1;
                    $who = $t->office ? $t->office->title . ' · ' . $mbMembers->name($om) : $mbMembers->name($om) . ($others > 1 ? ' +' . ($others - 1) : '');
                    $last = $t->latestMessage;
                @endphp
                <li class="{{ (int) $t->unread_count > 0 ? 'is-unread' : '' }}">
                    <a href="{{ route($mbRoute . '.show', $t->id) }}">
                        <span class="et-avatar {{ $om[0] === 'student' ? 'student' : '' }}" aria-hidden="true">{{ $mbInitials($mbMembers->name($om)) }}</span>
                        <span style="min-width:0">
                            <span class="et-msg-who">{{ $who }}</span>
                            <span class="et-msg-line"><b>{{ $t->subject }}</b>@if($last) — {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $last->body), 80) }}@endif</span>
                        </span>
                        <span class="et-msg-when">{{ optional($t->last_message_at)->isToday() ? $t->last_message_at->format('H:i') : optional($t->last_message_at)->format('d M') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <div class="et-empty"><i class="fas fa-inbox" aria-hidden="true" style="color: var(--et-faint)"></i> {{ __('No messages yet.') }}</div>
    @endif
    <div class="et-panel-foot">
        <a class="et-panel-link" href="{{ route($mbRoute . '.index') }}">{{ __('Open the mailbox') }} →</a>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            @if($mbGroupReplies)
                <a class="et-btn-quiet" href="{{ route($mbRoute . '.index', ['folder' => 'groups']) }}"><i class="fas fa-users" aria-hidden="true"></i> {{ trans_choice('{1} One new reply to a group message|[2,*] :n new replies to group messages', $mbGroupReplies, ['n' => $mbGroupReplies]) }}</a>
            @endif
            <a class="et-btn-quiet" href="{{ route($mbRoute . '.create') }}"><i class="fas fa-pen" aria-hidden="true"></i> {{ __('New message') }}</a>
        </div>
    </div>
</div>
