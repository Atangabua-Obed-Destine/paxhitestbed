@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('admin.equipment._style')
<div class="main-body eq">
    <div class="page-wrapper">
        <div class="eq-head">
            <div>
                <h4 class="eq-title">{{ $title }}</h4>
                <p class="eq-sub">{{ __('The code goes on the item\'s sticker and is what the desk scans.') }}</p>
            </div>
            <div class="eq-head-side">
                <a class="eq-btn eq-btn-ghost eq-btn-sm" href="{{ route('admin.equipment.items.index') }}">{{ __('Back to the list') }}</a>
            </div>
        </div>

        <div class="eq-card">
            <form method="post" action="{{ $item->exists ? route('admin.equipment.items.update', $item->id) : route('admin.equipment.items.store') }}" class="eq-form" novalidate>
                @csrf
                @if($item->exists) @method('PUT') @endif

                <div class="eq-grid2">
                    <div>
                        <label class="t" for="code">{{ __('Sticker code') }} <span style="color:var(--eq-bad)">*</span></label>
                        <input class="eq-input" id="code" name="code" value="{{ old('code', $item->code) }}" required maxlength="40" style="text-transform:uppercase" placeholder="PRJ-003">
                        <span class="help">{{ __('Capital letters, numbers and dashes, for example PRJ-003 or LAP-012.') }}</span>
                        @error('code')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="t" for="name">{{ __('What it is') }} <span style="color:var(--eq-bad)">*</span></label>
                        <input class="eq-input" id="name" name="name" value="{{ old('name', $item->name) }}" required maxlength="150" placeholder="{{ __('Epson projector') }}">
                        @error('name')<span class="err">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="eq-grid2">
                    <div>
                        <label class="t" for="category">{{ __('Kind') }}</label>
                        <input class="eq-input" id="category" name="category" value="{{ old('category', $item->category) }}" maxlength="80" placeholder="{{ __('Projector, laptop, speaker...') }}">
                    </div>
                    <div>
                        <label class="t" for="desk_id">{{ __('Lent from') }}</label>
                        <select class="eq-select" id="desk_id" name="desk_id">
                            <option value="">{{ __('Any desk') }}</option>
                            @foreach($desks as $d)<option value="{{ $d->id }}" @selected(old('desk_id', $item->desk_id) == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                        <span class="help">{{ __('Printed on the sticker: "Return to the ...".') }}</span>
                    </div>
                </div>

                <div>
                    <label class="t" for="accessories">{{ __('What goes with it') }}</label>
                    <textarea class="eq-textarea" id="accessories" name="accessories" maxlength="1000" placeholder="{{ __("Power cable\nRemote\nHDMI cable\nCarry bag") }}">{{ old('accessories', implode("\n", $item->accessoryList())) }}</textarea>
                    <span class="help">{{ __('One per line. The desk lists them when lending and ticks them off when the item comes back.') }}</span>
                </div>

                <label class="eq-check"><input type="hidden" name="students_may_borrow" value="0"><input type="checkbox" name="students_may_borrow" value="1" @checked(old('students_may_borrow', $item->students_may_borrow))> {{ __('Students may borrow it') }}</label>

                <div class="eq-grid2">
                    <div>
                        <label class="t" for="state">{{ __('State') }}</label>
                        <select class="eq-select" id="state" name="state">
                            @foreach($states as $k => $v)<option value="{{ $k }}" @selected(old('state', $item->state) === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    @if($assets->count())
                    <div>
                        <label class="t" for="fixed_asset_id">{{ __('Asset register entry') }}</label>
                        <select class="eq-select" id="fixed_asset_id" name="fixed_asset_id">
                            <option value="">{{ __('Not linked') }}</option>
                            @foreach($assets as $a)<option value="{{ $a->id }}" @selected(old('fixed_asset_id', $item->fixed_asset_id) == $a->id)>{{ $a->asset_code }} {{ $a->name }}</option>@endforeach
                        </select>
                    </div>
                    @endif
                </div>

                <div>
                    <label class="t" for="notes">{{ __('Notes') }}</label>
                    <textarea class="eq-textarea" id="notes" name="notes" maxlength="2000" placeholder="{{ __('Serial number, purchase date, anything the desk should know') }}">{{ old('notes', $item->notes) }}</textarea>
                </div>

                <div class="eq-inline">
                    <button class="eq-btn eq-btn-primary" type="submit">{{ $item->exists ? __('Save') : __('Add equipment') }}</button>
                    <a class="eq-btn eq-btn-ghost" href="{{ route('admin.equipment.items.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>

            @if($item->exists)
            <form method="post" action="{{ route('admin.equipment.items.destroy', $item->id) }}" style="margin-top:22px;padding-top:16px;border-top:1px solid var(--eq-rule)" onsubmit="return confirm(@json(__('Remove :code from the list? Its history is kept.', ['code' => $item->code])))">
                @csrf @method('DELETE')
                <button class="eq-btn eq-btn-bad eq-btn-sm" type="submit">{{ __('Remove from the list') }}</button>
                <span class="eq-hint" style="margin-left:8px">{{ __('To stop lending it but keep it listed, set its state to "No longer lent" instead.') }}</span>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
