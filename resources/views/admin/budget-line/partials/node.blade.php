{{--
    One row of the sheet, plus the lines filed under it.

    Nestable reads the structure from the markup: data-id on each .dd-item, and
    a nested <ol class="dd-list"> for children. The drag grip is .bl-grip, which
    is registered as the handle so a click on a button in the row never starts a
    drag.

    Expects: $line, $children (Collection), $accountsByLine, $usage
--}}
@php
    $children = $children ?? collect();

    // Built here rather than inline: a multi-line array inside @json() in an
    // attribute is more than Blade's parser can read.
    $payload = $line->only([
        'id', 'code', 'name', 'name_fr', 'description', 'section',
        'parent_id', 'faculty_id', 'sort_order', 'is_header', 'is_local', 'status',
    ]);

    $mapped = !$line->is_header && !empty($accountsByLine[$line->id]);
    $inUse = $usage[$line->id] ?? null;
@endphp

<li class="dd-item {{ $line->is_header ? 'bl-is-header' : '' }}" data-id="{{ $line->id }}">
    <div class="bl-row {{ $line->status ? '' : 'bl-retired' }}">

        @can('budget-line-edit')
            <button type="button" class="bl-grip" title="{{ __('Drag to move this line') }}">
                <i class="fas fa-grip-vertical"></i>
            </button>
        @else
            <span class="bl-grip" style="cursor:default;"><i class="fas fa-grip-vertical"></i></span>
        @endcan

        <span class="bl-code">{{ $line->code }}</span>

        <span class="bl-main">
            <span class="bl-name">{{ $line->name }}</span>

            @if($line->is_header)
                <span class="bl-account">
                    {{ trans_choice(':count line filed here|:count lines filed here', $children->count(), ['count' => $children->count()]) }}
                    · {{ __('subtotalled, holds no money of its own') }}
                </span>
            @elseif($mapped)
                <span class="bl-account">{{ implode(' · ', $accountsByLine[$line->id]) }}</span>
            @else
                {{-- Said plainly: an unmapped line is not merely unconfigured,
                     it will print a zero on the sheet whatever is spent. --}}
                <span class="bl-unmapped">
                    <i class="fas fa-exclamation-triangle me-1"></i>{{ __('no category maps here yet — this line will show zero on the sheet') }}
                </span>
            @endif
        </span>

        <span class="bl-meta">
            @if(optional($line->faculty)->title)
                <span class="bl-flag bl-flag-standard" title="{{ __('Applies to this faculty only.') }}">{{ $line->faculty->title }}</span>
            @endif

            @if($line->is_local)
                <span class="bl-flag bl-flag-local" title="{{ __('Added for this institution — not on the standard diocesan form.') }}">{{ __('local') }}</span>
            @else
                <span class="bl-flag bl-flag-standard" title="{{ __('Comes from the standard diocesan form. Renaming it breaks comparability with the form other institutions file.') }}">{{ __('diocesan') }}</span>
            @endif

            @if($line->status)
                <span class="badge bg-success">{{ __('On the sheet') }}</span>
            @else
                <span class="badge bg-secondary">{{ __('Retired') }}</span>
            @endif
        </span>

        <span class="bl-actions">
            @can('budget-line-edit')
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#lineModal"
                        title="{{ __('Edit') }}"
                        onclick='budgetLineForm(@json($payload))'>
                    <i class="fas fa-pen"></i>
                </button>

                <form action="{{ route('admin.budget-line.toggle', $line->id) }}" method="post" class="d-inline"
                      onsubmit="return confirm('{{ $line->status ? __('Take this line off the sheet?') : __('Put this line back on the sheet?') }}')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                            title="{{ $line->status ? __('Retire') : __('Restore') }}">
                        <i class="fas {{ $line->status ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                    </button>
                </form>
            @endcan

            @can('budget-line-delete')
                @if(empty($inUse))
                    <form action="{{ route('admin.budget-line.delete', $line->id) }}" method="post" class="d-inline"
                          onsubmit="return confirm('{{ __('Delete this line permanently?') }}')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                @else
                    {{-- Explains itself rather than showing a button that always fails. --}}
                    <button type="button" class="btn btn-sm btn-outline-danger" disabled
                            title="{{ __('In use: :reasons. Retire it instead.', ['reasons' => implode(', ', $inUse)]) }}">
                        <i class="fas fa-trash"></i>
                    </button>
                @endif
            @endcan
        </span>
    </div>

    @if($children->isNotEmpty())
        <ol class="dd-list">
            @foreach($children as $child)
                @include('admin.budget-line.partials.node', [
                    'line' => $child,
                    'children' => collect(),
                    'accountsByLine' => $accountsByLine,
                    'usage' => $usage,
                ])
            @endforeach
        </ol>
    @endif
</li>
