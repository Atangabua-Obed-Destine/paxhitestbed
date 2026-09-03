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


<!-- Download Js -->
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>

<script type="text/javascript">
$(document).ready(function() {
    "use strict";
    window.print();
});
</script>

</body>
</html>