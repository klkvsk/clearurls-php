<?php

declare(strict_types=1);

/**
 * Build script to compile ClearURLs rules to optimized PHP code
 *
 * This script:
 * 1. Fetches the latest rules from https://rules2.clearurls.xyz/data.minify.json (or uses local cache)
 * 2. Compiles all regex patterns for maximum performance
 * 3. Generates src/Rules.php with pre-compiled Provider instances
 *
 * Usage:
 *   php build/compile-rules.php           # Fetch from remote, save locally
 *   php build/compile-rules.php --local   # Use local cache only, skip fetching
 */

const RULES_URL = 'https://rules2.clearurls.xyz/data.minify.json';
const LOCAL_RULES_FILE = __DIR__ . '/../build/data.min.json';
const META_FILE = __DIR__ . '/../build/data.meta.json';
const OUTPUT_FILE = __DIR__ . '/../src/Rules.php';

// Parse command line arguments
$useLocalOnly = in_array('--local', $argv ?? []);

echo "ClearUrls PHP Rules Compiler\n";
echo "============================\n\n";

// Fetch or load rules
$rulesJson = null;

if ($useLocalOnly) {
    echo "Using --local flag: Loading from local cache only\n";
    if (file_exists(LOCAL_RULES_FILE)) {
        echo "Loading local rules from " . LOCAL_RULES_FILE . "...\n";
        $rulesJson = file_get_contents(LOCAL_RULES_FILE);
        echo "Local rules loaded successfully (" . strlen($rulesJson) . " bytes)\n";
    } else {
        die("ERROR: Local rules file not found at " . LOCAL_RULES_FILE . "\n" .
            "       Please run without --local flag first to download rules.\n");
    }
} else {
    // Try to fetch from remote
    echo "Fetching rules from " . RULES_URL . "...\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: ClearUrls-PHP-Builder/1.0',
            'timeout' => 30,
        ],
    ]);

    $rulesJson = @file_get_contents(RULES_URL, false, $context);

    if ($rulesJson === false) {
        echo "WARNING: Failed to fetch rules from remote.\n";

        // Try local fallback
        if (file_exists(LOCAL_RULES_FILE)) {
            echo "Using local fallback: " . LOCAL_RULES_FILE . "\n";
            $rulesJson = file_get_contents(LOCAL_RULES_FILE);
            echo "Local fallback loaded successfully (" . strlen($rulesJson) . " bytes)\n";
        } else {
            die("ERROR: Could not fetch rules from remote and no local cache found.\n" .
                "       Please check your internet connection or provide a local rules file.\n");
        }
    } else {
        echo "Rules fetched successfully (" . strlen($rulesJson) . " bytes)\n";

        // Calculate hash of fetched rules
        $newHash = hash('sha256', $rulesJson);
        echo "Rules hash: " . substr($newHash, 0, 16) . "...\n";

        // Load existing metadata to check if rules changed
        $existingMetadata = null;
        $rulesChanged = true;

        if (file_exists(META_FILE)) {
            $existingMetadata = json_decode(file_get_contents(META_FILE), true);
            $oldHash = $existingMetadata['hash'] ?? '';

            if ($oldHash === $newHash) {
                echo "✓ Rules hash unchanged - no update needed\n";
                $rulesChanged = false;
            } else {
                echo "✓ Rules hash changed - updating metadata\n";
            }
        } else {
            echo "✓ No previous metadata found - creating new\n";
        }

        // Save to local cache
        echo "Saving to local cache: " . LOCAL_RULES_FILE . "...\n";
        $dir = dirname(LOCAL_RULES_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(LOCAL_RULES_FILE, $rulesJson);
        echo "Local cache saved successfully\n";

        // Save metadata - only update updatedAt if rules changed
        if ($rulesChanged) {
            $metadata = [
                'updatedAt' => date('Y-m-d H:i:s'),
                'fetchedFrom' => RULES_URL,
                'hash' => $newHash,
            ];
            file_put_contents(META_FILE, json_encode($metadata, JSON_PRETTY_PRINT));
            echo "Metadata saved to " . META_FILE . " (updatedAt updated)\n";
        } else {
            // Keep existing updatedAt but update hash and fetchedFrom
            $metadata = [
                'updatedAt' => $existingMetadata['updatedAt'] ?? date('Y-m-d H:i:s'),
                'fetchedFrom' => RULES_URL,
                'hash' => $newHash,
            ];
            file_put_contents(META_FILE, json_encode($metadata, JSON_PRETTY_PRINT));
            echo "Metadata saved to " . META_FILE . " (updatedAt preserved)\n";
        }
    }
}

