@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{-- The list itself is shared with the other side of the sheet. The two files
     were identical, and had already drifted once from the forms that feed
     them, so there is now one of them. --}}
@include('admin.account.partials.transactions_index')

@endsection
