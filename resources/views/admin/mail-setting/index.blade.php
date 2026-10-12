@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12 col-lg-8">
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

                            <div class="form-group col-md-6">
                                <label for="driver" class="form-label">{{ __('field_mail_driver') }} <span>*</span></label>
                                <select class="form-control" name="driver" id="driver" required>
                                    <option value="smtp" @if(isset($row->driver)) @if($row->driver == 'smtp') selected @endif @endif>{{ __('SMTP') }}</option>
                                    <option value="sendmail"@if(isset($row->driver))  @if($row->driver == 'sendmail') selected @endif @endif>{{ __('SendMail') }}</option>
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_driver') }}
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="host" class="form-label">{{ __('field_mail_host') }} <span>*</span></label>
                                <input type="text" class="form-control" name="host" id="host" value="{{ isset($row->host)?$row->host:'' }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_host') }}
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="port" class="form-label">{{ __('field_mail_port') }} <span>*</span></label>
                                <input type="text" class="form-control" name="port" id="port" value="{{ isset($row->port)?$row->port:'' }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_port') }}
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="username" class="form-label">{{ __('field_mail_username') }} <span>*</span></label>
                                <input type="text" class="form-control" name="username" id="username" value="{{ isset($row->username)?$row->username:'' }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_username') }}
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="password" class="form-label">{{ __('field_mail_password') }} <span>*</span></label>
                                <input type="password" class="form-control" name="password" id="password" value="{{ isset($row->password)?$row->password:'' }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_password') }}
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="encryption" class="form-label">{{ __('field_mail_encryption') }} <span>*</span></label>

                                <select class="form-control" name="encryption" id="encryption" required>
                                    <option value="">{{ __('select') }}</option>
                                    <option value="ssl" @if(isset($row->encryption)) @if($row->encryption == 'ssl') selected @endif @endif>{{ __('SSL') }}</option>
                                    <option value="tls"@if(isset($row->encryption))  @if($row->encryption == 'tls') selected @endif @endif>{{ __('TLS') }}</option>
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_encryption') }}
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="sender_email" class="form-label">{{ __('field_mail_sender_email') }} <span>*</span></label>
                                <input type="email" class="form-control" name="sender_email" id="sender_email" value="{{ isset($row->sender_email)?$row->sender_email:'' }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_sender_email') }}
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="sender_name" class="form-label">{{ __('field_mail_sender_name') }} <span>*</span></label>
                                <input type="text" class="form-control" name="sender_name" id="sender_name" value="{{ isset($row->sender_name)?$row->sender_name:'' }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_sender_name') }}
                                </div>
                            </div>

                            {{-- <div class="form-group col-md-6">
                                <label for="reply_email" class="form-label">{{ __('field_mail_reply_email') }} <span>*</span></label>
                                <input type="email" class="form-control" name="reply_email" id="reply_email" value="{{ isset($row->reply_email)?$row->reply_email:'' }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_mail_reply_email') }}
                                </div>
                            </div> --}}
                            <!-- Form End -->
                          </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                        </div>
                    </div>

                    {{-- Mail is the one setting whose failure is silent: nothing
                         on screen breaks, applicants simply never receive their
                         letters. This asks the server directly, using whatever is
                         in the fields above rather than what was last saved, so a
                         correction can be proved before it replaces a value that
                         worked. --}}
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('Test these settings') }}</h5>
                            <span class="text-muted">{{ __('Sends one message using the values above. Nothing is saved.') }}</span>
                        </div>
                        <div class="card-block">
                            <div class="row align-items-end">
                                <div class="form-group col-md-7 mb-md-0">
                                    <label for="test_email" class="form-label">{{ __('Send the test to') }}</label>
                                    <input type="email" class="form-control" id="test_email"
                                           value="{{ optional(auth()->user())->email }}"
                                           placeholder="you@example.com">
                                </div>
                                <div class="form-group col-md-5 mb-md-0">
                                    <button type="button" class="btn btn-outline-primary w-100" id="mail-test-btn">
                                        <i class="fas fa-paper-plane"></i> {{ __('Send test email') }}
                                    </button>
                                </div>
                            </div>

                            <div id="mail-test-result" class="mt-3 d-none"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->


@push('scripts')
<script>
(function () {
    "use strict";

    var button = document.getElementById('mail-test-btn');
    var panel  = document.getElementById('mail-test-result');

    if (!button || !panel) {
        return;
    }

    var form = button.closest('form');

    function value(name) {
        var field = form.querySelector('[name="' + name + '"]');
        return field ? field.value : '';
    }

    function escape(text) {
        var holder = document.createElement('div');
        holder.textContent = text === null || text === undefined ? '' : text;
        return holder.innerHTML;
    }

    var MARKS = { passed: 'fa-check text-success', failed: 'fa-times text-danger', skipped: 'fa-minus text-muted', pending: 'fa-minus text-muted' };

    function render(result) {
        var rows = (result.steps || []).map(function (step) {
            return '<li class="mb-1"><i class="fas ' + (MARKS[step.state] || MARKS.pending) + ' me-2"></i>'
                 + escape(step.label)
                 + (step.note ? ' <span class="text-muted small d-block ms-4">' + escape(step.note) + '</span>' : '')
                 + '</li>';
        }).join('');

        panel.className = 'mt-3 alert ' + (result.ok ? 'alert-success' : 'alert-danger');
        panel.innerHTML =
            '<strong class="d-block mb-2">' + escape(result.headline) + '</strong>'
            + '<ul class="list-unstyled mb-0">' + rows + '</ul>'
            + (result.detail ? '<div class="small mt-2 pt-2 border-top">' + escape(result.detail) + '</div>' : '');
    }

    button.addEventListener('click', function () {
        var recipient = (document.getElementById('test_email') || {}).value || '';

        panel.classList.remove('d-none');

        if (!recipient.trim()) {
            panel.className = 'mt-3 alert alert-warning';
            panel.textContent = {!! json_encode(__('Enter an address to send the test to.')) !!};
            return;
        }

        var original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + {!! json_encode(__('Testing…')) !!};

        panel.className = 'mt-3 alert alert-secondary';
        panel.textContent = {!! json_encode(__('Contacting the mail server. This can take a few seconds.')) !!};

        var body = new FormData();
        body.append('_token', value('_token'));
        body.append('driver', value('driver'));
        body.append('host', value('host'));
        body.append('port', value('port'));
        body.append('username', value('username'));
        body.append('password', value('password'));
        body.append('encryption', value('encryption'));
        body.append('sender_email', value('sender_email'));
        body.append('sender_name', value('sender_name'));
        body.append('test_email', recipient);

        fetch({!! json_encode(route($route.'.test')) !!}, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: body
        })
        .then(function (response) {
            return response.json().then(function (data) { return { status: response.status, data: data }; });
        })
        .then(function (payload) {
            if (payload.status === 422 && payload.data && payload.data.errors) {
                // Laravel's own validation, shown as it came rather than as a
                // mail failure: a bad address is not a broken mail server.
                var first = Object.keys(payload.data.errors)[0];
                render({ ok: false, headline: payload.data.errors[first][0], steps: [], detail: null });
                return;
            }
            render(payload.data);
        })
        .catch(function () {
            panel.className = 'mt-3 alert alert-danger';
            panel.textContent = {!! json_encode(__('The test could not be completed. The request to the server failed.')) !!};
        })
        .finally(function () {
            button.disabled = false;
            button.innerHTML = original;
        });
    });
})();
</script>
@endpush

@endsection
