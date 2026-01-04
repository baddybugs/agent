# 📊 Schéma des Données BaddyBugs Agent

**Version:** 1.0.0  
**Date de mise à jour:** 04 janvier 2026

Ce document définit le schéma complet de toutes les données collectées par l'agent PHP BaddyBugs. 
Il sert de référence pour le dashboard et le service d'ingestion.

---

## 📦 Structure Générale des Événements

Tous les événements envoyés par l'agent suivent cette structure de base :

```json
{
  "type": "string",           // Type d'événement (ex: "request", "exception", "query")
  "name": "string",           // Nom spécifique (ex: "GET /api/users", "ValidationException")
  "data": {},                 // Payload spécifique au type (voir détails ci-dessous)
  "timestamp": "ISO8601",     // Horodatage
  "trace_id": "uuid",         // ID de trace pour corrélation
  "environment": "string",    // local, staging, production
  "deployment_hash": "string" // Hash du déploiement actuel
}
```

---

## 📋 Catalogue des Types d'Événements

| Type | Sous-types | Collector | Description |
|------|-----------|-----------|-------------|
| `request` | - | RequestCollector | Requêtes HTTP entrantes |
| `query` | - | QueryCollector | Requêtes SQL |
| `exception` | - | ExceptionCollector | Exceptions non gérées |
| `handled_exception` | - | HandledExceptionCollector | Exceptions attrapées |
| `job` | `processing`, `processed`, `failed` | JobCollector | Jobs de queue |
| `command` | `starting`, `finished` | CommandCollector | Commandes Artisan |
| `scheduled_task` | `starting`, `finished`, `failed`, `skipped` | ScheduledTaskCollector | Tâches planifiées |
| `cache` | `hit`, `miss`, `write`, `forget` | CacheCollector | Opérations cache |
| `mail` | - | MailCollector | Emails envoyés |
| `notification` | `sent`, `failed` | NotificationCollector | Notifications |
| `event` | - | EventCollector | Événements Laravel |
| `log` | `emergency`, `alert`, `critical`, `error`, `warning` | LogCollector | Logs applicatifs |
| `http_client` | - | HttpClientCollector | Requêtes HTTP sortantes |
| `model` | `created`, `updated`, `deleted`, `restored` | ModelCollector | Opérations Eloquent |
| `gate` | - | GateCollector | Vérifications d'autorisation |
| `redis` | - | RedisCollector | Commandes Redis |
| `livewire_component` | `initialized` | LivewireCollector | Composants Livewire |
| `livewire_performance` | `slow_request` | LivewireCollector | Performance Livewire |
| `livewire_error` | `message_failed`, `dehydration_exception` | LivewireCollector | Erreurs Livewire |
| `security` | `security_issue`, `dangerous_usage`, `composer_packages` | SecurityCollector | Issues de sécurité |
| `security_threat` | `detection` | ThreatCollector | Menaces détectées |
| `view` | `slow_view`, `rendered` | ViewCollector | Rendu des vues |
| `middleware` | `stack_executed`, `individual` | MiddlewareCollector | Performance middleware |
| `timeline` | `trace_timeline` | TimelineCollector | Timeline d'une trace |
| `trace_span` | `bootstrap`, `middleware`, `controller`, `sending` | TimelineCollector | Spans pour waterfall |
| `feature` | `route.accessed`, `job.executed`, `feature.used`, `custom.event` | FeatureCollector | Analytics produit |
| `health` | `scheduled_task_*`, `stuck_job_detected`, `queue_metrics`, `heartbeat` | HealthCollector | Santé système |
| `profiling_segment` | - | ProfilingCollector | Segments de profiling |
| `test` | `started`, `finished` | TestCollector | Tests PHPUnit/Pest |
| `llm_request` | - | LLMCollector | Requêtes LLM (OpenAI, etc.) |
| `eloquent` | `usage_summary` | EloquentCollector | Métriques Eloquent |
| `form` | `submission` | FormCollector | Soumissions formulaires |
| `file_upload` | `upload_batch` | FileUploadCollector | Uploads fichiers |
| `queue_metrics` | `snapshot` | QueueMetricsCollector | Métriques queue |
| `issue` | `n_plus_one` | QueryCollector | Problèmes détectés |
| `regression` | `baseline_snapshot` | TimelineCollector | Données de régression |
| `auth` | `login`, `logout`, `login_failed`, `lockout`, `password_reset`, `registered`, `email_verified`, `2fa_*`, `impersonation` | AuthCollector | Authentification |
| `broadcast` | `event_broadcasted`, `channel_authorized`, `channel_denied`, `presence_update` | BroadcastCollector | WebSocket/Pusher |
| `rate_limit` | `exceeded`, `usage`, `hit` | RateLimitCollector | Rate limiting |
| `session` | `analytics`, `regenerated`, `invalidated` | SessionCollector | Analytics session |
| `translation` | `locale_changed`, `missing_keys` | TranslationCollector | Traductions |
| `route` | `404_not_found`, `redirect`, `model_binding` | RouteCollector | Analytics routes |
| `validation` | `summary` | ValidationCollector | Statistiques validation |
| `filesystem` | `disk_usage`, `operations` | FilesystemCollector | Opérations fichiers |
| `database` | `transaction`, `connection_metrics`, `deadlock` | DatabaseCollector | Connexions DB |
| `memory` | `snapshot` | MemoryCollector | Usage mémoire |
| `lifecycle` | `http_request` | LifecycleCollector | **Lifecycle complet d'une requête HTTP** |

