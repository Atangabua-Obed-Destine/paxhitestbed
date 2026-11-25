{{-- Folder tabs, search and the New message button. Needs $mb, $folder, $unread. --}}
<div class="mbx-bar">
    <div class="mbx-tabs" role="tablist">
        <a class="mbx-tab {{ ($folder ?? '') === 'inbox' ? 'active' : '' }}" href="{{ route($mb['route'] . '.index') }}">
            <i class="fas fa-inbox"></i> {{ __('Inbox') }}@if(($unread ?? 0) > 0)<span class="badge bg-danger rounded-pill">{{ $unread }}</span>@endif
        </a>
        <a class="mbx-tab {{ ($folder ?? '') === 'sent' ? 'active' : '' }}" href="{{ route($mb['route'] . '.index', ['folder' => 'sent']) }}"><i class="fas fa-paper-plane"></i> {{ __('Sent') }}</a>
        @if($mb['isStaff'])
        <a class="mbx-tab {{ ($folder ?? '') === 'groups' ? 'active' : '' }}" href="{{ route($mb['route'] . '.index', ['folder' => 'groups']) }}"><i class="fas fa-users"></i> {{ __('Group messages') }}</a>
        @endif
        <a class="mbx-tab {{ ($folder ?? '') === 'archive' ? 'active' : '' }}" href="{{ route($mb['route'] . '.index', ['folder' => 'archive']) }}"><i class="fas fa-archive"></i> {{ __('Archive') }}</a>
        @if($mb['canOffices'])
        <a class="mbx-tab" href="{{ route('admin.mailbox.offices') }}"><i class="fas fa-building"></i> {{ __('Offices') }}</a>
        @endif
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(isset($folder) && $folder !== 'groups')
        <form class="mbx-search" method="get" action="{{ route($mb['route'] . '.index') }}">
            <input type="hidden" name="folder" value="{{ $folder }}">
            <input type="search" name="q" value="{{ $search ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('Search subject or text') }}" aria-label="{{ __('Search messages') }}">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
        </form>
        @endif
        <a href="{{ route($mb['route'] . '.create') }}" class="btn btn-sm btn-primary"><i class="fas fa-pen"></i> {{ __('New message') }}</a>
    </div>
</div>
