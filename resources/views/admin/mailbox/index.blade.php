@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('common.mailbox._style')
<div class="main-body mbx">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-envelope"></i> {{ $title }}</h5>
            </div>
            <div class="card-block">
                @include('common.mailbox._nav')

                @if($folder === 'groups')
                    <div class="mbx-list">
                        @forelse($broadcasts as $b)
                        <a class="mbx-row" href="{{ route('admin.mailbox.broadcast', $b->id) }}">
                            <span class="mbx-avatar" aria-hidden="true"><i class="fas fa-users"></i></span>
                            <span class="mbx-who">{{ $b->audience }}<small class="mbx-count">{{ trans_choice(':n person|:n people', $b->recipient_count, ['n' => $b->recipient_count]) }} · {{ trans_choice(':n reply|:n replies', $b->replied_count, ['n' => $b->replied_count]) }}</small></span>
                            <span class="mbx-line"><span class="mbx-subject">{{ $b->subject }}</span> — {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $b->body), 110) }}</span>
                            <span class="mbx-when">{{ $b->created_at->format('d M Y') }}</span>
                        </a>
                        @empty
                        <div class="mbx-empty">{{ __('Messages you send to a class, programme or department appear here, with who has replied.') }}</div>
                        @endforelse
                    </div>
                    <div class="mt-3">{{ $broadcasts->links() }}</div>
                @else
                    @include('common.mailbox._list')
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