---

## 📝 Schémas Détaillés par Type

### 1. `request` - Requêtes HTTP Entrantes

```typescript
interface RequestEvent {
  type: "request";
  name: string; // ex: "GET /api/users"
  data: {
    method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE" | "OPTIONS" | "HEAD";
    uri: string;
    full_url: string;
    status_code: number;
    duration_ms: number;
    controller: string | null;
    action: string | null;
    route_name: string | null;
    
    // Headers (si activé)
    headers?: Record<string, string>;
    
    // Inputs (si activé, avec redaction)
    inputs?: Record<string, any>;
    
    // User context
    user_id?: number | string;
    user_email?: string;
    
    // Client info
    ip: string;
    user_agent: string;
    referer?: string;
    
    // Memory
    memory_usage?: number;
    memory_peak?: number;
    memory_limit?: number;
  };
}
```

### 2. `query` - Requêtes SQL

```typescript
interface QueryEvent {
  type: "query";
  name: string; // SQL normalisé
  data: {
    sql: string;
    bindings: any[];
    time: number; // ms
    connection: string;
    
    // Analyse
    is_slow: boolean;
    slow_threshold_ms?: number;
    
    // Source
    file?: string;
    line?: number;
    
    // EXPLAIN (si activé)
    explain?: {
      rows: number;
      type: string;
      key?: string;
    };
    
    // Contexte
    request_id?: string;
  };
}
```

### 3. `exception` - Exceptions Non Gérées

```typescript
interface ExceptionEvent {
  type: "exception";
  name: string; // Classe de l'exception
  data: {
    message: string;
    exception_class: string;
    file: string;
    line: number;
    code: number | string;
    
    // Stack trace
    trace: Array<{
      file: string;
      line: number;
      function: string;
      class?: string;
      type?: string;
      args?: any[];
    }>;
    
    // Code source (si activé)
    source_code?: {
      [line_number: string]: string;
    };
    
    // Fingerprint pour grouping
    fingerprint: string;
    
    // Contexte
    url?: string;
    method?: string;
    user_id?: number;
    
    // Breadcrumbs
    breadcrumbs?: Array<{
      type: string;
      message: string;
      timestamp: number;
      data?: Record<string, any>;
    }>;
    
    // Metadata
    severity: "emergency" | "alert" | "critical" | "error" | "warning";
    handled: false;
    
    // Previous exception
    previous?: {
      class: string;
      message: string;
      file: string;
      line: number;
    };
  };
}
```

### 4. `handled_exception` - Exceptions Attrapées

