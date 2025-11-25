# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-25

### Added
- Initial release of ClearUrls PHP library
- Core URL cleaning functionality using ClearURLs ruleset
- Support for 205+ providers with 734+ rules
- Zero dependencies (pure PHP 8.3+ standard library)
- Pre-compiled regex patterns for optimal performance
- Comprehensive test suite
- Performance benchmarks
- Build script to fetch and compile latest rules
- Local caching of rules with `--local` flag support
- Documentation and usage examples

### Features
- Remove tracking parameters from URLs
- Handle URL redirections (extract embedded URLs)
- Support for referral marketing parameters
- Exception handling for specific URL patterns
- Raw rules for complex URL patterns
- Batch processing support
- Immutable operations (original URLs never modified)

### Performance
- Optimized for bulk URL cleaning
- Pre-compiled regex patterns (no runtime compilation)
- Efficient URL parsing and rebuilding
- Minimal memory usage
