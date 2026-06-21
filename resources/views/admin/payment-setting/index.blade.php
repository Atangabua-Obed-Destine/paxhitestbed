@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12 col-lg-10">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('btn_update') }} {{ $title }}</h5>
                        </div>
                        <div class="card-block">
                          <div class="row">
                            <!-- Form Start -->
                            <div class="container">
                            <div class="row">
                            <div class="col-md-12">
                            <div class="form-group">
                                <label for="status" class="form-label">{{ __('Select Gateway') }} <span>*</span></label>
                                <select class="form-control" name="status" id="status" required>
                                    <option value="none" @if(config('payment.status') == 'none') selected @endif>{{ __('None') }}</option>
                                    <option value="paypal" @if(config('payment.status') == 'paypal') selected @endif>{{ __('PayPal') }}</option>
                                    <option value="stripe" @if(config('payment.status') == 'stripe') selected @endif>{{ __('Stripe') }}</option>
                                    <option value="razorpay" @if(config('payment.status') == 'razorpay') selected @endif>{{ __('RazorPay') }}</option>
                                    <option value="paystack" @if(config('payment.status') == 'paystack') selected @endif>{{ __('PayStack') }}</option>
                                    <option value="flutterwave" @if(config('payment.status') == 'flutterwave') selected @endif>{{ __('Flutterwave') }}</option>
                                    {{-- <option value="skrill" @if(config('payment.status') == 'skrill') selected @endif>{{ __('Skrill') }}</option> --}}
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('Payment Gateway') }}
                                </div>
                            </div>

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active text-uppercase" id="PayPal-tab" data-bs-toggle="tab" href="#PayPal" role="tab" aria-controls="PayPal" aria-selected="false">{{ __('PayPal') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="Stripe-tab" data-bs-toggle="tab" href="#Stripe" role="tab" aria-controls="Stripe" aria-selected="true">{{ __('Stripe') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="RazorPay-tab" data-bs-toggle="tab" href="#RazorPay" role="tab" aria-controls="RazorPay" aria-selected="false">{{ __('RazorPay') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="PayStack-tab" data-bs-toggle="tab" href="#PayStack" role="tab" aria-controls="PayStack" aria-selected="false">{{ __('PayStack') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="Flutterwave-tab" data-bs-toggle="tab" href="#Flutterwave" role="tab" aria-controls="Flutterwave" aria-selected="false">{{ __('Flutterwave') }}</a>
                                </li>
                                {{-- <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="Skrill-tab" data-bs-toggle="tab" href="#Skrill" role="tab" aria-controls="Skrill" aria-selected="false">{{ __('Skrill') }}</a>
                                </li> --}}
                            </ul>
                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="PayPal" role="tabpanel" aria-labelledby="PayPal-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="paypal_client_id" class="form-label">{{ __('Client ID') }}</label>
                                        <input type="text" class="form-control" name="paypal_client_id" id="paypal_client_id" value="{{ config('payment.paypal.client_id') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Client ID') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="paypal_secret" class="form-label">{{ __('Secret Key') }}</label>
                                        <input type="password" class="form-control" name="paypal_secret" id="paypal_secret" value="{{ config('payment.paypal.secret') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Secret Key') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="Stripe" role="tabpanel" aria-labelledby="Stripe-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="stripe_key" class="form-label">{{ __('Public Key') }}</label>
                                        <input type="text" class="form-control" name="stripe_key" id="stripe_key" value="{{ config('payment.stripe.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Public Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="stripe_secret" class="form-label">{{ __('Secret Key') }}</label>
                                        <input type="password" class="form-control" name="stripe_secret" id="stripe_secret" value="{{ config('payment.stripe.secret') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Secret Key') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="RazorPay" role="tabpanel" aria-labelledby="RazorPay-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="razorpay_key" class="form-label">{{ __('Public Key') }}</label>
                                        <input type="text" class="form-control" name="razorpay_key" id="razorpay_key" value="{{ config('payment.razorpay.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Public Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="razorpay_secret" class="form-label">{{ __('Secret Key') }}</label>
                                        <input type="password" class="form-control" name="razorpay_secret" id="razorpay_secret" value="{{ config('payment.razorpay.secret') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Secret Key') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="PayStack" role="tabpanel" aria-labelledby="PayStack-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="paystack_key" class="form-label">{{ __('Public Key') }}</label>
                                        <input type="text" class="form-control" name="paystack_key" id="paystack_key" value="{{ config('payment.paystack.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Public Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="paystack_secret" class="form-label">{{ __('Secret Key') }}</label>
                                        <input type="password" class="form-control" name="paystack_secret" id="paystack_secret" value="{{ config('payment.paystack.secret') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Secret Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="paystack_email" class="form-label">{{ __('Merchant Email') }}</label>
                                        <input type="text" class="form-control" name="paystack_email" id="paystack_email" value="{{ config('payment.paystack.email') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Merchant Email') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="Flutterwave" role="tabpanel" aria-labelledby="Flutterwave-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="flutterwave_key" class="form-label">{{ __('Public Key') }}</label>
                                        <input type="text" class="form-control" name="flutterwave_key" id="flutterwave_key" value="{{ config('payment.flutterwave.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Public Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="flutterwave_secret" class="form-label">{{ __('Secret Key') }}</label>
                                        <input type="password" class="form-control" name="flutterwave_secret" id="flutterwave_secret" value="{{ config('payment.flutterwave.secret') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Secret Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="flutterwave_hash" class="form-label">{{ __('Hash') }}</label>
                                        <input type="password" class="form-control" name="flutterwave_hash" id="flutterwave_hash" value="{{ config('payment.flutterwave.hash') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Hash') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="Skrill" role="tabpanel" aria-labelledby="Skrill-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="skrill_email" class="form-label">{{ __('Email') }}</label>
                                        <input type="text" class="form-control" name="skrill_email" id="skrill_email" value="{{ config('payment.skrill.email') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Email') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="skrill_secret" class="form-label">{{ __('Secret Key') }}</label>
                                        <input type="password" class="form-control" name="skrill_secret" id="skrill_secret" value="{{ config('payment.skrill.secret') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Secret Key') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                            </div>
                            </div>

                            </div>
                            </div>
                            <!-- Form End -->
                          </div>
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

@endsection