<?php

declare(strict_types=1);

namespace ClearUrls;

/**
 * ClearUrls - High-performance URL cleaning library
 * Removes tracking parameters and unwanted fields from URLs
 */
class ClearUrls
{
    /** @var array<Provider> */
    private array $providers = [];

    /** @var bool */
    private bool $allowReferralMarketing = false;

    /**
     * @param array<Provider> $providers Array of Provider instances
     * @param bool $allowReferralMarketing Whether to keep referral marketing parameters
     */
    public function __construct(array $providers = [], bool $allowReferralMarketing = false)
    {
        $this->providers = $providers;
        $this->allowReferralMarketing = $allowReferralMarketing;
    }

    /**
     * Load rule sets from Rules
     */
    public static function createDefault(bool $allowReferralMarketing = false): self
    {
        if (!class_exists('ClearUrls\\Rules')) {
            throw new \RuntimeException(
                'Rules not found. Please run: php build/compile-rules.php'
            );
        }

        $providers = Rules::getProviders();
        return new self($providers, $allowReferralMarketing);
    }

    /**
     * Clean a URL by removing tracking parameters
     *
     * @param string $url The URL to clean
     * @return ClearUrlResult The result containing the cleaned URL and metadata
     */
    public function clean(string $url): ClearUrlResult
    {
        // Fast path: validate URL
        if (empty($url) || !$this->isValidUrl($url)) {
            return new ClearUrlResult($url, false, false, false);
        }

        $originalUrl = $url;
        $wasRedirected = false;
        $wasBlocked = false;
        $wasModified = false;

        // Process ALL matching providers (not just the first one)
        // This allows global rules to work alongside provider-specific rules
        foreach ($this->providers as $provider) {
            if (!$provider->matchesUrl($url)) {
                continue;
            }

            // Check for redirections first (immediate return)
            $redirectUrl = $provider->getRedirection($url);
            if ($redirectUrl !== null) {
                return new ClearUrlResult($redirectUrl, true, false, true);
            }

            // Check for complete provider blocking (immediate return)
            if ($provider->completeProvider) {
                return new ClearUrlResult($url, false, true, false);
            }

            // Apply raw rules (regex replacements on entire URL)
            foreach ($provider->rawRules as $rawRule) {
                $beforeReplace = $url;
                $url = preg_replace($rawRule, '', $url);

                if ($url !== $beforeReplace) {
                    $wasModified = true;
                }
            }

            // Parse URL components
            $parsed = parse_url($url);
            if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
                // Invalid URL after raw rules, return original
                return new ClearUrlResult($originalUrl, false, false, false);
            }

            // Process query parameters and fragments
            $queryParams = [];
            $fragmentParams = [];

            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
            }

            if (isset($parsed['fragment'])) {
                // Check if fragment contains query-like params (e.g., #key=value&key2=value2)
                if (str_contains($parsed['fragment'], '=')) {
                    parse_str($parsed['fragment'], $fragmentParams);
                }
            }

            // Get rules to apply
            $rules = $provider->getAllRules(!$this->allowReferralMarketing);

            // Remove matching fields from query parameters
            $queryParams = $this->removeMatchingFields($queryParams, $rules);

            // Remove matching fields from fragment parameters
            $fragmentParams = $this->removeMatchingFields($fragmentParams, $rules);

            // Rebuild URL
            $url = $this->rebuildUrl($parsed, $queryParams, $fragmentParams);

            if ($url !== $originalUrl) {
                $wasModified = true;
            }

            // Continue to next matching provider (for global rules)
        }

        // Return final result after processing all matching providers
        return new ClearUrlResult($url, $wasModified, $wasBlocked, $wasRedirected);
    }

    /**
     * Remove fields that match any of the rules
     *
     * @param array<string, mixed> $fields
     * @param array<string> $rules Array of compiled regex patterns
     * @return array<string, mixed>
     */
    private function removeMatchingFields(array $fields, array $rules): array
    {
        foreach (array_keys($fields) as $fieldName) {
            foreach ($rules as $rule) {
                // Match the entire field name against the rule
                if (preg_match($rule, (string)$fieldName)) {
                    unset($fields[$fieldName]);
                    break; // Field removed, check next field
                }
            }
        }

        return $fields;
    }

    /**
     * Rebuild URL from parsed components
     *
     * @param array<string, mixed> $parsed Parsed URL components
     * @param array<string, mixed> $queryParams Query parameters
     * @param array<string, mixed> $fragmentParams Fragment parameters
     * @return string
     */
    private function rebuildUrl(array $parsed, array $queryParams, array $fragmentParams): string
    {
        $url = $parsed['scheme'] . '://';

        // Add user/pass if present
        if (isset($parsed['user'])) {
            $url .= $parsed['user'];
            if (isset($parsed['pass'])) {
                $url .= ':' . $parsed['pass'];
            }
            $url .= '@';
        }

        $url .= $parsed['host'];

        // Add port if present and not default
        if (isset($parsed['port'])) {
            $defaultPort = ($parsed['scheme'] === 'https') ? 443 : 80;
            if ($parsed['port'] !== $defaultPort) {
                $url .= ':' . $parsed['port'];
            }
        }

        // Add path
        if (isset($parsed['path'])) {
            $url .= $parsed['path'];
        }

        // Add query string
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        }

        // Add fragment
        if (!empty($fragmentParams)) {
            $url .= '#' . http_build_query($fragmentParams, '', '&', PHP_QUERY_RFC3986);
        } elseif (isset($parsed['fragment']) && !str_contains($parsed['fragment'], '=')) {
            // Preserve non-query-like fragments
            $url .= '#' . $parsed['fragment'];
        }

        return $url;
    }

    /**
     * Basic URL validation
     */
    private function isValidUrl(string $url): bool
    {
        // Quick validation
        if (str_starts_with($url, 'data:') || str_starts_with($url, 'javascript:')) {
            return false;
        }

        return (bool)filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Set whether to allow referral marketing parameters
     */
    public function setAllowReferralMarketing(bool $allow): void
    {
        $this->allowReferralMarketing = $allow;
    }
}
