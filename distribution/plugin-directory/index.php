<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$route = ljndi_route();

if ($route === '/api/v1/plugins' || $route === '/api/v1/plugins/') {
    $catalog = array_map('ljndi_public_manifest', ljndi_load_catalog());
    ljndi_json(array(
        'data' => $catalog,
        'meta' => array(
            'count' => count($catalog),
            'generated_at' => gmdate('c'),
        ),
    ));
}

if (preg_match('#^/api/v1/plugins/([a-z0-9\-]+)/?$#', $route, $matches)) {
    $manifest = ljndi_load_manifest($matches[1]);
    if ($manifest === null) {
        ljndi_json(array('error' => 'plugin_not_found'), 404);
    }
    ljndi_json(ljndi_public_manifest($manifest), 200, json_encode($manifest));
}

if ($route === '/' || $route === '') {
    $plugins = ljndi_load_catalog();
    ljndi_page_start('Official WordPress Plugins', 'Browse official public WordPress plugins released by LJNDI.');
    ?>
    <section class="hero">
        <div class="shell hero-grid">
            <div>
                <span class="eyebrow">Official releases</span>
                <h1>WordPress plugins built for connected business systems.</h1>
                <p>Download verified LJNDI plugins, read release details, and receive updates directly inside WordPress.</p>
            </div>
            <div class="hero-stat"><strong><?php echo count($plugins); ?></strong><span>public plugin<?php echo count($plugins) === 1 ? '' : 's'; ?></span></div>
        </div>
    </section>
    <section class="shell catalogue-section">
        <div class="section-heading">
            <div><span class="eyebrow">Directory</span><h2>Available plugins</h2></div>
        </div>
        <?php if (empty($plugins)) : ?>
            <div class="empty-state"><h3>No public release yet</h3><p>Published plugins will appear here automatically.</p></div>
        <?php else : ?>
            <div class="plugin-grid">
                <?php foreach ($plugins as $plugin) :
                    $slug = (string) $plugin['slug'];
                    $detailUrl = ljndi_base_url() . '/' . rawurlencode($slug);
                    ?>
                    <article class="plugin-card">
                        <a class="plugin-icon" href="<?php echo ljndi_e($detailUrl); ?>">
                            <?php if (!empty($plugin['icon_url'])) : ?><img src="<?php echo ljndi_e($plugin['icon_url']); ?>" alt=""><?php else : ?><span><?php echo ljndi_e(strtoupper(substr((string) $plugin['name'], 0, 1))); ?></span><?php endif; ?>
                        </a>
                        <div class="plugin-card-body">
                            <div class="plugin-card-top"><span class="version">v<?php echo ljndi_e($plugin['version']); ?></span><span class="free">Free</span></div>
                            <h3><a href="<?php echo ljndi_e($detailUrl); ?>"><?php echo ljndi_e($plugin['name']); ?></a></h3>
                            <p><?php echo ljndi_e($plugin['summary'] ?? ''); ?></p>
                            <div class="card-actions"><a class="button button-secondary" href="<?php echo ljndi_e($detailUrl); ?>">View plugin</a><a class="text-link" href="<?php echo ljndi_e($plugin['download_url'] ?? '#'); ?>">Download</a></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    ljndi_page_end();
    exit;
}

if (preg_match('#^/([a-z0-9\-]+)/?$#', $route, $matches)) {
    $manifest = ljndi_load_manifest($matches[1]);
    if ($manifest === null) {
        http_response_code(404);
        ljndi_page_start('Plugin not found');
        echo '<section class="shell not-found"><span class="eyebrow">404</span><h1>Plugin not found</h1><p>This plugin is unpublished or the address is incorrect.</p><a class="button" href="' . ljndi_e(ljndi_base_url() . '/') . '">Back to directory</a></section>';
        ljndi_page_end();
        exit;
    }

    $sections = is_array($manifest['sections'] ?? null) ? $manifest['sections'] : array();
    ljndi_page_start((string) $manifest['name'], (string) ($manifest['summary'] ?? ''));
    ?>
    <section class="plugin-hero">
        <div class="shell plugin-hero-grid">
            <div class="plugin-heading">
                <div class="large-icon"><?php if (!empty($manifest['icon_url'])) : ?><img src="<?php echo ljndi_e($manifest['icon_url']); ?>" alt=""><?php else : ?><span><?php echo ljndi_e(strtoupper(substr((string) $manifest['name'], 0, 1))); ?></span><?php endif; ?></div>
                <div><span class="eyebrow">Official LJNDI plugin</span><h1><?php echo ljndi_e($manifest['name']); ?></h1><p><?php echo ljndi_e($manifest['summary'] ?? ''); ?></p></div>
            </div>
            <aside class="download-card">
                <a class="button button-primary button-wide" href="<?php echo ljndi_e($manifest['download_url'] ?? '#'); ?>">Download version <?php echo ljndi_e($manifest['version']); ?></a>
                <dl>
                    <div><dt>Version</dt><dd><?php echo ljndi_e($manifest['version']); ?></dd></div>
                    <div><dt>WordPress</dt><dd><?php echo ljndi_e($manifest['requires'] ?? 'Not specified'); ?>+</dd></div>
                    <div><dt>Tested up to</dt><dd><?php echo ljndi_e($manifest['tested'] ?? 'Not specified'); ?></dd></div>
                    <div><dt>PHP</dt><dd><?php echo ljndi_e($manifest['requires_php'] ?? 'Not specified'); ?>+</dd></div>
                </dl>
            </aside>
        </div>
    </section>
    <section class="shell plugin-content-grid">
        <article class="content-card prose">
            <h2>About this plugin</h2>
            <div><?php echo nl2br(ljndi_e($manifest['description'] ?? '')); ?></div>
            <?php foreach (array('installation' => 'Installation', 'changelog' => 'Changelog') as $key => $heading) : if (!empty($sections[$key])) : ?>
                <h2><?php echo ljndi_e($heading); ?></h2><div><?php echo nl2br(ljndi_e($sections[$key])); ?></div>
            <?php endif; endforeach; ?>
        </article>
        <aside class="content-card details-card">
            <h2>Plugin details</h2>
            <dl>
                <div><dt>Author</dt><dd><a href="<?php echo ljndi_e($manifest['author_url'] ?? 'https://lerionjakenwauda.com'); ?>"><?php echo ljndi_e($manifest['author'] ?? ljndi_config('company_name')); ?></a></dd></div>
                <div><dt>Last updated</dt><dd><?php echo ljndi_e(!empty($manifest['updated_at']) ? date('F j, Y', strtotime((string) $manifest['updated_at'])) : 'Not specified'); ?></dd></div>
                <div><dt>License</dt><dd>GPL-2.0-or-later</dd></div>
            </dl>
            <a class="text-link" href="<?php echo ljndi_e(ljndi_base_url() . '/api/v1/plugins/' . rawurlencode((string) $manifest['slug'])); ?>">View update metadata</a>
        </aside>
    </section>
    <?php
    ljndi_page_end();
    exit;
}

http_response_code(404);
ljndi_page_start('Page not found');
echo '<section class="shell not-found"><span class="eyebrow">404</span><h1>Page not found</h1><a class="button" href="' . ljndi_e(ljndi_base_url() . '/') . '">Back to directory</a></section>';
ljndi_page_end();