```typescript
interface HandledExceptionEvent {
  type: "handled_exception";
  name: string;
  data: {
    message: string;
    exception: string;
    file: string;
    line: number;
    code: number | string;
    trace: Array<{
      file: string;
      line: number;
      function: string;
      class?: string;
    }>;
    handled: true;
    context: Record<string, any>;
    severity: "critical" | "high" | "medium" | "low";
  };
}
```

### 5. `job` - Jobs de Queue

```typescript
interface JobEvent {
  type: "job";
  name: string; // Classe du job
  data: {
    status: "processing" | "processed" | "failed";
    job_class: string;
    job_id: string;
    queue: string;
    connection: string;
    attempts: number;
    
    // Timing
    wait_time_ms?: number;    // Temps en queue
    duration_ms?: number;     // Temps d'exécution
    
    // Payload (tronqué si trop grand)
    payload?: Record<string, any>;
    
    // En cas d'échec
    exception?: string;
    exception_class?: string;
    exception_message?: string;
    trace?: string;
  };
}
```

### 6. `command` - Commandes Artisan

```typescript
interface CommandEvent {
  type: "command";
  name: string; // Nom de la commande
  data: {
    status: "starting" | "finished";
    event?: "finished";
    exit_code?: number;
    
    // Arguments (si activé)
    arguments?: Record<string, any>;
    options?: Record<string, any>;
  };
}
```

### 7. `scheduled_task` - Tâches Planifiées

```typescript
interface ScheduledTaskEvent {
  type: "scheduled_task";
  name: string; // Commande ou "closure"
  data: {
    event: "starting" | "finished" | "failed" | "skipped";
    expression: string; // Cron expression
    timezone?: string;
    description?: string;
    user?: string;
    
    // Timing
    runtime?: number;
    frequency_seconds?: number;
    
    // En cas d'échec
    exit_code?: number;
    exception?: string;
    trace?: string;
  };
}
```

### 8. `cache` - Opérations Cache

```typescript
interface CacheEvent {
  type: "cache";
  name: string; // Clé du cache
  data: {
    action: "hit" | "miss" | "write" | "forget";
    key: string;
    store: string;
    tags: string[];
    
    // Pour write
    expiration?: number;
    ttl?: number;
    
    // Stats
    size?: number; // bytes
    value_preview?: string;
    value_type?: string;
  };
}
```

### 9. `mail` - Emails

```typescript
interface MailEvent {
  type: "mail";
  name: string; // Sujet
  data: {
    subject: string;
    to: string[];
    cc: string[];
    bcc: string[];
    from: string[];
    has_attachments: boolean;
    notification?: string; // Si via notification
  };
}
```

### 10. `notification` - Notifications

```typescript
interface NotificationEvent {
  type: "notification";
  name: string; // Classe notification
  data: {
    channel: string;
    notifiable: {
      type: string;
      id?: number | string;
    };
    duration_ms: number;
    status: "sent" | "failed";
    error?: string;
  };
}
```

### 11. `http_client` - Requêtes HTTP Sortantes

```typescript
interface HttpClientEvent {
  type: "http_client";
  name: string; // "GET https://api.example.com"
  data: {
    method: string;
    url: string;
    host: string;
    path: string;
    
    // Request
    request_headers?: Record<string, string>;
    request_body?: string;
    
    // Response
    status_code: number;
    response_headers?: Record<string, string>;
    response_body?: string;
    
    // Timing
    duration_ms: number;
    is_slow: boolean;
    
    // Tracing
    parent_trace_id?: string;
    outbound_trace_id?: string;
    
    // État
    success: boolean;
    error?: string;
  };
}
```

### 12. `model` - Opérations Eloquent

```typescript
interface ModelEvent {
  type: "model";
  name: string; // Classe du modèle
  data: {
    action: "created" | "updated" | "deleted" | "restored";
    model: string;
    key: number | string;
    table: string;
    
    // Pour updates
    changes?: string[]; // Noms des champs modifiés
  };
}
```

### 13. `gate` - Vérifications d'Autorisation

