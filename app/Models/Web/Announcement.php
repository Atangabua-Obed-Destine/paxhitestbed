<?php

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Language;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id', 'message', 'start_date', 'end_date', 'status'
    ];

    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    /**
     * Switched on and inside its dates today. Compared by day, so a notice
     * ending on the 25th still shows all of the 25th.
     */
    public function scopeLive($query)
    {
        $today = now()->toDateString();

        return $query->where('status', 1)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today));
    }

    /**
     * The message as one line of plain text, for the scrolling notice bar.
     *
     * The message is written in the rich-text editor, so it can carry headings,
     * paragraphs and pasted styles. The bar is a single line with its own look:
     * block ends become spaces so words do not run together, tags and styles
     * go, entities such as &nbsp; are decoded, and whitespace is collapsed.
     */
    public function getTickerTextAttribute(): string
    {
        $html = preg_replace('#<(br|hr)\b[^>]*>|</(p|div|h[1-6]|li|tr|td|th|blockquote)>#i', ' ', (string) $this->message);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}
