{{--
    Builds <option> markup for rows added in the browser, from the same list the
    server-rendered selects use, so a repeater row cannot offer a different set
    of countries to the one beside it.

    Include inside an existing <script> block.
--}}
const countryOptionsHtml = (selected) => {
    const groups = @json(\App\Support\Countries::grouped());
    const chosen = selected || @json(\App\Support\Countries::DEFAULT_COUNTRY);
    let html = '';
    Object.keys(groups).forEach(function (label) {
        html += '<optgroup label="' + label + '">';
        groups[label].forEach(function (name) {
            html += '<option value="' + name + '"' + (name === chosen ? ' selected' : '') + '>' + name + '</option>';
        });
        html += '</optgroup>';
    });
    return html;
};
