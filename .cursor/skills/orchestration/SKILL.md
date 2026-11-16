---
name: orchestration
description: Playbook d’orchestration de l’équipe d’agents Shifti. Enseigne à l’agent principal comment décomposer les demandes en unités parallèles, router vers les bons sous-agents, gérer les boucles de design adversariales avec garde planner-advocate, déclencher les reviewers et stager les commits. À utiliser pour toute coordination sur le projet.
---

# Playbook d’orchestration Shifti

Tu coordonnes une équipe de 15 sous-agents spécialisés. Ton rôle : décomposer, maximiser le parallélisme, et passer les portes qualité avant de stager un commit.

## Langue

- Toute l’orchestration (briefs, relais, résumés) se fait **en français**.
- Le dépôt et le produit sont francophones (UI, docs, messages, commits). Les identifiants techniques restent ceux du code.

## Roster des agents

| # | Agent | Modèle | Rôle | Accès |
|---|---|---|---|---|
| 1 | domain-architect | glm-5.2-high | Domaine WFM, services Cake, contrats solveur, pipelines jobs | Lecture seule |
| 2 | architecture-critic | glm-5.2-high | Revue adversariale des specs d’architecture | Lecture seule |
| 3 | ux-designer | glm-5.2-high | Design pages/parcours CakePHP + Bootstrap | Lecture seule |
| 4 | ux-critic | glm-5.2-high | Revue adversariale des specs UX | Lecture seule |
| 5 | planner-advocate | glm-5.2-high | Utilisabilité transverse (personas Camille / Samir) | Lecture seule |
| 6 | php-backend | composer-2.5 | Implémentation CakePHP dans `src/` | Écriture |
| 7 | db-engineer | composer-2.5 | Migrations/Seeds dans `config/Migrations`, `config/Seeds` | Écriture |
| 8 | templ-builder | composer-2.5 | Templates + CSS/JS webroot | Écriture |
| 9 | python-solver | composer-2.5 | FastAPI / OR-Tools / Prophet / Optuna dans `solver-python/` | Écriture |
| 10 | infra | composer-2.5 | Docker, Supervisord, deploy, CI, scripts/dev | Écriture |
| 11 | php-reviewer | kimi-k2.7-code | Revue PHP lecture seule | Lecture seule |
| 12 | template-reviewer | kimi-k2.7-code | Revue templates/CSS/JS lecture seule | Lecture seule |
| 13 | python-reviewer | kimi-k2.7-code | Revue solver-python lecture seule | Lecture seule |
| 14 | infra-reviewer | kimi-k2.7-code | Revue Docker/CI/deploy lecture seule | Lecture seule |
| 15 | git-commit | composer-2.5-fast | Stage + message de commit (ne commit jamais) | Écriture (git seul) |

## Paliers de modèles

| Palier | Modèle | Usage |
|--------|--------|-------|
| Doer | `composer-2.5` | php-backend, db-engineer, templ-builder, python-solver, infra |
| Doer (mécanique) | `composer-2.5-fast` | git-commit |
| Revue technique | `kimi-k2.7-code` | php-reviewer, template-reviewer, python-reviewer, infra-reviewer |
| Haute réflexion | `glm-5.2-high` | domain-architect, architecture-critic, ux-designer, ux-critic, planner-advocate |

## Étape 1 : Décomposer la demande

Pour chaque demande :

1. Identifier les domaines (architecture, UX, PHP, BDD, templates, solveur Python, infra, git)
2. Découper en unités indépendantes
3. Construire le graphe de dépendances
4. Grouper en voies parallèles

**Règle** : ne jamais sérialiser ce qui peut tourner en parallèle.

## Étape 2 : Router vers les agents

### Arbre de décision

- Modèle domaine, design de services Cake, contrats solveur, passes, jobs → **boucle architecture**
- Layout de page, flux admin, copy Bootstrap/UI → **boucle UX**
- PHP dans `src/` → **php-backend** (après boucle architecture si dépendant du domaine)
- Migrations / Seeds → **db-engineer** (après architecture si schéma)
- Templates / webroot CSS/JS → **templ-builder** (après UX si design)
- `solver-python/` → **python-solver** (après architecture si contrat)
- Docker, Supervisord, deploy, CI, scripts/dev → **infra**
- Commit du travail fini → **git-commit**
- Demande transverse → décomposer en voies parallèles selon les domaines touchés

## Étape 3 : Boucles de design

### Protocole boucle architecture

1. Déléguer au **domain-architect**
2. Passer la sortie verbatim à **architecture-critic**
3. Issues **critiques** → feedback complet à l’architecte (resume si possible)
4. Répéter 2–3 jusqu’à approbation sans critique
5. Passer la spec à **planner-advocate**
6. Issues critiques advocate → retour architecte puis re-critique
7. Spec prête pour **php-backend**, **db-engineer**, **python-solver** selon le besoin

### Protocole boucle UX

1. Déléguer à **ux-designer**
2. Passer verbatim à **ux-critic**
3. Issues critiques → révision designer puis relecture
4. Jusqu’à approbation sans critique
5. Passer à **planner-advocate**
6. Issues critiques → retour designer puis re-critique
7. Spec prête pour **templ-builder**

Les deux boucles PEUVENT tourner en parallèle.

## Étape 4 : Build

Déléguer les specs approuvées. Builders indépendants en parallèle :

- **db-engineer** — migrations/seeds
- **php-backend** — Controllers, Services, Policies, Commands
- **python-solver** — FastAPI / OR-Tools / Prophet / Optuna
- **templ-builder** — templates et assets webroot
- **infra** — Docker/CI/deploy/scripts

**Contrainte d’ordre** : db-engineer avant php-backend si PHP dépend de nouvelles colonnes/tables. php-backend et python-solver en parallèle si le contrat JSON est figé dans la spec ; sinon séquencer le propriétaire du contrat en premier.

### Brief des builders

Décrire le **problème et les critères d’acceptation**, pas la solution. Briefs **en français**.

Bon brief :
1. **Quoi**
2. **Où** (chemins, composants)
3. **Critères d’acceptation**
4. **Contraintes** éventuelles

Mauvais brief : snippets à coller, CSS prescrit, signatures imposées hors spec, patch reviewer recopié.

En relayant une revue : passer les **issues** (quoi/pourquoi), pas le code suggéré.

## Étape 5 : Revue

### Quand invoquer

**Invoquer** après changements substantiels (nouveaux modules, refactors, pages, CI/Docker, migrations, contrats solveur).

**Sauter** pour le trivial (renames, formatage, one-liners, typos docs).

### Matching

- PHP `src/` → **php-reviewer**
- `templates/` / webroot → **template-reviewer**
- `solver-python/` → **python-reviewer**
- Docker/CI/deploy/scripts/dev → **infra-reviewer**
- Tout changement user-facing → **planner-advocate**
- Multi-domaines → reviewers en parallèle

Issues critiques → builder correspondant puis re-revue.

## Étape 6 : Commit

Déléguer à **git-commit** :
- Lit le diff, stage, propose un message sémantique **en français**
- Ne commit ni push
- Présenter le message à l’utilisateur pour confirmation

## Ancres docs

Avant design ou large implémentation, s’assurer que les agents (ou toi) ont lu :
- `docs/project-overview.md`
- `docs/domain-model.md`
- `docs/solver-contracts.md`
- `docs/feature_groupes_offres.md` si groupes d’offres
