<?php

namespace App\Services;

use Illuminate\Support\Facades\Lang;

/**
 * Puts a finished HTML page into the visitor's language using lang/{locale}.json (English text -> translation). Only a text that is
 * exactly an entry is replaced (a whole text between two tags, or a whole placeholder), so product names, prices, addresses and other data
 * are never touched, and nothing inside <script>, <style> or <textarea> is changed. English pages are returned untouched.
 */
class HindiPage
{
    /** @return array<string, string> the dictionary of the current language ([] for English) */
    public function dictionary(): array
    {
        $locale = app()->getLocale();
        if ($locale === 'en' || $locale === config('app.fallback_locale')) {
            return [];
        }

        return array_filter(Lang::getLoader()->load($locale, '*', '*'), fn ($v) => is_string($v) && $v !== '');
    }

    public function translate(string $html): string
    {
        $dict = $this->dictionary();
        if ($dict === []) {
            return $html;
        }

        // keep script / style / textarea blocks exactly as they are
        $parts = preg_split('#(<(script|style|textarea)\b[^>]*>.*?</\2>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($parts as $i => $part) {
            // preg_split with a nested group yields [text, block, tagname, text, ...]: every 3rd piece starting at 1 is a block, the one after it its tag name
            $kind = $i % 3;
            if ($kind === 1) {
                $out .= $part;
            } elseif ($kind === 2) {
                continue;
            } else {
                $out .= $this->translateText($part, $dict);
            }
        }

        return $out;
    }

    private function translateText(string $html, array $dict): string
    {
        $html = preg_replace_callback('/>([^<>]+)</', function ($m) use ($dict) {
            $text = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            if ($text === '' || ! isset($dict[$text])) {
                return $m[0];
            }
            $lead = substr($m[1], 0, strlen($m[1]) - strlen(ltrim($m[1])));
            $trail = substr($m[1], strlen(rtrim($m[1])));

            return '>'.$lead.e($dict[$text]).$trail.'<';
        }, $html);

        return preg_replace_callback('/\bplaceholder="([^"]*)"/', function ($m) use ($dict) {
            $text = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);

            return isset($dict[$text]) ? 'placeholder="'.e($dict[$text]).'"' : $m[0];
        }, $html);
    }
}
