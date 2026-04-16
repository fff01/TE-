<?php
declare(strict_types=1);

if (!defined('TEKG_ROOT_DIR')) {
    define('TEKG_ROOT_DIR', __DIR__);
}
if (!defined('TEKG_DATA_FS_DIR')) {
    define('TEKG_DATA_FS_DIR', TEKG_ROOT_DIR . '/data');
}
if (!defined('TEKG_DATA_URL_BASE')) {
    define('TEKG_DATA_URL_BASE', '/TE-/data');
}
if (!defined('TEKG_JBROWSE_FS_DIR')) {
    define('TEKG_JBROWSE_FS_DIR', TEKG_DATA_FS_DIR . '/JBrowse');
}
if (!defined('TEKG_JBROWSE_URL_BASE')) {
    define('TEKG_JBROWSE_URL_BASE', TEKG_DATA_URL_BASE . '/JBrowse');
}

function tekg_fs_from_project_relative(string $relativePath): string
{
    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    return TEKG_ROOT_DIR . '/' . $normalized;
}

function tekg_url_from_project_relative(string $relativePath): string
{
    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    return '/TE-/' . $normalized;
}

function tekg_jbrowse_fs_path(string $suffix = ''): string
{
    $normalized = ltrim(str_replace('\\', '/', $suffix), '/');
    return $normalized === '' ? TEKG_JBROWSE_FS_DIR : (TEKG_JBROWSE_FS_DIR . '/' . $normalized);
}

function tekg_jbrowse_url(string $suffix = ''): string
{
    $normalized = ltrim(str_replace('\\', '/', $suffix), '/');
    return $normalized === '' ? TEKG_JBROWSE_URL_BASE : (TEKG_JBROWSE_URL_BASE . '/' . $normalized);
}
