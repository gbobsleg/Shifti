# Plans d'exécution — Phase 0 (pour un agent d'exécution)

_Rédigé le 18/11/2026. Référence : `docs/plan-de-route-shifti.md`._

## Règles impératives pour l'agent

1. Exécuter **une seule tâche à la fois**, dans l'ordre. Ne pas commencer la suivante sans validation de l'utilisateur.
2. Ne modifier **que** les fichiers listés dans la tâche. Aucun refactoring, aucun renommage, aucune correction « en passant ».
3. Chaque remplacement est donné sous la forme **AVANT / APRÈS**. Le texte AVANT doit être trouvé **exactement une fois**. S'il est introuvable ou présent plusieurs fois : **s'arrêter et le signaler**, ne pas improviser.
4. Ne jamais modifier un test pour le faire passer. Si un test échoue : s'arrêter, copier la sortie, la signaler.
5. Messages de commit **en français**.
6. Environnement : Windows, PowerShell. Racine du projet : `c:\wamp64\www\shifti`. Python du solveur : `solver-python\.venv\Scripts\python.exe`.
7. Commande de tests Python (à lancer depuis `c:\wamp64\www\shifti\solver-python`) :

   ```powershell
   .\.venv\Scripts\python.exe -m unittest discover -s tests -t . 2>&1 | % { "$_" } | Select-String -Pattern "^Ran |^OK|^FAILED|^FAIL:|^ERROR:"
   ```

   Résultat attendu : `Ran 63 tests` et `OK`. Toute ligne `FAIL:`, `ERROR:` ou `FAILED` = arrêt et signalement.

---

## Tâche 1 — Commiter la correction du solveur de rotation

**Contexte.** La branche `fix/rotation-priorites` contient une correction déjà testée. Il faut seulement la commiter.

**Étapes :**

1. Vérifier la branche courante :

   ```powershell
   cd c:\wamp64\www\shifti; git branch --show-current
   ```

   Attendu : `fix/rotation-priorites`. Sinon : s'arrêter.

2. Lancer les tests Python (commande de la règle 7). Attendu : `Ran 63 tests` et `OK`.

3. Commiter **uniquement** ces deux fichiers :

   ```powershell
   cd c:\wamp64\www\shifti
   git add solver-python/routers/solver_rotation.py solver-python/tests/test_solver_rotation.py
   git commit -m "Rotation : priorités lexicographiques (plages dues, besoin, équité, confort) et bornes de l'écart au besoin"
   ```

4. Commiter la documentation dans un second commit :

   ```powershell
   git add docs/diagnostic-shifti-vs-editeurs-wfm.md docs/plan-de-route-shifti.md docs/plans-grok-phase0.md
   git commit -m "Documentation : diagnostic face aux éditeurs WFM, plan de route et plans de la phase 0"
   ```

**Interdit :** `git add .` ou `git add -A`. Les fichiers `docs/bilan_*.md`, `docs/rapport_optuna_horizon_previsions.md` et le dossier `tmp/` ne doivent **pas** être commités.

---

## Tâche 2 — Supprimer le plancher de 120 sur le poids d'équité matin/après-midi (action 0.4)

**Contexte.** Dans `solver_coverage.py`, le poids d'équité est forcé à 120 minimum, quelle que soit la valeur envoyée par PHP (60). On supprime ce plancher **et** on envoie 120 depuis PHP : le comportement reste **identique**, mais le réglage devient réellement pilotable.

**Fichiers concernés (4 remplacements, 3 fichiers) :**

### 2.1 `solver-python/routers/solver_coverage.py`

AVANT :

```python
        W_EQUITY = max(int(getattr(problem, 'weight_equity', 40) or 0), 120)
```

APRÈS :

```python
        W_EQUITY = int(getattr(problem, 'weight_equity', 40) or 0)
```

### 2.2 `src/Service/ScheduleDayGenerationService.php`

AVANT :

```php
                'weight_equity' => 60,
```

APRÈS :

```php
                'weight_equity' => 120,
```

### 2.3 `src/Controller/SchedulesController.php` (premier remplacement)

AVANT :

```php
                $wfmProblem['weight_equity'] = 60; // renforcer l'équité AM/PM
```

APRÈS :

```php
                $wfmProblem['weight_equity'] = 120; // renforcer l'équité AM/PM
```

### 2.4 `src/Controller/SchedulesController.php` (second remplacement)

AVANT :

```php
                            $wfmProblemPasse2['weight_equity'] = 60;
```

APRÈS :

```php
                            $wfmProblemPasse2['weight_equity'] = 120;
```

**Vérifications :**

1. Recherche de contrôle (doit renvoyer **3 lignes**, toutes avec `120`, et **aucune** avec `60`) :

   ```powershell
   cd c:\wamp64\www\shifti; rg -n "weight_equity'\]? ?=>? ?\d+|weight_equity'\] = \d+" src
   ```

2. Tests Python (règle 7). Attendu : `OK`. Remarque : `tests/test_offer_groups_coverage.py` envoie `weight_equity=0`. Avant, le plancher le transformait en 120 ; désormais 0 désactive réellement l'équité. Si un de ces tests échoue, s'arrêter et signaler (ne pas modifier le test).

