@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('admin.equipment._style')
<div class="main-body eq">
    <div class="page-wrapper">
        <div class="eq-head">
            <div>
                <h4 class="eq-title">{{ __('Desk settings') }}</h4>
                <p class="eq-sub">{{ __('For the equipment desks and the library\'s scan desk.') }}</p>
            </div>
        </div>

        <div class="eq-grid2" style="align-items:start">
            <div class="eq-card">
                <form method="post" action="{{ route('admin.equipment.settings.update') }}" class="eq-form">
                    @csrf @method('PUT')
                    <div class="eq-grid2">
                        <div>
                            <label class="t" for="default_due_time">{{ __('Equipment is due back at') }}</label>
                            <input class="eq-input" type="time" id="default_due_time" name="default_due_time" value="{{ old('default_due_time', $deskSetting->default_due_time) }}" required>
                            <span class="help">{{ __('The end of the school day. Lent after it, an item is due the next day.') }}</span>
                            @error('default_due_time')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="t" for="library_loan_days">{{ __('Library books are lent for') }}</label>
                            <div class="eq-inline"><input class="eq-input" type="number" min="1" max="365" id="library_loan_days" name="library_loan_days" value="{{ old('library_loan_days', $deskSetting->library_loan_days) }}" required style="max-width:110px"> <span>{{ __('days') }}</span></div>
                            <span class="help">{{ __('The scan desk\'s starting due date. It can be changed for each book.') }}</span>
                            @error('library_loan_days')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="eq-grid2">
                        <div>
                            <label class="t" for="first_reminder_minutes">{{ __('First reminder, after the due time') }}</label>
                            <div class="eq-inline"><input class="eq-input" type="number" min="0" max="1440" id="first_reminder_minutes" name="first_reminder_minutes" value="{{ old('first_reminder_minutes', $deskSetting->first_reminder_minutes) }}" required style="max-width:110px"> <span>{{ __('minutes') }}</span></div>
                            @error('first_reminder_minutes')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="t" for="daily_reminder_time">{{ __('Then every day at') }}</label>
                            <input class="eq-input" type="time" id="daily_reminder_time" name="daily_reminder_time" value="{{ old('daily_reminder_time', $deskSetting->daily_reminder_time) }}" required>
                            <span class="help">{{ __('Not on Sundays. Reminders go to the Mailbox and by email.') }}</span>
                            @error('daily_reminder_time')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <label class="eq-check"><input type="hidden" name="block_across_desks" value="0"><input type="checkbox" name="block_across_desks" value="1" @checked($deskSetting->block_across_desks)> {{ __('An overdue library book stops equipment loans, and an overdue item stops library loans') }}</label>
                    <div><button class="eq-btn eq-btn-primary" type="submit">{{ __('Save settings') }}</button></div>
                </form>
            </div>

            <div class="eq-card">
                <h5 style="margin:0 0 4px;font-family:var(--eq-serif);font-weight:400;font-size:20px;color:var(--eq-ink)">{{ __('Lending desks') }}</h5>
                <p class="eq-sub" style="margin:0 0 12px">{{ __('The offices that lend equipment. The library has its own scan desk.') }}</p>
                @foreach($desks as $d)
                <form method="post" action="{{ route('admin.equipment.desks.update', $d->id) }}" class="eq-inline" style="padding:8px 0;border-bottom:1px solid var(--eq-rule)">
                    @csrf @method('PUT')
                    <input class="eq-input" name="name" value="{{ $d->name }}" required maxlength="100" aria-label="{{ __('Desk name') }}">
                    <label class="eq-check" style="font-size:13.5px"><input type="checkbox" name="status" value="1" @checked($d->status)> {{ __('In use') }}</label>
                    <span class="eq-hint" style="margin:0">{{ trans_choice(':count item|:count items', $d->items_count, ['count' => $d->items_count]) }}</span>
                    <button class="eq-btn eq-btn-ghost eq-btn-sm" type="submit">{{ __('Save') }}</button>
                </form>
                @endforeach
                <form method="post" action="{{ route('admin.equipment.desks.store') }}" class="eq-inline" style="margin-top:12px">
                    @csrf
                    <input class="eq-input" name="name" required maxlength="100" placeholder="{{ __('New desk, e.g. Science lab') }}" aria-label="{{ __('New desk name') }}">
                    <button class="eq-btn eq-btn-primary eq-btn-sm" type="submit">{{ __('Add desk') }}</button>
                </form>
                @error('name')<span class="err" style="display:block;margin-top:6px;color:var(--eq-bad)">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>
</div>
@endsection
