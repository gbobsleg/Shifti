# Feature : groupes d’offres (profils mixtes — passe 2)

Documentation interne de la feature livrée sur la branche `feature/offer-groups-mixed-profiles`.

## Objectif métier

Permettre à la passe 2 (solveur couverture) de planifier des **profils mixtes** (ex. `C/P`, `TI-AE`) tout en respectant les flux sous-jacents, via **un seul objet de config** : le **groupe d’offres**.

Deux cas unifiés :

| Cas | Réalité | Mode forecast du groupe |
|---|---|---|
| **C/P** (CESU / PAJEMPLOI) | 2 numéros, histo sur chaque membre | `members` |
| **TI / AE** | 1 numéro, histo global sur le mixte + ratios manuels | `group` |

## Architecture implémentée

```text
Admin UI (OfferGroups CRUD)
        │
        ▼
Tables offer_groups + offer_group_members
        │
        ▼
OfferGroupsNeedService
  • split need_curve (members | group + Largest Remainder)
  • mixte forcé à 0
  • payload offer_groups + buckets d’équité
  • éligibilité agents (skill mixte → membres)
        │
        ├── ScheduleProblemBuilderService
        ├── ScheduleDayGenerationService (lot multi-jours)
        └── SchedulesController::generate (chemin 1-jour)
                │
                ▼
Solveur Python (/api/v1/solve-schedule)
  • clé optionnelle offer_groups
  • sans clé → chemin legacy bit-identique
```

### Composants principaux

| Zone | Fichiers / points d’entrée |
|---|---|
| Migration | `config/Migrations/20260804160000_CreateOfferGroups.php` |
| ORM | `OfferGroupsTable`, `OfferGroupMembersTable`, associations sur `Offers` |
| Need / payload | `src/Service/OfferGroups/*` |
| Builder partagé | `ScheduleProblemBuilderService` |
| Lot | `ScheduleDayGenerationService` (equity buckets + `offer_groups` Passe 2) |
| 1-jour | `SchedulesController::generate` |
| Solveur | `solver-python` — `OfferGroupSpec`, branche couverture dans `solver_coverage.py` |
| UI admin | Hub `Pages/admin` → `OfferGroups` (CRUD) ; bandeau sur `Offers/view` |

### Décisions produit figées

- Compétences cochées = profils **autorisés** (pas de force magique hors soft).
- `prefer_mixed` : soft, **défaut ON**, désactivable au niveau groupe.
- Mixte = **1 capacité** partagée (pas de double comptage +1/+1).
- Équité période (lot) : seau = **nom du groupe** (pas d’imputation fantôme CESU/PAJE dans C/P).
- Ratios mode `group` : manuels, **somme exacte 100 %** (Largest Remainder au split).
- Sans groupe configuré / sans `offer_groups` dans le payload → comportement legacy.

### UI admin

1. Administration → **Groupes d’offres**.
2. Créer / éditer : nom, offre mixte, `forecast_source`, `prefer_mixed`, membres (+ ratios si mode `group`).
3. Sur la fiche d’une offre : bandeau **Membre** ou **Mixte** avec lien vers le groupe.

---

## Checklist config — cas C/P

Objectif : 2 flux forecastables (CESU, PAJEMPLOI), couverture possible aussi via le profil mixte `C/P`.

### Offres

| Offre | `offer_type` | `is_forecastable` | Historique |
|---|---|---|---|
| `CESU` | `normal` | **oui** | requis (série / scénario) |
| `PAJEMPLOI` | `normal` | **oui** | requis |
| `C/P` | `normal` | **non** | non utilisé pour le need (mixte non forecastable) |

### Groupe d’offres

| Champ | Valeur |
|---|---|
| Nom | ex. `C/P` (sert aussi de bucket d’équité) |
| Offre mixte | `C/P` |
| Source forecast | **`members`** |
| `prefer_mixed` | **ON** |
| Membres | `CESU` + `PAJEMPLOI` (≥ 2) |
| Ratios % | non applicables (masqués / nullifiés en mode `members`) |

### Compétences agents

- Cocher sur les agents les profils autorisés : `CESU`, `PAJEMPLOI`, et/ou `C/P` selon la réalité ACD.
- Un agent **uniquement** `C/P` reste éligible à la passe 2 dès qu’un membre du groupe est dans la `need_curve`.

