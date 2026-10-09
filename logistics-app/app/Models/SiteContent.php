<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Editable text shown on the Field Personnel dashboard. Written as plain text: a line
 * starting with "- " becomes a bullet point, and a blank line starts a new paragraph.
 */
class SiteContent extends Model
{
    /** The sections, in the order the dashboard shows them. */
    public const SECTIONS = ['guide', 'guidelines', 'company'];

    protected $fillable = ['key', 'title', 'body', 'updated_by'];

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** All sections in display order, keyed by their key. */
    public static function sections()
    {
        $order = array_flip(self::SECTIONS);

        return static::with('editor')->get()->sortBy(fn ($c) => $order[$c->key] ?? 99)->keyBy('key');
    }

    /** The body as safe HTML: escaped text, with "- " lines as bullet lists. */
    public function bodyHtml(): HtmlString
    {
        $html = '';
        foreach (preg_split('/\R{2,}/', trim($this->body)) as $block) {
            $lines = preg_split('/\R/', $block);
            $bullets = array_filter($lines, fn ($l) => str_starts_with(ltrim($l), '- '));

            if (count($bullets) === count($lines)) {
                $html .= '<ul>'.implode('', array_map(fn ($l) => '<li>'.e(substr(ltrim($l), 2)).'</li>', $lines)).'</ul>';
            } else {
                $html .= '<p>'.nl2br(e($block)).'</p>';
            }
        }

        return new HtmlString($html);
    }
}
