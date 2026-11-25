{{-- Chosen semesters left out because they have nothing to show (several chosen). --}}
@if(isset($skipped_semesters) && $skipped_semesters->count() > 0)
            <div class="col-sm-12">
                <div class="alert alert-light border semester-blocks-skipped">
                    <i class="fas fa-info-circle"></i> {{ __('No results for') }}: {{ $skipped_semesters->pluck('title')->implode(', ') }}
                </div>
            </div>
@endif
