<?php

declare(strict_types=1);

namespace ClearUrls;

/**
 * Provider represents a URL cleaning rule provider (e.g., Google, Amazon, etc.)
 * Optimized for speed with pre-compiled regex patterns
 */
readonly class Provider
{
    /**
     * @param string $name Provider name
     * @param string $urlPattern Regex pattern to match URLs (compiled)
     * @param bool $completeProvider If true, block entire provider
     * @param array<string> $rules Query/fragment parameter patterns to remove (compiled regexes)
     * @param array<string> $rawRules Raw URL patterns to remove (compiled regexes)
     * @param array<string> $referralMarketing Referral marketing patterns (compiled regexes)
     * @param array<string> $exceptions URL patterns to skip (compiled regexes)
     * @param array<string> $redirections URL redirection patterns (compiled regexes with capture groups)
     * @param bool $forceRedirection Whether redirects should be forced
     */
    public function __construct(
        public string $name,
        public string $urlPattern,
        public bool $completeProvider,
        public array $rules,
        public array $rawRules,
        public array $referralMarketing,
        public array $exceptions,
        public array $redirections,
        public bool $forceRedirection
    ) {}

    /**
     * Check if URL matches this provider's pattern and not in exceptions
     */
    public function matchesUrl(string $url): bool
    {
        // Fast path: check pattern match first
        if (!preg_match($this->urlPattern, $url)) {
            return false;
        }

        // Check exceptions
        foreach ($this->exceptions as $exception) {
            if (preg_match($exception, $url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if URL matches a redirection pattern and extract the target URL
     * Returns the decoded target URL or null if no match
     */
    public function getRedirection(string $url): ?string
    {
        foreach ($this->redirections as $redirection) {
            if (preg_match($redirection, $url, $matches)) {
                // First capture group contains the target URL
                if (isset($matches[1])) {
                    return urldecode($matches[1]);
                }
            }
        }

        return null;
    }

    /**
     * Get all rules including referral marketing rules if needed
     *
     * @param bool $includeReferralMarketing Whether to include referral marketing rules
     * @return array<string>
     */
    public function getAllRules(bool $includeReferralMarketing = false): array
    {
        if ($includeReferralMarketing) {
            return array_merge($this->rules, $this->referralMarketing);
        }

        return $this->rules;
    }
}
