# 🎯 Prompt: Service d'Ingestion BaddyBugs - Validation Complète

**Objectif:** S'assurer que le service d'ingestion récupère et stocke TOUTES les données collectées par l'agent PHP BaddyBugs sans aucune perte.

---

## Contexte

Tu travailles sur le service d'ingestion de la plateforme BaddyBugs. L'agent PHP envoie des événements au endpoint `/api/v1/ingest`. Tu dois vérifier et implémenter le code pour :

1. **Parser** correctement TOUS les types d'événements
2. **Valider** la structure de chaque événement
3. **Transformer** les données si nécessaire
4. **Stocker** dans ClickHouse sans perte de données
5. **Indexer** pour des requêtes rapides

---

## Structure Générale des Événements

Chaque événement envoyé par l'agent suit cette structure :

```typescript
interface BaseEvent {
  type: string;           // Type d'événement (voir catalogue ci-dessous)
  name: string;           // Sous-type ou identifiant
  data: Record<string, any>; // Payload spécifique au type
  timestamp: string;      // ISO8601
  trace_id: string;       // UUID pour corrélation
  environment: string;    // local, staging, production
  deployment_hash?: string;
}
```

---

## 📋 CATALOGUE COMPLET DES TYPES D'ÉVÉNEMENTS (42 types)

### 1. `request` - Requêtes HTTP Entrantes
```typescript
{
  type: "request",
  name: "GET /api/users",
  data: {
    method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE" | "OPTIONS" | "HEAD";
    uri: string;
    full_url: string;
    status_code: number;
    duration_ms: number;
    controller: string | null;
    action: string | null;
    route_name: string | null;
    ip: string;
    user_agent: string;
    referer: string | null;
    user_id: number | string | null;
    user_email: string | null;
    headers: Record<string, string>;  // Redactés
    inputs: Record<string, any>;      // Redactés
    memory_usage: number;
    memory_peak: number;
  }
}
```

### 2. `query` - Requêtes SQL
```typescript
{
  type: "query",
  name: "sql",
  data: {
    sql: string;
    bindings: any[];
    time: number;           // ms
    connection: string;
    is_slow: boolean;
    slow_threshold_ms: number;
    file?: string;          // Si performance_mode = false
    line?: number;
    explain?: {             // Si explain_slow_queries = true
      rows: number;
      type: string;
      key: string | null;
    };
  }
}
```

### 3. `exception` - Exceptions Non Gérées
```typescript
{
  type: "exception",
  name: "App\\Exceptions\\PaymentException",
  data: {
    message: string;
    exception_class: string;
    file: string;
    line: number;
    code: string | number;
    trace: Array<{
      file: string;
      line: number;
      function: string;
      class?: string;
      type?: string;
    }>;
    source_code?: Record<string, string>;  // Lignes autour de l'erreur
    fingerprint: string;
    url: string;
    method: string;
    user_id: number | string | null;
    breadcrumbs: Array<{
      type: string;
      message: string;
      timestamp: number;
      data?: Record<string, any>;
    }>;
    severity: "error" | "warning" | "info" | "critical";
    handled: boolean;
    previous?: {
      class: string;
      message: string;
    };
  }
}
```

### 4. `handled_exception` - Exceptions Attrapées
```typescript
{
  type: "handled_exception",
  name: string,
  data: {
    exception_class: string;
    message: string;
    file: string;
    line: number;
    code: string | number | null;
    trace: Array<{...}>;
    source_code?: Record<string, string>;
    fingerprint: string;
    severity: "error" | "warning" | "info" | "critical";
    handled: true;
    context?: Record<string, any>;
    url: string;
    method: string;
    user_id?: number | string;
    previous?: {...};
    breadcrumbs: Array<{...}>;
  }
}
```

### 5. `job` - Jobs de Queue
```typescript
{
  type: "job",
  name: "App\\Jobs\\ProcessOrder",
  data: {
    status: "processing" | "processed" | "failed";
    job_class: string;
    job_id: string;
    queue: string;
    connection: string;
    attempts: number;
    max_tries: number;
    wait_time_ms?: number;    // Temps en queue
    duration_ms?: number;     // Temps de traitement
    payload?: Record<string, any>;
    exception?: string;       // Si failed
    exception_message?: string;
  }
}
```