### Effet attendu runtime

- Needs : courbes `CESU` / `PAJEMPLOI` conservées ; courbe `C/P` injectée à **0**.
- Payload solveur : `offer_groups` avec `mixed=C/P`, `members=[CESU, PAJEMPLOI]`, `prefer_mixed=true`.
- Couverture : un agent en `C/P` compte **1** capacité allouée entre les needs membres (pas +1/+1).

---

## Checklist config — cas TI / AE

Objectif : 1 volume forecasté sur le mixte `TI-AE`, redistribué en needs `TI` / `AE` via ratios manuels ; agents mono ou mixtes.

### Offres

| Offre | `offer_type` | `is_forecastable` | Historique |
|---|---|---|---|
| `TI-AE` | `normal` | **oui** | requis (histo **global** du flux unique) |
| `TI` | `normal` | **non** | non utilisé pour le forecast (recevra le split) |
| `AE` | `normal` | **non** | idem |

### Groupe d’offres

| Champ | Valeur |
|---|---|
| Nom | ex. `TI-AE` |
| Offre mixte | `TI-AE` |
| Source forecast | **`group`** |
| `prefer_mixed` | **ON** |
| Membres | `TI` + `AE` |
| Ratios % | manuels, **somme = 100** (ex. 50 / 50, ou 60 / 40…) |

### Compétences agents

- Cocher `TI`, `AE`, et/ou `TI-AE` selon les profils ACD autorisés.
- Même règle d’éligibilité : skill mixte suffit si les membres sont dans le besoin.

### Effet attendu runtime

- Need `TI-AE` splité (Largest Remainder) vers `TI` / `AE` selon les ratios ; `TI-AE` forcé à **0** ensuite.
- Payload : `offer_groups` avec `mixed=TI-AE`, membres `TI`/`AE`, `prefer_mixed=true`.
- Couverture : pool partagé membres + mixte, sans double comptage.

---

## Validations fonctionnelles manuelles (obligatoires)

Avant de considérer la feature validée en prod / préprod, exécuter **ces trois tirs** (chemin 1-jour et/ou job multi-jours selon le process habituel) :

### 1. Non-régression — sans groupe

- Aucun enregistrement dans **Groupes d’offres** (ou groupes hors périmètre du scénario testé).
- Générer un planning sur un scénario / date déjà maîtrisé.
- **Attendu** : comportement identique au legacy (pas de clé `offer_groups` utile / chemin solveur legacy) ; pas de régression couverture / agents.

### 2. Tir configuration C/P

- Appliquer la checklist C/P ci-dessus.
- Générer une journée avec needs CESU + PAJEMPLOI non nuls.
- **Attendu** :
  - agents `C/P` planifiables ;
  - pas de double comptage sur les deux needs ;
  - courbe mixte à 0 côté besoin ;
  - `prefer_mixed` favorise le mixte sans l’imposer.

### 3. Tir configuration TI/AE

- Appliquer la checklist TI/AE (ratios à 100 %, histo sur `TI-AE`).
- Générer une journée avec need global sur `TI-AE`.
- **Attendu** :
  - needs `TI` / `AE` cohérents avec les ratios (somme ≈ need mixte d’origine) ;
  - agents `TI`, `AE`, `TI-AE` utilisés selon skills ;
  - mixte à 0 après split ; pas de double comptage.

### Points de contrôle communs

- Logs / payload Passe 2 : présence de `offer_groups` uniquement si un groupe est actif.
- Fiche offre : bandeau Membre / Mixte + lien groupe.
- Mode `group` : refus de sauvegarde si Σ ratios ≠ 100.
- Lot multi-jours : minutes d’équité agrégées par **nom de groupe** (bucket), pas par offre membre/mixte séparément.

---

## Tests automatisés (référence)

| Suite | Commande / emplacement |
|---|---|
| PHP need / LRM / equity migrator | `tests/TestCase/Service/OfferGroups/` |
| PHP table validations | `tests/TestCase/Model/Table/OfferGroupsTableTest.php` |
| Python couverture groupes | `solver-python` → `python -m unittest tests.test_offer_groups_coverage -v` |

Ces tests ne remplacent **pas** les trois validations manuelles ci-dessus.
