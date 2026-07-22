# Vision produit — Plateforme de supervision (nom de travail : "Sentinel")

> Document vivant. À amender au fil des sprints. Rédigé en posture Architecte Symfony senior + Product Owner + Mentor PHP moderne.

## 1. Pourquoi ce projet

Deux objectifs assumés et non contradictoires :

1. **Produit** : un outil de supervision auto-hébergé, capable de surveiller des serveurs, des sites web et des applications, avec détection d'incident et alerting — utilisable pour de vrai sur une infra personnelle ou professionnelle.
2. **Montée en compétence** : un terrain d'entraînement volontairement structuré comme un vrai projet d'entreprise (Epics/US, CI, tests, revue de code, architecture documentée) pour passer de PHP 5/7 "à l'ancienne" à PHP 8.4 + Symfony moderne.

Ces deux objectifs doivent rester alignés : chaque Epic est choisi *aussi* parce qu'il fait travailler une compétence Symfony/PHP précise, pas seulement parce qu'il apporte de la valeur produit.

## 2. Ce que le produit n'est pas (au départ)

Pour éviter le sur-engineering dès le MVP :

- Pas de multi-tenant en V1/V2 (un seul "espace" avec des utilisateurs).
- Pas d'agent système complexe en V1 (les métriques serveur type CPU/RAM arrivent en V2).
- Pas de scalabilité horizontale prématurée : on architecture *proprement* mais on ne construit pas pour 1M de checks/seconde dès le jour 1.
- Pas de UI framework front lourd imposé en V1 (Twig + Turbo/Stimulus suffit ; un front SPA est une décision à discuter plus tard, pas un pré-requis).

## 3. Grands domaines fonctionnels

| Domaine | Description |
|---|---|
| **Cibles (Targets)** | Ce qu'on surveille : serveurs, sites web, applications. CRUD, tags, groupes. |
| **Sondes (Probes/Checks)** | Comment on vérifie une cible : HTTP(S), Ping, TCP, SSL, DNS, contenu de page, métriques agent. |
| **Ordonnancement & exécution** | Planifier les checks, les exécuter de façon asynchrone et fiable, gérer les retries/timeouts. |
| **Collecte & historisation** | Stocker les résultats de checks (statut, latence, métadonnées) dans le temps. |
| **Détection d'incidents** | Passer d'un résultat brut à un état métier (UP/DOWN/DEGRADED), avec anti-flapping. |
| **Alerting & notifications** | Prévenir les humains : email, puis Slack/webhook/Telegram, règles, escalade, fenêtres de maintenance. |
| **Dashboard & visualisation** | Vue d'ensemble, uptime %, historique, graphes de latence. |
| **Comptes & sécurité** | Authentification, rôles, API tokens. |
| **API & intégrations** | API REST pour consulter/piloter la plateforme, intégrations tierces. |
| **Observabilité interne** | Logs structurés, health-checks de l'app elle-même, monitoring du monitoring. |

## 4. Priorisation générale

- **MVP (V1)** : "Je peux déclarer un site web ou un serveur, il est vérifié périodiquement, je vois son statut sur un dashboard, je reçois un email en cas de panne." Mono-utilisateur ou petite équipe, pas de RBAC fin.
- **V2** : "Outil crédible en usage pro" — sondes avancées, agent serveur, alerting fin, multi-canal, rôles, rapports SLA.
- **V3** : "Passage à l'échelle et écosystème" — multi-tenant, status page publique, intégrations tierces, performance, extensibilité (plugins de sondes).

Le détail Epics/User Stories est dans [`docs/backlog.md`](./backlog.md).

## 5. Décisions d'architecture structurantes

Ce sont les décisions que tu dois **comprendre**, pas juste accepter. Elles seront rediscutées au fil du backlog, mais voici le cadre initial recommandé et pourquoi.

### 5.1 Architecture en couches (DDD-lite), pas de la sur-ingénierie hexagonale

Recommandation : une séparation **Domain / Application / Infrastructure / UI** légère plutôt qu'une hexagonale à la lettre avec ports/adapters partout.

- `Domain` : entités métier, Value Objects, interfaces de repository, règles métier pures (ex : "un check est en échec après N tentatives" → logique de domaine, pas dans un contrôleur).
- `Application` : cas d'usage (Command/Query handlers via Symfony Messenger), orchestration.
- `Infrastructure` : Doctrine, clients HTTP, adapters de notification (SMTP, Slack...).
- `UI` : contrôleurs Symfony, formulaires, templates Twig, endpoints API.

