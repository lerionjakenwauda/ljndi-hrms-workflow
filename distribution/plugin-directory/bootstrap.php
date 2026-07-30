<?php

declare(strict_types=1);

$defaultConfig = array(
    'site_name'    => 'LJNDI Plugins',
    'company_name' => 'Lerion Jake Nwauda Digital Innovations Ltd',
    'base_url'     => '',
    'support_url'  => 'https://lerionjakenwauda.com/contact',
    'catalog_path' => __DIR__ . '/storage/catalog',
    'cache_seconds'=> 300,
);

$configFile = __DIR__ . '/config.php';
$config = $defaultConfig;
if (is_file($configFile)) {
    $loaded = require $configFile;
    if (is_array($loaded)) {
        $config = array_replace($defaultConfig, $loaded);
    }
}

function ljndi_config(?string $key = null)
{
    global $config;
    if ($key === null) {
        return $config;
    }
    return array_key_exists($key, $config) ? $config[$key] : null;
}

function ljndi_base_url(): string
{
    $configured = trim((string) ljndi_config('base_url'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/plugins/index.php')));
    $scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');

    return $scheme . '://' . $host . $scriptDir;
}

function ljndi_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ljndi_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\-]+/', '-', $value);
    return trim((string) $value, '-');
}

function ljndi_manifest_path(string $slug): string
{
    return rtrim((string) ljndi_config('catalog_path'), '/\\') . DIRECTORY_SEPARATOR . ljndi_slug($slug) . '.json';
}

function ljndi_load_manifest(string $slug): ?array
{
    $path = ljndi_manifest_path($slug);
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded) || ($decoded['status'] ?? 'published') !== 'published') {
        return null;
    }

    return $decoded;
}

function ljndi_load_catalog(): array
{
    $directory = rtrim((string) ljndi_config('catalog_path'), '/\\');
    if (!is_dir($directory)) {
        return array();
    }

    $plugins = array();
    $files = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: array();
    foreach ($files as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded) || ($decoded['status'] ?? 'published') !== 'published') {
            continue;
        }
        if (empty($decoded['slug']) || empty($decoded['name']) || empty($decoded['version'])) {
            continue;
        }
        $plugins[] = $decoded;
    }

    usort($plugins, static function (array $left, array $right): int {
        return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
    });

    return $plugins;
}

function ljndi_route(): string
{
    if (isset($_GET['route'])) {
        return '/' . trim((string) $_GET['route'], '/');
    }

    $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $uriPath = is_string($uriPath) ? rawurldecode($uriPath) : '/';
    $basePath = parse_url(ljndi_base_url(), PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';
    if ($basePath !== '' && strpos($uriPath, $basePath) === 0) {
        $uriPath = substr($uriPath, strlen($basePath));
    }

    return '/' . trim($uriPath, '/');
}

function ljndi_json(array $payload, int $status = 200, ?string $etagSeed = null): void
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        $json = '{"error":"encoding_failed"}';
        $status = 500;
    }

    $etag = '"' . sha1($etagSeed !== null ? $etagSeed : $json) . '"';
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        exit;
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=' . max(0, (int) ljndi_config('cache_seconds')));
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}

function ljndi_public_manifest(array $manifest): array
{
    return array(
        'slug'          => (string) ($manifest['slug'] ?? ''),
        'name'          => (string) ($manifest['name'] ?? ''),
        'version'       => (string) ($manifest['version'] ?? ''),
        'summary'       => (string) ($manifest['summary'] ?? ''),
        'description'   => (string) ($manifest['description'] ?? ''),
        'author'        => (string) ($manifest['author'] ?? ljndi_config('company_name')),
        'author_url'    => (string) ($manifest['author_url'] ?? 'https://lerionjakenwauda.com'),
        'homepage'      => (string) ($manifest['homepage'] ?? ''),
        'requires'      => (string) ($manifest['requires'] ?? ''),
        'tested'        => (string) ($manifest['tested'] ?? ''),
        'requires_php'  => (string) ($manifest['requires_php'] ?? ''),
        'download_url'  => (string) ($manifest['download_url'] ?? ''),
        'icon_url'      => (string) ($manifest['icon_url'] ?? ''),
        'banner_url'    => (string) ($manifest['banner_url'] ?? ''),
        'sections'      => is_array($manifest['sections'] ?? null) ? $manifest['sections'] : array(),
        'published_at'  => (string) ($manifest['published_at'] ?? ''),
        'updated_at'    => (string) ($manifest['updated_at'] ?? ''),
    );
}

function ljndi_page_start(string $title, string $description = ''): void
{
    $fullTitle = $title . ' — ' . (string) ljndi_config('site_name');
    $base = ljndi_base_url();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo ljndi_e($description !== '' ? $description : 'Official public WordPress plugins from LJNDI.'); ?>">
    <title><?php echo ljndi_e($fullTitle); ?></title>
    <link rel="stylesheet" href="<?php echo ljndi_e($base . '/assets/app.css'); ?>">
</head>
<body>
<header class="site-header">
    <div class="shell header-inner">
        <a class="brand" href="<?php echo ljndi_e($base . '/'); ?>" aria-label="LJNDI Plugins home">
            <span class="brand-mark">L</span>
            <span><strong>LJNDI</strong><small>Plugin Directory</small></span>
        </a>
        <nav aria-label="Primary navigation">
            <a href="<?php echo ljndi_e($base . '/'); ?>">Plugins</a>
            <a href="<?php echo ljndi_e((string) ljndi_config('support_url')); ?>">Support</a>
        </nav>
    </div>
</header>
<main>
<?php
}

function ljndi_page_end(): void
{
    ?>
</main>
<footer class="site-footer">
    <div class="shell footer-inner">
        <span>&copy; <?php echo date('Y'); ?> <?php echo ljndi_e((string) ljndi_config('company_name')); ?></span>
        <a href="<?php echo ljndi_e(ljndi_base_url() . '/api/v1/plugins'); ?>">Plugin API</a>
    </div>
</footer>
</body>
</html>
<?php
}
