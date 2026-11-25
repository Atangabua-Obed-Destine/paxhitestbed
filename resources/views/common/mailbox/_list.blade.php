{{-- The conversations in one folder. Needs $mb, $threads, $folder. --}}
@php
    $members = $mb['members'];
    $me = $mb['member'];
    $initials = function ($name) { $p = preg_split('/\s+/', trim($name)); return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[count($p) - 1] ?? '', 0, 1)); };
@endphp
<div class="mbx-list">
    @forelse($threads as $t)
        @php
            $others = $t->participants->reject(fn ($p) => $p->member_type === $me[0] && (int) $p->member_id === (int) $me[1])->values();
            $first = $others->first();
            $firstMember = $first ? [$first->member_type, (int) $first->member_id] : $me;
            $names = $others->take(3)->map(fn ($p) => $members->name([$p->member_type, (int) $p->member_id]))->implode(', ');
            if ($others->count() > 3) { $names .= ' +' . ($others->count() - 3); }
            $last = $t->latestMessage;
            $unreadRow = (int) $t->unread_count > 0;
            $fromMe = $last && $last->sender_type === $me[0] && (int) $last->sender_id === (int) $me[1];
        @endphp
        <a class="mbx-row {{ $unreadRow ? 'unread' : '' }}" href="{{ route($mb['route'] . '.show', $t->id) }}">
            <span class="mbx-avatar {{ $firstMember[0] === 'student' ? 'student' : '' }}" aria-hidden="true">{{ $initials($members->name($firstMember)) }}</span>
            <span class="mbx-who">
                @if($unreadRow)<span class="mbx-dot" title="{{ __('Unread') }}"></span>@endif{{ $names ?: __('Only you') }}
                <small>{{ $t->office ? $t->office->title : ($others->count() === 1 ? $members->detail($firstMember) : __(':n people', ['n' => $others->count()])) }}</small>
            </span>
            <span class="mbx-line">
                <span class="mbx-subject">{{ $t->subject }}</span>
                @if($last) — {{ $fromMe ? __('You') . ': ' : '' }}{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $last->body), 110) }}@endif
            </span>
            <span class="mbx-when">
                @if($unreadRow && (int) $t->unread_count > 1)<span class="badge bg-primary rounded-pill">{{ $t->unread_count }}</span><br>@endif
                {{ optional($t->last_message_at)->isToday() ? $t->last_message_at->format('H:i') : optional($t->last_message_at)->format('d M Y') }}
            </span>
        </a>
    @empty
        <div class="mbx-empty">
            @if(!empty($search))
                {{ __('No conversation matches ":q".', ['q' => $search]) }}
            @elseif($folder === 'sent')
                {{ __('Messages you send appear here.') }}
            @elseif($folder === 'archive')
                {{ __('Conversations you archive appear here. A new reply brings one back to the Inbox.') }}
            @else
                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>{{ __('No messages yet.') }}
            @endif
        </div>
    @endforelse
</div>
@if(method_exists($threads, 'links'))
<div class="mt-3">{{ $threads->links() }}</div>
@endif
