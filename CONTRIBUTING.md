# Přispívání do Symfony N8n Bundle

Děkujeme za váš zájem o přispívání do tohoto projektu! 🎉

## Jak začít

### 1. Vývojové prostředí

```bash
# Klonování repozitáře
git clone https://github.com/freema/n8n-bundle.git
cd n8n-bundle

# Instalace Task (https://taskfile.dev)
brew install go-task/tap/go-task

# Inicializace vývojového prostředí
task init

# Spuštění testů
task test
```

### 2. Struktura projektu

```
src/
├── Contract/           # PHP interfaces
├── Domain/            # Domain objekty
├── Service/           # Core služby
├── Http/              # HTTP komunikace
├── Controller/        # Symfony kontrolery
├── EventListener/     # Event listenery
├── Event/             # Event objekty
├── Exception/         # Custom exceptions
├── Command/           # Symfony příkazy
├── Enum/              # Enumerations (RequestMethod, CommunicationMode)
├── Debug/             # Debug panel pro Web Profiler
├── DependencyInjection/  # DI konfigurace
└── Resources/         # Konfigurace a templates

dev/                   # Testovací aplikace
├── Controller/        # Demo kontrolery
├── Entity/           # Příklady payload a response entit
└── Service/          # Příklady response handlerů
tests/                 # Unit a integration testy
```

## Typy příspěvků

### 🐛 Bug reporty
- Použijte GitHub Issues
- Uveďte kroky k reprodukci
- Přiložte error logy
- Specifikujte verzi PHP a Symfony

### 💡 Návrhy na vylepšení
- Otevřete Discussion nebo Issue
- Popište use case
- Navrhněte API design
- Zvažte backward compatibility

### 🔧 Pull requesty
- Forkněte repozitář
- Vytvořte feature branch
- Implementujte změny
- Napište testy
- Aktualizujte dokumentaci

## Coding Standards

### PHP Standards
- PSR-12 code style
- PHP 8.1+ type hints
- Strict types: `declare(strict_types=1)`
- Readonly properties kde je to možné

### Symfony Conventions
- Symfony best practices
- Service configuration přes YAML
- Event-driven architecture
- Proper DI container usage

### Naming Conventions
- Interfaces: `N8nPayloadInterface`
- Exceptions: `N8nCommunicationException`
- Services: `N8nClient`, `RequestTracker`
- Events: `N8nRequestSentEvent`

## Testování

### Spuštění testů
```bash
# Všechny testy
task test

# Specifické Symfony verze
task test:symfony64
task test:symfony70
task test:all

# Code quality
task stan           # PHPStan
task cs:fix         # PHP-CS-Fixer
```

### Testovací pokrytí
- Unit testy pro všechny služby
- Integration testy pro Symfony kompatibilitu
- Mock objekty pro HTTP komunikaci
- Testovací scénáře pro všechny komunikační módy

### Testovací data
```php
// Použijte factory pattern pro test data
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

## Dokumentace

### Aktualizace dokumentace
- README.md pro hlavní funkce
- VERSIONS.md pro changelog
- PHPDoc pro všechny public metody
- Příklady použití v `/dev` aplikaci

### Changelog
- Používejte [Keep a Changelog](https://keepachangelog.com/)
- Kategorie: Added, Changed, Deprecated, Removed, Fixed, Security
- Linkujte na GitHub Issues/PRs

## Pull Request Process

### 1. Příprava
```bash
# Vytvořte feature branch
git checkout -b feature/amazing-feature

# Implementujte změny
# ...

# Spusťte testy
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
- [ ] Testy projdou
- [ ] Dokumentace je aktualizována
- [ ] Changelog je doplněn
- [ ] Backward compatibility je zachována
- [ ] Code review je proveden

### 4. Review Process
- Minimálně 1 approve od maintainera
- Všechny CI checks musí projít
- Diskuze o implementaci
- Případné úpravy dle feedback

## Architektura a Design

### Principy
- **Type Safety**: Všechny parametry typovány
- **Separation of Concerns**: Každá třída má jednu odpovědnost
- **Dependency Injection**: Vše přes DI container
- **Event-Driven**: Komunikace přes eventy
- **Testability**: Všechny závislosti mockable

### Návrhové vzory
- Repository pattern pro data storage
- Factory pattern pro vytváření objektů
- Strategy pattern pro různé komunikační módy
- Observer pattern pro monitoring
- Circuit breaker pro error handling

### Rozšiřitelnost
```php
// Nové payload typy s všemi možnostmi
interface N8nPayloadInterface extends N8nResponseMappableInterface
{
    public function toN8nPayload(): array;
    public function getN8nContext(): array;
    
    // Volitelné: HTTP metoda a content type
    public function getN8nRequestMethod(): RequestMethod;
    
    // Volitelné: vlastní response handler
    public function getN8nResponseHandler(): ?N8nResponseHandlerInterface;
    
    // Volitelné: response entity mapping
    public function getN8nResponseClass(): ?string;
}

// Nové response handlery
interface N8nResponseHandlerInterface
{
    public function handleN8nResponse(array $responseData, string $requestUuid): void;
    public function getHandlerId(): string;
}

// Response entity pro type-safe práci s daty
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

### Vývojové nástroje
```bash
# Symfony Web Profiler s N8n debug panelem
task up
task dev:serve
# http://localhost:8080/_profiler

# N8n komunikace debugging
task n8n:ff        # Test fire & forget
task n8n:cb        # Test callback
task n8n:health    # Health check

# Code quality
task stan          # PHPStan analýza
task cs            # Check code style
task cs:fix        # Fix code style
```

### Debug panel
Bundle obsahuje vlastní panel v Symfony Web Profiler který zobrazuje:
- Všechny N8n requesty s UUID, duration a statusem
- Request/response payload data
- Mapped response objekty
- Chyby a jejich stack traces
- Performance metriky

Pro povolení debug panelu:
```yaml
# config/packages/n8n.yaml
n8n:
  debug:
    enabled: true  # nebo null pro auto-detekci
    collect_requests: true
    log_requests: true
```

### Logging
- Všechny N8n operace jsou logovány
- Používejte strukturované logování
- Různé log levels podle závažnosti
- Separátní log file pro N8n operace

## Bezpečnost

### Reporting
- Bezpečnostní chyby hlašte přímo maintainerům
- Neveřejné diskuze o security issues
- Zodpovědné disclosure

### Best Practices
- Validace všech vstupů
- Sanitizace výstupů
- Secure defaults
- Žádné secrets v kódu

## Komunita

### Komunikace
- GitHub Issues pro bug reporty
- GitHub Discussions pro návrhy
- Pull Request reviews
- Slack/Discord TBD

### Code of Conduct
- Buďte respektující k ostatním
- Konstruktivní feedback
- Inkluze všech přispěvatelů
- Profesionální komunikace

## Vydávání verzí

### Semantic Versioning
- MAJOR: Breaking changes
- MINOR: Nové funkce (backward compatible)
- PATCH: Bug fixes

### Release Process
1. Aktualizace VERSIONS.md
2. Testing na všech podporovaných verzích
3. Documentation review
4. Git tag vytvoření
5. Packagist publikace

## Otázky?

- Otevřete GitHub Issue s labelem `question`
- Checkněte existující Issues a Discussions
- Kontaktujte maintainery přímo pro urgentní věci

---

**Díky za váš příspěvek! 🚀**