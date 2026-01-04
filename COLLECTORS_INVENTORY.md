# 📦 BaddyBugs Agent - Inventaire Complet des Collectors

**Version:** 1.0.0  
**Nombre de Collectors:** 42  
**Date de mise à jour:** 04 janvier 2026

---

## 🎯 Statut des Collectors

| # | Collector | Fichier | Activé par Défaut | Clé de Config |
|---|-----------|---------|-------------------|---------------|
| 1 | AuthCollector | ✅ | Oui | `collectors.auth.enabled` |
| 2 | BroadcastCollector | ✅ | Non | `collectors.broadcast.enabled` |
| 3 | CacheCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 4 | CommandCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 5 | DatabaseCollector | ✅ | Oui | `collectors.database.enabled` |
| 6 | EloquentCollector | ✅ | Oui | `eloquent_tracking_enabled` |
| 7 | EventCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 8 | ExceptionCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 9 | FeatureCollector | ✅ | Oui | `feature_tracking_enabled` |
| 10 | FileUploadCollector | ✅ | Oui | `file_upload_tracking_enabled` |
| 11 | FilesystemCollector | ✅ | Non | `collectors.filesystem.enabled` |
| 12 | FormCollector | ✅ | Oui | `form_tracking_enabled` |
| 13 | GateCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 14 | HandledExceptionCollector | ✅ | Oui | `collectors.handled_exceptions.enabled` |
| 15 | HealthCollector | ✅ | Oui | `health_monitoring_enabled` |
| 16 | HttpClientCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 17 | JobCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 18 | **LifecycleCollector** ⭐ | ✅ | Oui | `lifecycle_tracking_enabled` |
| 19 | LLMCollector | ✅ | Oui | `collectors.llm.enabled` |
| 20 | LivewireCollector | ✅ | Non | `livewire_monitoring_enabled` |
| 21 | LogCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 22 | MailCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 23 | MemoryCollector | ✅ | Non | `collectors.memory.enabled` |
| 24 | MiddlewareCollector | ✅ | Oui | `track_middleware_timing` |
| 25 | ModelCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 26 | NotificationCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 27 | ProfilingCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 28 | QueryCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 29 | QueueMetricsCollector | ✅ | Oui | `queue_metrics_enabled` |
| 30 | RateLimitCollector | ✅ | Oui | `collectors.rate_limit.enabled` |
| 31 | RedisCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 32 | RequestCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 33 | RouteCollector | ✅ | Oui | `collectors.routes.enabled` |
| 34 | ScheduledTaskCollector | ✅ | Oui | Via BaddyBugs::bootCollectors |
| 35 | SecurityCollector | ✅ | Oui | `security_enabled` |
| 36 | SessionCollector | ✅ | Oui | `collectors.session.enabled` |
| 37 | TestCollector | ✅ | Non | `collectors.test` |
| 38 | ThreatCollector | ✅ | Oui | `threat_detection_enabled` |
| 39 | TimelineCollector | ✅ | Oui | `timeline_enabled` |
| 40 | TranslationCollector | ✅ | Non | `collectors.translations.enabled` |
| 41 | ValidationCollector | ✅ | Oui | `collectors.validation.enabled` |
| 42 | ViewCollector | ✅ | Oui | `track_view_rendering` |

---

## 📊 Catégories de Collectors

### 🌐 HTTP & Requêtes
- **RequestCollector** - Requêtes HTTP entrantes
- **HttpClientCollector** - Requêtes HTTP sortantes (Guzzle)
- **RouteCollector** - Analytics de routes (404, redirects, model binding)
- **RateLimitCollector** - Rate limiting et throttling
- **LifecycleCollector** ⭐ - **Lifecycle complet HTTP (waterfall)**

### 🗄️ Database & Cache
- **QueryCollector** - Requêtes SQL, N+1 detection
- **DatabaseCollector** - Connexions, transactions, deadlocks
- **CacheCollector** - Opérations cache (hits, misses)
- **RedisCollector** - Commandes Redis

### ⚙️ Eloquent & Models
- **ModelCollector** - Events CRUD sur modèles
- **EloquentCollector** - Eager/lazy loading, relations

