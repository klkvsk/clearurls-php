<?php

declare(strict_types=1);

/**
 * Advanced usage examples for ClearUrls PHP
 *
 * Run with: php examples/advanced_usage.php
 * Or with Docker: docker run --rm -v ./:/app -w /app php:8.3 php examples/advanced_usage.php
 */

require_once __DIR__ . '/../src/Provider.php';
require_once __DIR__ . '/../src/ClearUrlResult.php';
require_once __DIR__ . '/../src/ClearUrls.php';
require_once __DIR__ . '/../src/Rules.php';

use ClearUrls\ClearUrls;
use ClearUrls\Provider;

echo "ClearUrls PHP - Advanced Usage Examples\n";
echo "=======================================\n\n";

// Example 1: Custom providers
echo "Example 1: Using custom providers\n";
echo "-----------------------------------\n";
$customProviders = [
    new Provider(
        name: 'custom_tracking',
        urlPattern: '#^https?://mysite\.com#i',
        completeProvider: false,
        rules: [
            '#^tracking_id$#i',
            '#^session_token$#i',
            '#^debug$#i',
        ],
        rawRules: [],
        referralMarketing: [],
        exceptions: [],
        redirections: [],
        forceRedirection: false
    ),
];

$customCleaner = new ClearUrls($customProviders);
$url = 'https://mysite.com/page?id=123&tracking_id=xyz&session_token=abc';
$result = $customCleaner->clean($url);
echo "Original: $url\n";
echo "Cleaned:  {$result->url}\n\n";

// Example 2: Referral marketing control
echo "Example 2: Referral marketing control\n";
echo "--------------------------------------\n";

$cleaner = ClearUrls::createDefault();
$amazonUrl = 'https://www.amazon.com/dp/B123?tag=myaffiliate-20&ref=nav';

// With referral marketing disabled (default)
echo "Referral marketing DISABLED (default):\n";
$result1 = $cleaner->clean($amazonUrl);
echo "  Cleaned: {$result1->url}\n";
echo "  Note: 'tag' parameter removed\n\n";

// With referral marketing enabled
echo "Referral marketing ENABLED:\n";
$cleaner->setAllowReferralMarketing(true);
$result2 = $cleaner->clean($amazonUrl);
echo "  Cleaned: {$result2->url}\n";
echo "  Note: 'tag' parameter preserved\n\n";

// Example 3: Detailed result inspection
echo "Example 3: Detailed result inspection\n";
echo "--------------------------------------\n";
$cleaner = ClearUrls::createDefault();

function inspectResult(string $url, ClearUrls $cleaner): void
{
    echo "URL: $url\n";
    $result = $cleaner->clean($url);

    echo "  Result: {$result->url}\n";
    echo "  Modified:   " . ($result->wasModified ? 'Yes ✓' : 'No ✗') . "\n";
    echo "  Redirected: " . ($result->wasRedirected ? 'Yes ✓' : 'No ✗') . "\n";
    echo "  Blocked:    " . ($result->wasBlocked ? 'Yes ✓' : 'No ✗') . "\n";
    echo "  Any action: " . ($result->hadAnyAction() ? 'Yes ✓' : 'No ✗') . "\n\n";
}

inspectResult('https://example.com/page?utm_source=test&id=1', $cleaner);
inspectResult('https://www.google.com/url?q=https://target.com', $cleaner);
inspectResult('https://example.com/clean-url', $cleaner);

// Example 4: Batch processing with statistics
echo "Example 4: Batch processing with statistics\n";
echo "--------------------------------------------\n";

$urls = [
    'https://example.com/1?utm_source=email&utm_campaign=test',
    'https://www.google.com/search?q=test&ved=123&ei=456',
    'https://www.amazon.com/dp/B123?ref=sr&pf_rd_p=456&qid=789',
    'https://www.facebook.com/page?__tn__=abc&eid=123',
    'https://example.com/clean',
    'https://www.youtube.com/watch?v=abc123&feature=share',
];

$stats = [
    'total' => 0,
    'modified' => 0,
    'redirected' => 0,
    'unchanged' => 0,
];

foreach ($urls as $url) {
    $result = $cleaner->clean($url);
    $stats['total']++;

    if ($result->wasModified) {
        $stats['modified']++;
        echo "✓ {$result->url}\n";
    } elseif ($result->wasRedirected) {
        $stats['redirected']++;
        echo "→ {$result->url}\n";
    } else {
        $stats['unchanged']++;
        echo "- {$result->url}\n";
    }
}

echo "\nStatistics:\n";
echo "  Total URLs:   {$stats['total']}\n";
echo "  Modified:     {$stats['modified']}\n";
echo "  Redirected:   {$stats['redirected']}\n";
echo "  Unchanged:    {$stats['unchanged']}\n\n";

// Example 5: Performance-oriented processing
echo "Example 5: High-performance batch processing\n";
echo "---------------------------------------------\n";

$testUrls = array_fill(0, 1000, 'https://example.com/page?utm_source=test&id=123');

$start = hrtime(true);
foreach ($testUrls as $url) {
    $cleaner->clean($url);
}
$end = hrtime(true);

$duration = ($end - $start) / 1e9;
$urlsPerSecond = count($testUrls) / $duration;

echo "Processed " . count($testUrls) . " URLs in " . number_format($duration, 4) . " seconds\n";
echo "Performance: " . number_format($urlsPerSecond, 0) . " URLs/second\n";
echo "Average: " . number_format(($duration / count($testUrls)) * 1000, 3) . " ms/URL\n\n";

// Example 6: URL validation
echo "Example 6: Handling various URL formats\n";
echo "----------------------------------------\n";

$testCases = [
    'https://example.com:8080/page?utm_source=test',  // With port
    'https://user:pass@example.com/page?utm_source=test',  // With auth
    'https://example.com/page#utm_source=test',  // Fragment only
    'data:text/plain,hello',  // Data URL (should be ignored)
    'not-a-url',  // Invalid URL
    '',  // Empty string
];

foreach ($testCases as $testUrl) {
    $result = $cleaner->clean($testUrl);
    $changed = $result->wasModified || $result->wasRedirected ? '✓' : '✗';
    echo "[$changed] " . ($testUrl ?: '(empty)') . "\n";
    if ($testUrl !== $result->url) {
        echo "     -> {$result->url}\n";
    }
}

echo "\nDone!\n";
