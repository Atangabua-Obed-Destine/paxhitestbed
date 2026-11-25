{{-- One conversation: header, messages, reply box. Needs $mb, $thread, $me, $others, $archived, $members. --}}
@php
    $initials = function ($name) { $p = preg_split('/\s+/', trim($name)); return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[count($p) - 1] ?? '', 0, 1)); };
    $messages = $thread->messages;
@endphp
<div class="mbx-head">
    <div>
        <h4>{{ $thread->subject }}</h4>
        <div class="mbx-people">
            {{ __('With') }}
            @foreach($others as $o)
                <strong>{{ $members->name($o) }}</strong><span class="text-muted"> ({{ $members->detail($o) }})</span>@if(!$loop->last), @endif
            @endforeach
            @if($thread->office) · {{ __('Office') }}: {{ $thread->office->title }}@endif
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route($mb['route'] . '.index') }}"><i class="fas fa-arrow-left"></i> {{ __('Back to Inbox') }}</a>
        <form method="post" action="{{ route($mb['route'] . '.archive', $thread->id) }}">
            @csrf
            @if($archived)<input type="hidden" name="restore" value="1">@endif
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fas fa-archive"></i> {{ $archived ? __('Move to Inbox') : __('Archive') }}</button>
        </form>
        <form method="post" action="{{ route($mb['route'] . '.destroy', $thread->id) }}" onsubmit="return confirm('{{ __('Remove this conversation from your mailbox? The other people keep their copy.') }}')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash-alt"></i> {{ __('Remove') }}</button>
        </form>
    </div>
</div>

@if($thread->broadcast)
<div class="mbx-note">
    <i class="fas fa-users"></i>
    @if($thread->broadcast->sender_type === $me[0] && (int) $thread->broadcast->sender_id === (int) $me[1])
        {{ __('Part of your group message to :group. Replies here are between you and this person only.', ['group' => $thread->broadcast->audience]) }}
        @if($mb['isStaff'])<a href="{{ route('admin.mailbox.broadcast', $thread->broadcast->id) }}">{{ __('See every reply') }}</a>@endif
    @else
        {{ __('This message was sent to a group. Your reply goes only to :name.', ['name' => $members->name([$thread->broadcast->sender_type, (int) $thread->broadcast->sender_id])]) }}
    @endif
</div>
@endif

@foreach($messages as $msg)
    @php
        $sender = [$msg->sender_type, (int) $msg->sender_id];
        $mine = $sender[0] === $me[0] && $sender[1] === (int) $me[1];
    @endphp
    <div class="mbx-msg {{ $mine ? 'mine' : '' }}" @if($loop->last) id="latest" @endif>
        <span class="mbx-avatar {{ $sender[0] === 'student' ? 'student' : '' }}" aria-hidden="true">{{ $initials($members->name($sender)) }}</span>
        <div class="mbx-bubble">
            <div class="mbx-meta"><strong>{{ $mine ? __('You') : $members->name($sender) }}</strong> · <time datetime="{{ $msg->created_at->toIso8601String() }}">{{ $msg->created_at->format('d M Y, H:i') }}</time></div>
            <div class="mbx-body">{{ $msg->body }}</div>
            @if($msg->attachments->count())
            <div class="mbx-files">
                @foreach($msg->attachments as $file)
                <a class="mbx-file" href="{{ route($mb['route'] . '.attachment', $file->id) }}"><i class="fas fa-paperclip"></i> {{ $file->original_name }} <span class="text-muted">({{ $file->sizeLabel() }})</span></a>
                @endforeach
            </div>
            @endif
        </div>
    </div>
@endforeach

<form class="mbx-reply" method="post" action="{{ route($mb['route'] . '.reply', $thread->id) }}" enctype="multipart/form-data">
    @csrf
    <label for="mbx-reply-body" class="form-label fw-bold">{{ __('Reply') }}</label>
    <textarea id="mbx-reply-body" name="body" class="form-control @error('body') is-invalid @enderror" rows="4" required maxlength="20000" placeholder="{{ __('Write your reply') }}">{{ old('body') }}</textarea>
    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @include('common.mailbox._attach')
    <div class="mt-2 d-flex align-items-center gap-3 flex-wrap">
        <button type="submit" class="btn btn-primary"><i class="fas fa-reply"></i> {{ __('Send reply') }}</button>
        <span class="mbx-help">{{ __('The other people are told by email that a message is waiting; the text stays here.') }}</span>
    </div>
</form>
