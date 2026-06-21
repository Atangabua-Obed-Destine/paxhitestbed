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
                            <input name="id" type="hidden" value="{{ (isset($row->id))?$row->id:-1 }}">

                            <div class="container">
                            <div class="row">
                            <div class="col-md-12">
                            <div class="form-group">
                                <label for="status" class="form-label">{{ __('Select Provider') }} <span>*</span></label>
                                <select class="form-control" name="status" id="status" required>
                                    <option value="0" @if(isset($row->status)) @if($row->status == '0') selected @endif @endif>{{ __('None') }}</option>
                                    <option value="1" @if(isset($row->status)) @if($row->status == '1') selected @endif @endif>{{ __('Twilio') }}</option>
                                    <option value="2" @if(isset($row->status)) @if($row->status == '2') selected @endif @endif>{{ __('Vonage') }}</option>
                                    <option value="3" @if(isset($row->status)) @if($row->status == '3') selected @endif @endif>{{ __('Text Local') }}</option>
                                    <option value="4" @if(isset($row->status)) @if($row->status == '4') selected @endif @endif>{{ __('Clicka Tell') }}</option>
                                    <option value="5" @if(isset($row->status)) @if($row->status == '5') selected @endif @endif>{{ __('Africas Talking') }}</option>
                                    <option value="6" @if(isset($row->status)) @if($row->status == '6') selected @endif @endif>{{ __('SMS Country') }}</option>
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('SMS Provider') }}
                                </div>
                            </div>

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active text-uppercase" id="Twilio-tab" data-bs-toggle="tab" href="#Twilio" role="tab" aria-controls="Twilio" aria-selected="false">{{ __('Twilio') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="Vonage-tab" data-bs-toggle="tab" href="#Vonage" role="tab" aria-controls="Vonage" aria-selected="true">{{ __('Vonage') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="TextLocal-tab" data-bs-toggle="tab" href="#TextLocal" role="tab" aria-controls="TextLocal" aria-selected="false">{{ __('Text Local') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="ClickaTell-tab" data-bs-toggle="tab" href="#ClickaTell" role="tab" aria-controls="ClickaTell" aria-selected="false">{{ __('Clicka Tell') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="AfricasTalking-tab" data-bs-toggle="tab" href="#AfricasTalking" role="tab" aria-controls="AfricasTalking" aria-selected="false">{{ __('Africas Talking') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-uppercase" id="SMSCountry-tab" data-bs-toggle="tab" href="#SMSCountry" role="tab" aria-controls="SMSCountry" aria-selected="false">{{ __('SMS Country') }}</a>
                                </li>
                            </ul>
                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="Twilio" role="tabpanel" aria-labelledby="Twilio-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="twilio_sid" class="form-label">{{ __('Twilio SID') }}</label>
                                        <input type="text" class="form-control" name="twilio_sid" id="twilio_sid" value="{{ config('sms.twilio.sid') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Twilio SID') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="twilio_auth_token" class="form-label">{{ __('Auth Token') }}</label>
                                        <input type="password" class="form-control" name="twilio_auth_token" id="twilio_auth_token" value="{{ config('sms.twilio.token') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Auth Token') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="twilio_number" class="form-label">{{ __('Sender Number') }}</label>
                                        <input type="text" class="form-control" name="twilio_number" id="twilio_number" value="{{ config('sms.twilio.number') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Sender Number') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="Vonage" role="tabpanel" aria-labelledby="Vonage-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="vonage_key" class="form-label">{{ __('API Key') }}</label>
                                        <input type="text" class="form-control" name="vonage_key" id="vonage_key" value="{{ config('sms.vonage.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('API Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="vonage_secret" class="form-label">{{ __('Secret') }}</label>
                                        <input type="password" class="form-control" name="vonage_secret" id="vonage_secret" value="{{ config('sms.vonage.secret') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Secret') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="vonage_number" class="form-label">{{ __('Sender Number') }}</label>
                                        <input type="text" class="form-control" name="vonage_number" id="vonage_number" value="{{ config('sms.vonage.number') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Sender Number') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="TextLocal" role="tabpanel" aria-labelledby="TextLocal-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="textlocal_key" class="form-label">{{ __('API Key') }}</label>
                                        <input type="text" class="form-control" name="textlocal_key" id="textlocal_key" value="{{ config('sms.textlocal.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('API Key') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="textlocal_sender" class="form-label">{{ __('Sender Name') }}</label>
                                        <input type="text" class="form-control" name="textlocal_sender" id="textlocal_sender" value="{{ config('sms.textlocal.sender') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Sender Name') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="ClickaTell" role="tabpanel" aria-labelledby="ClickaTell-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="clickatell_key" class="form-label">{{ __('API Key') }}</label>
                                        <input type="text" class="form-control" name="clickatell_key" id="clickatell_key" value="{{ config('sms.clickatell.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('API Key') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="AfricasTalking" role="tabpanel" aria-labelledby="AfricasTalking-tab">
                                    
                                    <div class="container mt-3">
                                    <div class="form-group">
                                        <label for="africas_talking_username" class="form-label">{{ __('Username') }}</label>
                                        <input type="text" class="form-control" name="africas_talking_username" id="africas_talking_username" value="{{ config('sms.africastalking.username') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Username') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="africas_talking_key" class="form-label">{{ __('API Key') }}</label>
                                        <input type="text" class="form-control" name="africas_talking_key" id="africas_talking_key" value="{{ config('sms.africastalking.key') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('API Key') }}
                                        </div>
                                    </div>
                                    </div>

                                </div>
                                <div class="tab-pane fade" id="SMSCountry" role="tabpanel" aria-labelledby="SMSCountry-tab">
                                    
                                    <div class="container mt-3">
                                        <div class="form-group">
                                        <label for="sms_country_username" class="form-label">{{ __('Username') }}</label>
                                        <input type="text" class="form-control" name="sms_country_username" id="sms_country_username" value="{{ config('sms.smscountry.user') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Username') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="sms_country_password" class="form-label">{{ __('Password') }}</label>
                                        <input type="password" class="form-control" name="sms_country_password" id="sms_country_password" value="{{ config('sms.smscountry.password') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Password') }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="sms_country_sender_id" class="form-label">{{ __('Sender ID') }}</label>
                                        <input type="text" class="form-control" name="sms_country_sender_id" id="sms_country_sender_id" value="{{ config('sms.smscountry.sender_id') }}">

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('Sender ID') }}
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