3. Commit :

   ```powershell
   git add solver-python/routers/solver_coverage.py src/Service/ScheduleDayGenerationService.php src/Controller/SchedulesController.php
   git commit -m "Couverture : suppression du plancher de 120 sur le poids d'équité, valeur 120 envoyée par PHP (comportement inchangé)"
   ```

---

## Tâche 3 — Supprimer les paramètres de pauses inutilisés (action 0.5)

**Contexte.** `weight_break_alignment` et `weight_break_dispersion` sont déclarés dans le modèle Python et `weight_break_dispersion` est envoyé par PHP, mais **aucun solveur ne les lit**. Ils laissent croire à un réglage qui n'existe pas.

**Attention à l'ordre.** Le modèle Python refuse tout champ inconnu (`extra = "forbid"`). Il faut donc **d'abord** retirer l'envoi côté PHP, **ensuite** retirer les champs côté Python. Sinon, la génération renvoie une erreur 422.

### 3.1 `src/Service/ScheduleDayGenerationService.php`

AVANT (3 lignes) :

```php
                // Dispersion des pauses: pénalité renforcée pour éviter que plusieurs agents fassent la pause au même moment
                // Particulièrement important pour les agents avec activités fixes (même contraintes → même créneau optimal)
                'weight_break_dispersion' => 12,  // Augmenté de 3 (défaut) à 12 pour forcer le décalage
```

APRÈS : supprimer ces 3 lignes (rien à la place).

### 3.2 `src/Controller/SchedulesController.php`

AVANT (3 lignes, indentation plus profonde que dans 3.1) :

```php
                        // Dispersion des pauses: pénalité renforcée pour éviter que plusieurs agents fassent la pause au même moment
                        // Particulièrement important pour les agents avec activités fixes (même contraintes → même créneau optimal)
                        'weight_break_dispersion' => 12,  // Augmenté de 3 (défaut) à 12 pour forcer le décalage
```

APRÈS : supprimer ces 3 lignes.

### 3.3 `solver-python/models/data_models.py`

AVANT :

```python
    weight_break_alignment: int = 2
    weight_break_dispersion: int = 3
```

APRÈS : supprimer ces 2 lignes.

### 3.4 `solver-python/routers/solver_coverage.py`

AVANT :

```python
    # W_BRK_ALIGN ignoré intentionnellement (voir commentaires)
```

APRÈS : supprimer cette ligne.

**Vérifications :**

1. Recherche de contrôle (doit ne **rien** renvoyer) :

   ```powershell
   cd c:\wamp64\www\shifti; rg -n "weight_break_alignment|weight_break_dispersion|W_BRK_ALIGN" src solver-python -g "!**/__pycache__/**"
   ```

2. Tests Python (règle 7). Attendu : `OK`.

3. Contrôle PHP de syntaxe :

   ```powershell
   php -l src/Service/ScheduleDayGenerationService.php; php -l src/Controller/SchedulesController.php
   ```

   Attendu : `No syntax errors detected` deux fois.

4. Commit :

   ```powershell
   git add src/Service/ScheduleDayGenerationService.php src/Controller/SchedulesController.php solver-python/models/data_models.py solver-python/routers/solver_coverage.py
   git commit -m "Solveur : suppression des paramètres de pauses inutilisés (weight_break_alignment, weight_break_dispersion)"
   ```

5. Signaler à l'utilisateur qu'il doit **redémarrer le service Python** puis lancer une génération d'une journée pour vérifier l'absence d'erreur 422.

---

## Tâche 4 — Rendre la priorité des lignes de rotation visible et modifiable

**Contexte.** Chaque ligne d'une règle de rotation a une priorité (`sort_order`, 1 = la plus prioritaire). Elle décide ce que le solveur sacrifie en premier quand il manque des agents (exemple : téléphonie AE ou Livechat AE). Aujourd'hui, c'est un champ caché.

**Prérequis :** décision de l'utilisateur sur les priorités souhaitées. Ne pas modifier les données en base dans cette tâche.

### 4.1 `templates/element/RotationRules/line_row.php`

AVANT :

```php
    <?= $this->Form->hidden("rotation_rule_lines.$idx.sort_order", [
        'value' => $line->sort_order ?? (is_numeric($idx) ? (int)$idx + 1 : 1),
    ]) ?>
```

APRÈS :

```php
    <div class="row g-3 mb-2">
        <div class="col-md-4">
            <?= $this->Form->control("rotation_rule_lines.$idx.sort_order", [
                'type' => 'select',
                'options' => [
                    1 => '1 — la plus prioritaire',
                    2 => '2',
                    3 => '3',
                    4 => '4',
                    5 => '5 — la moins prioritaire',
                ],
                'value' => $line->sort_order ?? (is_numeric($idx) ? min(5, (int)$idx + 1) : 1),
                'label' => 'Priorité si les agents manquent',
                'class' => 'form-control',
                'empty' => false,
            ]) ?>
        </div>
    </div>
```

