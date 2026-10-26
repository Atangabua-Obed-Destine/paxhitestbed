{{--
  The income and expense category lists.

  One partial, because these two files were identical too. Same visual language
  as the records they classify, so the Account section reads as one thing.

  The add form stays beside the list rather than behind a button: on a screen
  whose whole job is maintaining a short list, a modal would be a click in the
  way.
--}}

@include('admin.account.partials.theme')

<div class="main-body acct acct-cat">
    <div class="page-wrapper">
        <div class="row">

            @can($access.'-create')
            <div class="col-lg-4">
                <form class="needs-validation" novalidate method="post"
                      action="{{ route($route.'.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="acct-panel">
                        <div class="acct-sec">
                            <div class="acct-sec-head">{{ __('acc_cat_new') }}</div>

                            <div class="form-group mb-0">
                                <label for="title" class="form-label">
                                    {{ __('field_title') }} <span>*</span>
                                </label>
                                <input type="text" class="form-control" name="title" id="title"
                                       value="{{ old('title') }}" required>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_title') }}
                                </div>
                            </div>
                        </div>

                        <div class="acct-foot">
                            <button type="submit" class="acct-btn acct-btn-main">
                                <i class="fas fa-plus"></i>{{ __('btn_save') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endcan

            <div class="col-lg-{{ auth()->user()->can($access.'-create') ? '8' : '12' }}">
                <div class="acct-panel">
                    <div class="acct-sec" style="padding-bottom:.5rem;">
                        <div class="acct-sec-head" style="margin-bottom:0;">
                            {{ __('acc_cat_list') }}
                            <span class="acct-label" style="letter-spacing:.04em;">
                                {{ number_format($rows->count()) }} {{ __('acc_cat_count') }}
                            </span>
                        </div>
                    </div>

                    @if($rows->isEmpty())
                        <div class="acct-empty">
                            <i class="fas fa-tags"></i>
                            <p>{{ __('acc_cat_none') }}</p>
                            <small>{{ __('acc_cat_none_hint') }}</small>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table id="basic-table" class="display table nowrap acct-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('field_title') }}</th>
                                    <th>{{ __('field_status') }}</th>
                                    <th class="n">{{ __('field_action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach( $rows as $key => $row )
                                <tr>
                                    <td class="acct-meta">{{ $key + 1 }}</td>
                                    <td class="acct-what">{{ $row->title }}</td>
                                    <td>
                                        {{-- State wears a word as well as a colour, so
                                             it does not depend on telling green from
                                             grey. --}}
                                        <span class="acct-state">
                                            <span class="acct-dot {{ $row->status == 1 ? 'is-on' : 'is-off' }}"></span>
                                            {{ $row->status == 1 ? __('status_active') : __('status_inactive') }}
                                        </span>
                                    </td>
                                    <td class="acct-acts">
                                        @can($access.'-edit')
                                        <button type="button" class="btn btn-icon btn-light btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}"
                                                title="{{ __('btn_edit') }}">
                                            <i class="far fa-edit"></i>
                                        </button>
                                        @include($view.'.edit')
                                        @endcan

                                        @can($access.'-delete')
                                        <button type="button" class="btn btn-icon btn-light btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}"
                                                title="{{ __('btn_delete') }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        @include('admin.layouts.inc.delete')
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
