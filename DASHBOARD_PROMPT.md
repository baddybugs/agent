# 🎯 Prompt: Dashboard BaddyBugs - Affichage Complet des Données

**Objectif:** S'assurer que TOUTES les données collectées par l'agent PHP BaddyBugs sont correctement affichées dans le dashboard, sans aucune donnée cachée ou inaccessible.

---

## Contexte

Tu travailles sur le dashboard BaddyBugs (Laravel + Inertia + Vue 3). Le service d'ingestion stocke les données dans ClickHouse. Tu dois vérifier et implémenter l'interface utilisateur pour :

1. **Afficher** toutes les données collectées
2. **Visualiser** avec les graphiques appropriés
3. **Filtrer** et rechercher efficacement
4. **Naviguer** entre les vues corrélées
5. **Alerter** sur les problèmes détectés

---

## 📊 STRUCTURE DU DASHBOARD

### Navigation Principale

```
├── Overview (Dashboard principal)
├── Requests
│   ├── Liste des requêtes
│   └── Détail requête (avec waterfall)
├── Exceptions
│   ├── Liste des exceptions
│   ├── Détail exception
│   └── Groupes (par fingerprint)
├── Performance
│   ├── Vue d'ensemble
│   ├── Slow Queries
│   ├── N+1 Issues
│   └── Slow Endpoints
├── Database
│   ├── Queries
│   ├── Transactions
│   ├── Connections
│   └── Deadlocks
├── Jobs & Queue
│   ├── Jobs
│   ├── Scheduled Tasks
│   └── Queue Metrics
├── Security
│   ├── Threats
│   ├── Auth Events
│   ├── Rate Limits
│   └── Vulnerabilities
├── Integrations
│   ├── HTTP Clients
│   ├── LLM Requests
│   ├── Mail & Notifications
│   └── Livewire
├── Analytics
│   ├── Routes
│   ├── Features
│   ├── Sessions
│   └── Forms
└── System
    ├── Logs
    ├── Health
    ├── Events
    └── Memory
```

---

## 📋 CHECKLIST PAR TYPE D'ÉVÉNEMENT

### 1. `request` - Requêtes HTTP

**Page:** `/requests`

| Donnée | Affichage | Visualisation |
|--------|-----------|---------------|
| method | Badge coloré (GET=vert, POST=bleu, etc.) | - |
| uri | Lien cliquable vers détail | - |
| status_code | Badge (2xx=vert, 4xx=orange, 5xx=rouge) | - |
| duration_ms | Valeur + barre de progression | Histogramme |
| controller | Texte | - |
| action | Texte | - |
| route_name | Texte | - |
| ip | Texte (avec géolocalisation optionnelle) | - |
| user_agent | Icône navigateur + tooltip | - |
| user_id | Lien vers profil utilisateur | - |
| user_email | Texte | - |
| headers | Tableau accordéon | - |
| inputs | Tableau accordéon (JSON viewer) | - |
| memory_usage | Valeur formatée (MB) | - |
| memory_peak | Valeur formatée (MB) | Sparkline |

**Graphiques:**
- [ ] Requêtes par minute (line chart)
- [ ] Distribution des status codes (pie chart)
- [ ] P50/P95/P99 response time (line chart)
- [ ] Top 10 endpoints les plus lents (bar chart)
- [ ] Top 10 endpoints les plus fréquents (bar chart)

**Filtres:**
- [ ] Date range
- [ ] Method (GET, POST, etc.)
- [ ] Status code (2xx, 4xx, 5xx)
- [ ] Route/Controller
- [ ] User ID
- [ ] Duration (> X ms)

---

### 2. `query` - Requêtes SQL

**Page:** `/database/queries`

| Donnée | Affichage | Visualisation |
|--------|-----------|---------------|
| sql | Code SQL avec syntax highlighting | - |
| bindings | Tableau JSON | - |
| time | Valeur colorée (rouge si slow) | Histogramme |
| connection | Badge | - |
| is_slow | Indicateur visuel (🐢) | - |
| file | Lien vers source | - |
| line | Numéro de ligne | - |
| explain | Tableau formaté | - |

