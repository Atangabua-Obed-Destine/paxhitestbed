@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('common.mailbox._style')
@php
    $initials = function ($name) { $p = preg_split('/\s+/', trim($name)); return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[count($p) - 1] ?? '', 0, 1)); };
    $replied = $threads->filter(fn ($t) => $t->messages->contains(fn ($m) => !($m->sender_type === $me[0] && (int) $m->sender_id === (int) $me[1])));
@endphp
<div class="main-body mbx">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-block">
                <div class="mbx-head">
                    <div>
                        <h4>{{ $broadcast->subject }}</h4>
                        <div class="mbx-people">{{ __('Sent to') }} <strong>{{ $broadcast->audience }}</strong> · {{ $broadcast->created_at->format('d M Y, H:i') }}</div>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.mailbox.index', ['folder' => 'groups']) }}"><i class="fas fa-arrow-left"></i> {{ __('Group messages') }}</a>
                </div>

                <div class="mbx-msg mine">
                    <div class="mbx-bubble">
                        <div class="mbx-meta"><strong>{{ __('You') }}</strong></div>
                        <div class="mbx-body">{{ $broadcast->body }}</div>
                        @php $files = optional($threads->first())->messages?->first()?->attachments ?? collect(); @endphp
                        @if($files->count())
                        <div class="mbx-files">
                            @foreach($files as $file)
                            <a class="mbx-file" href="{{ route('admin.mailbox.attachment', $file->id) }}"><i class="fas fa-paperclip"></i> {{ $file->original_name }}</a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>

                <p class="mbx-count"><strong>{{ trans_choice(':n person|:n people', $threads->count(), ['n' => $threads->count()]) }}</strong> {{ __('received it') }} · <strong>{{ $replied->count() }}</strong> {{ __('replied') }}. {{ __('Each reply is private between you and that person.') }}</p>

                <div class="mbx-list">
                    @foreach($threads->sortByDesc(fn ($t) => $replied->contains($t) ? $t->last_message_at : null) as $t)
                        @php
                            $other = $t->participants->first(fn ($p) => !($p->member_type === $me[0] && (int) $p->member_id === (int) $me[1]));
                            $om = $other ? [$other->member_type, (int) $other->member_id] : $me;
                            $theirs = $t->messages->filter(fn ($m) => !($m->sender_type === $me[0] && (int) $m->sender_id === (int) $me[1]));
                            $read = $other && $other->last_read_at;
                        @endphp
                        <a class="mbx-row" href="{{ route('admin.mailbox.show', $t->id) }}">
                            <span class="mbx-avatar {{ $om[0] === 'student' ? 'student' : '' }}" aria-hidden="true">{{ $initials($members->name($om)) }}</span>
                            <span class="mbx-who">{{ $members->name($om) }}<small>{{ $members->detail($om) }}</small></span>
                            <span class="mbx-line">
                                @if($theirs->count())
                                    <span class="badge bg-success">{{ __('Replied') }}</span> {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $theirs->last()->body), 100) }}
                                @elseif($read)
                                    <span class="badge bg-info">{{ __('Read') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('Not opened yet') }}</span>
                                @endif
                            </span>
                            <span class="mbx-when">{{ $theirs->count() ? $theirs->last()->created_at->format('d M, H:i') : '' }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
