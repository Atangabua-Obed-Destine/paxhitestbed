{{--
    A payment receipt, shown on the page rather than only linked.

    Before this, a PDF receipt showed an icon and the words "PDF receipt
    uploaded" — to read it you had to open it in another tab, which is a poor
    way to verify a payment when the figures you are checking it against are
    on the page behind you. Images were shown; PDFs were not. Now both are.

    It also checks the file is actually there. A row can name a file that the
    server does not have — the uploads directory is deliberately outside
    version control, so it does not travel with a deploy, and a restored
    database can easily name files a new server never received. The old
    markup linked to it anyway, and the student or bursar got the
    application's "Error 404, Maybe this page not exist" with nothing to
    explain it. Saying the file is missing, and where it was expected, is the
    difference between a support ticket and a fixable fact.

    Expects: $file (the stored filename) and $dir (the uploads subdirectory).
    $compact = true for the narrower student column.
--}}
@php
    $receiptFile = trim((string) ($file ?? ''));
    $receiptDir = trim((string) ($dir ?? ''), '/');
    $receiptCompact = $compact ?? false;

    $receiptExtension = strtolower(pathinfo($receiptFile, PATHINFO_EXTENSION));

    // Looks where the receipt belongs, then where receipts were once wrongly
    // put. Applicant receipts went to uploads/student for a while, and their
    // rows record only a bare filename, so without this every one of them
    // reads as missing.
    $receiptFound = \App\Support\ReceiptFile::locate($receiptFile, $receiptDir);
    $receiptRelative = $receiptFound['relative']
        ?? \App\Support\ReceiptFile::expectedPath($receiptFile, $receiptDir);
    $receiptUrl = $receiptFound ? asset($receiptFound['relative']) : null;
    $receiptOnDisk = (bool) $receiptFound;
    $receiptLegacy = (bool) ($receiptFound['legacy'] ?? false);

    $receiptIsImage = in_array($receiptExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    $receiptIsPdf = $receiptExtension === 'pdf';
    $receiptHeight = $receiptCompact ? 380 : 520;
@endphp

@if($receiptFile === '')
    <div class="alert alert-secondary mb-0">
        <i class="fas fa-file"></i> {{ __('No receipt file was uploaded.') }}
    </div>

@elseif(!$receiptOnDisk)
    {{-- Named in the record, absent from the server. --}}
    <div class="alert alert-warning mb-0 text-start">
        <strong><i class="fas fa-exclamation-triangle"></i>
            {{ __('The receipt file is missing from the server.') }}</strong>
        <div class="mt-2" style="font-size:13px">
            {{ __('The record names :file, but there is no such file in the uploads folder, so it cannot be shown or downloaded.', ['file' => $receiptFile]) }}
        </div>
        <div class="mt-2" style="font-size:12px;opacity:.85">
            {{ __('Expected at') }}: <code>{{ $receiptRelative }}</code>
        </div>
    </div>

@else
    <div class="receipt-preview">
        @if($receiptIsImage)
            <a href="{{ $receiptUrl }}" target="_blank" rel="noopener"
               title="{{ __('Open full size') }}">
                <img src="{{ $receiptUrl }}" alt="{{ __('receipt_file') }}"
                     class="img-fluid"
                     style="max-height: {{ $receiptHeight }}px; border: 1px solid #dfe3e8; border-radius: 6px; padding: 6px; background: #fff;">
            </a>

        @elseif($receiptIsPdf)
            {{-- Shown on the page. An <object> rather than an <iframe> so the
                 fallback inside it is used by a browser that will not render
                 a PDF inline, instead of leaving an empty grey box. --}}
            <object data="{{ $receiptUrl }}#toolbar=1&navpanes=0&view=FitH"
                    type="application/pdf"
                    style="width: 100%; height: {{ $receiptHeight }}px; border: 1px solid #dfe3e8; border-radius: 6px; background: #fff;">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-file-pdf fa-2x mb-2 text-danger d-block"></i>
                    {{ __('This browser will not show the PDF here.') }}
                    <a href="{{ $receiptUrl }}" target="_blank" rel="noopener">{{ __('Open it in a new tab') }}</a>.
                </div>
            </object>

        @else
            <div class="alert alert-secondary mb-0">
                <i class="fas fa-file fa-2x mb-2 d-block"></i>
                {{ __('file_uploaded') }} ({{ strtoupper($receiptExtension) ?: __('unknown type') }})
            </div>
        @endif
    </div>

    @if($receiptLegacy)
    <div class="alert alert-info py-2 mt-2 mb-0" style="font-size:12px">
        <i class="fas fa-info-circle"></i>
        {{ __('This receipt is stored in an older folder (:dir). It opens normally; newer receipts are filed with the others.', ['dir' => 'uploads/' . ($receiptFound['dir'] ?? '')]) }}
    </div>
    @endif

    <div class="text-center mt-2">
        <a href="{{ $receiptUrl }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener">
            <i class="fas fa-external-link-alt"></i> {{ __('Open in a new tab') }}
        </a>
        <a href="{{ $receiptUrl }}" class="btn btn-sm btn-outline-secondary" download="{{ $receiptFile }}">
            <i class="fas fa-download"></i> {{ __('btn_download_receipt') }}
        </a>
    </div>
@endif
