{{--
  The admission approvals.

  This is the screen that replaces the printed "For Official Use Only" block:
  the same four signatures, given on the system by whoever holds each step's
  permission, in order, with a reason recorded whenever an application is sent
  back or refused.

  Expects: $row (Application), $approval (ApplicationApprovalService::state()).
--}}
@php
  $user = auth()->guard('web')->user();
  $stepMap = \App\Models\Application::approvalStepMap();
@endphp

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">{{ __('Admission approvals') }}</h5>
    @if($approval['complete'])
      <span class="badge bg-success">{{ __('Complete') }}</span>
    @elseif($approval['rejected'])
      <span class="badge bg-danger">{{ __('Refused') }}</span>
    @else
      <span class="badge bg-warning text-dark">{{ __('In progress') }}</span>
    @endif
  </div>
  <div class="card-block">

    <ol class="list-unstyled mb-0 approval-steps">
      @foreach($approval['steps'] as $step)
        @php
          $mayDecide = $user && $user->can($step['permission']);
          $signed = $step['approval'];
        @endphp
        <li class="approval-step approval-step--{{ $step['state'] }} mb-3 pb-3 border-bottom">
          <div class="d-flex align-items-start">
            <span class="approval-step__mark me-2">
              @if($step['approved'])
                <i class="fas fa-check-circle text-success"></i>
              @elseif($step['is_current'])
                <i class="fas fa-dot-circle text-primary"></i>
              @else
                <i class="far fa-circle text-muted"></i>
              @endif
            </span>
            <div class="flex-grow-1">
              <div class="fw-bold">{{ $step['sequence'] }}. {{ $step['title'] }}</div>

              @if($signed)
                {{-- Who signed, as it was true at the time of signing. --}}
                <div class="small text-muted">
                  {{ $signed->signatory() }}@if($signed->signed_position), {{ $signed->signed_position }}@endif
                  · {{ optional($signed->decided_at)->format('d M Y H:i') }}
                </div>
                @if($signed->note)
                  <div class="small mt-1">{{ $signed->note }}</div>
                @endif
              @elseif($step['is_current'])
                @if($mayDecide)
                  <div class="small text-muted">{{ __('Waiting for your decision.') }}</div>
                @else
                  {{-- Say who it is waiting for, rather than leaving a dead panel. --}}
                  <div class="small text-muted">
                    {{ __('Waiting for whoever holds this step. You do not hold it.') }}
                  </div>
                @endif
              @else
                <div class="small text-muted">{{ __('Not yet reached.') }}</div>
              @endif

              @php $isFinalStep = $step['key'] === \App\Models\Application::finalApprovalStep(); @endphp

              @if($step['is_current'] && $mayDecide && !$approval['rejected'])
                <div class="mt-2">
                  @if($isFinalStep)
                    {{-- The last approval and the record are one action, so this
                         opens the conversion form rather than a note box. The
                         approval is recorded with the record, in one go. --}}
                    <button class="btn btn-sm btn-success" type="button"
                            data-bs-toggle="modal" data-bs-target="#convertApplicationModal">
                      <i class="fas fa-user-check"></i> {{ __('Approve & create student record') }}
                    </button>
                  @else
                    <button class="btn btn-sm btn-success" type="button"
                            data-bs-toggle="collapse" data-bs-target="#approve-{{ $step['key'] }}">
                      <i class="fas fa-check"></i> {{ __('Approve') }}
                    </button>
                  @endif
                  @if($step['sequence'] > 1)
                    <button class="btn btn-sm btn-outline-warning" type="button"
                            data-bs-toggle="collapse" data-bs-target="#return-{{ $step['key'] }}">
                      <i class="fas fa-undo"></i> {{ __('Return') }}
                    </button>
                  @endif
                  @if($step['can_reject'])
                    <button class="btn btn-sm btn-outline-danger" type="button"
                            data-bs-toggle="collapse" data-bs-target="#reject-{{ $step['key'] }}">
                      <i class="fas fa-times"></i> {{ __('Refuse') }}
                    </button>
                  @endif
                </div>

                {{-- Approve. Not for the final step: that one is given by
                     creating the student record, so there is no second path
                     that could approve without creating. --}}
                @if(!$isFinalStep)
                  <div class="collapse mt-2" id="approve-{{ $step['key'] }}">
                    <form action="{{ route('admin.application.approval.approve', $row->id) }}" method="post">
                      @csrf
                      <input type="hidden" name="step" value="{{ $step['key'] }}">
                      <textarea name="note" class="form-control form-control-sm mb-2" rows="2"
                                maxlength="2000" placeholder="{{ __('Note (optional)') }}"></textarea>
                      <button type="submit" class="btn btn-sm btn-success">
                        {{ __('Give :step', ['step' => $step['title']]) }}
                      </button>
                    </form>
                  </div>
                @endif

                {{-- Return: a missing document is put right, not refused --}}
                @if($step['sequence'] > 1)
                  <div class="collapse mt-2" id="return-{{ $step['key'] }}">
                    <form action="{{ route('admin.application.approval.return', $row->id) }}" method="post">
                      @csrf
                      <input type="hidden" name="step" value="{{ $step['key'] }}">
                      <label class="small mb-1">{{ __('Send back to') }}</label>
                      <select name="returned_to_step" class="form-control form-control-sm mb-2" required>
                        @foreach($approval['steps'] as $target)
                          @if($target['sequence'] < $step['sequence'])
                            <option value="{{ $target['key'] }}">{{ $target['title'] }}</option>
                          @endif
                        @endforeach
                      </select>
                      <textarea name="note" class="form-control form-control-sm mb-2" rows="2" maxlength="2000"
                                required placeholder="{{ __('What has to be put right? The applicant is told.') }}"></textarea>
                      <button type="submit" class="btn btn-sm btn-warning">{{ __('Return') }}</button>
                    </form>
                  </div>
                @endif

                {{-- Refuse --}}
                @if($step['can_reject'])
                  <div class="collapse mt-2" id="reject-{{ $step['key'] }}">
                    <form action="{{ route('admin.application.approval.reject', $row->id) }}" method="post">
                      @csrf
                      <input type="hidden" name="step" value="{{ $step['key'] }}">
                      <textarea name="note" class="form-control form-control-sm mb-2" rows="2" maxlength="2000"
                                required placeholder="{{ __('Why is the application refused? The applicant is told.') }}"></textarea>
                      <button type="submit" class="btn btn-sm btn-danger">{{ __('Refuse the application') }}</button>
                    </form>
                  </div>
                @endif
              @endif
            </div>
          </div>
        </li>
      @endforeach
    </ol>

    @if($approval['rejected'])
      <div class="alert alert-danger small mb-0 mt-3">
        {{ __('This application was refused. Returning it to an earlier step reopens it.') }}
      </div>
    @endif

    {{-- The history, including what was crossed out. A returned step keeps the
         approval it had, the way the paper file kept its corrections. --}}
    @if($approval['history']->count() > count($approval['steps']))
      <a class="small" data-bs-toggle="collapse" href="#approval-history" role="button">
        {{ __('Full approval history') }} ({{ $approval['history']->count() }})
      </a>
      <div class="collapse mt-2" id="approval-history">
        <ul class="list-unstyled small mb-0">
          @foreach($approval['history']->sortByDesc('id') as $entry)
            <li class="mb-2">
              <span class="badge bg-{{ $entry->decision === 'approved' ? 'success' : ($entry->decision === 'rejected' ? 'danger' : 'warning text-dark') }}">
                {{ __('application_approval.decision.' . $entry->decision) }}
              </span>
              {{ $entry->stepTitle() }} — {{ $entry->signatory() }},
              {{ optional($entry->decided_at)->format('d M Y H:i') }}
              @if($entry->note)<div class="text-muted">{{ $entry->note }}</div>@endif
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  </div>
</div>
