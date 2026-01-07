# 🐛 BaddyBugs PHP Agent

**L'agent d'observabilité complet pour Laravel** - Collecte automatiquement toutes les métriques, erreurs et traces de votre application.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

---

## 📋 Table des Matières

- [Fonctionnalités](#-fonctionnalités)
- [Installation](#-installation)
- [Configuration Rapide](#-configuration-rapide)
- [Données Collectées](#-données-collectées)
- [Configuration Avancée](#-configuration-avancée)
- [Lifecycle HTTP](#-lifecycle-http-complet)
- [Intégrations](#-intégrations)
- [API Manuelle](#-api-manuelle)
- [Performance](#-performance)
- [Dépannage](#-dépannage)

---

## ✨ Fonctionnalités

### 🔍 Observabilité Complète
- **42 collectors** couvrant tous les aspects de Laravel
- **Lifecycle HTTP complet** : Bootstrap → Middleware → Controller → Response
- **Distributed tracing** avec propagation des trace IDs
- **Timeline waterfall** pour visualiser chaque requête

### 🚨 Détection Automatique
- **Exceptions** (gérées et non gérées)
- **Requêtes SQL lentes** et problèmes N+1
- **Menaces de sécurité** (SQL injection, XSS, path traversal)
- **Fuites mémoire** et usage excessif
- **Jobs bloqués** et échecs de queue

### 📊 Analytics Produit
- **Feature tracking** automatique par route
- **Session analytics** (durée, bounce rate)
- **Form analytics** (erreurs de validation, types de formulaires)
- **Authentication events** (login, 2FA, lockouts)

### 🔒 Sécurité & Confidentialité
- **Redaction automatique** des données sensibles
- **Scrubbing PII** (mots de passe, cartes bancaires, tokens)
- **Mode GDPR** pour anonymisation complète
- **Sampling configurable** par collector

---

## 📦 Installation

### Prérequis
- PHP 8.2+
- Laravel 10.x, 11.x ou 12.x
- Extension JSON activée

### Via Composer

```bash
composer require baddybugs/agent
```

### Publication de la Configuration

```bash
php artisan vendor:publish --tag=baddybugs-config
```

---

## ⚡ Configuration Rapide

### 1. Ajoutez vos clés dans `.env`

```env
BADDYBUGS_ENABLED=true
BADDYBUGS_API_KEY=your-api-key-here
BADDYBUGS_ENV=production
```

### 2. C'est tout ! 🎉

Puis faites 
```
php artisan baddybugs:agent
```

L'agent démarre automatiquement et collecte les données.

En production vous devez la mettre comme tache de fond soit avec cron ou supervisor

---

## 📊 Données Collectées

### Lifecycle HTTP Complet

Pour chaque requête HTTP, l'agent capture :

```
┌─────────────────────────────────────────────────────────────────────┐
│ BOOTSTRAP (2.3ms)                                                    │
│ ├── PHP Version: 8.2.0                                               │
│ ├── Laravel Version: 11.0.0                                          │
│ └── SAPI: fpm-fcgi                                                   │
├─────────────────────────────────────────────────────────────────────┤
│ ROUTING (0.8ms)                                                      │
│ ├── Route: user.profile                                              │
│ ├── URI: /api/users/{user}                                           │
│ └── Methods: GET, HEAD                                               │
├─────────────────────────────────────────────────────────────────────┤
│ MIDDLEWARE (12.5ms)                                                  │
│ ├── Global: EncryptCookies, VerifyCsrfToken (5)                      │
│ ├── Route: auth, throttle:60,1 (2)                                   │
│ └── Total: 7 middlewares                                             │
├─────────────────────────────────────────────────────────────────────┤
│ CONTROLLER (45.2ms)                                                  │
│ ├── Class: App\Http\Controllers\UserController                       │
│ ├── Action: show                                                     │
│ └── Parameters: [user]                                               │
├─────────────────────────────────────────────────────────────────────┤
│ RESPONSE (1.2ms)                                                     │
│ ├── Status: 200                                                      │
│ ├── Content-Type: application/json                                   │
│ └── Size: 1,234 bytes                                                │
├─────────────────────────────────────────────────────────────────────┤
│ TERMINATE (0.5ms)                                                    │
│ └── Callbacks executed                                               │
└─────────────────────────────────────────────────────────────────────┘
TOTAL: 62.5ms | Memory Peak: 24.5 MB
```

### Collectors Disponibles (42)

| Catégorie | Collectors |
|-----------|------------|
| **HTTP & Requêtes** | RequestCollector, HttpClientCollector, RouteCollector, RateLimitCollector, LifecycleCollector |
| **Database** | QueryCollector, DatabaseCollector, RedisCollector |
| **Eloquent** | ModelCollector, EloquentCollector |
| **Queue & Jobs** | JobCollector, ScheduledTaskCollector, QueueMetricsCollector |
| **Exceptions** | ExceptionCollector, HandledExceptionCollector |
| **Logs** | LogCollector |
| **Email & Notifications** | MailCollector, NotificationCollector, BroadcastCollector |
| **Sécurité** | SecurityCollector, ThreatCollector, GateCollector, AuthCollector |
| **Analytics** | FeatureCollector, SessionCollector, FormCollector, ValidationCollector |
| **Performance** | TimelineCollector, MiddlewareCollector, ProfilingCollector, MemoryCollector, ViewCollector |
| **Fichiers** | FileUploadCollector, FilesystemCollector |
| **Livewire** | LivewireCollector |
| **i18n** | TranslationCollector |
| **Tests** | TestCollector |
| **AI/LLM** | LLMCollector |
| **Health** | HealthCollector |
| **Cache** | CacheCollector |
| **Events** | EventCollector |
| **Commands** | CommandCollector |

---

## ⚙️ Configuration Avancée

### Collectors Individuels

Activez/désactivez chaque collector :

```env
# Désactiver un collector spécifique
BADDYBUGS_ELOQUENT_TRACKING_ENABLED=false
BADDYBUGS_LIVEWIRE_MONITORING_ENABLED=false
BADDYBUGS_COLLECTORS_BROADCAST_ENABLED=false
```

### Sécurité & Redaction

```php
// config/baddybugs.php

'redact_keys' => [
    'password',
    'password_confirmation',
    'credit_card',
    'cvv',
    'ssn',
    'token',
    'secret',
    'api_key',
],

'redact_headers' => [
    'authorization',
    'cookie',
    'x-xsrf-token',
],
```

### Sampling

Réduisez le volume de données avec le sampling :

```env
# Sampling global (0.0 à 1.0)
BADDYBUGS_SAMPLING_RATE=0.5  # 50% des requêtes

# Sampling par type
BADDYBUGS_SESSION_REPLAY_SAMPLING=0.01  # 1% pour replay
BADDYBUGS_QUERY_SAMPLING_RATE=0.1       # 10% des queries
```

### Ignorer des Routes

```php
// config/baddybugs.php

'ignore_paths' => [
    'health-check',
    'livewire/*',
    'telescope/*',
    '_debugbar/*',
],
```

---

## 🌊 Lifecycle HTTP Complet

L'agent capture le lifecycle complet de chaque requête avec le `LifecycleCollector` :

### Phases Capturées

| Phase | Description | Données |
|-------|-------------|---------|
| **Bootstrap** | Chargement de Laravel | PHP version, Laravel version, SAPI |
| **Routing** | Matching de route | Route name, URI, methods |
| **Middleware** | Exécution middleware | Stack complète, timings individuels |
| **Controller** | Logique métier | Class, action, parameters |
| **Response** | Préparation réponse | Status, content-type, size |
| **Terminate** | Callbacks terminables | Cleanup actions |

### Visualisation Waterfall

Chaque requête génère un événement `lifecycle.http_request` avec :

```json
{
  "type": "lifecycle",
  "name": "http_request",
  "data": {
    "total_duration_ms": 62.5,
    "phases": [
      {"name": "bootstrap", "duration_ms": 2.3, "percentage": 3.68},
      {"name": "routing", "duration_ms": 0.8, "percentage": 1.28},
      {"name": "middleware", "duration_ms": 12.5, "percentage": 20.0},
      {"name": "controller", "duration_ms": 45.2, "percentage": 72.32},
      {"name": "response", "duration_ms": 1.2, "percentage": 1.92},
      {"name": "terminate", "duration_ms": 0.5, "percentage": 0.8}
    ],
    "summary": {
      "controller": "App\\Http\\Controllers\\UserController",
      "action": "show",
      "route_name": "user.profile",
      "method": "GET",
      "url": "https://example.com/api/users/123",
      "status_code": 200
    },
    "memory": {
      "peak_mb": 24.5
    }
  }
}
```

---

## 🔗 Intégrations

### Livewire / Filament

```env
BADDYBUGS_LIVEWIRE_MONITORING_ENABLED=true
```

Capture automatiquement :
- Initialisation des composants
- Requêtes lentes
- Erreurs de déshydratation
- Actions utilisateur

### OpenAI / LLM

```php
use BaddyBugs\Agent\Facades\BaddyBugs;

// Tracking automatique via recordLLMRequest()
BaddyBugs::recordLLMRequest(
    provider: 'openai',
    model: 'gpt-4',
    prompt: $prompt,
    response: $response,
    usage: ['prompt_tokens' => 100, 'completion_tokens' => 50],
    durationMs: 1500
);
```

### Guzzle HTTP Client

```php
$client = new \GuzzleHttp\Client([
    'handler' => BaddyBugs::getGuzzleMiddlewareStack(),
]);

// Toutes les requêtes sont automatiquement tracées
$response = $client->get('https://api.example.com/data');
```

---

## 🛠️ API Manuelle

### Enregistrer un Événement Custom

```php
use BaddyBugs\Agent\Facades\BaddyBugs;

BaddyBugs::record('custom', 'user_purchased', [
    'product_id' => 123,
    'amount' => 99.99,
    'currency' => 'EUR',
]);
```

### Feature Tracking

```php
// Tracker l'utilisation d'une feature
BaddyBugs::feature('dark_mode_enabled', [
    'user_segment' => 'premium',
]);

// Tracker un événement custom
BaddyBugs::track('button_clicked', [
    'button' => 'subscribe',
    'page' => 'pricing',
]);
```

### Breadcrumbs

```php
use BaddyBugs\Agent\Breadcrumbs;

// Ajouter un breadcrumb pour contexte
Breadcrumbs::add('user.action', 'User clicked checkout button');
Breadcrumbs::add('api.call', 'Called payment gateway', ['gateway' => 'stripe']);
```

### Profiling Manuel

```php
use BaddyBugs\Agent\Facades\BaddyBugs;

// Mesurer une opération
BaddyBugs::startTimer('heavy_computation');
// ... code coûteux ...
BaddyBugs::stopTimer('heavy_computation'); // Enregistre automatiquement la durée
```

### Context Partagé

```php
// Ajouter du contexte à tous les événements
BaddyBugs::setContext([
    'tenant_id' => $tenant->id,
    'subscription_plan' => 'enterprise',
]);
```

### Exceptions Gérées

```php
try {
    // Code qui peut échouer
    $result = riskyOperation();
} catch (Exception $e) {
    // Reporter l'exception même si elle est gérée
    reportHandledException($e, [
        'operation' => 'risky_operation',
        'severity' => 'medium',
    ]);
    
    // Fallback
    $result = defaultValue();
}
```

---

## ⚡ Performance

### Impact Minimal

L'agent est conçu pour un impact minimal :
- **< 2ms** overhead par requête en mode standard
- **Async sending** des événements (ne bloque pas la réponse)
- **Compression Gzip** des payloads
- **Buffering intelligent** avec envoi par batch

### Mode Performance

Pour les applications haute performance :

```env
BADDYBUGS_PERFORMANCE_MODE=true
```

Cela désactive automatiquement les collectors à haut overhead.

### Sampling Recommandé pour Production

```env
BADDYBUGS_SAMPLING_RATE=1.0        # Toutes les requêtes
BADDYBUGS_QUERY_SAMPLING_RATE=0.1  # 10% des queries (volume élevé)
BADDYBUGS_CACHE_SAMPLING=0.05      # 5% des ops cache
BADDYBUGS_SESSION_REPLAY_SAMPLING=0.01  # 1% session replay
```

---

## 🔧 Dépannage

### L'agent ne collecte pas

```php
// Vérifiez que l'agent est activé
php artisan tinker
>>> config('baddybugs.enabled')
true

// Vérifiez la clé API
>>> config('baddybugs.api_key')
"your-api-key"
```

### Vérifier les logs

```bash
tail -f storage/logs/laravel.log | grep -i baddybugs
```

### Tester la connexion

```bash
php artisan baddybugs:send --test
```

### Commandes Artisan

```bash
# Information sur l'agent
php artisan about

# Envoyer les événements en attente
php artisan baddybugs:agent

# Vider le buffer
php artisan baddybugs:flush
```


---

## 🤝 Support

- **Documentation** : [docs.baddybugs.com](https://docs.baddybugs.com)
- **Issues** : [GitHub Issues](https://github.com/baddybugs/agent/issues)
- **Email** : support@baddybugs.com

---

## 📄 Licence

MIT License - voir [LICENSE](LICENSE) pour plus de détails.

---

**Fait avec ❤️ par l'équipe BaddyBugs**