```typescript
interface GateEvent {
  type: "gate";
  name: string; // Ability name
  data: {
    ability: string;
    result: "allowed" | "denied";
    arguments: any[];
    user_id?: number | string;
    target?: string;
  };
}
```

### 14. `redis` - Commandes Redis

```typescript
interface RedisEvent {
  type: "redis";
  name: string; // "GET key" ou "SET key value..."
  data: {
    command: string;
    parameters: any[];
    connection: string;
    duration_ms: number;
  };
}
```

### 15. `livewire_*` - Événements Livewire

```typescript
interface LivewireComponentEvent {
  type: "livewire_component";
  name: "initialized";
  data: {
    component: string;
    component_id: string;
    url: string;
    user_id?: number;
  };
}

interface LivewirePerformanceEvent {
  type: "livewire_performance";
  name: "slow_request";
  data: {
    component: string;
    component_id: string;
    duration_ms: number;
    threshold_ms: number;
    updates: any[];
    url: string;
    user_id?: number;
  };
}

interface LivewireErrorEvent {
  type: "livewire_error";
  name: "message_failed" | "dehydration_exception";
  data: {
    component: string;
    component_id: string;
    duration_ms?: number;
    updates?: any[];
    calls?: any[];
    response_status?: number;
    exception?: string;
    message?: string;
    file?: string;
    line?: number;
    url: string;
    user_id?: number;
  };
}
```

### 16. `security` - Issues de Sécurité

```typescript
interface SecurityEvent {
  type: "security";
  name: "security_issue" | "dangerous_usage" | "composer_packages";
  data: {
    // Pour security_issue
    injection_attempts?: Array<{
      type: "sql_injection" | "xss";
      field: string;
      pattern_matched: string;
      value_excerpt: string;
    }>;
    sensitive_data_findings?: Array<{
      type: "credit_card" | "ssn" | "api_key" | "jwt" | "private_key";
      field: string;
      severity: "critical" | "high" | "medium";
    }>;
    url?: string;
    method?: string;
    ip?: string;
    user_agent?: string;
    user_id?: number;
    severity?: "critical" | "high" | "medium";
    
    // Pour dangerous_usage
    issues?: string[];
    environment?: string;
    
    // Pour composer_packages
    total_packages?: number;
    packages?: Array<{
      name: string;
      version: string;
    }>;
    scan_timestamp?: string;
  };
}
```

### 17. `security_threat` - Menaces Détectées

```typescript
interface SecurityThreatEvent {
  type: "security_threat";
  name: "detection";
  data: {
    threats_detected: number;
    threats: Array<{
      type: "sql_injection" | "xss" | "path_traversal" | "bot" | "suspicious_pattern";
      severity: "critical" | "high" | "medium" | "low";
      score?: number;
      details: string;
      patterns?: string[];
    }>;
    url: string;
    method: string;
    ip: string;
    user_agent: string;
    user_id?: number;
  };
}
```

### 18. `view` - Rendu des Vues

```typescript
interface ViewEvent {
  type: "view";
  name: "slow_view" | "rendered";
  data: {
    view_name: string;
    duration_ms: number;
    is_slow: boolean;
    url: string;
    route?: string;
    threshold_ms?: number;
    severity?: "high" | "medium";
  };
}
```

### 19. `middleware` - Performance Middleware

```typescript
interface MiddlewareEvent {
  type: "middleware";
  name: "stack_executed" | "individual";
  data: {
    total_middleware_count?: number;
    total_duration_ms: number;
    average_per_middleware?: number;
    middleware_stack?: string[];
    middleware_name?: string;
    url: string;
    method?: string;
    route?: string;
    is_slow?: boolean;
    severity?: "high" | "medium";
  };
}
```

### 20. `timeline` - Timeline d'une Trace

```typescript
interface TimelineEvent {
  type: "timeline";
  name: "trace_timeline";
  data: {
    trace_id: string;
    event_count: number;
    total_duration_ms: number;
    detail_level: "basic" | "detailed" | "full";
    events: Array<{
      type: string;
      name: string;
      data: Record<string, any>;
      timestamp: number;
      relative_time_ms: number;
    }>;
  };
}
```

