<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
{{-- Stickers for label paper. Each carries the code twice: a QR code for the
     desk's camera and a Code 39 barcode for a handheld scanner, which often
     cannot read QR. Both are drawn here, offline, so printing never depends
     on an outside service. --}}
<style>
    @page { size: A4; margin: 10mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #182b49; background: #eef1f6; }
    .bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e3e7ee; }
    .bar p { margin: 0; font-size: 14px; color: #5f6f86; }
    .bar button { min-height: 40px; padding: 8px 18px; border: 0; border-radius: 8px; background: #1f5fa8; color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; }
    .sheet { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; width: 190mm; margin: 12px auto; padding: 0; }
    .tag { display: grid; grid-template-columns: 22mm 1fr; gap: 3mm; align-items: center; height: 38mm; padding: 3mm; background: #fff; border: 0.3mm dashed #9fb0c8; border-radius: 2mm; break-inside: avoid; overflow: hidden; }
    .qr svg { width: 22mm; height: 22mm; display: block; }
    .txt { min-width: 0; }
    .code { display: block; font-size: 15pt; font-weight: 700; letter-spacing: .03em; line-height: 1.1; }
    .name { display: block; margin-top: 1mm; font-size: 8pt; color: #334155; line-height: 1.2; max-height: 2.4em; overflow: hidden; }
    .c39 { margin-top: 1.5mm; }
    .c39 svg { width: 100%; height: 7mm; display: block; }
    .own { display: block; margin-top: 1mm; font-size: 6.5pt; color: #5f6f86; line-height: 1.2; }
    .empty { max-width: 520px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 10px; text-align: center; color: #5f6f86; }
    @media print { body { background: #fff; } .bar { display: none; } .sheet { margin: 0; } }
</style>
</head>
<body>
<div class="bar">
    <p>{{ trans_choice(':count sticker. Print on A4 label paper, then cut along the dashed lines.|:count stickers. Print on A4 label paper, then cut along the dashed lines.', $items->count(), ['count' => $items->count()]) }}</p>
    <button type="button" onclick="window.print()">{{ __('Print') }}</button>
</div>

@if($items->isEmpty())
    <div class="empty">{{ __('There is nothing to print: no equipment that can be lent matches.') }}</div>
@else
<div class="sheet">
    @foreach($items as $item)
        @php
            $qr = preg_replace('~^.*?(<svg)~s', '$1', DNS2D::getBarcodeSVG($item->code, 'QRCODE', 3, 3, '#182b49'));
            $qr = preg_replace('~<svg width="([\d.]+)" height="([\d.]+)"~', '<svg viewBox="0 0 $1 $2" preserveAspectRatio="xMidYMid meet"', $qr, 1);
            $bar = preg_match('~^[A-Z0-9\-. $/+%]+$~', $item->code) ? DNS1D::getBarcodeSVG($item->code, 'C39', 1, 30, '#182b49', false, true) : '';
            $bar = $bar ? preg_replace('~<svg width="([\d.]+)" height="([\d.]+)"~', '<svg viewBox="0 0 $1 $2" preserveAspectRatio="none"', $bar, 1) : '';
        @endphp
        <div class="tag">
            <div class="qr">{!! $qr !!}</div>
            <div class="txt">
                <span class="code">{{ $item->code }}</span>
                <span class="name">{{ $item->name }}</span>
                @if($bar)<div class="c39">{!! $bar !!}</div>@endif
                <span class="own">{{ $item->desk ? __('School property. Return to the :desk.', ['desk' => $item->desk->name]) : __('School property.') }}</span>
            </div>
        </div>
    @endforeach
</div>
@endif
</body>
</html>