### 6. `command` - Commandes Artisan
```typescript
{
  type: "command",
  name: "starting" | "finished",
  data: {
    command: string;
    arguments: Record<string, any>;
    options: Record<string, any>;
    exit_code?: number;       // Si finished
    duration_ms?: number;
  }
}
```

### 7. `scheduled_task` - Tâches Planifiées
```typescript
{
  type: "scheduled_task",
  name: "starting" | "finished" | "failed" | "skipped",
  data: {
    task: string;
    expression: string;
    description?: string;
    without_overlapping: boolean;
    run_in_background: boolean;
    duration_ms?: number;
    exit_code?: number;
    output?: string;
    exception?: string;
    skip_reason?: string;
  }
}
```

### 8. `cache` - Opérations Cache
```typescript
{
  type: "cache",
  name: "hit" | "miss" | "write" | "forget",
  data: {
    key: string;
    store: string;
    tags?: string[];
    ttl?: number;        // Pour write
  }
}
```

### 9. `mail` - Emails Envoyés
```typescript
{
  type: "mail",
  name: string,
  data: {
    mailable: string;
    subject: string;
    to: string[];
    cc?: string[];
    bcc?: string[];
    queued: boolean;
  }
}
```

### 10. `notification` - Notifications
```typescript
{
  type: "notification",
  name: "sent" | "failed",
  data: {
    notification: string;
    channel: string;
    notifiable_type: string;
    notifiable_id: string | number;
    response?: any;
    exception?: string;
  }
}
```

### 11. `event` - Événements Laravel
```typescript
{
  type: "event",
  name: string,
  data: {
    event_class: string;
    listeners: string[];
    broadcast: boolean;
    payload_keys: string[];
  }
}
```

### 12. `log` - Logs Applicatifs
```typescript
{
  type: "log",
  name: "emergency" | "alert" | "critical" | "error" | "warning",
  data: {
    level: string;
    message: string;
    context: Record<string, any>;
    channel: string;
  }
}
```

### 13. `http_client` - Requêtes HTTP Sortantes
```typescript
{
  type: "http_client",
  name: "GET https://api.example.com/users",
  data: {
    method: string;
    url: string;
    host: string;
    path: string;
    status_code: number;
    duration_ms: number;
    is_slow: boolean;
    success: boolean;
    parent_trace_id: string;
    outbound_trace_id?: string;
    request_body?: any;    // Si configuré
    response_body?: any;   // Si configuré
  }
}
```

### 14. `model` - Opérations Eloquent
```typescript
{
  type: "model",
  name: "created" | "updated" | "deleted" | "restored",
  data: {
    model: string;
    key: string | number;
    changes?: Record<string, { old: any; new: any }>;
    original?: Record<string, any>;
  }
}
```

### 15. `gate` - Vérifications d'Autorisation
```typescript
{
  type: "gate",
  name: string,
  data: {
    ability: string;
    result: boolean;
    arguments: any[];
    user_id?: number | string;
  }
}
```

### 16. `redis` - Commandes Redis
```typescript
{
  type: "redis",
  name: string,
  data: {
    command: string;
    parameters: any[];
    time: number;
    connection: string;
  }
}
```

### 17. `livewire_component` - Composants Livewire
```typescript
{
  type: "livewire_component",
  name: "initialized",
  data: {
    component: string;
    component_id: string;
    url: string;
    user_id?: number | string;
  }
}
```

### 18. `livewire_performance` - Performance Livewire
```typescript
{
  type: "livewire_performance",
  name: "slow_request",
  data: {
    component: string;
    duration_ms: number;
    threshold_ms: number;
    updates: Array<{ type: string; payload: any }>;
  }
}
```

### 19. `livewire_error` - Erreurs Livewire
```typescript
{
  type: "livewire_error",
  name: "message_failed" | "dehydration_exception",
  data: {
    component: string;
    response_status?: number;
    calls?: Array<{ method: string; params: any[] }>;
    exception?: string;
  }
}
```

