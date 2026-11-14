{{-- The validity row under Date Issued, for the card class $card.

     Columns are the artwork's: labels start at 45px, the colons sit at
     129px, values start at 150px like every other value on the card. The row
     is one artwork row below Date Issued and clears the barcode panel.

     The label imitates the artwork's printed labels (Anton); the
     value is set exactly like the values above it, fallback included, so it
     matches them whether or not the page managed to load Anton. Left-aligned
     explicitly: the download page centres its text, which would otherwise
     push the label and colon off their columns. --}}
{{ $card }} .validity-row {
    position: absolute;
    top: 292px;
    left: 45px;
    display: flex;
    align-items: baseline;
    text-align: left;
    white-space: nowrap;
    color: #000;
}
{{ $card }} .validity-row .validity-label,
{{ $card }} .validity-row .validity-colon {
    font-family: 'Anton', 'Arial Narrow', Arial, sans-serif;
    /* Bold for the fallback, which needs it to look like the artwork; Anton
       has one weight and must not be thickened artificially. */
    font-weight: 700;
    font-synthesis: none;
    font-size: 17px;
}
{{ $card }} .validity-row .validity-label {
    width: 84px;
    letter-spacing: 0.2px;
}
{{ $card }} .validity-row .validity-colon {
    width: 21px;
}
{{ $card }} .validity-row .validity-value {
    font-family: "Anton";
    font-weight: 700;
    font-size: 20px;
    letter-spacing: 0.3px;
}
