@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">

            <div class="col-md-12 col-lg-5">
                <form class="needs-validation" novalidate method="post"
                      action="{{ $row ? route($route.'.update', $row) : route($route.'.store') }}">
                    @csrf
                    @if($row) @method('PUT') @endif

                    <div class="card">
                        <div class="card-header">
                            <h5>{{ $row ? __('Edit entry') : __('New entry') }}</h5>
                        </div>
                        <div class="card-block">
                            <div class="form-group">
                                <label for="question" class="form-label">{{ __('Question') }} <span>*</span></label>
                                <input type="text" class="form-control" name="question" id="question"
                                       value="{{ old('question', $row->question ?? '') }}" required>
                                <div class="invalid-feedback">{{ __('required_field') }}</div>
                            </div>

                            <div class="form-group">
                                <label for="answer" class="form-label">{{ __('Answer') }} <span>*</span></label>
                                <textarea class="form-control" rows="6" name="answer" id="answer" required>{{ old('answer', $row->answer ?? '') }}</textarea>
                                <small class="text-muted">{{ __('Write it as you would say it. The assistant may quote or paraphrase this.') }}</small>
                            </div>

                            <div class="form-group">
                                <label for="tags" class="form-label">{{ __('Tags') }}</label>
                                <input type="text" class="form-control" name="tags" id="tags"
                                       value="{{ old('tags', $row->tags ?? '') }}" placeholder="{{ __('fees, deadline, scholarship') }}">
                            </div>

                            <div class="form-group">
                                <label class="form-label d-block">{{ __('Show to') }}</label>
                                @foreach(['for_web' => __('Public visitors'), 'for_application' => __('Applicants'), 'for_student' => __('Students'), 'for_admin' => __('Staff')] as $field => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" value="1" name="{{ $field }}" id="{{ $field }}"
                                               {{ old($field, $row->{$field} ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="row">
                                <div class="form-group col-6">
                                    <label for="sort_order" class="form-label">{{ __('Sort order') }}</label>
                                    <input type="number" min="0" class="form-control" name="sort_order" id="sort_order"
                                           value="{{ old('sort_order', $row->sort_order ?? 0) }}">
                                </div>
                                <div class="form-group col-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" name="status" id="status"
                                               {{ old('status', $row->status ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="status">{{ __('Active') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            @if($row)
                                <a href="{{ route($route.'.index') }}" class="btn btn-outline-secondary">{{ __('btn_cancel') }}</a>
                            @endif
                            <button type="submit" class="btn btn-primary">{{ __('btn_save') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-md-12 col-lg-7">
                <div class="card">
                    <div class="card-header"><h5>{{ __('Published entries') }}</h5></div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('Question') }}</th>
                                        <th>{{ __('Audience') }}</th>
                                        <th class="text-center">{{ __('Active') }}</th>
                                        <th class="text-end">{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($rows as $entry)
                                    <tr>
                                        <td>{{ $entry->question }}</td>
                                        <td>
                                            <small class="text-muted">
                                                {{ collect([
                                                    $entry->for_web ? __('Public') : null,
                                                    $entry->for_application ? __('Applicants') : null,
                                                    $entry->for_student ? __('Students') : null,
                                                    $entry->for_admin ? __('Staff') : null,
                                                ])->filter()->implode(', ') ?: __('Nobody') }}
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $entry->status ? 'success' : 'secondary' }}">
                                                {{ $entry->status ? __('Yes') : __('No') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @can('chat-knowledge-edit')
                                                <a href="{{ route($route.'.edit', $entry) }}" class="btn btn-sm btn-outline-primary">{{ __('btn_edit') }}</a>
                                            @endcan
                                            @can('chat-knowledge-delete')
                                                <form action="{{ route($route.'.destroy', $entry) }}" method="post" class="d-inline"
                                                      onsubmit="return confirm('{{ __('msg_delete_confirm') }}');">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger">{{ __('btn_delete') }}</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No entries yet. Add the questions people ask most often.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $rows->links() }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@endsection
