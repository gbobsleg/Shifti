# Shifti — Glossaire du domaine

Glossaire court pour architectes et builders. Pas un ERD complet.

**Langue :** documentation et libellés métier en français. Les noms techniques du code (tables, classes, clés JSON) suivent l’existant (souvent anglais).

## Personnes et organisation

| Concept | Signification |
|---------|---------------|
| **User / Agent** | Collaborateur planifié par le WFM (compétences, disponibilités, site). « Agent » dans le code produit = humain, pas un agent Cursor. |
| **Site / Région** | Placement organisationnel des agents et périmètre de planning. |
| **Skill (compétence)** | Capacité requise par les offres ; sert à l’éligibilité. |
| **Role** | Rôle d’autorisation des utilisateurs applicatifs (Policies). |

## Demande et produits

| Concept | Signification |
|---------|---------------|
| **Offer (offre)** | Produit / file à couvrir (`offer_type`, `is_forecastable`, compétences). |
| **Offer group (groupe d’offres)** | Objet de config pour **profils mixtes** (ex. C/P, TI-AE) en passe couverture. Modes : source forecast `members` vs `group`. Voir [feature_groupes_offres.md](feature_groupes_offres.md). |
| **Range** | Configuration de plages / horaires côté UI planning. |
| **Historical data** | Séries temporelles alimentant les prévisions. |
| **Forecast scenario** | Scénario Prophet / prévision nommé pour les offres ; sert aux courbes de besoin. |

## Temps et contraintes

| Concept | Signification |
|---------|---------------|
| **Grid (grille)** | Grille structurelle de planning (créneaux / affichage couverture). |
| **Schedule (planning)** | Activités assignées aux agents sur des jours ; point d’entrée de génération. |
| **Absence** | Indisponibilité d’un agent. |
| **User availability** | Fenêtres de disponibilité déclarées. |
| **Fixed activity rule** | Activités dures placées en **passe 1** (ciblage global OR-Tools). |
| **Rotation rule** | Contraintes de rotation via **solve-rotation**. |
| **Remote work** | Règles de télétravail influençant le placement. |
| **WFM settings** | Bornes de journée, flags Optuna/cron, réglages planificateur. |

## Pipeline de génération

| Concept | Signification |
|---------|---------------|
| **Planning generation job** | Job de génération multi-jours en arrière-plan (worker Cake). |
| **Passe 1** | Activités fixes — `POST /api/v1/solve-fixed-activities`. |
| **Passe 2** | Couverture / forecastables — `POST /api/v1/solve-schedule` (`offer_groups` optionnel). |
| **Passe 3 / rotation** | Rotations — `POST /api/v1/solve-rotation`. |
| **Equity (équité)** | Seaux d’équité sur une période (lot) ; les groupes d’offres utilisent le nom du groupe comme seau. |
| **Draft compliance** | Vérifie qu’un brouillon respecte les règles métier avant publication. |
| **Planning event mapping** | Correspondance entre événements de planning et entités métier. |

## Prévision / tuning

| Concept | Signification |
|---------|---------------|
| **Prévision Prophet** | Service HTTP sur :8001 pour les courbes de demande. |
| **Prophet tuning job** | Job Optuna walk-forward sur `prophet_tuning_jobs` (worker, pas d’HTTP). |
| **Ticker cron Optuna** | Commande Cake qui enqueue les offres éligibles (`Europe/Paris`). |

## Zones de services PHP (`src/Service/`)

- Planning : `ScheduleDayGenerationService`, `ScheduleProblemBuilderService`, `FixedActivitiesBuilderService`, `AgentsAfterFixedActivitiesService`, `Planning/DraftComplianceService`
- Groupes d’offres : `OfferGroups/*`
- Équité : `Equity/*`
- Forecast / WFM : `ForecastService`, `WfmScenarioService`, `WfmCalculatorService`, `Prophet*`
- Télétravail, import Excel, statut des jobs, health

## Domaines UI / Controllers

Schedules, Grids, Offers, OfferGroups, Users, Skills, Sites, Regions, Ranges, Absences, Alerts, FixedActivityRules, ForecastScenarios, HistoricalData, PlanningGenerationJobs, PlanningEventMappings, RotationRules, RemoteWork, UserAvailabilities, ExcelUploads, WfmSettings, DisplaySettings, BackgroundJobs, Roles, Pages.
