<?php
/**
 * SEO Master - Core Engine
 * Intercepts requests and injects SEO optimizations on-the-fly using Output Buffering.
 * ADD ONLY PRINCIPLE - Does not modify existing logic.
 */

require_once __DIR__ . '/helpers/PerformanceHelper.php';
require_once __DIR__ . '/helpers/MetaGenerator.php';
require_once __DIR__ . '/helpers/SchemaGenerator.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// 1. Intercept Sitemaps
if (preg_match('/^\/sitemap(-.*)?\.xml$/', $path)) {
    require_once __DIR__ . '/SitemapHandler.php';
    \SEO\SitemapHandler::handleRequest($path);
    exit;
}

// 2. Intercept RSS/Feed
if (preg_match('/^\/(feed|rss)(\.xml)?$/', $path)) {
    require_once __DIR__ . '/FeedHandler.php';
    \SEO\FeedHandler::handleRequest();
    exit;
}

// 3. Output Buffering for HTML Modification
ob_start(function($buffer) {
    // Only process HTML output
    if (stripos($buffer, '<html') === false) {
        return $buffer;
    }

    $metaGen = new \SEO\Helpers\MetaGenerator();
    $schemaGen = new \SEO\Helpers\SchemaGenerator();
    $perfGen = new \SEO\Helpers\PerformanceHelper();

    $seoHtml = "\n<!-- ================= SEO INJECTED ================= -->\n";
    $seoHtml .= $perfGen->generateHeaders();
    $seoHtml .= $metaGen->generateTags();
    $seoHtml .= $schemaGen->generateSchemas();
    $seoHtml .= "<!-- ================= END SEO INJECTED ================= -->\n";

    // Inject just before </head>
    $buffer = preg_replace('/<\/head>/i', $seoHtml . '</head>', $buffer, 1);

    // Apply Lazy Loading for Images
    $buffer = $perfGen->applyLazyLoading($buffer);

    // Apply HTML Minifier (if enabled in helper)
    $buffer = $perfGen->minifyHtml($buffer);

    return $buffer;
});

// Set Security and Cache Headers early
\SEO\Helpers\PerformanceHelper::setResponseHeaders();
