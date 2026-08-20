{{--
    A country dropdown, defaulting to Cameroon.

    Used everywhere a country is collected so the stored values stay consistent
    across applications, students and academic history.

    Expects:
      $name      input name, e.g. "country" or "academic_history[0][country]"
      $value     currently stored value (may be null)
      $id        optional element id
      $required  optional bool
      $class     optional extra classes
--}}
@php
    $countryValue = filled($value ?? null) ? $value : \App\Support\Countries::DEFAULT_COUNTRY;
    $countryGroups = \App\Support\Countries::grouped();
    // A value saved before this list existed (or typed by an admin) must not be
    // silently replaced, so it is offered as its own option.
    $countryUnknown = filled($countryValue) && !\App\Support\Countries::has($countryValue);
    // The priority group repeats countries from the full list, so the chosen
    // one would otherwise carry `selected` twice in a single-select.
    $countrySelected = false;
@endphp
<select name="{{ $name }}"
        @if(!empty($id)) id="{{ $id }}" @endif
        class="form-control {{ $class ?? '' }}"
        @if(!empty($required)) required @endif>
    @if($countryUnknown)
        <option value="{{ $countryValue }}" selected>{{ $countryValue }}</option>
    @endif
    @foreach($countryGroups as $groupLabel => $groupCountries)
        <optgroup label="{{ $groupLabel }}">
            @foreach($groupCountries as $country)
                @php $isChosen = !$countryUnknown && !$countrySelected && $countryValue === $country; @endphp
                <option value="{{ $country }}" @selected($isChosen)>{{ $country }}</option>
                @php $countrySelected = $countrySelected || $isChosen; @endphp
            @endforeach
        </optgroup>
    @endforeach
</select>
