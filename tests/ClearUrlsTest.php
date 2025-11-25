<?php

declare(strict_types=1);

namespace ClearUrls\Tests;

use ClearUrls\ClearUrls;
use ClearUrls\Provider;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for ClearUrls library
 */
class ClearUrlsTest extends TestCase
{
    private ClearUrls $cleaner;

    protected function setUp(): void
    {
        // Create test providers with common tracking parameters
        $providers = [
            // Google provider
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
                referralMarketing: ['#^referrer$#i'],
                exceptions: [
                    '#^https?://mail\.google\.com/mail/u/#i',
                    '#^https?://accounts\.google\.com/o/oauth2/#i',
                ],
                redirections: [
                    '#^https?://(?:[a-z0-9-]+\.)*?google(?:\.[a-z]{2,}){1,}/url\?.*?(?:url|q)=(https?[^&]+)#i',
                ],
                forceRedirection: false
            ),
            // Amazon provider
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
                referralMarketing: ['#^tag$#i'],
                exceptions: [],
                redirections: [],
                forceRedirection: false
            ),
            // Facebook provider
            new Provider(
                name: 'facebook',
                urlPattern: '#^https?://(?:[a-z0-9-]+\.)*?facebook\.com#i',
                completeProvider: false,
                rules: [
                    '#^__tn__$#i',
                    '#^eid$#i',
                    '#^hc_[a-z_%\[\]0-9]*$#i',
                ],
                rawRules: [],
                referralMarketing: [],
                exceptions: [
                    '#^https?://(?:[a-z0-9-]+\.)*?facebook\.com/(?:login_alerts|ajax)/#i',
                ],
                redirections: [
                    '#^https?://l[a-z]?\.facebook\.com/l\.php\?.*?u=(https?%3A%2F%2F[^&]*)#i',
                ],
                forceRedirection: false
            ),
            // Global rules provider (matches all URLs)
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