**Graphiques:**
- [ ] Queries par minute (line chart)
- [ ] Distribution du temps d'exécution (histogram)
- [ ] Top 10 queries les plus lentes (table)
- [ ] Top 10 queries les plus fréquentes (table)
- [ ] Queries par connexion (pie chart)

**Fonctionnalités spéciales:**
- [ ] Formatage SQL automatique
- [ ] Copier la query avec bindings
- [ ] Voir le EXPLAIN plan
- [ ] Lien vers la requête HTTP parente

---

### 3. `exception` / `handled_exception` - Exceptions

**Page:** `/exceptions`

| Donnée | Affichage | Visualisation |
|--------|-----------|---------------|
| exception_class | Titre principal | - |
| message | Sous-titre | - |
| file | Lien cliquable | - |
| line | Badge | - |
| code | Badge | - |
| trace | Stack trace interactive | - |
| source_code | Code viewer avec highlighting | - |
| fingerprint | Badge (pour grouping) | - |
| url | Lien | - |
| method | Badge | - |
| user_id | Lien utilisateur | - |
| breadcrumbs | Timeline verticale | - |
| severity | Badge coloré | - |
| handled | Indicateur | - |
| previous | Exception chaînée (accordéon) | - |
| context | JSON viewer | - |

**Graphiques:**
- [ ] Exceptions par heure (line chart)
- [ ] Distribution par severity (pie chart)
- [ ] Top 10 exceptions (table avec count)
- [ ] Exceptions par utilisateur (si pertinent)
- [ ] Nouveaux vs récurrents (stacked bar)

**Fonctionnalités spéciales:**
- [ ] Grouping par fingerprint
- [ ] Marquer comme résolu
- [ ] Assigner à un membre
- [ ] Créer issue (Jira/GitHub/GitLab)
- [ ] Session replay (si disponible)
- [ ] Comparaison avec versions précédentes

---

### 4. `job` - Jobs de Queue

**Page:** `/jobs`

| Donnée | Affichage | Visualisation |
|--------|-----------|---------------|
| status | Badge (processing=jaune, processed=vert, failed=rouge) | - |
| job_class | Lien vers détail | - |
| job_id | Texte | - |
| queue | Badge | - |
| connection | Texte | - |
| attempts | Compteur | - |
| max_tries | Compteur | - |
| wait_time_ms | Valeur formatée | Histogramme |
| duration_ms | Valeur formatée | Histogramme |
| payload | JSON viewer (accordéon) | - |
| exception | Message d'erreur | - |
| exception_message | Détails | - |

**Graphiques:**
- [ ] Jobs par heure (stacked: success/failed)
- [ ] Distribution par queue (pie chart)
- [ ] Temps d'attente moyen (line chart)
- [ ] Temps de traitement moyen (line chart)
- [ ] Taux d'échec (line chart)

---

### 5. `command` - Commandes Artisan

**Page:** `/system/commands`

| Donnée | Affichage |
|--------|-----------|
| command | Titre |
| arguments | Tableau |
| options | Tableau |
| exit_code | Badge (0=vert, autre=rouge) |
| duration_ms | Valeur formatée |

---

### 6. `scheduled_task` - Tâches Planifiées

**Page:** `/jobs/scheduled`

| Donnée | Affichage |
|--------|-----------|
| task | Titre |
| expression | Badge CRON |
| description | Texte |
| without_overlapping | Indicateur |
| run_in_background | Indicateur |
| duration_ms | Valeur |
| exit_code | Badge |
| output | Code block |
| exception | Message d'erreur |
| skip_reason | Texte |

**Graphiques:**
- [ ] Timeline des exécutions (Gantt chart)
- [ ] Success/failure rate par tâche

---

### 7. `cache` - Opérations Cache

**Page:** `/performance/cache`

| Donnée | Affichage |
|--------|-----------|
| key | Texte (tronqué) |
| store | Badge |
| tags | Tags |
| ttl | Durée formatée |

**Graphiques:**
- [ ] Hit ratio (gauge)
- [ ] Hits vs Misses (line chart)
- [ ] Top keys (table)
- [ ] Distribution par store (pie chart)

**Métriques clés:**
- [ ] Hit ratio global
- [ ] Nombre d'opérations/min
- [ ] Top 10 keys manquées (pour optimisation)

---

### 8. `mail` - Emails