// Parse JSON
echo "Parsing rules JSON...\n";
$rulesData = json_decode($rulesJson, true);

if ($rulesData === null) {
    die("ERROR: Failed to parse JSON: " . json_last_error_msg() . "\n");
}

if (!isset($rulesData['providers']) || !is_array($rulesData['providers'])) {
    die("ERROR: Invalid rules format - missing 'providers' key\n");
}

$providers = $rulesData['providers'];
echo "Found " . count($providers) . " providers\n";

// Compile providers
echo "Compiling providers...\n";

$compiledProviders = [];
$totalRules = 0;

foreach ($providers as $name => $providerData) {
    try {
        $compiled = compileProvider($name, $providerData);
        $compiledProviders[] = $compiled;
        $totalRules += count($providerData['rules'] ?? []) + count($providerData['rawRules'] ?? []);
    } catch (Exception $e) {
        echo "WARNING: Failed to compile provider '$name': " . $e->getMessage() . "\n";
    }
}

echo "Compiled " . count($compiledProviders) . " providers with $totalRules rules\n";

// Generate PHP code
echo "Generating PHP code...\n";
$phpCode = generatePhpCode($compiledProviders);

// Write to file
echo "Writing to " . OUTPUT_FILE . "...\n";
file_put_contents(OUTPUT_FILE, $phpCode);

echo "\n✓ Build complete!\n";
echo "Generated file: " . OUTPUT_FILE . "\n";
echo "File size: " . number_format(filesize(OUTPUT_FILE)) . " bytes\n";
if (!$useLocalOnly) {
    echo "Local cache: " . LOCAL_RULES_FILE . "\n";
}

/**
 * Compile a single provider
 */
function compileProvider(string $name, array $data): array
{
    // Validate and get required fields
    $urlPattern = $data['urlPattern'] ?? '';
    if (empty($urlPattern)) {
        throw new Exception("Missing urlPattern");
    }

    $completeProvider = $data['completeProvider'] ?? false;
    $forceRedirection = $data['forceRedirection'] ?? false;

    // Compile URL pattern
    $compiledUrlPattern = compileRegex($urlPattern, 'i');

    // Compile rules (query/fragment parameters)
    $rules = $data['rules'] ?? [];
    $compiledRules = [];
    foreach ($rules as $rule) {
        // Rules are automatically wrapped to match full field names
        $compiledRules[] = compileRegex('^' . $rule . '$', 'i');
    }

    // Compile raw rules (applied to entire URL)
    $rawRules = $data['rawRules'] ?? [];
    $compiledRawRules = [];
    foreach ($rawRules as $rawRule) {
        $compiledRawRules[] = compileRegex($rawRule, 'gi');
    }

    // Compile referral marketing rules
    $referralMarketing = $data['referralMarketing'] ?? [];
    $compiledReferralMarketing = [];
    foreach ($referralMarketing as $rule) {
        $compiledReferralMarketing[] = compileRegex('^' . $rule . '$', 'i');
    }

    // Compile exceptions
    $exceptions = $data['exceptions'] ?? [];
    $compiledExceptions = [];
    foreach ($exceptions as $exception) {
        $compiledExceptions[] = compileRegex($exception, 'i');
    }

    // Compile redirections
    $redirections = $data['redirections'] ?? [];
    $compiledRedirections = [];
    foreach ($redirections as $redirection) {
        $compiledRedirections[] = compileRegex($redirection, 'i');
    }

    return [
        'name' => $name,
        'urlPattern' => $compiledUrlPattern,
        'completeProvider' => $completeProvider,
        'rules' => $compiledRules,
        'rawRules' => $compiledRawRules,
        'referralMarketing' => $compiledReferralMarketing,
        'exceptions' => $compiledExceptions,
        'redirections' => $compiledRedirections,
        'forceRedirection' => $forceRedirection,
    ];
}

