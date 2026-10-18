<?php
// Required router for PHP's development server; Apache uses .htaccess.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('#(?:^|/)(?:\.|config(?:/|$)|database(?:/|$)|includes(?:/|$)|scripts(?:/|$)|tests(?:/|$)|storage(?:/|$)|uploads(?:/|$))#i', $path)
    || preg_match('/\.(?:sqlite(?:-.*)?|sql|env|log|sh|md|txt)$/i', $path)) {
    http_response_code(403);
    exit;
}
if ($path !== '/' && !is_file(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    http_response_code(404);
    exit;
}
return false;
