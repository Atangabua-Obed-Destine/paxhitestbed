<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    
     <title>{{ $title }}</title>
     @include('admin.layouts.common.header_script')

</head>

<body>


    <!-- [ Main Content ] start -->
    <div class="container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    
                    <!-- Start Content-->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row justify-content-center mt-5">
                                <div class="col-lg-6 col-md-8">
                                <form class="needs-validation" novalidate action="{{ route('verify-purchase') }}" method="post" enctype="multipart/form-data">
                                @csrf
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>{{ $title }}</h5>
                                        </div>
                                        <div class="card-block">
                                            <!-- Form Start -->
                                            <div class="form-group">
                                                <label for="purchase_code" class="form-label">{{ __('Purchase Code') }} <span>*</span></label>
                                                <input type="text" class="form-control" name="purchase_code" id="purchase_code" value="{{ old('purchase_code') }}" required>

                                                <div class="invalid-feedback">
                                                  {{ __('required_field') }} {{ __('Purchase Code') }}
                                                </div>
                                            </div>
                                            <!-- Form End -->
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                                        </div>
                                    </div>
                                </form>
                                </div>
                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                    <!-- End Content-->
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->


    @include('admin.layouts.common.footer_script')

</body>
</html>