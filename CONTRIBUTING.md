# Contributing to Symfony N8n Bundle

Thank you for your interest in contributing to this project! 🎉

## Getting Started

### 1. Development Environment

```bash
# Clone repository
git clone https://github.com/freema/n8n-bundle.git
cd n8n-bundle

# Install Task (https://taskfile.dev)
brew install go-task/tap/go-task

# Initialize development environment
task init

# Run tests
task test
```

### 2. Project Structure

```
src/
├── Contract/           # PHP interfaces
├── Domain/            # Domain objects
├── Service/           # Core services
├── Http/              # HTTP communication
├── Controller/        # Symfony controllers
├── EventListener/     # Event listeners
├── Event/             # Event objects
├── Exception/         # Custom exceptions
├── Command/           # Symfony commands
├── Enum/              # Enumerations (RequestMethod, CommunicationMode)
├── Debug/             # Debug panel for Web Profiler
├── DependencyInjection/  # DI configuration
└── Resources/         # Configuration and templates

dev/                   # Test application
├── Controller/        # Demo controllers
├── Entity/           # Example payload and response entities
└── Service/          # Example response handlers
tests/                 # Unit and integration tests
```

## Types of Contributions

### 🐛 Bug Reports
- Use GitHub Issues
- Include steps to reproduce
- Attach error logs
- Specify PHP and Symfony versions

### 💡 Feature Suggestions
- Open Discussion or Issue
- Describe use case
- Propose API design
- Consider backward compatibility

### 🔧 Pull Requests
- Fork repository
- Create feature branch
- Implement changes
- Write tests
- Update documentation

## Coding Standards

### PHP Standards
- PSR-12 code style
- PHP 8.2+ type hints
- Strict types: `declare(strict_types=1)`
- Readonly properties where possible

### Symfony Conventions
- Symfony best practices
- Service configuration via YAML
- Event-driven architecture
- Proper DI container usage

### Naming Conventions
- Interfaces: `N8nPayloadInterface`
- Exceptions: `N8nCommunicationException`
- Services: `N8nClient`, `RequestTracker`
- Events: `N8nRequestSentEvent`

## Testing

### Running Tests
```bash
# All tests
task test

# Unit tests only
task test:unit

# Code quality
task stan           # PHPStan
task cs:fix         # PHP-CS-Fixer
```

### Test Coverage
- Unit tests for all services
- Integration tests for Symfony compatibility
- Mock objects for HTTP communication
- Test scenarios for all communication modes

### Test Data
```php
// Use factory pattern for test data
class N8nTestDataFactory
{
    public static function createForumPost(): ForumPost
    {
        return new ForumPost(
            id: 1,
            content: 'Test content',
            authorId: 123,
            createdAt: new \DateTimeImmutable(),
            threadId: 'thread-456'
        );
    }
}
```

## Documentation

### Updating Documentation
- README.md for main features
- VERSIONS.md for changelog
- PHPDoc for all public methods
- Usage examples in `/dev` application

### Changelog
- Use [Keep a Changelog](https://keepachangelog.com/)
- Categories: Added, Changed, Deprecated, Removed, Fixed, Security
- Link to GitHub Issues/PRs

## Pull Request Process

### 1. Preparation
```bash
# Create feature branch
git checkout -b feature/amazing-feature

# Implement changes
# ...

# Run tests
task test
task stan
task cs:fix
```

### 2. Commit Messages
```
feat: add support for batch operations

- Implement BatchN8nClient for bulk operations
- Add configuration for batch size limits
- Update documentation with batch examples

Fixes #123
```

### 3. PR Checklist
- [ ] Tests pass
- [ ] Documentation is updated
- [ ] Changelog is updated
- [ ] Backward compatibility is maintained
- [ ] Code review is performed

### 4. Review Process
- Minimum 1 approval from maintainer
- All CI checks must pass
- Discussion about implementation
- Possible adjustments based on feedback

## Architecture and Design

### Principles
- **Type Safety**: All parameters typed
- **Separation of Concerns**: Each class has one responsibility
- **Dependency Injection**: Everything through DI container
- **Event-Driven**: Communication through events
- **Testability**: All dependencies mockable

### Design Patterns
- Repository pattern for data storage
- Factory pattern for object creation
- Strategy pattern for different communication modes
- Observer pattern for monitoring
- Circuit breaker for error handling

### Extensibility
```php
// New payload types with all capabilities
interface N8nPayloadInterface extends N8nResponseMappableInterface
{
    public function toN8nPayload(): array;
    public function getN8nContext(): array;
    
    // Optional: HTTP method and content type
    public function getN8nRequestMethod(): RequestMethod;
    
    // Optional: custom response handler
    public function getN8nResponseHandler(): ?N8nResponseHandlerInterface;
    
    // Optional: response entity mapping
    public function getN8nResponseClass(): ?string;
}

// New response handlers
interface N8nResponseHandlerInterface
{
    public function handleN8nResponse(array $responseData, string $requestUuid): void;
    public function getHandlerId(): string;
}

// Response entity for type-safe data handling
class CustomResponse
{
    public function __construct(
        public readonly string $status,
        public readonly array $data,
        public readonly ?string $message = null
    ) {}
}
```

## Debugging

### Development Tools
```bash
# Symfony Web Profiler with N8n debug panel
task up
task serve
# http://localhost:8080/_profiler

# N8n communication debugging
task test:health    # Health check

# Code quality
task stan          # PHPStan analysis
task cs            # Check code style
task cs:fix        # Fix code style
```

### Debug Panel
Bundle includes custom panel in Symfony Web Profiler that shows:
- All N8n requests with UUID, duration and status
- Request/response payload data
- Mapped response objects
- Errors and their stack traces
- Performance metrics

To enable debug panel:
```yaml
# config/packages/n8n.yaml
n8n:
  debug:
    enabled: true  # or null for auto-detection
    log_requests: true
```

### Logging
- All N8n operations are logged
- Use structured logging
- Different log levels by severity
- Separate log file for N8n operations

## Security

### Reporting
- Report security issues directly to maintainers
- Private discussion of security issues
- Responsible disclosure

### Best Practices
- Validate all inputs
- Sanitize outputs
- Secure defaults
- No secrets in code

## Community

### Communication
- GitHub Issues for bug reports
- GitHub Discussions for suggestions
- Pull Request reviews
- Slack/Discord TBD

### Code of Conduct
- Be respectful to others
- Constructive feedback
- Inclusion of all contributors
- Professional communication

## Release Process

### Semantic Versioning
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes

### Release Process
1. Update VERSIONS.md
2. Testing on all supported versions
3. Documentation review
4. Git tag creation
5. Packagist publication

## Questions?

- Open GitHub Issue with `question` label
- Check existing Issues and Discussions
- Contact maintainers directly for urgent matters

---

**Thanks for your contribution! 🚀**