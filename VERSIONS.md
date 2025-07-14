# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- ✅ Symfony 6.4.x
- ✅ Symfony 7.0.x
- ✅ Symfony 7.1.x (planned)

### PHP versions
- ✅ PHP 8.2
- ✅ PHP 8.3
- 🔄 PHP 8.4 (in testing)

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
