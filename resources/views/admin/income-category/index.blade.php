@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{-- Shared with the other side of the sheet; the two files were identical. --}}
@include('admin.account.partials.category_index')

@endsection
