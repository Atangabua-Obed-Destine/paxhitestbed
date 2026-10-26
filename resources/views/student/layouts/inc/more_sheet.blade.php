{{--
  Everything the bar does not reach directly.

  A sheet rather than a page, because opening it should not cost a navigation
  and closing it should put the student back exactly where they were.

  Built on Bootstrap's modal rather than something hand-rolled, so it keeps
  focus trapping, Escape, and the backdrop for free — and so it behaves the
  same as every other dialog in the portal. .app-sheet excludes it from the
  generic dialog-to-sheet styling, which it does not need: it is already one.

  Expects $appGroups from the bar.
--}}

<div class="modal fade app-sheet" id="appMore" tabindex="-1"
     aria-labelledby="appMoreLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="app-grab" aria-hidden="true"></div>

            <div class="app-sheet-head">
                <h6 id="appMoreLabel">{{ __('nav_everything') }}</h6>
                <button type="button" class="app-sheet-close" data-bs-dismiss="modal"
                        aria-label="{{ __('nav_close') }}">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <div class="modal-body pt-0 pb-3">
                @foreach($appGroups as $groupLabel => $items)
                <div class="app-group">
                    <div class="app-group-head">{{ $groupLabel }}</div>

                    @foreach($items as $item)
                    <a href="{{ $item['url'] }}" class="app-link {{ $item['active'] ? 'is-on' : '' }}"
                       @if($item['active']) aria-current="page" @endif>
                        <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                        <i class="fas fa-chevron-right chev" aria-hidden="true"></i>
                    </a>
                    @endforeach
                </div>
                @endforeach
            </div>

        </div>
    </div>
</div>
