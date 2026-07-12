<?php
namespace SEO\Helpers;

class PerformanceHelper {

    public static function setResponseHeaders() {
        if (!headers_sent()) {
            // Browser Cache
            header("Cache-Control: max-age=3600, must-revalidate");
            
            // Security Headers
            header("X-Content-Type-Options: nosniff");
            header("X-Frame-Options: SAMEORIGIN");
            header("X-XSS-Protection: 1; mode=block");
            header("Referrer-Policy: strict-origin-when-cross-origin");
        }
    }

    public function generateHeaders() {
        $html = "";
        
        // DNS Prefetch & Preconnect
        $domains = [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://cdn.tailwindcss.com'
        ];
        
        foreach ($domains as $domain) {
            $html .= "<link rel=\"dns-prefetch\" href=\"{$domain}\" />\n";
            $html .= "<link rel=\"preconnect\" href=\"{$domain}\" crossorigin />\n";
        }
        
        // Preload Fonts (from original index.php)
        $html .= "<link rel=\"preload\" href=\"https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Lato:wght@300;400;700&display=swap\" as=\"style\" />\n";

        // Web App Manifest
        $html .= "<link rel=\"manifest\" href=\"/manifest.json\" />\n";
        $html .= "<meta name=\"theme-color\" content=\"#14532D\" />\n";

        return $html;
    }

    public function applyLazyLoading($html) {
        // Find all <img> tags that don't have loading attribute and add loading="lazy"
        $html = preg_replace_callback('/<img\s+(?![^>]*loading=)([^>]+)>/i', function($matches) {
            return "<img loading=\"lazy\" {$matches[1]}>";
        }, $html);
        return $html;
    }

    public function minifyHtml($html) {
        // Basic HTML minification (optional, can be disabled if it breaks SPA structure)
        // But for safety against "DO NOT change logic", we will keep it light.
        // Remove HTML comments (except IE conditionals and our own SEO comments)
        $search = [
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s' // Remove HTML comments
        ];
        $replace = [
            '>',
            '<',
            '\\1',
            ''
        ];
        // We skip aggressive minification to avoid breaking `<script>` tags or SPA
        // Return original HTML for now to ensure 100% safety as per "JANGAN mengubah script yang sudah berjalan"
        // Actually, just returning the original HTML is safer.
        return $html;
    }
}
