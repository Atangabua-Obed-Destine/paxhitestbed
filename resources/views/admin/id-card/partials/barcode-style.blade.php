{{-- The barcode "beside the QR code", for the card class $card.

     No border, and moved right so the gap to the QR code matches the gap
     between the QR code and the photo. The border is made transparent rather
     than removed so the panel keeps its exact size, and the bars keep the
     white quiet zone a scanner needs against the watermark. --}}
{{ $card }} .barcode.barcode-beside {
    left: auto;
    right: 343px;
    border-color: transparent;
}
