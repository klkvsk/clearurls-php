<?php

declare(strict_types=1);

namespace ClearUrls\Tests;

use ClearUrls\ClearUrls;
use ClearUrls\Provider;
use PHPUnit\Framework\TestCase;

/**
 * Performance benchmarks for ClearUrls library
 *
 * Run with: phpunit tests/PerformanceTest.php --verbose
 */
class PerformanceTest extends TestCase
{
    private ClearUrls $cleaner;
    private const ITERATIONS = 10000;

    protected function setUp(): void
    {
        // Create a realistic set of providers
        $providers = $this->createTestProviders();
        $this->cleaner = new ClearUrls($providers);
    }

    public function testSimpleUrlPerformance(): void
    {
        $url = 'https://example.com/page?id=123';

        $start = hrtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->cleaner->clean($url);
        }
        $end = hrtime(true);

        $duration = ($end - $start) / 1e9; // Convert to seconds
        $urlsPerSecond = self::ITERATIONS / $duration;

        $this->addToAssertionCount(1);
        echo sprintf(
            "\n  Simple URL: %.2f ms total, %.0f URLs/sec, %.3f µs/URL\n",
            $duration * 1000,
            $urlsPerSecond,
            ($duration / self::ITERATIONS) * 1e6
        );

        // Should process at least 10,000 URLs per second
        $this->assertGreaterThan(10000, $urlsPerSecond, 'Performance is too slow');
    }

    public function testComplexUrlPerformance(): void
    {
        $url = 'https://www.google.com/search?q=test&ved=123&ei=456&source=hp&utm_source=email&utm_medium=social&gclid=abc&fbclid=xyz';

        $start = hrtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->cleaner->clean($url);
        }
        $end = hrtime(true);

        $duration = ($end - $start) / 1e9;
        $urlsPerSecond = self::ITERATIONS / $duration;

        $this->addToAssertionCount(1);
        echo sprintf(
            "\n  Complex URL: %.2f ms total, %.0f URLs/sec, %.3f µs/URL\n",
            $duration * 1000,
            $urlsPerSecond,
            ($duration / self::ITERATIONS) * 1e6
        );

        // Should process at least 5,000 complex URLs per second
        $this->assertGreaterThan(5000, $urlsPerSecond, 'Performance is too slow');
    }

    public function testRedirectionPerformance(): void
    {
        $url = 'https://www.google.com/url?q=https://example.com/target&ved=123&source=web';

        $start = hrtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->cleaner->clean($url);
        }
        $end = hrtime(true);

        $duration = ($end - $start) / 1e9;
        $urlsPerSecond = self::ITERATIONS / $duration;

        $this->addToAssertionCount(1);
        echo sprintf(
            "\n  Redirection URL: %.2f ms total, %.0f URLs/sec, %.3f µs/URL\n",
            $duration * 1000,
            $urlsPerSecond,
            ($duration / self::ITERATIONS) * 1e6
        );
    }

    public function testMixedUrlsBenchmark(): void
    {
        $urls = [
            'https://example.com/page?id=123',
            'https://www.google.com/search?q=test&ved=123&utm_source=email',
            'https://www.amazon.com/dp/B123?ref=sr&pf_rd_p=456',
            'https://www.facebook.com/page?__tn__=abc&eid=123',
            'https://example.com/page?utm_campaign=test&utm_medium=social&fbclid=xyz',
            'https://example.com/simple',
            'https://www.google.com/url?q=https://target.com&ved=123',
            'https://example.com/page#utm_source=hash&section=content',
        ];

        $totalIterations = self::ITERATIONS * count($urls);

        $start = hrtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            foreach ($urls as $url) {
                $this->cleaner->clean($url);
            }
        }
        $end = hrtime(true);

        $duration = ($end - $start) / 1e9;
        $urlsPerSecond = $totalIterations / $duration;

        $this->addToAssertionCount(1);
        echo sprintf(
            "\n  Mixed URLs: %.2f ms total, %.0f URLs/sec, %.3f µs/URL\n",
            $duration * 1000,
            $urlsPerSecond,
            ($duration / $totalIterations) * 1e6
        );

        echo sprintf(
            "  Processed %s URLs in %.2f seconds\n",
            number_format($totalIterations),
            $duration
        );
    }

    public function testMemoryUsage(): void
    {
        $url = 'https://www.google.com/search?q=test&ved=123&ei=456&source=hp&utm_source=email';

        $memBefore = memory_get_usage();

        for ($i = 0; $i < 1000; $i++) {
            $this->cleaner->clean($url);
        }

        $memAfter = memory_get_usage();
        $memUsed = $memAfter - $memBefore;

        $this->addToAssertionCount(1);
        echo sprintf(
            "\n  Memory usage for 1,000 URLs: %.2f KB (%.2f bytes/URL)\n",
            $memUsed / 1024,
            $memUsed / 1000
        );

        // Should use less than 100 KB for 1000 URLs
        $this->assertLessThan(100 * 1024, $memUsed, 'Memory usage is too high');
    }

    private function createTestProviders(): array
    {
        return [
            new Provider(
                name: 'google',
                urlPattern: '#^https?://(?:[a-z0-9-]+\.)*?google(?:\.[a-z]{2,}){1,}#i',
                completeProvider: false,
                rules: [
                    '#^ved$#i',
                    '#^ei$#i',
                    '#^usg$#i',
                    '#^source$#i',
                    '#^gs_[a-z]*$#i',
                    '#^gfe_[a-z]*$#i',
                ],
                rawRules: [],
                referralMarketing: [],
                exceptions: [],
                redirections: [
                    '#^https?://(?:[a-z0-9-]+\.)*?google(?:\.[a-z]{2,}){1,}/url\?.*?(?:url|q)=(https?[^&]+)#i',
                ],
                forceRedirection: false
            ),
            new Provider(
                name: 'amazon',
                urlPattern: '#^https?://(?:[a-z0-9-]+\.)*?amazon(?:\.[a-z]{2,}){1,}#i',
                completeProvider: false,
                rules: [
                    '#^ref_?$#i',
                    '#^pf_rd_[a-z]*$#i',
                    '#^qid$#i',
                    '#^sr$#i',
                ],
                rawRules: ['#/ref=[^/?]*#i'],
                referralMarketing: [],
                exceptions: [],
                redirections: [],
                forceRedirection: false
            ),
            new Provider(
                name: 'facebook',
                urlPattern: '#^https?://(?:[a-z0-9-]+\.)*?facebook\.com#i',
                completeProvider: false,
                rules: [
                    '#^__tn__$#i',
                    '#^eid$#i',
                ],
                rawRules: [],
                referralMarketing: [],
                exceptions: [],
                redirections: [],
                forceRedirection: false
            ),
            new Provider(
                name: 'globalRules',
                urlPattern: '#.*#i',
                completeProvider: false,
                rules: [
                    '#^(?:%3F)?utm(?:_[a-z_]*)?$#i',
                    '#^(?:%3F)?fbclid$#i',
                    '#^(?:%3F)?gclid$#i',
                    '#^(?:%3F)?_ga$#i',
                ],
                rawRules: [],
                referralMarketing: [],
                exceptions: [],
                redirections: [],
                forceRedirection: false
            ),
        ];
    }
}
