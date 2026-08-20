<?php

namespace App\Services;

use App\Models\LetterheadSetting;
use App\Models\Setting;

/**
 * Renders the institution's letterhead for whichever destination is asking.
 *
 * The letterhead is flow content at the top of a document, deliberately not a
 * running page header — it belongs on the first page only. Making it repeat
 * would mean position:fixed, which is exactly what dompdf treats as a running
 * header on every page.
 */
class LetterheadService
{
    /**
     * The letterhead, ready to drop into a document body.
     *
     * @param  bool $forPdf  true when dompdf will render it, which changes how
     *                       image paths must be written
     */
    public function render(bool $forPdf = false): string
    {
        $settings = LetterheadSetting::current();

        if (!$settings->isActive()) {
            return '';
        }

        if ($settings->mode === LetterheadSetting::MODE_RESERVE) {
            return $this->reservedSpace($settings->reserve_height_mm);
        }

        $html = DocumentHtml::prepare($this->substituteTokens($settings->html), $forPdf);

        if (trim((string) $html) === '') {
            return '';
        }

        return '<div class="letterhead">' . $html . '</div>';
    }

    /**
     * Blank space for paper that already carries the letterhead.
     *
     * Printing a letterhead onto headed paper overlaps the printed one, so this
     * mode reserves the room and draws nothing.
     */
    protected function reservedSpace(int $millimetres): string
    {
        $millimetres = max(0, min($millimetres, 150));

        return '<div class="letterhead-reserved" style="height:' . $millimetres . 'mm"></div>';
    }

    /**
     * Square-bracket tokens, matching the convention the acceptance letter
     * already uses so there is one syntax to learn.
     *
     * @return array<string,string>
     */
    public function tokens(): array
    {
        $setting = Setting::first();

        return [
            '[institution_name]' => optional($setting)->title ?? config('app.name'),
            '[institution_code]' => optional($setting)->academy_code ?? '',
            '[address]' => optional($setting)->address ?? '',
            '[phone]' => optional($setting)->phone ?? '',
            '[email]' => optional($setting)->email ?? '',
            '[date]' => date('F j, Y'),
            '[year]' => date('Y'),
        ];
    }

    protected function substituteTokens(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $tokens = $this->tokens();

        return str_replace(array_keys($tokens), array_values($tokens), $html);
    }

    /**
     * Will render() actually produce a letterhead?
     *
     * Lets a document fall back to a masthead built from settings when the
     * letterhead is switched off, rather than printing nothing at all.
     */
    public function isConfigured(): bool
    {
        return $this->render(false) !== '';
    }

    /**
     * What this institution is called, for running text.
     *
     * Documents used to write `$setting->title ?? 'PAX HIGHER INSTITUTE'`, which
     * put one particular school's name in the code of a system meant to serve
     * any. The fallback is now the configured application name.
     *
     * The de-duplication is not cosmetic fussiness: the stored title currently
     * reads "PAX HIGHER INSTITUTE NDOP PAX HIGHER INSTITUTE NDOP" — the same
     * name entered twice — and printing that on an official document is worse
     * than trimming it. Correct the value under Settings and this becomes a
     * no-op.
     */
    public function institutionName(): string
    {
        $name = trim((string) optional(Setting::first())->title);

        if ($name === '') {
            return (string) config('app.name');
        }

        return $this->collapseRepeat($name);
    }

    /**
     * A short form for places too narrow for the full name.
     *
     * Falls back to the initials of the full name rather than a hardcoded code,
     * so an institution that never set one still gets something sensible.
     */
    public function institutionCode(): string
    {
        $code = trim((string) optional(Setting::first())->academy_code);
        if ($code !== '') {
            return $code;
        }

        $words = preg_split('~\s+~', $this->institutionName(), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $skip = ['of', 'the', 'and', 'for', 'de', 'du', 'des', 'la', 'le'];

        $initials = '';
        foreach ($words as $word) {
            if (!in_array(mb_strtolower($word), $skip, true)) {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            }
        }

        return $initials !== '' ? $initials : $this->institutionName();
    }

    /** "NAME NAME" entered twice becomes "NAME"; anything else is left alone. */
    protected function collapseRepeat(string $value): string
    {
        $normalised = preg_replace('~\s+~', ' ', $value);
        $length = mb_strlen($normalised);

        // Only an exact doubling, with a single space between the halves.
        if ($length % 2 === 1) {
            $half = intdiv($length, 2);
            if (mb_substr($normalised, 0, $half) === mb_substr($normalised, $half + 1)
                && mb_substr($normalised, $half, 1) === ' ') {
                return mb_substr($normalised, 0, $half);
            }
        }

        return $normalised;
    }

    /** Styles the partial injects once, so documents need not each define them. */
    public function styles(): string
    {
        return '<style>'
            . '.letterhead { width:100%; margin:0 0 10px; }'
            . '.letterhead img { max-width:100%; height:auto; }'
            . '.letterhead p { margin:0 0 4px; }'
            // Never fixed: that is what would repeat it on every page.
            . '.letterhead, .letterhead-reserved { position:static; }'
            . '</style>';
    }
}
