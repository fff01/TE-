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

if (!function_exists('site_renderer')) {
    function site_renderer(): string
    {
        static $renderer = null;
        if ($renderer !== null) {
            return $renderer;
        }

        $requested = strtolower(trim((string) ($_GET['renderer'] ?? '')));
        if ($requested === 'cytoscape' || $requested === 'g6') {
            $renderer = 'g6';
            if (!headers_sent()) {
                setcookie('site_renderer', $renderer, time() + 86400 * 30, '/');
            }
            $_COOKIE['site_renderer'] = $renderer;
            return $renderer;
        }

        $cookie = strtolower(trim((string) ($_COOKIE['site_renderer'] ?? '')));
        $renderer = 'g6';
        if ($cookie === 'cytoscape' && !headers_sent()) {
            setcookie('site_renderer', $renderer, time() + 86400 * 30, '/');
            $_COOKIE['site_renderer'] = $renderer;
        }
        return $renderer;
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

if (!function_exists('site_url_with_state')) {
    function site_url_with_state(string $href, ?string $lang = null, ?string $renderer = null, array $extraParams = []): string
    {
        $lang = $lang ?? site_lang();
        $renderer = $renderer ?? site_renderer();

        $parts = parse_url($href);
        $path = (string) ($parts['path'] ?? $href);
        $params = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }

        $params = array_merge($params, $extraParams, [
            'lang' => $lang,
            'renderer' => $renderer,
        ]);

        $query = http_build_query($params);
        return $path . ($query !== '' ? '?' . $query : '');
    }
}