### 🔔 Jobs & Queue
- **JobCollector** - Jobs de queue
- **ScheduledTaskCollector** - Tâches CRON
- **QueueMetricsCollector** - Métriques queue

### 🚨 Exceptions & Logs
- **ExceptionCollector** - Exceptions non gérées
- **HandledExceptionCollector** - Exceptions attrapées
- **LogCollector** - Logs applicatifs

### 📧 Communications
- **MailCollector** - Emails envoyés
- **NotificationCollector** - Notifications Laravel
- **BroadcastCollector** - WebSocket/Pusher

### 🔐 Sécurité
- **SecurityCollector** - Scan de sécurité (PII, SQL injection, XSS)
- **ThreatCollector** - Détection de menaces
- **GateCollector** - Vérifications d'autorisation
- **AuthCollector** - Login, logout, 2FA, impersonation

### 🎯 Analytics & Features
- **FeatureCollector** - Analytics produit
- **SessionCollector** - Analytics de session
- **FormCollector** - Soumissions de formulaires
- **ValidationCollector** - Statistiques de validation

### 🎨 Frontend & Views
- **ViewCollector** - Performance des vues
- **LivewireCollector** - Monitoring Livewire/Filament
- **MiddlewareCollector** - Performance middleware

### 📈 Performance & Monitoring
- **TimelineCollector** - Timeline d'une trace
- **ProfilingCollector** - Profiling manuel
- **MemoryCollector** - Usage mémoire
- **HealthCollector** - Heartbeat, jobs bloqués

### 📁 Fichiers
- **FileUploadCollector** - Uploads de fichiers
- **FilesystemCollector** - Opérations fichiers

### 🌍 Internationalisation
- **TranslationCollector** - Traductions manquantes

### 🧪 Testing
- **TestCollector** - Tests PHPUnit/Pest

### 🤖 AI/LLM
- **LLMCollector** - Requêtes OpenAI, Anthropic, etc.

### 📢 Events
- **EventCollector** - Événements Laravel

---

## 🔧 Configuration Rapide

### Activer/Désactiver en masse

```env
# Désactiver complètement l'agent
BADDYBUGS_ENABLED=false

# Mode performance (réduit les collectors actifs)
BADDYBUGS_PERFORMANCE_MODE=true
```

### Collectors haute performance (recommandés pour production)

```php
// config/baddybugs.php
'collectors' => [
    'broadcast' => ['enabled' => false],      // Haute fréquence
    'filesystem' => ['enabled' => false],     // I/O intensive
    'translations' => ['enabled' => false],   // Développement only
    'memory' => ['enabled' => false],         // Debug only
    'test' => ['enabled' => false],           // CI/CD only
],
```

### Mode debug complet (développement)

```env
BADDYBUGS_ENABLED=true
BADDYBUGS_PERFORMANCE_MODE=false
BADDYBUGS_LIVEWIRE_ENABLED=true
BADDYBUGS_SESSION_REPLAY_ENABLED=true
BADDYBUGS_COLLECTORS_MEMORY_ENABLED=true
BADDYBUGS_COLLECTORS_FILESYSTEM_ENABLED=true
BADDYBUGS_COLLECTORS_TRANSLATIONS_ENABLED=true
```

---

## 📤 Événements Émis par Collector