/**
 * Compile a regex pattern to PHP PCRE format with flags
 *
 * @param string $pattern The regex pattern
 * @param string $flags Flags: 'i' for case-insensitive, 'gi' for global+case-insensitive
 * @return string PHP PCRE regex with delimiters and flags
 */
function compileRegex(string $pattern, string $flags = ''): string
{
    // Convert JavaScript regex to PHP PCRE
    // Use '#' as delimiter to avoid conflicts with common URL characters

    $pcreFlags = '';
    if (str_contains($flags, 'i')) {
        $pcreFlags .= 'i';
    }
    // Note: 'g' flag is handled by preg_replace vs preg_match behavior in PHP

    // Escape the delimiter in the pattern
    $pattern = str_replace('#', '\\#', $pattern);

    return '#' . $pattern . '#' . $pcreFlags;
}

/**
 * Generate the PHP code for Rules.php
 */
function generatePhpCode(array $providers): string
{
    $date = date('Y-m-d H:i:s');
    $count = count($providers);

    $code = <<<PHP
<?php

declare(strict_types=1);

namespace ClearUrls;

/**
 * Auto-generated compiled rules
 * Generated: $date
 * Providers: $count
 *
 * DO NOT EDIT THIS FILE MANUALLY
 * Run: php build/compile-rules.php to regenerate
 */
class Rules
{
    /**
     * Get all compiled rule sets
     *
     * @return array<Provider>
     */
    public static function getProviders(): array
    {
        return [

PHP;

    foreach ($providers as $provider) {
        $code .= generateProviderCode($provider);
    }

    $code .= <<<'PHP'
        ];
    }
}

PHP;

    return $code;
}

/**
 * Generate PHP code for a single provider
 */
function generateProviderCode(array $provider): string
{
    $name = var_export($provider['name'], true);
    $urlPattern = var_export($provider['urlPattern'], true);
    $completeProvider = $provider['completeProvider'] ? 'true' : 'false';
    $forceRedirection = $provider['forceRedirection'] ? 'true' : 'false';

    $rules = exportArray($provider['rules']);
    $rawRules = exportArray($provider['rawRules']);
    $referralMarketing = exportArray($provider['referralMarketing']);
    $exceptions = exportArray($provider['exceptions']);
    $redirections = exportArray($provider['redirections']);

    return <<<PHP
            new Provider(
                name: $name,
                urlPattern: $urlPattern,
                completeProvider: $completeProvider,
                rules: $rules,
                rawRules: $rawRules,
                referralMarketing: $referralMarketing,
                exceptions: $exceptions,
                redirections: $redirections,
                forceRedirection: $forceRedirection
            ),

PHP;
}

/**
 * Export array with nice formatting
 */
function exportArray(array $arr): string
{
    if (empty($arr)) {
        return '[]';
    }

    $items = array_map(fn($item) => var_export($item, true), $arr);

    if (count($items) <= 3 && max(array_map('strlen', $items)) < 50) {
        // Short arrays on one line
        return '[' . implode(', ', $items) . ']';
    }

    // Long arrays with one item per line
    $indented = array_map(fn($item) => "                    $item", $items);
    return "[\n" . implode(",\n", $indented) . "\n                ]";
}
