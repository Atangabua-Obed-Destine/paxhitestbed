@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="text-center mb-4">
                            <a href="{{ route('student.form-a2.download') }}" class="btn btn-primary" target="_blank">
                                <i class="fas fa-print"></i> Print / Download Form A2
                            </a>
                        </div>
                        
                        <div class="embed-responsive embed-responsive-16by9" style="height: 800px; border: 1px solid #ddd;">
                            <iframe class="embed-responsive-item" src="{{ route('student.form-a2.download', ['preview' => 1]) }}" style="width: 100%; height: 100%; border: none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