| Collector | Type(s) d'événement | Volume estimé |
|-----------|---------------------|---------------|
| AuthCollector | `auth` | Faible |
| BroadcastCollector | `broadcast` | Moyen |
| CacheCollector | `cache` | Élevé |
| CommandCollector | `command` | Faible |
| DatabaseCollector | `database` | Moyen |
| EloquentCollector | `eloquent` | Faible |
| EventCollector | `event` | Élevé |
| ExceptionCollector | `exception` | Faible |
| FeatureCollector | `feature` | Moyen |
| FileUploadCollector | `file_upload` | Faible |
| FilesystemCollector | `filesystem` | Moyen |
| FormCollector | `form` | Faible |
| GateCollector | `gate` | Moyen |
| HandledExceptionCollector | `handled_exception` | Faible |
| HealthCollector | `health` | Faible |
| HttpClientCollector | `http_client` | Moyen |
| JobCollector | `job` | Moyen |
| LLMCollector | `llm_request` | Faible |
| LivewireCollector | `livewire_*` | Élevé |
| LogCollector | `log` | Moyen |
| MailCollector | `mail` | Faible |
| MemoryCollector | `memory` | Faible |
| MiddlewareCollector | `middleware` | Moyen |
| ModelCollector | `model` | Élevé |
| NotificationCollector | `notification` | Faible |
| ProfilingCollector | `profiling_segment` | Faible |
| QueryCollector | `query`, `issue` | Élevé |
| QueueMetricsCollector | `queue_metrics` | Faible |
| RateLimitCollector | `rate_limit` | Faible |
| RedisCollector | `redis` | Élevé |
| RequestCollector | `request` | 1/requête |
| RouteCollector | `route` | Faible |
| ScheduledTaskCollector | `scheduled_task` | Faible |
| SecurityCollector | `security` | Faible |
| SessionCollector | `session` | 1/requête |
| TestCollector | `test` | Tests only |
| ThreatCollector | `security_threat` | Faible |
| TimelineCollector | `timeline`, `trace_span`, `regression` | 1/requête |
| TranslationCollector | `translation` | Faible |
| ValidationCollector | `validation` | Faible |
| ViewCollector | `view` | Moyen |

---

## 🎛️ Variables d'Environnement

```env
# Core
BADDYBUGS_ENABLED=true
BADDYBUGS_API_KEY=your_api_key
BADDYBUGS_ENDPOINT=https://ingest.baddybugs.com/api/v1/events

# Collectors (nouveaux)
BADDYBUGS_COLLECTORS_AUTH_ENABLED=true
BADDYBUGS_COLLECTORS_BROADCAST_ENABLED=false
BADDYBUGS_COLLECTORS_DATABASE_ENABLED=true
BADDYBUGS_COLLECTORS_FILESYSTEM_ENABLED=false
BADDYBUGS_COLLECTORS_LLM_ENABLED=true
BADDYBUGS_COLLECTORS_MEMORY_ENABLED=false
BADDYBUGS_COLLECTORS_RATE_LIMIT_ENABLED=true
BADDYBUGS_COLLECTORS_ROUTES_ENABLED=true
BADDYBUGS_COLLECTORS_SESSION_ENABLED=true
BADDYBUGS_COLLECTORS_TRANSLATIONS_ENABLED=false
BADDYBUGS_COLLECTORS_VALIDATION_ENABLED=true
BADDYBUGS_COLLECTORS_HANDLED_EXCEPTIONS_ENABLED=true

# Legacy (toujours supportées)
BADDYBUGS_SECURITY_ENABLED=true
BADDYBUGS_THREAT_DETECTION_ENABLED=true
BADDYBUGS_ELOQUENT_TRACKING_ENABLED=true
BADDYBUGS_FORM_TRACKING_ENABLED=true
BADDYBUGS_FILE_UPLOAD_TRACKING_ENABLED=true
BADDYBUGS_QUEUE_METRICS_ENABLED=true
BADDYBUGS_HEALTH_MONITORING_ENABLED=true
BADDYBUGS_TIMELINE_ENABLED=true
BADDYBUGS_LIVEWIRE_MONITORING_ENABLED=false
BADDYBUGS_TRACK_VIEW_RENDERING=true
BADDYBUGS_TRACK_MIDDLEWARE_TIMING=true
BADDYBUGS_FEATURE_TRACKING_ENABLED=true
```

---

## 📈 Métriques de Performance

### Impact CPU par Collector (estimation)
- **Très faible** (< 0.1ms): Auth, Mail, Notification, Command
- **Faible** (0.1-0.5ms): Health, Session, Form, Rate Limit
- **Moyen** (0.5-2ms): Request, Query, Model, Middleware
- **Élevé** (2-10ms): Timeline, Security, Memory, Profiling
- **Variable**: Livewire, Event, Cache, Redis (dépend du volume)

### Recommandations Production
1. Désactiver les collectors "Élevé" sauf si nécessaire
2. Activer le sampling sur les collectors à volume élevé
3. Utiliser `performance_mode=true` pour une config optimisée

---

*Document généré automatiquement - BaddyBugs Agent PHP v1.0.0*
