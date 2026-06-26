# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.1] - 2026-06-26

### Added
- **Documentation**: new "Error Handling" section covering the exception hierarchy, retry behavior, circuit breaker and test webhooks; documented the `N8nResponse` object accessors.

### Fixed
- **Documentation**: corrected the `send()` / `sendSync()` return type in the README — they return an `N8nResponse` object, not an array.

### Changed
- **Package metadata**: fixed `homepage` to the current repository URL, added a `support` section, and corrected the author name in `composer.json`.

## [2.0.0] - 2026-06-26

### Added
- **Symfony 8.0 Support**: Full compatibility with Symfony 8.0
- **PHP 8.4 Support**: Tested and verified with PHP 8.4
- **Enhanced CI/CD Pipeline**: Matrix testing across all supported PHP and Symfony versions
- **Full RequestMethod Support**: HTTP client now respects `getN8nRequestMethod()` from payload (GET, POST_JSON, POST_FORM, PUT, PATCH)
- **Test Webhook Support**: New `use_test_webhook` option targets n8n test webhooks (`/webhook-test/`), allowing use of unpublished workflows during development

### Changed
- **Minimum PHP Version**: Now requires PHP 8.2+ (previously 8.1)
- **Route Attribute Namespace**: Updated from deprecated `Annotation\Route` to `Attribute\Route`
- **PHPUnit Configuration**: Updated for PHPUnit 10/11 compatibility
- **Stricter Deprecation Testing**: Enhanced deprecation detection in tests
- **N8nRequest**: Added `requestMethod` property for proper HTTP method handling

### Fixed
- **Memory Leak in RequestTracker**: Fixed missing `completeRequest()` call on successful `send()` - requests were never removed from tracker causing memory growth
- **RequestMethod Ignored**: `N8nPayloadInterface::getN8nRequestMethod()` is now properly used by `N8nHttpClient` to set HTTP method and Content-Type
- **Circuit Breaker Missed Transport Failures**: `N8nClient::send()` now records a failure for transport-level errors (timeouts, connection failures), not only HTTP error statuses, so the breaker can actually open on outages
- **Hardcoded Callback Route**: `sendWithCallback()` now uses the configured `callback.route_name` instead of a hardcoded `n8n_callback`
- **Double Slash in URLs**: `getWebhookUrl()` and `healthCheck()` now trim trailing slashes from `base_url`
- **Retries Never Triggered on Network Failures**: the HTTP response is now materialized inside `N8nHttpClient`, so the retry handler actually re-attempts transport errors, timeouts and HTTP 5xx (previously the lazy response made the retry loop succeed on the first attempt)
- **Untyped Transport Errors**: transport/timeout failures are now wrapped in `N8nCommunicationException` / `N8nTimeoutException` (typed via Symfony's `TransportExceptionInterface` / `TimeoutExceptionInterface`) instead of leaking raw Symfony exceptions; timeout detection no longer relies on string matching

### Breaking Changes
- PHP 8.1 is no longer supported
- Symfony 8.0 requires PHP 8.4+ (Symfony framework requirement)
- `N8nHttpClient::sendWebhook()` now returns a materialized `Freema\N8nBundle\Dto\N8nHttpResult` instead of a lazy `Symfony\...\ResponseInterface` (internal collaborator; affects code that called `N8nHttpClient` directly)

## [1.1.0] - 2025-07-14

### Added
- **N8nResponse DTO**: New response object for type-safe response handling
- **HTTP Proxy Support**: Enterprise-ready proxy configuration with environment variable support
- **Enhanced Service Configuration**: Improved DI container integration with proper interface aliases
- **Type-Safe Response Handling**: Replaced array returns with proper N8nResponse objects

### Changed
- **N8nClient Interface**: Updated to return N8nResponse objects instead of arrays
- **Response Processing**: Enhanced response handling with `isSuccess()`, `getUuid()`, `getResponse()` methods
- **Configuration Structure**: Added proxy configuration options to bundle configuration
- **Health Check**: Enhanced with proxy support for enterprise environments

### Fixed
- **DemoController**: Fixed array access error when using N8nResponse objects
- **Type Safety**: Improved type declarations throughout the codebase
- **Error Handling**: Enhanced exception management and error reporting

### Technical Improvements
- Better encapsulation with proper response object methods
- Consistent code formatting across the entire codebase
- Enhanced type declarations (`array|object|null` for mapped responses)
- Improved service definitions and DI integration

## [1.0.0] - 2025-07-13

### Added
- Core N8n Bundle implementation for Symfony
- Type-safe communication using PHP interfaces (`N8nPayloadInterface`, `N8nResponseHandlerInterface`)
- Three communication modes: Fire & Forget, Async with callback, Sync
- UUID tracking system for request/response pairing
- Robust error handling with retry mechanism and circuit breaker
- Event-driven architecture for monitoring and logging
- Multi-instance support for different environments (dev/staging/prod)
- Dry run mode for testing without actual sending
- Symfony Web Profiler integration with debug panel
- Docker development environment with Taskfile.yml
- Complete test application with demo endpoints
- Automatic logging of all N8n operations
- Callback controller for receiving responses from N8n
- Cleanup command for removing old requests
- Configuration through Symfony config with validation
- Optional response entity mapping for type-safe handling
- Environment variable support for sensitive data

### Technical Details
- PHP 8.2+ support
- Symfony 6.4+ and 7.0+ compatibility
- PSR-4 autoloading
- Complete DI container integration
- Event subscriber for automatic logging
- HTTP client with configurable timeout and retry
- Circuit breaker pattern for protection against overload
- PHPStan static analysis integration
- PHP CS Fixer code style enforcement
- PHPUnit testing framework with coverage reports
- GitHub Actions CI/CD pipeline

---

## Change Format

- **Added** - new features
- **Changed** - changes in existing functionality
- **Deprecated** - features that will be removed
- **Removed** - removed features
- **Fixed** - bug fixes
- **Security** - security fixes

## Compatibility

### Symfony versions
- ✅ Symfony 6.4.x (LTS) - PHP 8.2+
- ✅ Symfony 7.0.x - PHP 8.2+
- ✅ Symfony 7.1.x - PHP 8.2+
- ✅ Symfony 8.0.x - PHP 8.4+ (Symfony requirement)

### PHP versions
- ✅ PHP 8.2 (Symfony 6.4, 7.x)
- ✅ PHP 8.3 (Symfony 6.4, 7.x)
- ✅ PHP 8.4 (all Symfony versions including 8.0)

### N8n versions
- ✅ N8n 1.0+
- ✅ N8n Cloud
- ✅ Self-hosted N8n

## Migration

### From version 0.x to 1.x
This is the first stable release - no migration needed.

## Planned Features

### v1.2.0
- [ ] Batch operations for bulk sending
- [ ] Metrics and monitoring integration (Prometheus)
- [ ] Webhook signature verification
- [ ] Enhanced retry strategies (exponential backoff)

### v1.3.0
- [ ] N8n REST API integration (beyond webhooks)
- [ ] Workflow management features
- [ ] Caching layer for frequently used requests
- [ ] Rate limiting support
