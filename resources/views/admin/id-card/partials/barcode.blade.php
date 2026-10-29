{{-- Machine-readable twin of the QR code on an ID card.

     The QR carries the verification URL; this carries the bare identifier
     printed on the card - a student's matricule, a staff member's staff id -
     which is what the admin looks either of them up by, so a handheld scanner
     at a gate or a library desk reads out something the system can resolve.

     Two quirks of milon/barcode shape the code below. It prefixes an XML
     declaration and a DOCTYPE unless the last argument is true, and it writes
     no viewBox, so the SVG cannot be resized by CSS at all until one is added.
     Adding the viewBox is what lets a four-character id and an eleven-
     character one fill the same panel instead of the longer one spilling past
     it - which also means the caller's width is what the bars are drawn at,
     and the quiet zone around them has to be sized against it. --}}
@php
    $value = strtoupper(trim((string) ($matricule ?? '')));

    // The panel this is drawn into, in px. The caller passes its own so the
    // intrinsic size on the SVG matches what CSS shows - html2canvas reads
    // the attribute, not the stylesheet, when it rasterises a card.
    $barWidth = $width ?? 258;
    $barHeight = $height ?? 38;

    // Code 39 carries only these characters. Anything else makes the library
    // return false, and a barcode that silently encodes something other than
    // the number printed beside it is worse than no barcode, so draw none.
    $drawBarcode = $value !== '' && preg_match('~^[A-Z0-9\-. $/+%]+$~', $value) === 1;

    if ($drawBarcode) {
        $svg = DNS1D::getBarcodeSVG($value, 'C39', 1, $barHeight, '#001F5B', false, true);

        $svg = preg_replace(
            '~<svg width="([\d.]+)" height="([\d.]+)"~',
            '<svg viewBox="0 0 $1 $2" preserveAspectRatio="none"'
                . ' width="' . $barWidth . '" height="' . $barHeight . '"',
            $svg,
            1
        );
    }
@endphp

@if($drawBarcode)
    <div class="barcode">
        {!! $svg !!}
        <span class="barcode-label">{{ $value }}</span>
    </div>
@endif
