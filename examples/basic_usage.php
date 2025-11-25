<?php

declare(strict_types=1);

/**
 * Basic usage examples for ClearUrls PHP
 *
 * Run with: php examples/basic_usage.php
 * Or with Docker: docker run --rm -v ./:/app -w /app php:8.3 php examples/basic_usage.php
 */

require_once __DIR__ . '/../src/Provider.php';
require_once __DIR__ . '/../src/ClearUrlResult.php';
require_once __DIR__ . '/../src/ClearUrls.php';
require_once __DIR__ . '/../src/Rules.php';

use ClearUrls\ClearUrls;

echo "ClearUrls PHP - Basic Usage Examples\n";
echo "====================================\n\n";

// Create cleaner with default rules
$cleaner = ClearUrls::createDefault();

// Example 1: Remove UTM parameters
echo "Example 1: Remove UTM parameters\n";
$url1 = 'https://example.com/page?utm_source=twitter&utm_medium=social&id=123';
$result1 = $cleaner->clean($url1);
echo "Original: $url1\n";
echo "Cleaned:  {$result1->url}\n";
echo "Status:   " . ($result1->wasModified ? '✓ Modified' : '✗ Unchanged') . "\n\n";

// Example 2: Remove Google tracking
echo "Example 2: Remove Google tracking\n";
$url2 = 'https://www.google.com/search?q=php&ved=123&ei=456&source=hp';
$result2 = $cleaner->clean($url2);
echo "Original: $url2\n";
echo "Cleaned:  {$result2->url}\n";
echo "Status:   " . ($result2->wasModified ? '✓ Modified' : '✗ Unchanged') . "\n\n";

// Example 3: Handle redirections
echo "Example 3: Handle Google redirections\n";
$url3 = 'https://www.google.com/url?q=https://example.com/target&ved=123';
$result3 = $cleaner->clean($url3);
echo "Original: $url3\n";
echo "Cleaned:  {$result3->url}\n";
echo "Status:   " . ($result3->wasRedirected ? '✓ Redirected' : '✗ Not redirected') . "\n\n";

// Example 4: Multiple tracking parameters
echo "Example 4: Multiple tracking parameters\n";
$url4 = 'https://example.com/page?fbclid=abc&gclid=xyz&_ga=1.2.3&id=999';
$result4 = $cleaner->clean($url4);
echo "Original: $url4\n";
echo "Cleaned:  {$result4->url}\n";
echo "Status:   " . ($result4->wasModified ? '✓ Modified' : '✗ Unchanged') . "\n\n";

// Example 5: Clean URL with fragment
echo "Example 5: Clean URL with fragment\n";
$url5 = 'https://example.com/page#utm_source=email&section=content';
$result5 = $cleaner->clean($url5);
echo "Original: $url5\n";
echo "Cleaned:  {$result5->url}\n";
echo "Status:   " . ($result5->wasModified ? '✓ Modified' : '✗ Unchanged') . "\n\n";

// Example 6: No tracking parameters (unchanged)
echo "Example 6: No tracking parameters\n";
$url6 = 'https://example.com/page?id=123&sort=date';
$result6 = $cleaner->clean($url6);
echo "Original: $url6\n";
echo "Cleaned:  {$result6->url}\n";
echo "Status:   " . ($result6->wasModified ? '✓ Modified' : '✗ Unchanged') . "\n\n";

// Example 7: Batch processing
echo "Example 7: Batch processing\n";
$urls = [
    'https://example.com/page1?utm_source=email',
    'https://www.amazon.com/dp/B123?ref=nav&pf_rd_p=456',
    'https://www.facebook.com/page?__tn__=abc',
];

foreach ($urls as $i => $url) {
    $result = $cleaner->clean($url);
    $status = $result->wasModified ? '✓' : '✗';
    echo sprintf("  [%s] %s\n      -> %s\n", $status, $url, $result->url);
}

echo "\nDone!\n";
