<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width,maximum-scale=1.0">
    <title>{{ $title }}</title>

    @include('admin.marksheet.partials.styles')
</head>

<body>

@include('admin.marksheet.partials.page')


<script type="text/javascript">
(function () {
    "use strict";
    // Print once the page has fully loaded, not on DOM-ready. The letterhead's
    // crest is an image fetched after the markup, and a print dialog opened
    // before it arrived takes its preview from a letterhead with no logo.
    // window's load event waits for every image; the fonts are waited for
    // too, so the preview is set in the face the sheet was designed in.
    function printNow() {
        var fonts = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
        fonts.then(function () { window.print(); }, function () { window.print(); });
    }
    if (document.readyState === 'complete') {
        printNow();
    } else {
        window.addEventListener('load', printNow);
    }
})();
</script>

</body>
</html>