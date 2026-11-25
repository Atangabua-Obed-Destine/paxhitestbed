@extends('student.layouts.master')
@section('title', $title)
@section('content')
@include('common.mailbox._style')
@php $to = old('to', $selected); @endphp
<div class="main-body mbx">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-pen"></i> {{ $title }}</h5>
            </div>
            <div class="card-block">
                @include('common.mailbox._nav', ['folder' => null, 'unread' => 0])

                @if(empty($lecturers) && $offices->isEmpty())
                    <div class="mbx-empty">
                        {{ __('There is nobody you can write to yet. Your lecturers appear here once you have registered your courses for this semester.') }}
                        <div class="mt-2"><a href="{{ route('student.mailbox.index') }}">{{ __('Back to Messages') }}</a></div>
                    </div>
                @else
                <form method="post" action="{{ route('student.mailbox.store') }}" enctype="multipart/form-data" style="max-width:48rem">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="mbx-to">{{ __('To') }}</label>
                        <select id="mbx-to" name="to" class="form-control @error('to') is-invalid @enderror" required>
                            <option value="">{{ __('Choose who to write to') }}</option>
                            @if(count($lecturers))
                            <optgroup label="{{ __('My lecturers') }}">
                                @foreach($lecturers as $userId => $codes)
                                @php $key = 'staff:' . $userId; @endphp
                                <option value="{{ $key }}" @selected($to === $key)>{{ $members->name(['staff', $userId]) }} — {{ implode(', ', $codes) }}</option>
                                @endforeach
                            </optgroup>
                            @endif
                            @if($offices->count())
                            <optgroup label="{{ __('Offices') }}">
                                @foreach($offices as $office)
                                @php $key = 'office:' . $office->id; @endphp
                                <option value="{{ $key }}" @selected($to === $key)>{{ $office->title }}@if($office->description) — {{ $office->description }}@endif</option>
                                @endforeach
                            </optgroup>
                            @endif
                        </select>
                        @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="mbx-subject">{{ __('Subject') }}</label>
                        <input id="mbx-subject" name="subject" class="form-control @error('subject') is-invalid @enderror" required maxlength="190" value="{{ old('subject') }}">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold" for="mbx-body">{{ __('Message') }}</label>
                        <textarea id="mbx-body" name="body" rows="7" class="form-control @error('body') is-invalid @enderror" required maxlength="20000">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @include('common.mailbox._attach', ['attachId' => 'c'])
                    <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> {{ __('Send') }}</button></div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
