# Changelog

Všechny významné změny v tomto projektu budou zdokumentovány v tomto souboru.

Formát vychází z [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
a tento projekt dodržuje [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Základní implementace N8n Bundle pro Symfony
- Type-safe komunikace pomocí PHP interfaces (`N8nPayloadInterface`, `N8nResponseHandlerInterface`)
- Tři komunikační módy: Fire & Forget, Async s callbackem, Sync
- UUID tracking systém pro párování request/response
- Robustní error handling s retry mechanikem a circuit breaker
- Event-driven architektura pro monitoring a logging
- Multi-instance podpora pro různá prostředí (dev/staging/prod)
- Dry run mode pro testování bez skutečného odeslání
- Symfony Web Profiler integrace
- Docker vývojové prostředí s Taskfile.yml
- Kompletní testovací aplikace s demo endpointy
- Automatické logování všech N8n operací
- Callback controller pro příjem odpovědí z N8n
- Cleanup příkaz pro vyčištění starých requestů
- Konfigurace přes Symfony config s validací

### Technical Details
- PHP 8.1+ podpora
- Symfony 6.4+ a 7.0+ kompatibilita
- PSR-4 autoloading
- Kompletní DI container integrace
- Event subscriber pro automatické logování
- HTTP client s konfigurovatelné timeout a retry
- Circuit breaker pattern pro ochranu před přetížením

## [1.0.0] - TBD

### Added
- První stabilní verze N8n Bundle
- Kompletní dokumentace a příklady použití
- Testovací pokrytí pro všechny hlavní komponenty
- Production-ready konfigurace

---

## Formát změn

- **Added** - nové funkce
- **Changed** - změny v existujících funkcích
- **Deprecated** - funkce, které budou odstraněny
- **Removed** - odstraněné funkce
- **Fixed** - opravy chyb
- **Security** - bezpečnostní opravy

## Kompatibilita

### Symfony verze
- ✅ Symfony 6.4.x
- ✅ Symfony 7.0.x
- ✅ Symfony 7.1.x (plánováno)

### PHP verze
- ✅ PHP 8.1
- ✅ PHP 8.2
- ✅ PHP 8.3
- 🔄 PHP 8.4 (v testování)

### N8n verze
- ✅ N8n 1.0+
- ✅ N8n Cloud
- ✅ Self-hosted N8n

## Migrace

### Z verze 0.x na 1.x
TBD - bude doplněno při vydání první stable verze

## Plánované funkce

### v1.1.0
- [ ] Batch operace pro hromadné odesílání
- [ ] Metriky a monitoring integrace (Prometheus)
- [ ] Webhook signature verification
- [ ] Rozšířené retry strategie (exponential backoff)

### v1.2.0
- [ ] N8n REST API integrace (kromě webhooků)
- [ ] Workflow management funkce
- [ ] Caching layer pro často používané requesty
- [ ] Rate limiting podpora

### v2.0.0
- [ ] Async/await pattern s ReactPHP
- [ ] Symfony Messenger integrace
- [ ] GraphQL endpoint podpora
- [ ] Advanced security features