**Page:** `/integrations/mail`

| Donnée | Affichage |
|--------|-----------|
| mailable | Classe |
| subject | Texte |
| to | Liste emails |
| cc | Liste emails |
| bcc | Liste emails |
| queued | Indicateur |

---

### 9. `notification` - Notifications

**Page:** `/integrations/notifications`

| Donnée | Affichage |
|--------|-----------|
| notification | Classe |
| channel | Badge |
| notifiable_type | Texte |
| notifiable_id | Lien |
| response | JSON |
| exception | Erreur |

---

### 10. `event` - Événements Laravel

**Page:** `/system/events`

| Donnée | Affichage |
|--------|-----------|
| event_class | Titre |
| listeners | Liste |
| broadcast | Indicateur |
| payload_keys | Tags |

---

### 11. `log` - Logs

**Page:** `/system/logs`

| Donnée | Affichage |
|--------|-----------|
| level | Badge coloré |
| message | Texte |
| context | JSON viewer |
| channel | Badge |

**Niveaux de couleur:**
- emergency: rouge foncé
- alert: rouge
- critical: orange foncé
- error: orange
- warning: jaune

---

### 12. `http_client` - Requêtes HTTP Sortantes

**Page:** `/integrations/http-clients`

| Donnée | Affichage |
|--------|-----------|
| method | Badge |
| url | Lien (externe) |
| host | Texte |
| status_code | Badge coloré |
| duration_ms | Valeur |
| is_slow | Indicateur 🐢 |
| success | Indicateur |
| request_body | JSON viewer |
| response_body | JSON viewer |

**Graphiques:**
- [ ] Requêtes par hôte (pie chart)
- [ ] Latence par hôte (bar chart)
- [ ] Taux de succès (gauge)

---

### 13. `model` - Opérations Eloquent

**Page:** `/database/models`

| Donnée | Affichage |
|--------|-----------|
| model | Classe |
| key | ID |
| changes | Diff viewer (old → new) |
| original | JSON viewer |

**Graphiques:**
- [ ] Opérations par modèle (stacked bar)
- [ ] CRUD distribution (pie chart)

---

### 14. `gate` - Autorisations

**Page:** `/security/gates`

| Donnée | Affichage |
|--------|-----------|
| ability | Texte |
| result | Badge (granted=vert, denied=rouge) |
| arguments | JSON |
| user_id | Lien |

---

### 15. `redis` - Commandes Redis

**Page:** `/database/redis`

| Donnée | Affichage |
|--------|-----------|
| command | Code |
| parameters | JSON |
| time | Valeur |
| connection | Badge |

---

### 16-18. `livewire_*` - Livewire

**Page:** `/integrations/livewire`

| Donnée | Affichage |
|--------|-----------|
| component | Classe |
| component_id | ID |
| url | Lien |
| duration_ms | Valeur |
| updates | JSON |
| calls | Tableau |

**Graphiques:**
- [ ] Composants les plus utilisés
- [ ] Composants les plus lents
- [ ] Erreurs par composant

---

### 19-21. `security*` - Sécurité

**Page:** `/security`

#### Threats

| Donnée | Affichage |
|--------|-----------|
| threat_type | Badge coloré |
| severity | Badge |
| field | Texte |
| value | Texte (tronqué) |
| ip | Texte + géolocalisation |
| user_agent | Texte |

**Alertes visuelles:**
- [ ] Bannière d'alerte pour menaces critiques
- [ ] Notification en temps réel

---

### 22. `view` - Rendu des Vues

**Page:** `/performance/views`

| Donnée | Affichage |
|--------|-----------|
| view | Nom du fichier |
| duration_ms | Valeur |
| is_slow | Indicateur |
| data_keys | Tags |

---

### 23. `middleware` - Performance Middleware

**Page:** `/performance/middleware`

| Donnée | Affichage |
|--------|-----------|
| middlewares | Liste |
| total_duration_ms | Valeur |
| individual timings | Tableau avec barres |

---

### 24-25. `timeline` / `trace_span` - Timeline

**Page:** Intégré dans détail requête