### 20. `security` - Issues de Sécurité
```typescript
{
  type: "security",
  name: "security_issue" | "dangerous_usage" | "composer_packages",
  data: {
    // Pour security_issue:
    sensitive_data_findings?: Array<{
      type: string;
      field: string;
      severity: "low" | "medium" | "high" | "critical";
    }>;
    
    // Pour dangerous_usage:
    issues?: string[];
    environment?: string;
    
    // Pour composer_packages:
    vulnerabilities?: Array<{
      package: string;
      version: string;
      advisory: string;
      severity: string;
    }>;
  }
}
```

### 21. `security_threat` - Menaces Détectées
```typescript
{
  type: "security_threat",
  name: "detection",
  data: {
    threat_type: "sql_injection" | "xss" | "path_traversal" | "command_injection" | "ssrf";
    severity: "low" | "medium" | "high" | "critical";
    field: string;
    value: string;    // Tronqué
    url: string;
    ip: string;
    user_agent: string;
    user_id?: number | string;
  }
}
```

### 22. `view` - Rendu des Vues
```typescript
{
  type: "view",
  name: "slow_view" | "rendered",
  data: {
    view: string;
    duration_ms: number;
    is_slow: boolean;
    data_keys: string[];
  }
}
```

### 23. `middleware` - Performance Middleware
```typescript
{
  type: "middleware",
  name: "stack_executed" | "individual",
  data: {
    // Pour stack_executed:
    middlewares?: string[];
    total_duration_ms?: number;
    
    // Pour individual:
    middleware?: string;
    duration_ms?: number;
  }
}
```

### 24. `timeline` - Timeline d'une Trace
```typescript
{
  type: "timeline",
  name: "trace_timeline",
  data: {
    trace_id: string;
    event_count: number;
    total_duration_ms: number;
    events: Array<{
      type: string;
      name: string;
      timestamp: number;
      relative_time_ms: number;
      data: Record<string, any>;
    }>;
    detail_level: "basic" | "detailed" | "full";
  }
}
```

### 25. `trace_span` - Spans pour Waterfall
```typescript
{
  type: "trace_span",
  name: "bootstrap" | "middleware" | "controller" | "sending",
  data: {
    trace_id: string;
    span_name: string;
    duration_ms: number;
    start_offset_ms: number;
    percentage: number;
    additional_data?: Record<string, any>;
  }
}
```

### 26. `feature` - Analytics Produit
```typescript
{
  type: "feature",
  name: "route.accessed" | "job.executed" | "feature.used" | "custom.event",
  data: {
    identifier: string;
    user_id?: number | string;
    metadata?: Record<string, any>;
    count?: number;
  }
}
```

### 27. `health` - Santé Système
```typescript
{
  type: "health",
  name: "scheduled_task_*" | "stuck_job_detected" | "queue_metrics" | "heartbeat",
  data: {
    // Varie selon le sous-type
    job_id?: string;
    queue?: string;
    duration_seconds?: number;
    severity?: string;
    queues?: Record<string, { pending: number; processing: number; failed: number }>;
  }
}
```

### 28. `profiling_segment` - Segments de Profiling
```typescript
{
  type: "profiling_segment",
  name: string,
  data: {
    segment_name: string;
    duration_ms: number;
    memory_start: number;
    memory_end: number;
    memory_peak: number;
    additional_data?: Record<string, any>;
  }
}
```

### 29. `test` - Tests PHPUnit/Pest
```typescript
{
  type: "test",
  name: "started" | "finished",
  data: {
    test_name: string;
    test_class: string;
    status?: "passed" | "failed" | "skipped" | "incomplete";
    duration_ms?: number;
    exception?: string;
    assertions?: number;
  }
}
```

### 30. `llm_request` - Requêtes LLM
```typescript
{
  type: "llm_request",
  name: "openai:gpt-4" | "anthropic:claude-3" | ...,
  data: {
    provider: string;
    model: string;
    prompt?: string;       // Si track_prompts = true
    response?: string;     // Si track_responses = true
    usage: {
      prompt_tokens: number;
      completion_tokens: number;
      total_tokens: number;
    };
    cost?: number;         // Estimé en USD
    duration_ms: number;
    status: "success" | "error";
    error?: string;
  }
}
```

