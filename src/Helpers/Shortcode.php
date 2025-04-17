<?php

namespace Dominservice\LaravelCms\Helpers;

class Shortcode
{
    protected array $types = ['section', 'block', 'faq'];
    protected ?string $type = null;
    protected array $codes = [];
    protected string $content = '';

    public function __construct(string $content, ?string $type = null)
    {
        $this->type = $type;
        $this->content = $content;
        $shortcodeNames = $this->type ?: $this->getShortcodeNames();
        $regex = "@\\[!!(\\[?)($shortcodeNames)(?![\\w-])([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(!!\\]?)@";
        preg_match_all($regex, $this->content, $codeMatches);

        if (!empty($codeMatches[0])) {
            foreach ($codeMatches[0] as $lp => $match) {
                $this->codes[$codeMatches[0][$lp]] = (object)[
                    'type' => $codeMatches[2][$lp],
                    'values' => (object)$this->parseAttributes($codeMatches[3][$lp]),
                ];
            }
        }
    }

    protected function parseAttributes(string $text): array
    {
        $text = htmlspecialchars_decode($text, ENT_QUOTES);
        $text = str_replace('|', ' ', $text);
        $attributes = [];
        $pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';

        if (preg_match_all($pattern, preg_replace('/[\x{00a0}\x{200b}]+/u', " ", $text), $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $attributes[strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $attributes[strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $attributes[strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) && strlen($m[7])) {
                    $attributes[] = stripcslashes($m[7]);
                } elseif (isset($m[8])) {
                    $attributes[] = stripcslashes($m[8]);
                }
            }
        } else {
            $attributes = ltrim($text);
        }

        return is_array($attributes) ? $attributes : [$attributes];
    }

    protected function getShortcodeNames(): string
    {
        return implode('|', array_map('preg_quote', $this->types));
    }

    public function getCodesParams(): array
    {
        return $this->codes;
    }
}