### 21. `trace_span` - Spans pour Waterfall

```typescript
interface TraceSpanEvent {
  type: "trace_span";
  name: "bootstrap" | "middleware" | "controller" | "sending";
  data: {
    type: string;
    name: string;
    start_time: number;
    end_time: number;
    duration_ms: number;
  };
}
```

### 22. `feature` - Analytics Produit

```typescript
interface FeatureEvent {
  type: "feature";
  name: "route.accessed" | "job.executed" | "feature.used" | "custom.event";
  data: {
    route_name?: string;
    route_uri?: string;
    controller?: string;
    method?: string;
    status_code?: number;
    
    job_name?: string;
    queue?: string;
    connection?: string;
    
    feature_name?: string;
    event_name?: string;
    
    user_id?: number;
    user_email?: string;
    user_name?: string;
    user_roles?: string[];
    
    url?: string;
    timestamp: string;
  };
}
```

### 23. `health` - Santé Système

```typescript
interface HealthEvent {
  type: "health";
  name: "scheduled_task_started" | "scheduled_task_finished" | "scheduled_task_failed" | 
        "stuck_job_detected" | "queue_metrics" | "heartbeat";
  data: {
    // Scheduled tasks
    task_name?: string;
    command?: string;
    expression?: string;
    duration_ms?: number;
    exit_code?: number;
    exception?: string;
    success?: boolean;
    
    // Stuck jobs
    job_id?: string;
    job_name?: string;
    queue?: string;
    duration_seconds?: number;
    threshold_seconds?: number;
    severity?: "critical";
    
    // Queue metrics
    total_jobs?: number;
    succeeded?: number;
    failed?: number;
    success_rate?: number;
    failure_rate?: number;
    
    // Heartbeat
    timestamp?: string;
    uptime?: number;
    memory_usage?: number;
    memory_usage_mb?: number;
    peak_memory?: number;
    peak_memory_mb?: number;
  };
}
```

### 24. `llm_request` - Requêtes LLM

```typescript
interface LLMEvent {
  type: "llm_request";
  name: string;
  data: {
    provider: "openai" | "anthropic" | "google" | "mistral" | "deepseek" | string;
    model: string;
    prompt: string;
    response: string;
    usage: {
      prompt_tokens: number;
      completion_tokens: number;
      total_tokens: number;
    };
    cost: number; // USD
    duration_ms: number;
    status: "success" | "failed";
    error?: string;
  };
}
```

### 25. `eloquent` - Métriques Eloquent

```typescript
interface EloquentEvent {
  type: "eloquent";
  name: "usage_summary";
  data: {
    eager_loads_count: number;
    lazy_loads_count: number;
    unique_models_used: number;
    models_list: string[];
    model_events_fired: number;
    events_by_type: Record<string, number>;
    relationships_loaded: Array<{
      model: string;
      relation: string;
      relation_type: string;
      relation_count: number;
    }>;
    pivot_accesses_count: number;
    eager_to_lazy_ratio?: number;
  };
}
```

### 26. `form` - Soumissions Formulaires

```typescript
interface FormEvent {
  type: "form";
  name: "submission";
  data: {
    route?: string;
    url: string;
    method: string;
    field_count: number;
    fields_submitted: string[];
    has_validation_errors: boolean;
    validation_attempts_count: number;
    validation_errors?: Record<string, string[]>;
    error_fields?: string[];
    error_field_count?: number;
    total_error_count?: number;
    likely_form_type: "login" | "registration" | "contact" | "search" | "payment" | "settings" | "generic";
    contains_file_upload: boolean;
  };
}
```

### 27. `file_upload` - Uploads Fichiers

```typescript
interface FileUploadEvent {
  type: "file_upload";
  name: "upload_batch";
  data: {
    upload_count: number;
    total_size_bytes: number;
    total_size_mb: number;
    processing_time_ms: number;
    route?: string;
    url: string;
    files: Array<{
      field_name: string;
      original_name: string;
      size_bytes?: number;
      mime_type?: string;
      extension?: string;
      is_image: boolean;
      is_valid: boolean;
      error?: string;
      image_width?: number;
      image_height?: number;
      image_megapixels?: number;
    }>;
  };
}
```

