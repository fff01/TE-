<?php

if (!function_exists('site_lang')) {
    function site_lang(): string
    {
        static $lang = null;
        if ($lang !== null) {
            return $lang;
        }

        $requested = strtolower(trim((string) ($_GET['lang'] ?? '')));
        if (in_array($requested, ['zh', 'en'], true)) {
            $lang = $requested;
            if (!headers_sent()) {
                setcookie('site_lang', $lang, time() + 86400 * 30, '/');
            }
            $_COOKIE['site_lang'] = $lang;
            return $lang;
        }

        $cookie = strtolower(trim((string) ($_COOKIE['site_lang'] ?? '')));
        $lang = in_array($cookie, ['zh', 'en'], true) ? $cookie : 'en';
        return $lang;
    }
}

if (!function_exists('site_t')) {
    function site_t(array $messages, ?string $lang = null): string
    {
        $lang = $lang ?? site_lang();
        return (string) ($messages[$lang] ?? $messages['en'] ?? $messages['zh'] ?? reset($messages) ?? '');
    }
}

if (!function_exists('site_url_with_lang')) {
    function site_url_with_lang(string $href, ?string $lang = null): string
    {
        $lang = $lang ?? site_lang();
        $separator = str_contains($href, '?') ? '&' : '?';
        return $href . $separator . 'lang=' . rawurlencode($lang);
    }
}