**Visualisation:**
- [ ] **Waterfall Chart** style Nightwatch
  - Barres horizontales pour chaque span
  - Positionnées selon start_offset_ms
  - Longueur proportionnelle à duration_ms
  - Couleur selon le type (QUERY, CACHE, HTTP, etc.)
  - Nesting visuel selon depth

---

### 26. `feature` - Analytics Produit

**Page:** `/analytics/features`

| Donnée | Affichage |
|--------|-----------|
| identifier | Texte |
| user_id | Lien |
| metadata | JSON |
| count | Compteur |

---

### 27. `health` - Santé Système

**Page:** `/system/health`

**Dashboard cards:**
- [ ] Status global (vert/orange/rouge)
- [ ] Jobs bloqués
- [ ] Queue depth
- [ ] Dernière exécution des tâches planifiées

---

### 28. `profiling_segment` - Profiling

**Page:** Intégré dans détail requête

| Donnée | Affichage |
|--------|-----------|
| segment_name | Titre |
| duration_ms | Barre + valeur |
| memory_start/end | Diff |
| memory_peak | Valeur |

---

### 29. `test` - Tests

**Page:** `/system/tests` (optionnel)

| Donnée | Affichage |
|--------|-----------|
| test_name | Titre |
| test_class | Sous-titre |
| status | Badge |
| duration_ms | Valeur |
| assertions | Compteur |

---

### 30. `llm_request` - Requêtes LLM

**Page:** `/integrations/llm`

| Donnée | Affichage |
|--------|-----------|
| provider | Badge avec logo |
| model | Texte |
| prompt | Code block (tronqué) |
| response | Code block (tronqué) |
| usage.prompt_tokens | Compteur |
| usage.completion_tokens | Compteur |
| usage.total_tokens | Compteur |
| cost | Valeur $ |
| duration_ms | Valeur |
| status | Badge |
| error | Message |

**Graphiques:**
- [ ] Coût par jour (line chart)
- [ ] Tokens par modèle (pie chart)
- [ ] Latence par modèle (bar chart)

---

### 31. `eloquent` - Métriques Eloquent

**Page:** `/database/eloquent`

**Dashboard:**
- [ ] CRUD par modèle (heatmap)
- [ ] Eager vs Lazy loads (ratio)
- [ ] Slow queries count

---

### 32. `form` - Formulaires

**Page:** `/analytics/forms`

| Donnée | Affichage |
|--------|-----------|
| form_id | ID |
| url | Lien |
| method | Badge |
| fields | Liste |
| validation_errors | Liste rouge |
| duration_ms | Valeur |

---

### 33. `file_upload` - Uploads

**Page:** `/analytics/uploads`

| Donnée | Affichage |
|--------|-----------|
| files | Tableau |
| total_size | Valeur formatée |
| file_count | Compteur |

---

### 34. `queue_metrics` - Métriques Queue

**Page:** `/jobs/metrics`

**Dashboard temps réel:**
- [ ] Gauge par queue (pending, processing, failed)
- [ ] Workers actifs
- [ ] Jobs/minute
- [ ] Temps d'attente moyen

---

### 35. `issue` - Problèmes N+1

**Page:** `/performance/issues`

| Donnée | Affichage |
|--------|-----------|
| query | SQL |
| count | Compteur (avec seuil) |
| location | Lien vers code |
| severity | Badge |

**Actions:**
- [ ] Voir les occurrences
- [ ] Marquer comme résolu/ignoré

---

### 36. `regression` - Régressions

**Page:** `/performance/baselines`

| Donnée | Affichage |
|--------|-----------|
| duration_ms | Comparaison avec baseline |
| query_count | Comparaison |
| memory_peak | Comparaison |
| error_flag | Indicateur |

---

### 37. `auth` - Authentification

**Page:** `/security/auth`

| Donnée | Affichage |
|--------|-----------|
| user_id | Lien |
| email | Texte |
| guard | Badge |
| ip | Texte + géoloc |
| user_agent | Browser icon |
| reason | Texte (si failed) |
| lockout_seconds | Durée |

**Graphiques:**
- [ ] Logins par heure
- [ ] Failed attempts (alerte si spike)
- [ ] Distribution par guard

---

### 38. `broadcast` - WebSocket

**Page:** `/integrations/broadcast`

