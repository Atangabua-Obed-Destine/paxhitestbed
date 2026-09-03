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
        $html = preg_replace('/(?<![a-z-])(?:min-|max-)?width\s*:\s*[^;"\']*;?/i', '', $html);
        $html = preg_replace('/(?<!font-)(?<!line-)(?<![a-z])(?:min-|max-)?height\s*:\s*[^;"\']*;?/i', '', $html);
        $html = preg_replace('/text-indent\s*:\s*[^;"\']*;?/i', '', $html);

        $html = preg_replace('/\s(?:width|height)\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s(?:width|height)\s*=\s*'[^']*'/i", '', $html);
        $html = preg_replace('/\s(?:width|height)\s*=\s*[0-9.]+%?/i', '', $html);

        return preg_replace_callback(
            '/<!--dochtml-img-(\d+)-->/',
            fn ($match) => $images[(int) $match[1]] ?? '',
            $html
        );
    }

    /** Both passes, in the order they need to run. */
    public static function prepare(?string $html, bool $forPdf = false): ?string
    {
        return static::resolveImages(static::normalize($html), $forPdf);
    }
}