        $this->cleaner = new ClearUrls($providers);
    }

    public function testBasicUrlUnchanged(): void
    {
        $url = 'https://example.com/page';
        $result = $this->cleaner->clean($url);

        $this->assertEquals($url, $result->url);
        $this->assertFalse($result->wasModified);
        $this->assertFalse($result->wasBlocked);
        $this->assertFalse($result->wasRedirected);
    }

    public function testRemoveUtmParameters(): void
    {
        $url = 'https://example.com/page?utm_source=twitter&utm_medium=social&id=123';
        $result = $this->cleaner->clean($url);

        $this->assertEquals('https://example.com/page?id=123', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testRemoveMultipleTrackingParams(): void
    {
        $url = 'https://example.com/page?fbclid=abc123&gclid=xyz789&_ga=1.2.3.4&id=999';
        $result = $this->cleaner->clean($url);

        $this->assertEquals('https://example.com/page?id=999', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testGoogleSearchTracking(): void
    {
        $url = 'https://www.google.com/search?q=test&ved=123&ei=456&usg=789';
        $result = $this->cleaner->clean($url);

        $this->assertEquals('https://www.google.com/search?q=test', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testGoogleRedirect(): void
    {
        $url = 'https://www.google.com/url?q=https://example.com/target&ved=123';
        $result = $this->cleaner->clean($url);

        $this->assertEquals('https://example.com/target', $result->url);
        $this->assertTrue($result->wasRedirected);
    }

    public function testAmazonRawRule(): void
    {
        $url = 'https://www.amazon.com/product/B08ABC123/ref=sr_1_1?qid=123';
        $result = $this->cleaner->clean($url);

        // Raw rule should remove "/ref=..." part
        $this->assertStringNotContainsString('/ref=', $result->url);
        $this->assertStringNotContainsString('qid=', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testAmazonQueryParams(): void
    {
        $url = 'https://www.amazon.com/dp/B08ABC123?ref_=nav&pf_rd_p=123&qid=456';
        $result = $this->cleaner->clean($url);

        $this->assertStringNotContainsString('ref_=', $result->url);
        $this->assertStringNotContainsString('pf_rd_p=', $result->url);
        $this->assertStringNotContainsString('qid=', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testFacebookRedirect(): void
    {
        $targetUrl = 'https://example.com/page';
        $encodedTarget = 'https%3A%2F%2Fexample.com%2Fpage';
        $url = "https://l.facebook.com/l.php?u=$encodedTarget&h=abc123";
        $result = $this->cleaner->clean($url);

        $this->assertEquals($targetUrl, $result->url);
        $this->assertTrue($result->wasRedirected);
    }

    public function testPreserveNonTrackingParams(): void
    {
        $url = 'https://example.com/search?q=test&page=2&sort=date&utm_source=email';
        $result = $this->cleaner->clean($url);

        $this->assertStringContainsString('q=test', $result->url);
        $this->assertStringContainsString('page=2', $result->url);
        $this->assertStringContainsString('sort=date', $result->url);
        $this->assertStringNotContainsString('utm_source', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testFragmentParameters(): void
    {
        $url = 'https://example.com/page#utm_source=test&section=content';
        $result = $this->cleaner->clean($url);

        $this->assertStringNotContainsString('utm_source', $result->url);
        $this->assertStringContainsString('section=content', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testPreserveNonQueryFragment(): void
    {
        $url = 'https://example.com/page#section';
        $result = $this->cleaner->clean($url);

        $this->assertEquals($url, $result->url);
        $this->assertFalse($result->wasModified);
    }

    public function testGoogleException(): void
    {
        $url = 'https://mail.google.com/mail/u/0/?ved=123';
        $result = $this->cleaner->clean($url);

        // Should not be modified due to exception
        $this->assertEquals($url, $result->url);
        $this->assertFalse($result->wasModified);
    }

    public function testEmptyUrl(): void
    {
        $result = $this->cleaner->clean('');

        $this->assertEquals('', $result->url);
        $this->assertFalse($result->wasModified);
    }

    public function testInvalidUrl(): void
    {
        $result = $this->cleaner->clean('not-a-url');

        $this->assertEquals('not-a-url', $result->url);
        $this->assertFalse($result->wasModified);
    }

    public function testDataUrl(): void
    {
        $url = 'data:text/plain;base64,SGVsbG8gV29ybGQ=';
        $result = $this->cleaner->clean($url);

        $this->assertEquals($url, $result->url);
        $this->assertFalse($result->wasModified);
    }

    public function testUrlWithPort(): void
    {
        $url = 'https://example.com:8080/page?utm_source=test&id=123';
        $result = $this->cleaner->clean($url);

        $this->assertEquals('https://example.com:8080/page?id=123', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testUrlWithAuth(): void
    {
        $url = 'https://user:pass@example.com/page?utm_source=test&id=123';
        $result = $this->cleaner->clean($url);

        $this->assertStringContainsString('user:pass@', $result->url);
        $this->assertStringContainsString('id=123', $result->url);
        $this->assertStringNotContainsString('utm_source', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testComplexUrl(): void
    {
        $url = 'https://www.google.com/search?q=php+url+cleaning&source=hp&ei=abc&ved=123&oq=php&gs_lcp=xyz';
        $result = $this->cleaner->clean($url);

        $this->assertStringContainsString('q=php', $result->url);
        $this->assertStringNotContainsString('ved=', $result->url);
        $this->assertStringNotContainsString('ei=', $result->url);
        $this->assertStringNotContainsString('source=', $result->url);
        $this->assertStringNotContainsString('gs_lcp=', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testReferralMarketingDisabled(): void
    {
        $providers = [
            new Provider(
                name: 'test',
                urlPattern: '#^https?://example\.com#i',
                completeProvider: false,
                rules: [],
                rawRules: [],
                referralMarketing: ['#^ref$#i'],
                exceptions: [],
                redirections: [],
                forceRedirection: false
            ),
        ];

        $cleaner = new ClearUrls($providers, allowReferralMarketing: false);
        $url = 'https://example.com/page?ref=affiliate123&id=999';
        $result = $cleaner->clean($url);

        // Referral marketing disabled, so 'ref' should be removed
        $this->assertStringNotContainsString('ref=', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testReferralMarketingEnabled(): void
    {
        $providers = [
            new Provider(
                name: 'test',
                urlPattern: '#^https?://example\.com#i',
                completeProvider: false,
                rules: [],
                rawRules: [],
                referralMarketing: ['#^ref$#i'],
                exceptions: [],
                redirections: [],
                forceRedirection: false
            ),
        ];

        $cleaner = new ClearUrls($providers, allowReferralMarketing: true);
        $url = 'https://example.com/page?ref=affiliate123&id=999';
        $result = $cleaner->clean($url);

        // Referral marketing enabled, so 'ref' should be kept
        $this->assertStringContainsString('ref=affiliate123', $result->url);
        $this->assertFalse($result->wasModified);
    }

    public function testMultipleProviderRules(): void
    {
        // Test that both provider-specific and global rules apply
        $url = 'https://www.google.com/search?q=test&ved=123&utm_source=email';
        $result = $this->cleaner->clean($url);

        // Google-specific 'ved' should be removed
        $this->assertStringNotContainsString('ved=', $result->url);
        // Global 'utm_source' should be removed
        $this->assertStringNotContainsString('utm_source=', $result->url);
        // Query param 'q' should be preserved
        $this->assertStringContainsString('q=test', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testUrlEncodedParams(): void
    {
        $url = 'https://example.com/page?utm_source=test&name=John+Doe&id=123';
        $result = $this->cleaner->clean($url);

        $this->assertStringNotContainsString('utm_source', $result->url);
        $this->assertStringContainsString('name=John', $result->url);
        $this->assertStringContainsString('id=123', $result->url);
        $this->assertTrue($result->wasModified);
    }

    public function testSpecialCharactersInParams(): void
    {
        $url = 'https://example.com/page?search=hello%20world&utm_source=test';
        $result = $this->cleaner->clean($url);

        $this->assertStringNotContainsString('utm_source', $result->url);
        $this->assertStringContainsString('search=hello', $result->url);
        $this->assertTrue($result->wasModified);
    }
}
