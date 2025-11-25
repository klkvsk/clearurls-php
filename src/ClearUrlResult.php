<?php

declare(strict_types=1);

namespace ClearUrls;

/**
 * Result of URL cleaning operation
 */
readonly class ClearUrlResult
{
    /**
     * @param string $url The cleaned URL
     * @param bool $wasModified Whether the URL was modified
     * @param bool $wasBlocked Whether the URL was blocked (completeProvider)
     * @param bool $wasRedirected Whether the URL was redirected to an embedded URL
     */
    public function __construct(
        public string $url,
        public bool $wasModified,
        public bool $wasBlocked,
        public bool $wasRedirected
    ) {}

    /**
     * Check if any action was taken on the URL
     */
    public function hadAnyAction(): bool
    {
        return $this->wasModified || $this->wasBlocked || $this->wasRedirected;
    }
}
