<?php
/**
 * MSHW-proxy - DOM-based HTML/CSS Rewriter
 * Safe, standards-compliant URL proxification (no regex hacks)
 */

declare(strict_types=1);

namespace MSHW\Proxy\Core;

use Masterminds\HTML5;
use DOMDocument;
use DOMXPath;
use DOMElement;

class HtmlRewriter
{
    private HTML5 $parser;
    private string $proxyBaseUrl;

    public function __construct(string $proxyBaseUrl = '')
    {
        $this->parser = new HTML5(['disable_html_ns' => true]);
        $this->proxyBaseUrl = $proxyBaseUrl ?: $this->detectProxyBase();
    }

    /**
     * Rewrite URLs in HTML/CSS/JS content
     */
    public function rewrite(string $content, string $baseUrl, string $contentType): string
    {
        $this->proxyBaseUrl = rtrim($this->proxyBaseUrl, '/');
        
        if (str_contains($contentType, 'html')) {
            return $this->rewriteHtml($content, $baseUrl);
        }
        if (str_contains($contentType, 'css')) {
            return $this->rewriteCss($content, $baseUrl);
        }
        // JS rewriting is complex; skip for now (can add JS parser later)
        return $content;
    }

    /**
     * Rewrite HTML with DOM manipulation
     */
    private function rewriteHtml(string $html, string $baseUrl): string
    {
        try {
            $dom = $this->parser->loadHTML($html);
            $xpath = new DOMXPath($dom);
            
            // Map of tags and attributes to rewrite
            $targets = [
                ['tag' => 'a', 'attr' => 'href'],
                ['tag' => 'img', 'attr' => 'src'],
                ['tag' => 'image', 'attr' => 'src'],
                ['tag' => 'script', 'attr' => 'src'],
                ['tag' => 'link', 'attr' => 'href'],
                ['tag' => 'iframe', 'attr' => 'src'],
                ['tag' => 'form', 'attr' => 'action'],
                ['tag' => 'video', 'attr' => 'src'],
                ['tag' => 'audio', 'attr' => 'src'],
                ['tag' => 'source', 'attr' => 'srcset'],
            ];

            foreach ($targets as $target) {
                $nodes = $xpath->query("//{$target['tag']}[@{$target['attr']}]");
                foreach ($nodes as $node) {
                    if (!$node instanceof DOMElement) continue;
                    $original = $node->getAttribute($target['attr']);
                    $proxified = $this->proxifyUrl($original, $baseUrl);
                    if ($proxified !== $original) {
                        $node->setAttribute($target['attr'], $proxified);
                    }
                }
            }

            // Rewrite inline CSS (style attributes)
            $styleNodes = $xpath->query("//*[@style]");
            foreach ($styleNodes as $node) {
                if (!$node instanceof DOMElement) continue;
                $css = $node->getAttribute('style');
                $node->setAttribute('style', $this->rewriteCssUrls($css, $baseUrl));
            }

            // Rewrite <style> tags
            $styleTags = $xpath->query("//style");
            foreach ($styleTags as $tag) {
                if ($tag->nodeValue) {
                    $tag->nodeValue = $this->rewriteCss($tag->nodeValue, $baseUrl);
                }
            }

            // Inject base tag for relative URL resolution
            $head = $xpath->query('//head')->item(0);
            if ($head instanceof DOMElement) {
                $base = $dom->createElement('base');
                $base->setAttribute('href', $baseUrl);
                $head->insertBefore($base, $head->firstChild);
            }

            return $this->parser->saveHTML($dom);
        } catch (\Throwable $e) {
            error_log("HTML rewrite error: " . $e->getMessage());
            return $html; // Fallback to original
        }
    }

    /**
     * Rewrite CSS url() references
     */
    private function rewriteCss(string $css, string $baseUrl): string
    {
        // Match url("..."), url('...'), url(...)
        return preg_replace_callback(
            '#url\(\s*([\'"]?)([^\'")]+)\1\s*\)#i',
            function ($matches) use ($baseUrl) {
                $quote = $matches[1];
                $url = trim($matches[2]);
                // Skip data: and # anchors
                if (str_starts_with($url, 'data:') || str_starts_with($url, '#')) {
                    return $matches[0];
                }
                $proxified = $this->proxifyUrl($url, $baseUrl);
                return "url({$quote}{$proxified}{$quote})";
            },
            $css
        );
    }

    /**
     * Rewrite CSS url() inside style attributes
     */
    private function rewriteCssUrls(string $style, string $baseUrl): string
    {
        return $this->rewriteCss($style, $baseUrl);
    }

    /**
     * Convert a URL to its proxified version
     */
    private function proxifyUrl(string $url, string $baseUrl): string
    {
        // Skip protocols we don't proxy
        if (preg_match('#^(javascript|mailto|tel|data|blob):#i', $url)) {
            return $url;
        }

        // Resolve relative URLs
        $absolute = $this->resolveUrl($url, $baseUrl);
        
        // Encode for proxy parameter
        $encoded = base64_encode($absolute);
        
        return "{$this->proxyBaseUrl}/?q={$encoded}";
    }

    /**
     * Resolve relative URL against base (RFC 3986)
     */
    private function resolveUrl(string $relative, string $base): string
    {
        // Use Guzzle's UriResolver for standards compliance
        if (class_exists(\GuzzleHttp\Psr7\Uri::class) && class_exists(\GuzzleHttp\Psr7\UriResolver::class)) {
            $baseUri = new \GuzzleHttp\Psr7\Uri($base);
            $relativeUri = new \GuzzleHttp\Psr7\Uri($relative);
            return (string) \GuzzleHttp\Psr7\UriResolver::resolve($baseUri, $relativeUri);
        }
        
        // Fallback simple resolver
        if (preg_match('#^https?://#i', $relative)) {
            return $relative; // Already absolute
        }
        if (str_starts_with($relative, '/')) {
            $parts = parse_url($base);
            return "{$parts['scheme']}://{$parts['host']}" . (isset($parts['port']) ? ":{$parts['port']}" : '') . $relative;
        }
        // Relative to current directory
        $baseDir = dirname($base);
        return rtrim($baseDir, '/') . '/' . ltrim($relative, './');
    }

    /**
     * Detect proxy base URL from current request
     */
    private function detectProxyBase(): string
    {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $port = $_SERVER['SERVER_PORT'] ?? 80;
        $script = $_SERVER['PHP_SELF'] ?? '/index.php';
        
        $scheme = $https ? 'https' : 'http';
        $portSuffix = ($scheme === 'http' && $port == 80) || ($scheme === 'https' && $port == 443) ? '' : ":$port";
        
        // Remove filename to get base path
        $basePath = dirname($script);
        $basePath = $basePath === '\\' || $basePath === '/' ? '' : $basePath;
        
        return "{$scheme}://{$host}{$portSuffix}{$basePath}";
    }
}