### 28. `queue_metrics` - Métriques Queue

```typescript
interface QueueMetricsEvent {
  type: "queue_metrics";
  name: "snapshot";
  data: {
    timestamp: string;
    total_pending: number;
    total_failed: number;
    queues: Record<string, {
      name: string;
      pending_count: number;
      processing_count: number;
      oldest_job_age_seconds?: number;
    }>;
    redis_memory_usage?: number;
  };
}
```

### 29. `issue` - Problèmes Détectés

```typescript
interface IssueEvent {
  type: "issue";
  name: "n_plus_one";
  data: {
    query: string;
    count: number;
    suggestion: string;
    file?: string;
    line?: number;
    model?: string;
  };
}
```

### 30. `regression` - Données de Régression

```typescript
interface RegressionEvent {
  type: "regression";
  name: "baseline_snapshot";
  data: {
    duration_ms: number;
    query_count: number;
    memory_peak: number;
    error_flag: 0 | 1;
  };
}
```

### 31. `auth` - Authentification

```typescript
interface AuthEvent {
  type: "auth";
  name: "login" | "logout" | "logout_other_devices" | "login_failed" | "login_attempt" | 
        "lockout" | "password_reset" | "registered" | "email_verified" | 
        "2fa_challenged" | "2fa_enabled" | "2fa_disabled" | "2fa_recovery_generated" | 
        "impersonation";
  data: {
    user_id?: number | string;
    guard?: string;
    remember?: boolean;
    ip: string;
    user_agent?: string;
    timestamp: string;
    
    // Pour login_failed
    credentials?: Record<string, string>; // Redacted values
    
    // Pour lockout
    email?: string;
    
    // Pour impersonation
    event?: string;
    admin_id?: number | string;
    target_id?: number | string;
    
    severity?: "warning" | "high";
  };
}
```

### 32. `broadcast` - WebSocket/Pusher

```typescript
interface BroadcastEvent {
  type: "broadcast";
  name: "event_broadcasted" | "channel_authorized" | "channel_denied" | "presence_update";
  data: {
    event?: string;
    event_name?: string;
    channels?: string[];
    channel?: string;
    connection?: string;
    user_id?: number;
    member_id?: number | string;
    event_type?: string;
    ip?: string;
    timestamp: string;
    severity?: "warning";
  };
}
```

### 33. `rate_limit` - Rate Limiting

```typescript
interface RateLimitEvent {
  type: "rate_limit";
  name: "exceeded" | "usage" | "hit";
  data: {
    url?: string;
    route?: string;
    method?: string;
    ip?: string;
    user_id?: number;
    user_agent?: string;
    
    retry_after?: string;
    x_ratelimit_limit?: number;
    x_ratelimit_remaining?: number;
    
    limit?: number;
    remaining?: number;
    usage_percentage?: number;
    
    // Pour hit
    key?: string;
    attempts?: number;
    max_attempts?: number;
    decay_seconds?: number;
    
    timestamp: string;
    severity?: "warning";
  };
}
```

### 34. `session` - Analytics Session

```typescript
interface SessionEvent {
  type: "session";
  name: "analytics" | "regenerated" | "invalidated";
  data: {
    session_id_hash?: string; // Anonymized
    is_new_session?: boolean;
    session_started?: boolean;
    pages_in_session?: number;
    session_duration_seconds?: number;
    has_navigation?: boolean;
    has_flash_data?: boolean;
    flash_keys?: string[];
    potential_bounce?: boolean;
    user_id?: number;
    ip?: string;
    timestamp?: string;
  };
}
```

### 35. `translation` - Traductions