### 31. `eloquent` - Métriques Eloquent
```typescript
{
  type: "eloquent",
  name: "usage_summary",
  data: {
    models: Record<string, {
      created: number;
      updated: number;
      deleted: number;
      retrieved: number;
    }>;
    eager_loads: string[];
    lazy_loads: string[];
    slow_queries: number;
  }
}
```

### 32. `form` - Soumissions Formulaires
```typescript
{
  type: "form",
  name: "submission",
  data: {
    form_id?: string;
    url: string;
    method: string;
    fields: string[];       // Noms des champs (sans valeurs)
    validation_errors?: string[];
    duration_ms: number;
    user_id?: number | string;
  }
}
```

### 33. `file_upload` - Uploads Fichiers
```typescript
{
  type: "file_upload",
  name: "upload_batch",
  data: {
    files: Array<{
      name: string;
      size: number;
      mime_type: string;
      extension: string;
      disk: string;
      path: string;
    }>;
    total_size: number;
    file_count: number;
    user_id?: number | string;
  }
}
```

### 34. `queue_metrics` - Métriques Queue
```typescript
{
  type: "queue_metrics",
  name: "snapshot",
  data: {
    queues: Record<string, {
      pending: number;
      processing: number;
      failed: number;
      delayed: number;
    }>;
    total_workers: number;
    jobs_per_minute: number;
    avg_wait_time_ms: number;
  }
}
```

### 35. `issue` - Problèmes Détectés
```typescript
{
  type: "issue",
  name: "n_plus_one",
  data: {
    query: string;
    count: number;
    threshold: number;
    location: string;
    severity: "low" | "medium" | "high";
  }
}
```

### 36. `regression` - Données de Régression
```typescript
{
  type: "regression",
  name: "baseline_snapshot",
  data: {
    duration_ms: number;
    query_count: number;
    memory_peak: number;
    error_flag: 0 | 1;
  }
}
```

### 37. `auth` - Authentification
```typescript
{
  type: "auth",
  name: "login" | "logout" | "login_failed" | "lockout" | "password_reset" | 
        "registered" | "email_verified" | "2fa_challenged" | "2fa_verified" | 
        "impersonation_started" | "impersonation_stopped",
  data: {
    user_id?: number | string;
    email?: string;
    guard: string;
    remember?: boolean;
    ip: string;
    user_agent: string;
    reason?: string;          // Pour login_failed
    lockout_seconds?: number; // Pour lockout
    severity?: string;
  }
}
```

### 38. `broadcast` - WebSocket/Pusher
```typescript
{
  type: "broadcast",
  name: "event_broadcasted" | "channel_authorized" | "channel_denied" | "presence_update",
  data: {
    event?: string;
    channels?: string[];
    channel?: string;
    user_id?: number | string;
    channel_type?: "public" | "private" | "presence";
    members?: number;
  }
}
```

### 39. `rate_limit` - Rate Limiting
```typescript
{
  type: "rate_limit",
  name: "exceeded" | "usage" | "hit",
  data: {
    key: string;
    limit: number;
    remaining: number;
    reset_at: string;
    ip?: string;
    user_id?: number | string;
    route?: string;
  }
}
```

### 40. `session` - Analytics Session
```typescript
{
  type: "session",
  name: "analytics" | "regenerated" | "invalidated",
  data: {
    session_id: string;      // Hash
    user_id?: number | string;
    duration_seconds?: number;
    page_views?: number;
    driver: string;
  }
}
```

### 41. `translation` - Traductions
```typescript
{
  type: "translation",
  name: "locale_changed" | "missing_keys",
  data: {
    locale?: string;
    previous_locale?: string;
    missing_keys?: string[];
    namespace?: string;
  }
}
```

### 42. `route` - Analytics Routes
```typescript
{
  type: "route",
  name: "404_not_found" | "redirect" | "model_binding",
  data: {
    url: string;
    method: string;
    referer?: string;
    redirect_to?: string;
    redirect_status?: number;
    model?: string;
    key?: string;
    found?: boolean;
  }
}
```