| Donnée | Affichage |
|--------|-----------|
| event | Texte |
| channels | Liste |
| channel_type | Badge |
| members | Compteur (presence) |

---

### 39. `rate_limit` - Rate Limiting

**Page:** `/security/rate-limits`

| Donnée | Affichage |
|--------|-----------|
| key | Texte |
| limit | Valeur |
| remaining | Gauge |
| reset_at | Countdown |
| route | Lien |

**Alertes:**
- [ ] Top IPs bloquées
- [ ] Routes les plus limitées

---

### 40. `session` - Sessions

**Page:** `/analytics/sessions`

| Donnée | Affichage |
|--------|-----------|
| session_id | ID (hash) |
| user_id | Lien |
| duration_seconds | Durée formatée |
| page_views | Compteur |
| driver | Badge |

---

### 41. `translation` - Traductions

**Page:** `/system/translations`

| Donnée | Affichage |
|--------|-----------|
| locale | Drapeau + code |
| missing_keys | Liste cliquable |
| namespace | Badge |

---

### 42. `route` - Analytics Routes

**Page:** `/analytics/routes`

#### 404 Not Found
| Donnée | Affichage |
|--------|-----------|
| url | Texte |
| referer | Lien |

**Top 404:**
- [ ] URLs les plus fréquentes

---

### 43. `validation` - Validation

**Page:** `/analytics/validation`

| Donnée | Affichage |
|--------|-----------|
| url | Lien |
| rules_count | Compteur |
| failed_rules | Liste rouge |
| passed | Badge |

---

### 44. `filesystem` - Fichiers

**Page:** `/system/filesystem`

| Donnée | Affichage |
|--------|-----------|
| disk | Badge |
| operation | Badge |
| path | Texte |
| size | Valeur formatée |
| duration_ms | Valeur |

**Pour disk_usage:**
- [ ] Gauge utilisation disque

---

### 45. `database` - Connexions DB

**Page:** `/database/connections`

| Donnée | Affichage |
|--------|-----------|
| connection | Badge |
| status | Badge |
| duration_ms | Valeur |
| active_connections | Gauge |
| open_transactions | Compteur |
| pool | Tableau |

**Alertes:**
- [ ] Deadlock détecté (notification)
- [ ] Long-running transaction

---

### 46. `memory` - Mémoire

**Page:** `/system/memory`

| Donnée | Affichage |
|--------|-----------|
| current_bytes | Gauge |
| peak_bytes | Max indicator |
| limit_bytes | Limite |
| percentage_used | Progress bar |

---

### 47. `lifecycle` - Lifecycle HTTP + Waterfall

**Page:** Détail requête (`/requests/{trace_id}`)

**Sections:**

#### 1. Header
```
┌─────────────────────────────────────────────┐
│ GET /api/users                              │
│ 200 OK • 242.31ms • 25.0 MB peak           │
└─────────────────────────────────────────────┘
```

#### 2. Phases (barres horizontales)
```
BOOTSTRAP   ████░░░░░░░░░░░░░░░░  20.6ms (8.5%)
MIDDLEWARE  ██████████░░░░░░░░░░  59.17ms (24.4%)
CONTROLLER  ████████████████████  142.94ms (59.0%)
```

#### 3. Waterfall complet
Pour CHAQUE span, afficher:

| Donnée | Affichage |
|--------|-----------|
| type | Icône (○ QUERY, ● CACHE HIT, → HTTP, ⚡ JOB) |
| label | Texte (tronqué) |
| duration_ms | Valeur |
| start_offset_ms | Position horizontale sur timeline |
| depth | Indentation |

**Interactivité:**
- [ ] Clic sur un span → détails
- [ ] Hover → tooltip avec infos complètes
- [ ] Zoom timeline
- [ ] Filtrer par type

#### 4. Onglets de détails

| Onglet | Contenu |
|--------|---------|
| Spans | Liste interactive de tous les spans |
| Request | Headers, inputs, cookies |
| Response | Status, headers, size |
| Route | Name, URI, parameters, middleware |
| User | ID, email, context |
| Memory | Current, peak, timeline |
| Environment | PHP, Laravel, SAPI |

#### 5. Compteurs (cards)
```
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ 12       │ │ 5/2      │ │ 3        │ │ 1        │
│ Queries  │ │ Cache H/M│ │ HTTP Out │ │ Jobs     │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
```