### 4.2 `templates/element/RotationRules/lines.php`

Le script qui ajoute une ligne donne déjà une valeur au champ `[sort_order]`. Avec une liste à 5 choix, il faut borner la valeur.

AVANT :

```javascript
            sort.value = String(idx + 1);
```

APRÈS :

```javascript
            sort.value = String(Math.min(5, idx + 1));
```

**Vérifications :**

1. `php -l templates/element/RotationRules/line_row.php` → `No syntax errors detected`.
2. Test manuel par l'utilisateur : ouvrir une règle de rotation en modification, vérifier que la liste « Priorité si les agents manquent » apparaît pour chaque ligne, enregistrer, rouvrir, vérifier que la valeur est conservée. Ajouter une ligne avec le bouton d'ajout : la priorité proposée doit être remplie.
3. Commit :

   ```powershell
   git add templates/element/RotationRules/line_row.php templates/element/RotationRules/lines.php
   git commit -m "Règles de rotation : priorité des lignes visible et modifiable dans le formulaire"
   ```

---

## Tâche 5 — Documenter les poids de la passe 1 (action 0.6)

**Contexte.** On **documente**, on ne modifie **aucune valeur**.

### 5.1 `solver-python/routers/solver_fixed.py`

AVANT :

```python
    # --- MINIMISATION (POIDS REAJUSTÉS) ---
```

APRÈS :

```python
    # --- MINIMISATION (POIDS REAJUSTÉS) ---
    # Somme pondérée unique (pas lexicographique). Hiérarchie obtenue par l'écart des poids.
    # Unités hétérogènes : voir docs/diagnostic-shifti-vs-editeurs-wfm.md, section 1 (Passe 1).
    #   10**10 / créneau  : activité obligatoire non couverte
    #   10**7  / booléen  : conflit d'offres
    #   10**6  / minute   : écart au quota d'équité (pool obligatoire)
    #   10**6  / agent    : pause courte simultanée en trop sur un groupe
    #   10**5  / booléen  : diversification de la seconde activité
    #   -10**5 / booléen  : bonus par pause ou déjeuner placé
    #   10**4  / créneau  : activité optionnelle non couverte
    #   100    / minute   : écart à la cible de diversité
    #   10     / %        : équilibrage min-max (pool optionnel)
    #   -1     / créneau  : bonus par créneau d'activité affecté
    # Ne pas rééquilibrer sans tests de référence (plan de route, actions 1.1 et 1.5).
```

**Vérifications :**

1. Tests Python (règle 7). Attendu : `OK`.
2. Commit :

   ```powershell
   git add solver-python/routers/solver_fixed.py
   git commit -m "Passe 1 : documentation des poids de la fonction objectif"
   ```

---

## Tâche 6 — Corriger le diagnostic sur le solveur de rotation

**Fichier :** `docs/diagnostic-shifti-vs-editeurs-wfm.md`

AVANT (un seul paragraphe, commence par « Portée : ») :

```markdown
Portée : le **nombre** de plages par GRC (cible hebdomadaire, par exemple 3) est protégé par un poids de 1 000 000 (l.392). Le bug porte sur **l'emplacement** des plages (GRC mal placés), pas sur leur nombre. La seconde fonction objectif du fichier (l.915) n'a pas été analysée.
```

APRÈS :

```markdown
Portée et correctif (branche `fix/rotation-priorites`, 18/11/2026) :

- Shifti utilise le chemin multi-lignes `solve_rotation_lines`, pas le chemin historique. Le poids d'une plage manquante y dépendait du rang de la ligne (`line_weight`) : 1 000 000 au rang 1, 10 000 au rang 2, 100 au rang 3, **1 au-delà**. Une ligne de rang 3 ou plus pouvait perdre des plages au profit d'un jour non répété. Toutes les lignes actuelles sont au rang 1 : pas d'impact constaté.
- Le seuil réel du bug d'emplacement était plus bas qu'estimé : un GRC était déplacé dès 2 agents manquants.
- Bug supplémentaire : si le besoin d'un quart d'heure dépassait le nombre de GRC envoyés plus 1, la rotation de la semaine devenait **INFEASIBLE** (aucune plage). Bornes corrigées.
- Correctif : résolution lexicographique en 4 niveaux (plages dues → besoin → équité des lignes de couverture → confort des jours), dans les deux chemins. 4 tests de non-régression ajoutés.
- Comparaison jobs 269 / 273 (4 semaines, mêmes données) : 250 plages dans les deux cas ; manques totaux identiques (45) mais répartis autrement (avant : 4 plages AE + 41 créneaux Livechat ; après : 21 plages AE + 24 créneaux Livechat) ; jours répétés 66 → 61. À lignes de même rang, le solveur arbitre seul entre téléphonie et Livechat : la priorité des lignes doit être fixée par l'utilisateur.
```

**Vérification :** relire le fichier autour du paragraphe modifié, vérifier que la liste à puces est bien rendue.

**Commit :**

```powershell
git add docs/diagnostic-shifti-vs-editeurs-wfm.md
git commit -m "Diagnostic : bilan du correctif du solveur de rotation"
```