### 43. `validation` - Statistiques Validation
```typescript
{
  type: "validation",
  name: "summary",
  data: {
    url: string;
    method: string;
    rules_count: number;
    failed_rules: string[];
    passed: boolean;
  }
}
```

### 44. `filesystem` - Opérations Fichiers
```typescript
{
  type: "filesystem",
  name: "disk_usage" | "operations",
  data: {
    disk: string;
    operation?: "read" | "write" | "delete" | "copy" | "move";
    path?: string;
    size?: number;
    duration_ms?: number;
    
    // Pour disk_usage:
    total_bytes?: number;
    used_bytes?: number;
    free_bytes?: number;
  }
}
```

### 45. `database` - Connexions DB
```typescript
{
  type: "database",
  name: "transaction" | "connection_metrics" | "deadlock",
  data: {
    connection: string;
    
    // Pour transaction:
    status?: "committed" | "rolled_back";
    duration_ms?: number;
    is_slow?: boolean;
    
    // Pour connection_metrics:
    active_connections?: number;
    open_transactions?: number;
    pool?: {
      total?: number;
      active?: number;
      idle?: number;
    };
    
    // Pour deadlock:
    message?: string;
    exception?: string;
    severity?: "critical";
  }
}
```

### 46. `memory` - Usage Mémoire
```typescript
{
  type: "memory",
  name: "snapshot",
  data: {
    current_bytes: number;
    peak_bytes: number;
    limit_bytes: number;
    percentage_used: number;
  }
}
```

### 47. `lifecycle` - Lifecycle HTTP Complet + Waterfall
```typescript
{
  type: "lifecycle",
  name: "http_request",
  data: {
    trace_id: string;
    total_duration_ms: number;
    
    request: {
      method: string;
      url: string;
      full_url: string;
      status_code: number;
    };
    
    phases: Array<{
      name: "BOOTSTRAP" | "MIDDLEWARE" | "CONTROLLER";
      label?: string;
      duration_ms: number;
      start_offset_ms: number;
      percentage: number;
    }>;
    
    spans: Array<{
      span_id: number;
      trace_id: string;
      type: "QUERY" | "CACHE_HIT" | "CACHE_MISS" | "CACHE_WRITE" | "OUTGOING_REQUEST" | "JOB_DISPATCHED";
      label: string;
      duration_ms: number;
      start_offset_ms: number;
      depth: number;
      
      // Type-specific:
      sql?: string;
      bindings?: any[];
      connection?: string;
      key?: string;
      method?: string;
      url?: string;
      status_code?: number;
      job_class?: string;
    }>;
    
    counts: {
      queries: number;
      cache_hits: number;
      cache_misses: number;
      cache_writes: number;
      outgoing_requests: number;
      jobs_dispatched: number;
    };
    
    route: {
      name: string | null;
      uri: string;
      methods: string[];
      parameters: string[];
    };
    
    controller: {
      class: string;
      action: string;
      full: string;
    };
    
    middleware: {
      global: string[];
      route: string[];
      total_count: number;
    };
    
    breakdown: {
      bootstrap: { duration_ms: number; percentage: number };
      middleware: { duration_ms: number; percentage: number };
      controller: { duration_ms: number; percentage: number };
    };
    
    memory: {
      peak_bytes: number;
      peak_mb: number;
      current_bytes: number;
      current_mb: number;
    };
    
    environment: {
      php_version: string;
      laravel_version: string;
      sapi: string;
    };
  }
}
```

---

## 🗄️ SCHÉMA CLICKHOUSE

### Table principale: `events`

```sql
CREATE TABLE events (
    -- Identifiants
    id UUID DEFAULT generateUUIDv4(),
    trace_id UUID,
    organization_id UUID,
    application_id UUID,
    environment_id UUID,
    
    -- Type et classification
    type LowCardinality(String),
    name String,
    
    -- Timing
    timestamp DateTime64(3),
    duration_ms Float64,
    
    -- Données JSON
    data String,  -- JSON compressé
    
    -- Métadonnées dénormalisées pour filtrage rapide
    user_id String,
    status_code UInt16,
    is_error UInt8,
    severity LowCardinality(String),
    
    -- Pour corrélation
    request_id String,
    job_id String,
    
    -- Indexation
    INDEX idx_type type TYPE set(100) GRANULARITY 4,
    INDEX idx_user_id user_id TYPE bloom_filter GRANULARITY 4,
    INDEX idx_is_error is_error TYPE minmax GRANULARITY 4
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (organization_id, application_id, environment_id, type, timestamp)
TTL timestamp + INTERVAL 90 DAY;
```

