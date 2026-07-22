# Backlog — Epics & User Stories

> Complète [`docs/product-vision.md`](./product-vision.md). Niveau de détail volontairement décroissant du MVP vers V3 (grooming réaliste : on affine V2/V3 quand on s'en approche).
>
> Format des US : *En tant que ... je veux ... afin de ...* + critères d'acceptation + notes techniques. Chaque US est pensée pour être implémentable "en un ticket" par un agent IA supervisé par toi.

---

## MVP (V1) — "Surveiller quelques cibles, voir leur statut, être alerté par email"

### EPIC 01 — Socle technique & architecture du projet

**Objectif** : poser des fondations propres avant d'écrire la moindre fonctionnalité métier.

**Compétences travaillées** : Composer moderne (PSR-4, scripts), Docker Compose multi-services, configuration Symfony (`.env`, secrets), PHPStan/ECS-CS Fixer, structure de dossiers Domain/Application/Infrastructure/UI, CI GitHub Actions.

**Décisions d'architecture liées** : §5.1, 5.8, 5.9 de la vision produit.

- **US-01.1 — Initialisation du projet Symfony**
  En tant que développeur, je veux un squelette Symfony 7.x (skeleton minimal, pas `webapp` complet) afin de contrôler chaque dépendance ajoutée.
  Critères d'acceptation :
  - `composer create-project symfony/skeleton` avec PHP 8.4 déclaré dans `composer.json` (`"php": ">=8.4"`).
  - Structure de dossiers créée : `src/Domain`, `src/Application`, `src/Infrastructure`, `src/UI` (ou `src/Controller` pour rester Symfony-idiomatique au début).
  - `.gitignore` correct (`vendor/`, `.env.local`, `var/`).
  Notes techniques : ne pas ajouter `symfony/webapp-pack` d'un coup — ajouter les bundles au fur et à mesure des Epics pour comprendre à quoi sert chacun.

- **US-01.2 — Environnement Docker Compose**
  En tant que développeur, je veux un environnement Docker reproductible afin de ne pas dépendre de ma machine locale.
  Critères d'acceptation :
  - Services : `php` (php-fpm 8.4), `nginx`, `postgres` (16+), `worker` (même image que php, `command` différente pour Messenger).
  - `docker compose up` démarre l'app et une page d'accueil Symfony répond sur `localhost`.
  - Volumes Docker nommés pour la persistance PostgreSQL.
  Notes techniques : Dockerfile multi-stage (base commune, cible `php-fpm`, cible `worker` avec `messenger:consume`).

- **US-01.3 — PostgreSQL & Doctrine configurés**
  En tant que développeur, je veux Doctrine ORM connecté à PostgreSQL via variables d'environnement afin de pouvoir créer des entités.
  Critères d'acceptation :
  - `DATABASE_URL` pointant vers le service `postgres` du compose.
  - `doctrine:database:create` et une migration vide fonctionnent.
  - `doctrine/doctrine-migrations-bundle` installé.

- **US-01.4 — Qualité de code : PHPStan + PHP-CS-Fixer**
  En tant que développeur, je veux un pipeline de qualité statique dès le premier commit afin de ne jamais accumuler de dette.
  Critères d'acceptation :
  - PHPStan configuré niveau `max` (ou `9`), extension Symfony/Doctrine (`phpstan/phpstan-symfony`, `phpstan/phpstan-doctrine`).
  - PHP-CS-Fixer avec un ruleset explicite (PSR-12 + règles modernes) et une commande `composer cs-fix`.
  - Ces commandes tournent sans erreur sur le skeleton vide.

- **US-01.5 — CI GitHub Actions**
  En tant qu'équipe (même solo), je veux une CI qui bloque les régressions afin de garder un `main` toujours vert.
  Critères d'acceptation :
  - Workflow déclenché sur PR/push : install Composer (cache activé), lint YAML/Twig, PHPStan, PHPUnit.
  - Un job échoue volontairement (test cassé) pour vérifier que la CI bloque bien — puis on le retire.

- **US-01.6 — Convention de tests & premier test bidon**
  En tant que développeur, je veux la stack PHPUnit prête (unit + fonctionnel) afin de commencer chaque Epic suivant en TDD si je le souhaite.
  Critères d'acceptation :
  - `phpunit.xml.dist` avec deux suites : `unit` (pas de kernel Symfony) et `functional` (`KernelTestCase`).
  - Un test unitaire trivial et un test fonctionnel (`GET /` retourne 200) passent en CI.

---

### EPIC 02 — Authentification & sécurité de base

**Objectif** : un système d'utilisateurs minimal mais correct (pas de RBAC fin en V1 — ça arrive en V2/Epic 15).

**Compétences travaillées** : `symfony/security-bundle` moderne (authenticators, pas les anciens `guard`), hashing de mots de passe, Value Objects (Email), attributs `#[Assert\...]`, `readonly` DTOs pour les formulaires.

- **US-02.1 — Entité User & migration**
  En tant qu'admin, je veux une entité `User` (email, mot de passe hashé, rôles) afin de pouvoir m'authentifier.
  Critères d'acceptation :
  - Entité Doctrine `User` implémentant `UserInterface` et `PasswordAuthenticatedUserInterface`.
  - Champ `roles` en tableau (array Doctrine type `json`), au moins `ROLE_USER`.
  - Migration générée et appliquée.

- **US-02.2 — Formulaire de connexion**
  En tant qu'utilisateur, je veux me connecter via email/mot de passe afin d'accéder au dashboard.
  Critères d'acceptation :
  - `LoginFormAuthenticator` custom (pas de bundle tiers).
  - Page de login Twig simple, message d'erreur si échec, CSRF token présent.
  - Test fonctionnel : login réussi redirige vers le dashboard, login raté réaffiche le formulaire avec erreur.

- **US-02.3 — Création d'utilisateur en ligne de commande**
  En tant qu'admin, je veux créer un utilisateur via une commande Symfony (`app:user:create`) afin de ne pas exposer d'inscription publique en V1.
  Critères d'acceptation :
  - Commande interactive (email + mot de passe demandé, masqué).
  - Hash du mot de passe via `UserPasswordHasherInterface`.
  - Test : la commande crée bien un utilisateur en base.

- **US-02.4 — Protection des routes**
  En tant qu'utilisateur non connecté, je ne dois pas accéder au dashboard ni à l'API afin que mes données restent privées.
  Critères d'acceptation :
  - `security.yaml` : firewall configuré, `access_control` protégeant `/dashboard/*` et `/api/*` (sauf endpoint de login/health).
  - Test fonctionnel : accès anonyme → redirection 302 vers login (UI) ou 401 (API).

---

### EPIC 03 — Gestion des cibles (Targets)

**Objectif** : pouvoir déclarer ce qu'on surveille.

**Compétences travaillées** : modélisation de domaine (héritage Doctrine ou composition ?), Value Objects (URL, Hostname), enums PHP 8.4 (`TargetType`), formulaires Symfony (`FormType`), validation, CRUD complet, tests fonctionnels de formulaires.

- **US-03.1 — Modèle de domaine `Target`**
  En tant que développeur, je veux modéliser une cible surveillable afin de représenter serveurs/sites/applications de façon unifiée.
  Critères d'acceptation :
  - Entité `Target` avec `id`, `name`, `type` (enum `TargetType::Server|Website|Application`), `identifier` (URL ou hostname selon type — Value Object), `tags` (collection simple), `createdAt`.
  - Décision de modélisation documentée en commentaire d'ADR (une seule table `Target` polymorphe simple en V1, pas de Single Table Inheritance complexe sauf si le besoin apparaît réellement).
  - PHPStan niveau max sans ignorer d'erreurs.

- **US-03.2 — CRUD Target (UI)**
  En tant qu'utilisateur connecté, je veux créer/lister/modifier/supprimer une cible depuis l'UI afin de gérer mon périmètre de supervision.
  Critères d'acceptation :
  - Contrôleur + `FormType` Symfony avec validation (`#[Assert\Url]` conditionnelle selon le type, `#[Assert\NotBlank]`).
  - Liste paginée (Doctrine Paginator ou `knp-paginator` — décision à trancher : recommandé d'écrire la pagination à la main une fois pour comprendre, puis évaluer le bundle).
  - Tests fonctionnels : création, modification, suppression, validation des erreurs.

- **US-03.3 — Tags et regroupement**
  En tant qu'utilisateur, je veux tagger mes cibles (ex: "prod", "client-x") afin de les filtrer sur le dashboard.
  Critères d'acceptation :
  - Relation Many-to-Many `Target` ↔ `Tag` (ou tableau simple en V1 si on veut différer le Many-to-Many — à trancher en ticket).
  - Filtre par tag sur la liste des cibles.

---

### EPIC 04 — Sondes HTTP(S) & Ping

**Objectif** : définir *comment* on vérifie une cible.

**Compétences travaillées** : modélisation de règles métier (Value Objects `CheckConfig`), enums, interfaces + implémentations multiples (Strategy pattern), `symfony/http-client` moderne (pas cURL brut).

- **US-04.1 — Modèle `Probe` (sonde) rattachée à une `Target`**
  En tant qu'utilisateur, je veux configurer une sonde HTTP sur une cible (URL, méthode, code HTTP attendu, timeout) afin de définir la vérification.
  Critères d'acceptation :
  - Entité `Probe` : `type` (enum `ProbeType::Http|Ping`), `config` (Value Object sérialisé en JSON — ex `HttpProbeConfig { url, expectedStatusCode, timeoutMs }`).
  - Une `Target` peut avoir plusieurs `Probe`.
  - Validation : timeout raisonnable (borné), URL bien formée.

- **US-04.2 — Interface `ProbeExecutorInterface` + implémentation HTTP**
  En tant que développeur, je veux une abstraction d'exécution de sonde afin de pouvoir ajouter d'autres types de sondes plus tard sans casser l'existant.
  Critères d'acceptation :
  - Interface `ProbeExecutorInterface::execute(Probe $probe): ProbeResult`.
  - `HttpProbeExecutor` utilisant `HttpClientInterface` (injection, mockable en test), gère timeout et exceptions réseau proprement (pas de `try/catch` générique masquant l'erreur — typer les exceptions).
  - Tests unitaires avec `MockHttpClient` de Symfony (pas d'appel réseau réel en test).

- **US-04.3 — Implémentation Ping/TCP simple**
  En tant qu'utilisateur, je veux une sonde "ping" (ou TCP connect si ICMP impossible en conteneur) afin de vérifier qu'un serveur répond.
  Critères d'acceptation :
  - `PingProbeExecutor` (ou `TcpProbeExecutor` si ICMP non disponible dans le conteneur Docker — décision technique à documenter, ICMP nécessite souvent des privilèges root, TCP connect sur un port est plus simple et plus fiable en conteneur).
  - Test unitaire avec un socket mocké ou un serveur TCP de test local.

---

### EPIC 05 — Moteur d'exécution des checks (scheduling + workers)

**Objectif** : exécuter les sondes périodiquement, de façon asynchrone et fiable. **C'est le cœur technique du MVP.**

**Compétences travaillées** : Symfony Messenger (Command Bus, handlers, retry, middlewares), Symfony Scheduler, idempotence, gestion d'erreurs/retries, `#[AsMessageHandler]`.

**Décisions d'architecture liées** : §5.2, §5.3.

- **US-05.1 — Message `ExecuteProbeMessage` et handler**
  En tant que développeur, je veux dispatcher un message par sonde à exécuter afin de découpler la planification de l'exécution.
  Critères d'acceptation :
  - `ExecuteProbeMessage` (DTO `readonly`, contient `probeId`).
  - `ExecuteProbeMessageHandler` (`#[AsMessageHandler]`) qui charge la `Probe`, appelle le bon `ProbeExecutorInterface` (résolu via un `ServiceLocator`/tag Symfony selon le `ProbeType`), et persiste un `ProbeResult`.
  - Transport Messenger configuré (Doctrine transport en V1, suffisant avant Redis en V2).
  - Test d'intégration : dispatcher le message en mode synchrone de test, vérifier qu'un `ProbeResult` est créé.

- **US-05.2 — Planification périodique via Symfony Scheduler**
  En tant que système, je dois exécuter chaque sonde selon sa fréquence configurée (ex: toutes les 60s) afin de détecter les incidents rapidement.
  Critères d'acceptation :
  - Composant `symfony/scheduler` configuré avec un `Schedule` qui interroge les `Probe` actives et dispatch un `ExecuteProbeMessage` par sonde due.
  - Fréquence configurable par `Probe` (champ `intervalSeconds`).
  - Test : une sonde due dispatche bien un message, une sonde non due n'en dispatche pas.

- **US-05.3 — Gestion des erreurs et retries**
  En tant que système, je veux gérer proprement les erreurs réseau transitoires afin de ne pas déclencher de faux incidents.
  Critères d'acceptation :
  - Configuration retry Messenger (`retry_strategy`) avec backoff, nombre de tentatives borné.
  - Distinction claire entre "échec de la sonde" (ex: site down → résultat métier valide `ProbeResult::failed()`) et "erreur technique du worker" (ex: base de données injoignable → doit lever une exception et retry Messenger).
  - Test : une exception réseau typée aboutit à un `ProbeResult` en échec, pas à une exception non gérée.

- **US-05.4 — Worker Docker dédié**
  En tant qu'opérateur, je veux un conteneur worker qui consomme la queue Messenger en continu afin que les checks s'exécutent réellement.
  Critères d'acceptation :
  - Commande `messenger:consume` lancée dans le conteneur `worker` (supervisée, redémarre en cas de crash — `restart: unless-stopped` ou Supervisor).
  - Vérification manuelle : lancer `docker compose up`, constater que les `ProbeResult` s'accumulent en base sans action manuelle.

---

### EPIC 06 — Détection d'incidents & historique d'état

**Objectif** : transformer des résultats bruts en état métier exploitable (UP/DOWN), avec anti-flapping basique.

**Compétences travaillées** : logique de domaine pure (testable sans Symfony), enums, Value Objects, event-driven léger (Symfony EventDispatcher ou Messenger events).

- **US-06.1 — Entité `ProbeResult` et historique**
  En tant que système, je veux stocker chaque résultat de sonde (statut, latence, timestamp, message d'erreur éventuel) afin de construire un historique.
  Critères d'acceptation :
  - Entité `ProbeResult` : `probeId`, `status` (enum `ProbeResultStatus::Success|Failure`), `latencyMs`, `checkedAt`, `errorMessage` nullable.
  - Index Doctrine sur `(probe_id, checked_at)`.

- **US-06.2 — Calcul de l'état courant d'une cible (`Incident`)**
  En tant qu'utilisateur, je veux que le système déclare une cible "DOWN" seulement après N échecs consécutifs (anti-flapping) afin d'éviter les fausses alertes.
  Critères d'acceptation :
  - Service de domaine `IncidentDetector` (pur, testable en unitaire sans Symfony/Doctrine) : reçoit l'historique récent d'une sonde, retourne l'état (`Up`/`Down`) selon un seuil configurable de tentatives consécutives.
  - Entité `Incident` créée à l'ouverture d'un problème, `resolvedAt` renseigné à la résolution.
  - Tests unitaires couvrant : premier échec (pas encore DOWN), N échecs consécutifs (DOWN), retour à la normale (incident résolu).

- **US-06.3 — Événement métier `IncidentOpened` / `IncidentResolved`**
  En tant que développeur, je veux dispatcher un événement métier à l'ouverture/fermeture d'incident afin que la notification (Epic 08) s'y branche sans coupler la détection à l'envoi d'email.
  Critères d'acceptation :
  - Utilisation d'`EventDispatcherInterface` (ou messages Messenger dédiés — à trancher : Messenger recommandé pour rester cohérent avec le reste de l'archi asynchrone).
  - Test : ouverture d'incident déclenche bien l'événement, sans dépendance directe à un service de notification dans le detector.

---

### EPIC 07 — Dashboard de supervision

**Objectif** : une vue humaine de l'état du système.

**Compétences travaillées** : Twig moderne, Stimulus/Turbo (rafraîchissement partiel sans full-page reload), requêtes Doctrine optimisées (éviter le N+1), pagination/filtre.

- **US-07.1 — Liste des cibles avec statut courant**
  En tant qu'utilisateur, je veux voir toutes mes cibles avec leur statut actuel (UP/DOWN/inconnu) et leur dernier temps de réponse afin d'avoir une vue d'ensemble.
  Critères d'acceptation :
  - Page `/dashboard` listant les `Target` avec badge de statut coloré.
  - Requête Doctrine unique optimisée (pas de N+1 — vérifié via `doctrine:query:sql` ou profiler en dev).
  - Filtre par tag et par type de cible.

- **US-07.2 — Détail d'une cible : historique et graphe de latence**
  En tant qu'utilisateur, je veux voir l'historique récent (24h/7j) d'une cible afin de comprendre son comportement.
  Critères d'acceptation :
  - Page `/targets/{id}` avec liste des derniers `ProbeResult` et graphe simple (Chart.js ou équivalent léger, pas de dépendance lourde).
  - Calcul d'un taux d'uptime sur la période affichée.

- **US-07.3 — Rafraîchissement live basique (polling ou Turbo Stream)**
  En tant qu'utilisateur, je veux que le statut se mette à jour sans recharger la page afin d'avoir une vue temps quasi-réel.
  Critères d'acceptation :
  - Polling AJAX simple (toutes les 10-15s) ou Turbo Stream — Mercure explicitement différé en V2 (§5.6).
  - Test manuel documenté (pas nécessairement un test automatisé JS en V1).

---

### EPIC 08 — Notifications (email)

**Objectif** : alerter un humain en cas d'incident.

**Compétences travaillées** : `symfony/mailer`, Messenger (envoi async des emails), templates Twig d'email, gestion de configuration SMTP via secrets.

- **US-08.1 — Envoi d'email à l'ouverture d'un incident**
  En tant qu'utilisateur, je veux recevoir un email quand une de mes cibles passe DOWN afin de réagir rapidement.
  Critères d'acceptation :
  - Listener/handler écoutant `IncidentOpened`, dispatchant un email via `symfony/mailer` en asynchrone (transport Messenger dédié `async_mail`).
  - Template Twig d'email clair (nom de la cible, heure, dernière erreur).
  - Test : Mailer en mode test (`assertEmailCount`, assertions sur le contenu).

- **US-08.2 — Email de résolution d'incident**
  En tant qu'utilisateur, je veux être notifié quand une cible redevient UP afin de savoir que l'incident est clos.
  Critères d'acceptation :
  - Même mécanisme sur `IncidentResolved`, avec durée de l'incident dans le message.

- **US-08.3 — Configuration SMTP via secrets Symfony**
  En tant qu'opérateur, je veux configurer le SMTP sans committer de credentials afin de respecter les bonnes pratiques de sécurité.
  Critères d'acceptation :
  - `MAILER_DSN` via variable d'environnement / Symfony secrets vault (`secrets:set`), jamais en clair dans le repo.
  - Documentation dans le README de dev.

---

### EPIC 09 — API REST (lecture)

**Objectif** : exposer le statut de la plateforme pour de futures intégrations.

**Compétences travaillées** : Symfony Serializer (groupes de sérialisation), design d'API REST, versionning d'API, authentification API par token.

- **US-09.1 — Endpoint `GET /api/targets` et `GET /api/targets/{id}`**
  En tant que consommateur API, je veux lister les cibles et leur statut afin d'intégrer la supervision ailleurs.
  Critères d'acceptation :
  - Contrôleur API dédié, réponse JSON via Symfony Serializer avec groupes explicites (pas de sérialisation "à l'aveugle" des entités Doctrine — éviter d'exposer des champs internes).
  - Pagination simple (`?page=`, `?limit=`).
  - Tests fonctionnels sur les codes HTTP et la forme du JSON.

- **US-09.2 — Authentification API par token**
  En tant qu'utilisateur, je veux un token d'API personnel afin d'appeler l'API sans exposer mon mot de passe.
  Critères d'acceptation :
  - Entité `ApiToken` liée à `User`, génération sécurisée (`random_bytes`/`Uuid`), hashage en base si pertinent.
  - Authenticator Symfony dédié pour le firewall `/api`.
  - Test : requête sans token → 401, avec token valide → 200, token invalide/révoqué → 401.

- **US-09.3 — Endpoint de health-check interne**
  En tant qu'opérateur, je veux un endpoint `/health` (sans auth) afin de vérifier que l'application elle-même est en vie (utile pour un futur monitoring... du monitoring).
  Critères d'acceptation :
  - `/health` retourne 200 + vérifie la connexion DB (pas juste "l'app répond", vérifie ses dépendances critiques).
  - Exclu du firewall authentifié.

---

## V2 — "Un outil crédible en usage professionnel"

*Détail volontairement plus léger — à approfondir en ticket au moment de démarrer chaque Epic.*

### EPIC 10 — Sondes avancées
Compétences : parsing/regex, `symfony/http-client` avancé (options SSL), manipulation de certificats X.509 en PHP.
- US-10.1 Sonde TCP port générique (au-delà du ping de l'Epic 04).
- US-10.2 Sonde SSL : vérifier l'expiration du certificat, alerter à J-30/J-7.
- US-10.3 Sonde DNS (résolution attendue, propagation).
- US-10.4 Sonde HTTP avec assertion de contenu (regex/JSONPath sur le corps de la réponse).

### EPIC 11 — Agent de supervision serveur
Compétences : conception d'API d'ingestion (push depuis un agent externe), sécurité (auth par token dédié agent), écriture d'un script agent minimal (bash/PHP CLI).
- US-11.1 Endpoint `POST /api/agent/metrics` (CPU, RAM, disque, uptime système).
- US-11.2 Script agent léger (cron + curl, ou petit binaire PHP CLI) publié dans le repo.
- US-11.3 Dashboard des métriques système par serveur.

### EPIC 12 — Alerting avancé & fenêtres de maintenance
Compétences : machine à états (State pattern/enum riche), règles configurables (Strategy), planification calendaire.
- US-12.1 Fenêtres de maintenance (suppression temporaire des alertes sur une cible).
- US-12.2 Règles de seuil configurables par sonde (ex: latence > Xms = DEGRADED).
- US-12.3 Anti-flapping avancé et escalade (relance si non résolu après Y minutes).

### EPIC 13 — Notifications multi-canal
Compétences : Strategy pattern pour les canaux, intégrations HTTP tierces (webhooks), gestion de secrets par canal.
- US-13.1 Notification Slack (webhook entrant Slack).
- US-13.2 Webhook générique sortant configurable par l'utilisateur.
- US-13.3 Telegram bot (optionnel).
- US-13.4 Préférences de notification par utilisateur/par cible.

### EPIC 14 — Rapports & SLA
Compétences : requêtes d'agrégation Doctrine/SQL, génération de PDF/CSV, Value Objects de période.
- US-14.1 Rapport d'uptime mensuel par cible (%, nombre d'incidents, MTTR).
- US-14.2 Export CSV/PDF.

### EPIC 15 — Rôles & permissions (RBAC)
Compétences : Voters Symfony (`SecurityVoterInterface`), permissions fines par ressource.
- US-15.1 Rôles `ROLE_ADMIN` / `ROLE_VIEWER`.
- US-15.2 Voter pour restreindre l'édition des cibles selon le rôle.

### EPIC 16 — Observabilité interne de l'application
Compétences : Monolog structuré (handlers, processors), corrélation d'ID de requête, métriques applicatives.
- US-16.1 Logs structurés JSON avec `request_id`.
- US-16.2 Endpoint de métriques internes (nombre de checks/minute, latence moyenne du worker).

---

## V3 — "Passage à l'échelle et écosystème"

*Niveau Epic uniquement — à transformer en User Stories quand on s'en approche.*

- **EPIC 17 — Multi-tenant / organisations** : isolation des données par organisation, invitations d'équipe. Compétences : Doctrine filters, contexte de sécurité multi-tenant.
- **EPIC 18 — Status page publique** : page publique de statut par organisation (à la "status.exemple.com"). Compétences : cache HTTP, contrôleurs publics performants.
- **EPIC 19 — Intégrations tierces** : PagerDuty, Microsoft Teams, OpsGenie. Compétences : design d'intégrations tierces, gestion de credentials externes.
- **EPIC 20 — Scalabilité & performance** : partitionnement PostgreSQL ou TimescaleDB, cache Redis, scaling horizontal des workers. Compétences : perf profiling, architecture de données à grande échelle.
- **EPIC 21 — Sondes personnalisées / plugins** : permettre à un utilisateur de définir une sonde custom (script ou définition déclarative). Compétences : architecture plugin, sandboxing, sécurité d'exécution de code tiers.

---

## Comment on va procéder

1. Valider/ajuster ce backlog ensemble (noms, priorités, granularité).
2. Créer les tickets (GitHub Issues ou fichiers, selon ta préférence d'outillage) pour l'Epic 01 uniquement au départ.
3. Implémenter US par US, avec revue de code à chaque étape (je peux jouer le rôle de reviewer senior avant de passer à la US suivante).
4. Re-groomer V2 en détail une fois le MVP stable.