**Pourquoi** : en venant de PHP 5/7 procédural ou MVC "fat controller", le piège est de tout remettre dans les contrôleurs ou les entités Doctrine. Cette séparation te force à pratiquer l'injection de dépendances, les interfaces, le découplage — sans te noyer dans du DDD tactique complet (pas de CQRS complexe, pas d'Event Sourcing en V1).

### 5.2 Symfony Messenger comme colonne vertébrale asynchrone

Les checks (HTTP, ping, etc.) sont exécutés **de façon asynchrone** via Messenger (transport Doctrine puis Redis/RabbitMQ en V2). C'est le cœur technique de l'appli : un check n'est jamais exécuté en synchrone dans une requête HTTP.

**Pourquoi** : Messenger est LE pattern moderne Symfony à maîtriser (Command Bus, handlers, retry strategy, middlewares). C'est aussi ce qui rend le produit robuste (un check lent ne bloque pas l'UI).

### 5.3 Ordonnancement : Symfony Scheduler (composant récent) plutôt que cron brut

Symfony 6.3+ propose un composant `Scheduler` natif (RecurringMessage, cron expressions en PHP). On l'utilise pour déclencher périodiquement les checks, plutôt que des crontabs système opaques.

**Pourquoi** : c'est un composant récent, peu connu des devs venant de PHP legacy, qui remplace élégamment "un cron qui appelle un script".

### 5.4 Stockage : PostgreSQL dès le départ, avec un œil sur les séries temporelles

Les résultats de checks sont une **série temporelle** (timestamp, target_id, statut, latence...). En V1, une table Doctrine classique bien indexée (index sur `(target_id, checked_at)`) suffit. En V3, on évoquera partitionnement PostgreSQL natif ou extension type TimescaleDB — décision différée volontairement pour ne pas sur-architecturer trop tôt.

### 5.5 API : contrôleurs API manuels en V1, API Platform à évaluer en V2

En V1, on écrit des contrôleurs API "à la main" (avec un normalizer Symfony Serializer propre) pour bien comprendre ce qui se passe sous le capot. API Platform sera proposé en V2 comme évolution *une fois* que les mécanismes REST/Serializer/Validator sont acquis, pas avant.

**Pourquoi** : apprendre "ce qu'API Platform fait pour toi" a plus de valeur pédagogique après avoir écrit l'équivalent à la main.

### 5.6 Temps réel : Mercure (Epic V2/V3)

Pour le rafraîchissement live du dashboard (statut qui change sans reload), Symfony Mercure sera introduit en V2. Pas indispensable au MVP (un refresh Turbo Stream périodique ou un polling suffit au départ).

### 5.7 Stratégie de tests (pyramide)

- **Unit tests** (PHPUnit) sur le Domain et l'Application (logique métier pure, sans Symfony kernel).
- **Tests fonctionnels** (`KernelTestCase`/`WebTestCase`) sur les contrôleurs et l'intégration Doctrine.
- **Tests d'intégration Messenger** pour vérifier qu'un message déclenche bien le bon handler.
- Objectif : PHPStan niveau max (9/10) dès le début — pas de dette statique.

### 5.8 Docker Compose — services cibles

`php-fpm` (ou FrankenPHP à évaluer), `nginx`, `postgres`, `worker` (consumer Messenger, même image que php-fpm mais commande différente), `redis` (cache + transport Messenger V2), plus tard `mercure`.

### 5.9 CI

GitHub Actions : install Composer → PHPStan → PHPUnit → (plus tard) tests de mutation (Infection) et Rector en mode dry-run pour surveiller la modernisation du code.

## 6. Compétences PHP 8.4 à infuser volontairement

À placer consciemment dans le code au fil des Epics, pas comme un exercice académique isolé :

- `readonly` classes/propriétés (Value Objects, DTOs).
- Enums natifs (statut de check : `CheckStatus::Up`, `Down`, `Degraded`).
- **Property hooks** (PHP 8.4) sur des Value Objects ou entités pour la validation/dérivation de propriétés.
- **Asymmetric visibility** (PHP 8.4, `public private(set)`) pour des entités dont l'état ne doit être modifié que par leur propre logique interne.
- Attributs natifs (`#[Route]`, `#[AsMessageHandler]`, validation `#[Assert\...]`, `#[ORM\...]`).
- `match` plutôt que `switch`.
- Named arguments pour la lisibilité des DTOs/Value Objects complexes.
- First-class callable syntax (`strtoupper(...)`) et closures modernes.
- Nullsafe operator (`?->`) pour remplacer les chaînes `isset()` imbriquées.

## 7. Prochaine étape

Une fois ce document validé, on détaille les tickets de l'Epic 01 (socle technique) au niveau "prêt à implémenter" et on commence à coder — un ticket à la fois, avec revue à chaque étape.