### Table spans: `lifecycle_spans`

```sql
CREATE TABLE lifecycle_spans (
    id UUID DEFAULT generateUUIDv4(),
    trace_id UUID,
    organization_id UUID,
    application_id UUID,
    
    -- Span info
    span_id UInt32,
    span_type LowCardinality(String),  -- QUERY, CACHE_HIT, etc.
    label String,
    
    -- Timing
    timestamp DateTime64(3),
    duration_ms Float64,
    start_offset_ms Float64,
    depth UInt8,
    
    -- Type-specific (nullable)
    sql Nullable(String),
    cache_key Nullable(String),
    http_url Nullable(String),
    http_method Nullable(String),
    http_status Nullable(UInt16)
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (trace_id, start_offset_ms);
```

### Table exceptions: `exceptions`

```sql
CREATE TABLE exceptions (
    id UUID DEFAULT generateUUIDv4(),
    trace_id UUID,
    organization_id UUID,
    application_id UUID,
    environment_id UUID,
    
    -- Exception info
    exception_class String,
    message String,
    file String,
    line UInt32,
    fingerprint String,
    
    -- Stack trace (JSON)
    trace String,
    source_code Nullable(String),
    breadcrumbs Nullable(String),
    
    -- Metadata
    timestamp DateTime64(3),
    severity LowCardinality(String),
    handled UInt8,
    user_id Nullable(String),
    url String,
    method LowCardinality(String),
    
    -- Grouping
    first_seen DateTime64(3),
    last_seen DateTime64(3),
    occurrence_count UInt64,
    
    INDEX idx_fingerprint fingerprint TYPE bloom_filter GRANULARITY 4,
    INDEX idx_class exception_class TYPE set(1000) GRANULARITY 4
)
ENGINE = ReplacingMergeTree(last_seen)
PARTITION BY toYYYYMM(timestamp)
ORDER BY (organization_id, fingerprint, timestamp);
```

---

## ✅ CHECKLIST DE VALIDATION

Pour CHAQUE type d'événement, vérifie que :

1. [ ] Le type est reconnu et routé vers le bon handler
2. [ ] Tous les champs du schéma sont extraits
3. [ ] Les champs optionnels sont gérés (nullable)
4. [ ] Les types sont corrects (number, string, array, object)
5. [ ] Les données sont validées avant insertion
6. [ ] Les données sensibles sont déjà redactées par l'agent
7. [ ] Le JSON "data" est correctement sérialisé
8. [ ] Les index sont utilisés pour les requêtes fréquentes
9. [ ] Le partitionnement permet une purge efficace
10. [ ] Les métriques (counts, durations) sont dénormalisées

---

## 🔧 IMPLÉMENTATION REQUISE

1. **Parser d'événements** : Classe qui parse le JSON entrant et route vers le bon handler

2. **Handlers par type** : Un handler pour chaque catégorie d'événements

3. **Validation** : Validation du schéma pour chaque type

4. **Dénormalisation** : Extraction des champs fréquemment filtrés

5. **Insertion en batch** : Buffering et insertion par lots dans ClickHouse

6. **Gestion des erreurs** : Log des événements malformés sans bloquer

7. **Tests** : Tests unitaires pour CHAQUE type d'événement

---

## 📊 MÉTRIQUES À MONITORER

- Événements reçus par seconde par type
- Événements rejetés (malformés)
- Latence d'insertion
- Taille des batches
- Utilisation mémoire des buffers

---

**IMPORTANT:** Assure-toi qu'AUCUN champ n'est perdu lors de l'ingestion. Le schéma `data` JSON doit contenir TOUTES les données originales, même si certains champs sont aussi dénormalisés pour l'indexation.