```typescript
interface TranslationEvent {
  type: "translation";
  name: "locale_changed" | "missing_keys";
  data: {
    // Pour locale_changed
    locale?: string;
    previous_locale?: string;
    
    // Pour missing_keys
    total_missing?: number;
    total_fallbacks?: number;
    keys?: Array<{
      key: string;
      locale: string;
      fallback?: string;
      count: number;
      locations: string[];
    }>;
    
    url?: string;
    user_id?: number;
    timestamp: string;
  };
}
```

### 36. `route` - Analytics Routes

```typescript
interface RouteEvent {
  type: "route";
  name: "404_not_found" | "redirect" | "model_binding";
  data: {
    // Pour 404
    url?: string;
    path?: string;
    method?: string;
    referrer?: string;
    user_agent?: string;
    ip?: string;
    user_id?: number;
    pattern_guess?: string;
    
    // Pour redirect
    from?: string;
    to?: string;
    status_code?: number;
    is_permanent?: boolean;
    route?: string;
    
    // Pour model_binding
    models_bound?: number;
    bindings?: Array<{
      parameter: string;
      model: string;
      id: number | string;
    }>;
    
    timestamp: string;
  };
}
```

### 37. `validation` - Statistiques Validation

```typescript
interface ValidationEvent {
  type: "validation";
  name: "summary";
  data: {
    total_validations: number;
    passed: number;
    failed: number;
    avg_duration_ms: number;
    total_fields_validated: number;
    url: string;
    route?: string;
    most_used_rules: Record<string, number>;
    most_failed_fields?: Record<string, number>;
    timestamp: string;
  };
}
```

### 38. `filesystem` - Opérations Fichiers

```typescript
interface FilesystemEvent {
  type: "filesystem";
  name: "disk_usage" | "operations";
  data: {
    // Pour disk_usage
    disks?: Record<string, {
      free_bytes: number;
      total_bytes: number;
      used_bytes: number;
      usage_percentage: number;
    }>;
    
    // Pour operations
    operation_count?: number;
    reads?: number;
    writes?: number;
    deletes?: number;
    total_bytes_read?: number;
    total_bytes_written?: number;
    slow_operations?: number;
    operations?: Array<{
      type: "read" | "write" | "delete";
      path: string;
      disk: string;
      size_bytes?: number;
      duration_ms?: number;
      is_slow?: boolean;
    }>;
    
    timestamp: string;
  };
}
```

### 39. `database` - Connexions DB

```typescript
interface DatabaseEvent {
  type: "database";
  name: "transaction" | "connection_metrics" | "deadlock";
  data: {
    // Pour transaction
    connection?: string;
    status?: "committed" | "rolled_back";
    duration_ms?: number;
    is_slow?: boolean;
    
    // Pour connection_metrics
    active_connections?: number;
    open_transactions?: number;
    pool?: {
      total?: number;
      active?: number;
      idle?: number;
      connected?: number;
      max_connections?: number;
    };
    long_running_transactions?: Array<{
      connection: string;
      duration_ms: number;
    }>;
    
    // Pour deadlock
    message?: string;
    exception?: string;
    
    timestamp: string;
    severity?: "warning" | "critical" | "info";
  };
}
```

### 40. `memory` - Usage Mémoire

```typescript
interface MemoryEvent {
  type: "memory";
  name: "snapshot";
  data: {
    trigger: string;
    timestamp: number;
    memory_usage: number;
    memory_peak: number;
    memory_real: number;
    memory_limit: number;
    start_memory: number;
    memory_delta: number;
    checkpoints: Array<{
      label: string;
      memory: number;
      peak: number;
      delta: number;
      timestamp: number;
    }>;
  };
}
```

### 41. `lifecycle` - Lifecycle HTTP Complet ⭐

**C'est l'événement le plus important pour visualiser le waterfall d'une requête HTTP.**