---

## 🎨 COMPOSANTS UI REQUIS

### 1. WaterfallChart.vue
```vue
<WaterfallChart
  :phases="lifecycleData.phases"
  :spans="lifecycleData.spans"
  :total-duration="lifecycleData.total_duration_ms"
/>
```

### 2. SpanRow.vue
```vue
<SpanRow
  :type="span.type"
  :label="span.label"
  :duration="span.duration_ms"
  :offset="span.start_offset_ms"
  :depth="span.depth"
  :total-duration="totalDuration"
/>
```

### 3. SqlViewer.vue
```vue
<SqlViewer
  :sql="query.sql"
  :bindings="query.bindings"
  :formatted="true"
  :copyable="true"
/>
```

### 4. StackTrace.vue
```vue
<StackTrace
  :frames="exception.trace"
  :source-code="exception.source_code"
  :highlight-line="exception.line"
/>
```

### 5. JsonViewer.vue
```vue
<JsonViewer
  :data="anyJsonData"
  :collapsed-depth="2"
/>
```

### 6. TimelineChart.vue
```vue
<TimelineChart
  :events="timeline.events"
  :start-time="startTime"
  :end-time="endTime"
/>
```

---

## ✅ CHECKLIST DE VÉRIFICATION FINALE

Pour CHAQUE type d'événement (47 types), vérifier:

- [ ] **Listage** : Les événements apparaissent dans la liste appropriée
- [ ] **Détail** : Clic sur un événement affiche TOUS les champs
- [ ] **Filtrage** : Tous les champs pertinents sont filtrables
- [ ] **Recherche** : Full-text search fonctionne
- [ ] **Export** : Possibilité d'exporter les données
- [ ] **Corrélation** : Liens vers événements liés (même trace_id)
- [ ] **Graphiques** : Visualisations temps réel
- [ ] **Alertes** : Notifications pour conditions critiques

---

## 📊 DASHBOARD OVERVIEW

La page d'accueil doit afficher un résumé de TOUT:

```
┌─────────────────────────────────────────────────────────────┐
│                    LAST 24 HOURS                            │
├─────────────┬─────────────┬─────────────┬─────────────────┤
│ 45,230      │ 12          │ 2.3k        │ 99.2%           │
│ Requests    │ Exceptions  │ Jobs        │ Uptime          │
├─────────────┴─────────────┴─────────────┴─────────────────┤
│                                                             │
│  [Request Volume Chart - Line]                             │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [Response Time P50/P95/P99 - Line]                        │
│                                                             │
├──────────────────────────┬──────────────────────────────────┤
│ RECENT EXCEPTIONS        │ SLOW ENDPOINTS                  │
│ • PaymentError (5)       │ • POST /checkout 1.2s          │
│ • ValidationErr (3)      │ • GET /search 890ms            │
│ • ...                    │ • ...                           │
├──────────────────────────┼──────────────────────────────────┤
│ SECURITY ALERTS          │ PERFORMANCE ISSUES              │
│ • 2 SQL injection        │ • 8 N+1 detected               │
│ • 1 XSS attempt          │ • 3 slow queries               │
└──────────────────────────┴──────────────────────────────────┘
```

---

## 🔔 SYSTÈME D'ALERTES

Configurer des alertes pour:

| Condition | Sévérité |
|-----------|----------|
| Nouvelle exception | Warning |
| Exception critique | Critical |
| Spike d'erreurs 5xx | Critical |
| Slow endpoint > seuil | Warning |
| N+1 détecté | Warning |
| Security threat | Critical |
| Job failed | Warning |
| Job stuck | Critical |
| Rate limit exceeded | Info |
| Deadlock | Critical |
| High memory usage | Warning |

---

## 📱 RESPONSIVE

Toutes les pages doivent fonctionner sur:
- [ ] Desktop (1920px+)
- [ ] Laptop (1280px)
- [ ] Tablet (768px)
- [ ] Mobile (375px) - au moins consultation

---

**IMPORTANT:** Aucune donnée collectée par l'agent ne doit être inaccessible depuis le dashboard. Si une donnée est stockée, elle DOIT être visible quelque part dans l'interface.
