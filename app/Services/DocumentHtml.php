<?php

namespace App\Services;

/**
 * Prepares editor HTML for a document.
 *
 * Two jobs, both about the gap between what a rich-text editor stores and what
 * dompdf can actually render.
 *
 * The important one is images. The editor stores a relative path:
 *
 *     <img src="../../../../uploads/editor/editor_123.png">
 *
 * A browser resolves that against the page URL. dompdf has no page URL, so it
 * resolves to nothing — and drops the image without raising anything. Measured:
 * a PDF built from that markup came to 1,288 bytes with no image, while the same
 * markup with an absolute path came to 1.26MB with the image embedded. Silent
 * loss is the worst kind, because the document still looks finished.
 */
class DocumentHtml
{
    /** Put on a table whose author turned its borders off. */
    public const NO_BORDER_CLASS = 'dochtml-no-borders';

    /**
     * Point every local image at something the destination can actually open.
     *
     * dompdf reads the filesystem directly, which is faster and does not depend
     * on the server being able to reach its own public URL. A browser needs a
     * URL. Genuinely remote images are left alone.
     */
    public static function resolveImages(?string $html, bool $forPdf = false): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return preg_replace_callback(
            '/(<img\b[^>]*\bsrc\s*=\s*)(["\'])(.*?)\2/i',
            function ($match) use ($forPdf) {
                $resolved = static::resolveOne($match[3], $forPdf);

                return $match[1] . $match[2] . $resolved . $match[2];
            },
            $html
        );
    }

    /**
     * Turn one src into an absolute path or URL.
     *
     * @param  string $src  as the editor stored it
     */
    protected static function resolveOne(string $src, bool $forPdf): string
    {
        $src = trim($src);

        if ($src === '') {
            return $src;
        }

        // Already embedded — nothing to resolve, and nothing to gain.
        if (stripos($src, 'data:') === 0) {
            return $src;
        }

        $relative = null;

        if (preg_match('#^https?://#i', $src)) {
            // A full URL pointing at this installation still becomes a local
            // path for PDF: dompdf fetching over the network can fail on a
            // self-signed certificate or an unresolvable hostname.
            $host = parse_url($src, PHP_URL_HOST);
            $ownHost = parse_url(config('app.url'), PHP_URL_HOST) ?: request()?->getHost();

            if ($host && $ownHost && strcasecmp($host, $ownHost) !== 0) {
                return $src;   // genuinely someone else's image
            }

            $relative = ltrim((string) parse_url($src, PHP_URL_PATH), '/');
        } else {
            // Strip any number of leading ../ and any leading slash, which is
            // what the editor produces and what dompdf cannot follow.
            $relative = preg_replace('#^(?:\.\./)+#', '', $src);
            $relative = ltrim($relative, '/');
        }

        // The app may be served from a subdirectory; drop it if the editor
        // captured it in the path.
        $basePath = trim((string) parse_url(config('app.url'), PHP_URL_PATH), '/');
        if ($basePath !== '' && str_starts_with($relative, $basePath . '/')) {
            $relative = substr($relative, strlen($basePath) + 1);
        }

        if ($relative === '' || !is_file(public_path($relative))) {
            // Leave anything we cannot place untouched rather than producing a
            // path that is confidently wrong.
            return $src;
        }

        return $forPdf ? public_path($relative) : asset($relative);
    }

    /**
     * Strip the markup dompdf mishandles.
     *
     * Word pastes carry <o:p> tags, mso-* properties and fixed pixel widths that
     * overflow the page; its list indents are negative, which makes bullets
     * overlap their text.
     */
    public static function normalize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $html = preg_replace('/<\/?o:p[^>]*>/i', '', $html);
        $html = preg_replace('/mso-[^:;"\']+:[^;"\']*;?/i', '', $html);

        // Images are set aside before the dimension strips below and restored
        // afterwards. Everywhere else a fixed width is Word overflowing the
        // page, but on an image it is the size the author chose in the editor:
        // a letterhead logo sized to 56px was rendering at its natural 495px,
        // pushing the whole document down the page. Overflow is still prevented
        // — the letterhead's own stylesheet caps images at max-width:100%.
        $images = [];

        $html = preg_replace_callback('/<img\b[^>]*>/i', function ($match) use (&$images) {
            $images[] = $match[0];

            return '<!--dochtml-img-' . (count($images) - 1) . '-->';
        }, $html);

        // Keep line-height and font-size; drop fixed dimensions.
        //
        // A width in PER CENT is kept. Stripping those as well was what made a
        // letterhead look nothing like its own editor: the letterhead is a
        // three-column table carrying width:100%, and with that removed the
        // table shrank to fit its text and its margin:auto centred it, so the
        // institution's details ended up huddled in the middle of the page with
        // white space either side. A relative width cannot overflow the page,
        // which is the only thing this strip exists to prevent; a fixed one in
        // px, pt or inches still goes.
        $html = preg_replace('/(?<![a-z-])(?:min-|max-)?width\s*:\s*(?![^;"\']*%)[^;"\']*;?/i', '', $html);
        $html = preg_replace('/(?<!font-)(?<!line-)(?<![a-z])(?:min-|max-)?height\s*:\s*[^;"\']*;?/i', '', $html);
        $html = preg_replace('/text-indent\s*:\s*[^;"\']*;?/i', '', $html);

        // Same rule for the HTML attribute, with one guard: Word writes values
        // like width="109%", which would overflow, so anything above 100 goes
        // with the fixed ones.
        $keepPercentAttribute = function (array $match): string {
            $value = trim($match[2], "\"' ");

            if (strtolower($match[1]) === 'width'
                && substr($value, -1) === '%'
                && (float) rtrim($value, '%') <= 100) {
                return $match[0];
            }

            return '';
        };

        $html = preg_replace_callback('/\s(width|height)\s*=\s*("[^"]*")/i', $keepPercentAttribute, $html);
        $html = preg_replace_callback("/\s(width|height)\s*=\s*('[^']*')/i", $keepPercentAttribute, $html);
        $html = preg_replace_callback('/\s(width|height)\s*=\s*([0-9.]+%?)/i', $keepPercentAttribute, $html);

        $html = preg_replace_callback(
            '/<!--dochtml-img-(\d+)-->/',
            fn ($match) => $images[(int) $match[1]] ?? '',
            $html
        );

        return static::markBorderlessTables($html);
    }

    /**
     * Mark tables whose author turned borders off.
     *
     * A document stylesheet gives tables a default border, because a table
     * pasted in with no styling at all should still read as a table. That
     * default is written against th and td, and a rich-text editor records
     * "no borders" on the <table> element only - the cells it writes carry no
     * border declaration to override it with. So a table explicitly set to no
     * borders still came out with a line around every cell.
     *
     * The intent is read off the table tag and handed to the stylesheet as a
     * class, rather than guessed at in CSS with a substring match on the style
     * attribute, which would turn on whitespace.
     */
    public static function markBorderlessTables(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return preg_replace_callback('/<table\b[^>]*>/i', function ($match) {
            $tag = $match[0];

            if (stripos($tag, static::NO_BORDER_CLASS) !== false) {
                return $tag;
            }

            if (!static::declaresNoBorder($tag)) {
                return $tag;
            }

            // Into an existing class attribute, or a new one.
            $withClass = preg_replace(
                '/(\bclass\s*=\s*)(["\'])(.*?)\2/i',
                '$1$2$3 ' . static::NO_BORDER_CLASS . '$2',
                $tag,
                1,
                $count
            );

            if ($count > 0) {
                return $withClass;
            }

            return preg_replace('/^<table\b/i', '<table class="' . static::NO_BORDER_CLASS . '"', $tag, 1);
        }, $html);
    }

    /** Does this opening tag say the table has no border? */
    protected static function declaresNoBorder(string $tag): bool
    {
        // The HTML attribute, which is how older editors and Word say it.
        if (preg_match('/\bborder\s*=\s*["\']?0["\']?/i', $tag)) {
            return true;
        }

        if (!preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/is', $tag, $style)) {
            return false;
        }

        // border-style:none / hidden, or the shorthand set to none or zero.
        // A shorthand carrying a real width and style (1px solid #555) is an
        // author asking FOR a border, so it is left alone.
        return (bool) preg_match(
            '/(?:^|;)\s*border(?:-style)?\s*:\s*(?:none|hidden|0(?:px|pt|em|rem)?)\s*(?:;|$)/i',
            trim($style[2])
        );
    }

    /** Both passes, in the order they need to run. */
    public static function prepare(?string $html, bool $forPdf = false): ?string
    {
        $html = static::resolveImages(static::normalize($html), $forPdf);

        return $forPdf ? static::mapPdfFonts($html) : $html;
    }

    /**
     * Everything a PDF needs doing to already-substituted HTML.
     *
     * The sent letter substitutes its tokens between normalising and rendering,
     * so it cannot call prepare(); this is the same tail end of it.
     */
    public static function finishForPdf(?string $html): ?string
    {
        return static::mapPdfFonts(static::resolveImages($html, true));
    }

    /**
     * Lift editor markup out of a placeholder, keeping the formatting.
     *
     * A substitution matches a placeholder only when its letters sit together
     * in the source, and the editor does not guarantee that. Bold half of
     * [name] and TinyMCE stores "[na<strong>me]</strong>" — the token is still
     * one word on screen, so whoever wrote it has no way of knowing anything
     * is wrong, but str_replace cannot see it and the document goes out
     * reading "[name]" to its reader.
     *
     * A tag between the brackets is moved across them — an opening tag to just
     * before the "[", a closing tag to just after the "]" — so the placeholder
     * becomes one matchable word again AND the bold, italic or font the author
     * applied now wraps the whole token, and therefore the value that replaces
     * it. Nothing is added or removed, only moved from one side of a bracket
     * to the other, and both brackets sit inside the same text node, so this
     * cannot change how any tag nests.
     *
     * A bracketed run that is not a known placeholder is left exactly as it
     * is: "[see <em>note</em>]" is prose, not a token.
     *
     * @param  array $tokens  the placeholder keys, square brackets included
     */
    public static function repairTokens(?string $html, array $tokens): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $known = [];

        foreach ($tokens as $token) {
            $known[mb_strtolower(trim((string) $token, '[]'))] = $token;
        }

        $repaired = preg_replace_callback(
            '/\[[a-z0-9_]*(?:<[^>]+>[a-z0-9_]*)+\]/i',
            function ($match) use ($known) {
                $name = mb_strtolower(trim(strip_tags($match[0]), '[] '));

                if (!isset($known[$name])) {
                    return $match[0];
                }

                preg_match_all('/<[^>]+>/', $match[0], $tags);

                $before = '';
                $after = '';

                foreach ($tags[0] as $tag) {
                    if (str_starts_with($tag, '</')) {
                        $after .= $tag;
                    } else {
                        $before .= $tag;
                    }
                }

                return $before . $known[$name] . $after;
            },
            $html
        );

        // preg_replace_callback returns null on failure (a backtrack limit on
        // a very large document, say). A document with its placeholders
        // unmatched is a great deal better than no document at all.
        return $repaired ?? $html;
    }

    /**
     * The families dompdf has a font file for, after its own aliasing.
     *
     * dompdf ships thirteen and no more. Measured rather than assumed, because
     * the assumption is wrong in a way nothing reports: dompdf does NOT alias
     * the Windows names. "font-family: arial" on its own renders in
     * Times-Roman, and so does "courier new" - a monospace choice coming out
     * proportional. They only work when a generic follows them in the list,
     * which is why they are aliases below and not members here.
     */
    protected const PDF_FONTS = [
        'helvetica', 'times', 'times-roman', 'courier', 'symbol', 'zapfdingbats',
        'dejavu sans', 'dejavu serif', 'dejavu sans mono',
        'serif', 'sans-serif', 'monospace', 'fixed',
    ];

    /**
     * What to print instead, for a family dompdf has no file for.
     *
     * Keyed on what editors actually offer. The mapping keeps the kind of face
     * the author chose: a serif stays a serif, a sans stays a sans. Without it
     * an unknown name falls through to dompdf's default, which is serif — so
     * "Verdana" printed in Times, the opposite of what was asked for.
     */
    protected const PDF_FONT_ALIASES = [
        // The Windows names dompdf does not resolve on its own.
        'arial' => 'helvetica',
        'times new roman' => 'times',
        'courier new' => 'courier',
        'arial narrow' => 'helvetica',
        'arial black' => 'helvetica',
        // serif
        'georgia' => 'times',
        'palatino' => 'times',
        'palatino linotype' => 'times',
        'book antiqua' => 'times',
        'garamond' => 'times',
        'cambria' => 'times',
        'constantia' => 'times',
        'baskerville' => 'times',
        'georgia pro' => 'times',
        // sans
        'verdana' => 'helvetica',
        'tahoma' => 'helvetica',
        'geneva' => 'helvetica',
        'segoe ui' => 'helvetica',
        'calibri' => 'helvetica',
        'candara' => 'helvetica',
        'corbel' => 'helvetica',
        'trebuchet ms' => 'helvetica',
        'helvetica neue' => 'helvetica',
        'roboto' => 'helvetica',
        'open sans' => 'helvetica',
        'lato' => 'helvetica',
        'lucida sans unicode' => 'helvetica',
        'lucida grande' => 'helvetica',
        // monospace
        'consolas' => 'courier',
        'monaco' => 'courier',
        'menlo' => 'courier',
        'lucida console' => 'courier',
    ];

    /**
     * Rewrite every font-family so it names something dompdf can print.
     *
     * Only the declaration is touched, never the text. A family dompdf serves
     * is left exactly as written; one it does not is replaced by the nearest
     * face of the same kind; a list that ends up empty loses its declaration
     * altogether, so the element inherits rather than landing on the default.
     */
    public static function mapPdfFonts(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return preg_replace_callback(
            // A double-quoted family name is part of the value, not the end of
            // it: style='font-family: "Courier New", monospace'. Excluding the
            // quote outright skipped those declarations entirely, and a skipped
            // "courier new" prints proportional.
            '/font-family\s*:\s*((?:[^;"}]|"[^"]*")+)/i',
            function ($match) {
                $families = [];

                foreach (explode(',', $match[1]) as $family) {
                    $name = mb_strtolower(trim(trim($family), "'\" \t\r\n"));

                    if ($name === '') {
                        continue;
                    }

                    if (in_array($name, static::PDF_FONTS, true)) {
                        $families[] = $name;
                        continue;
                    }

                    if (isset(static::PDF_FONT_ALIASES[$name])) {
                        $families[] = static::PDF_FONT_ALIASES[$name];
                    }
                }

                $families = array_values(array_unique($families));

                // Nothing survived: drop the declaration so the element
                // inherits the family its container was given.
                if ($families === []) {
                    return '';
                }

                return 'font-family: ' . implode(', ', $families);
            },
            $html
        );
    }
}