```typescript
interface LifecycleEvent {
  type: "lifecycle";
  name: "http_request";
  data: {
    trace_id: string;
    total_duration_ms: number;
    
    // Phases du lifecycle
    phases: Array<{
      name: "bootstrap" | "routing" | "middleware" | "controller" | "response" | "terminate";
      start_time: number;
      end_time: number;
      duration_ms: number;
      start_offset_ms: number; // Offset depuis le début de la requête
      percentage: number;      // % du temps total
      data: {
        // Bootstrap
        php_version?: string;
        laravel_version?: string;
        sapi?: string;
        
        // Routing
        route_name?: string;
        route_uri?: string;
        route_methods?: string[];
        
        // Middleware
        global_middleware?: string[];
        route_middleware?: string[];
        total_count?: number;
        individual_timings?: Record<string, {
          name: string;
          start: number;
          end: number;
          duration_ms: number;
        }>;
        
        // Controller
        controller?: string;
        action?: string;
        route_parameters?: string[];
        
        // Response
        status_code?: number;
        content_type?: string;
        response_size?: number;
        
        // Terminate
        callbacks_executed?: boolean;
      };
    }>;
    
    phase_count: number;
    
    // Résumé de la requête
    summary: {
      controller: string;
      action: string;
      route_name: string;
      method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE" | "OPTIONS" | "HEAD";
      url: string;
      status_code: number;
    };
    
    // Breakdown par phase (pour graphiques)
    breakdown: Record<string, {
      duration_ms: number;
      percentage: number;
    }>;
    
    // Métriques mémoire
    memory: {
      peak_bytes: number;
      peak_mb: number;
      current_bytes: number;
    };
    
    timestamp: string;
  };
}
```

#### Exemple d'utilisation côté Dashboard

```javascript
// Afficher un waterfall chart
const lifecycle = event.data;

lifecycle.phases.forEach(phase => {
  renderBar({
    name: phase.name,
    startX: phase.start_offset_ms,
    width: phase.duration_ms,
    label: `${phase.name}: ${phase.duration_ms.toFixed(1)}ms (${phase.percentage}%)`,
    color: getColorForPhase(phase.name)
  });
});

// Afficher le breakdown en camembert
Object.entries(lifecycle.breakdown).forEach(([phase, data]) => {
  addPieSlice({
    label: phase,
    value: data.percentage,
    tooltip: `${data.duration_ms.toFixed(2)}ms`
  });
});
```

---

## 🔧 Champs Enrichis Automatiquement

Tous les événements sont automatiquement enrichis avec ces champs de contexte :

```typescript
interface EnrichedContext {
  // Identification
  trace_id: string;
  span_id?: string;
  parent_span_id?: string;
  
  // Environnement
  environment: string;
  app_name: string;
  app_version?: string;
  
  // Déploiement
  deployment_hash?: string;
  deployment_hash_short?: string;
  deployment_tag?: string;
  deployment_source?: string;
  deployment_phase?: "warmup" | "post_deploy";
  
  // Horodatage
  timestamp: string; // ISO8601
  timestamp_unix: number;
  
  // User context (si disponible)
  user_id?: number | string;
  user_email?: string;
  user_name?: string;
  
  // Agent metadata
  agent_version: string;
  php_version: string;
  laravel_version: string;
}
```

---

## 📊 Index Recommandés pour le Dashboard

### Indexes Primaires
- `(project_id, type, timestamp)` - Requêtes générales
- `(project_id, trace_id)` - Recherche par trace
- `(project_id, type, name, timestamp)` - Drill-down spécifique

### Indexes Secondaires
- `(project_id, deployment_hash)` - Analyse par déploiement
- `(project_id, user_id, timestamp)` - Activité utilisateur
- `(project_id, type, severity)` - Issues par priorité
- `(project_id, type, status_code)` - Erreurs HTTP

### Partitionnement Recommandé
- Par `timestamp` (quotidien ou hebdomadaire)
- Par `project_id`

---

## 📤 Format d'Envoi au Serveur

```json
{
  "events": [
    { ... event 1 ... },
    { ... event 2 ... },
    ...
  ]
}
```

### Headers HTTP
```
Authorization: Bearer {api_key}
X-Agent-Version: 1.0.0
X-Signature: {hmac_sha256}
X-Timestamp: {unix_timestamp}
Content-Type: application/json
Content-Encoding: gzip (si compression activée)
```

---

*Document généré automatiquement - BaddyBugs Agent PHP v1.0.0*
