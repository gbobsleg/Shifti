# Diagnostic technique de Shifti face à Peopleware, Verint et Timesquare

_Date : 18/11/2026 (révisé le même jour avec le contexte métier) — basé sur le code de `solver-python/` et `src/`._

**Verdict.** Shifti est plus fort que les trois éditeurs sur un point : son modèle mathématique est exact et explicable. On peut lire chaque règle, et le solveur prouve qu'un planning est optimal ou explique pourquoi il est impossible.

Plusieurs arguments phares des éditeurs ne s'appliquent pas à notre contexte :

- **application mobile** : inutile, les outils sont derrière le VPN et les agents n'ont pas de smartphone professionnel ;
- **paie, annualisation** : pas de besoin, la plage de planification est fixe (9 h-17 h, ouverture des PFT), un 36 h ou un 39 h ne change rien ;
- **supervision temps réel** : déjà couverte par Pulse, qu'il n'est pas question de remplacer ;
- **ré-optimisation intra-jour agressive** : socialement inapplicable (planning publié plus d'un mois à l'avance, 1 à 2 changements d'offre par agent et par jour au maximum).

Les vrais écarts de Shifti sont :

- la **bourse d'échange**, obligatoire et prévue, absente du code mais faisable avec l'architecture actuelle ;
- la **re-génération partielle** (un ou plusieurs agents, un jour ou une période, en figeant le déjà planifié), existante en partie ;
- l'**adhérence** et la **comparaison flux réel / prévision**, faisables à condition d'obtenir l'accès aux données ;
- des **poids mal calibrés** entre eux, dont un vrai bug d'ordre des priorités dans le solveur des rotations.

---

## 1. Cartographie de la fonction objectif

Le solveur ne cherche pas « le meilleur planning » dans l'absolu. Il minimise une somme de pénalités : chaque règle souple (soft constraint) violée coûte des points, multipliés par un poids. Chaque journée est générée en deux passes successives, appelées depuis `ScheduleDayGenerationService`.

### Passe 1 : activités fixes (`solver_fixed.py`, l.775)

Un seul `Minimize`. L'ordre des priorités est imposé par l'écart entre les poids (approche « big-M »), pas par une vraie hiérarchie.

```python
model.Minimize(
    sum(shortfall_weighted_terms) +
    sum(p2_diversification_penalties) * 10**5 +
    sum(sum(c) for c in agent_conflict_offers.values()) * 10**7 +
    sum(staggering_penalty_vars) * 10**6 +
    equity_gap_weighted * EQUITY_WEIGHT +
    sum(balance_terms) * BALANCE_WEIGHT +
    sum(all_pauses) * -10**5 +
    sum(all_lunches) * -10**5 -
    sum(x_prio.values()) -
    sum(x_sec.values()) +
    sum(diversity_penalty_vars) * DIVERSITY_WEIGHT
)
```

| Terme | Poids | Unité de la variable |
|---|---|---|
| Activité obligatoire non couverte | 10¹⁰ | créneau |
| Conflit d'offres | 10⁷ | booléen |
| Écart au quota d'équité (pool obligatoire), en valeur absolue | 10⁶ | **minute** |
| Pauses courtes simultanées sur un même groupe | 10⁶ | agent en trop |
| Diversification de la deuxième activité | 10⁵ | booléen |
| Pause ou déjeuner placé | **−10⁵** (bonus) | booléen |
| Activité optionnelle non couverte | 10⁴ | créneau |
| Écart à la cible de diversité | 100 | minute |
| Équilibrage min-max (pool optionnel) | 10 | pourcentage |

- **Unités mélangées.** Une minute d'écart d'équité coûte 10⁶, un créneau optionnel non couvert 10⁴. Donc 15 minutes d'iniquité pèsent autant que 1 500 créneaux optionnels vides. Le solveur sacrifie toujours la couverture optionnelle pour l'équité. Peut-être voulu, mais non documenté.
- **Poids identiques pour des notions différentes.** Une pause simultanée de trop (10⁶) vaut exactement une minute d'écart d'équité. Aucune justification métier visible.
- **Bonus négatifs.** Pauses et déjeuners sont « récompensés » (−10⁵) au lieu d'être imposés. Si une contrainte empêche une pause, rien n'est signalé.

### Passe 2 : couverture (`solver_coverage.py`, l.1208-1440)

Hiérarchie réellement stricte (objectif lexicographique) :

- la phase 1 (2/3 du temps) minimise seulement la couverture : manque × 1000 × multiplicateur des offres prioritaires, plus sureffectif × 1 ;
- les totaux de manque et de surplus obtenus sont figés (`model.Add(total_shortage_var == best...)`) ;
- la phase 2 optimise le reste sans dégrader cette couverture.

| Terme de la phase 2 | Poids effectif |
|---|---|
| Même offre le matin et l'après-midi | `W_SAME`×100 = 30 000 |
| Continuité | `W_SAME`×10 = 3 000 |
| Rattachement au déjeuner | `W_SAME`×6 = 1 800 |
| Plus de 33 % des éligibles en pause au même moment | 1 000 000 par agent en trop |
| Lissage des pauses (somme des carrés) | 5 000 × n² |
| Équité matin/après-midi sur une même offre | `max(weight_equity, 120)` |
| Équité sur la période | `W_PER_EQ` × historique × créneaux du jour |
| Préférence pour le profil mixte | 80 |
| Continuité et nombre d'agents utilisés | 20 |

- **Bug de paramétrage (corrigé en phase 0).** `W_EQUITY = max(..., 120)` imposait un plancher. Le plancher est supprimé ; PHP envoie 120, comportement inchangé (commit `71c5778`).
- **Paramètres morts (supprimés en phase 0).** `weight_break_alignment` et `weight_break_dispersion` étaient exposés mais ignorés par le solveur. Ils ont été retirés (commit `da91409`).

### Solveur des rotations (`solver_rotation.py`, l.483-501) : bug de priorité

Le commentaire annonce « 1. Shortfall, 2. Need Curve, 3. Préférences ». Les poids réels :

- écart à la courbe de besoin : `delta²` × 1 ;
- retravailler le même jour que la semaine passée : **100** ;
- deux jours consécutifs : 10.

La pénalité de 100 s'applique **une fois par jour travaillé**, alors que l'écart au besoin se paie **sur chaque quart d'heure** du bloc. Exemple : un bloc GRC de 3 h couvre 12 quarts d'heure. Le placer là où il manque 3 agents plutôt qu'à un endroit déjà couvert rapporte environ 12 × (3² − 2²) = 60 points, moins que les 100 de pénalité. Le solveur préfère donc éviter le jour répété. Le seuil réel se situe vers **4 à 5 agents de manque** : la rotation passe avant la couverture en dessous. C'est l'inverse du commentaire.

Portée et correctif (branche `fix/rotation-priorites`, 18/11/2026) :

- Shifti utilise le chemin multi-lignes `solve_rotation_lines`, pas le chemin historique. Le poids d'une plage manquante y dépendait du rang de la ligne (`line_weight`) : 1 000 000 au rang 1, 10 000 au rang 2, 100 au rang 3, **1 au-delà**. Une ligne de rang 3 ou plus pouvait perdre des plages au profit d'un jour non répété. Toutes les lignes actuelles sont au rang 1 : pas d'impact constaté.
- Le seuil réel du bug d'emplacement était plus bas qu'estimé : un GRC était déplacé dès 2 agents manquants.
- Bug supplémentaire : si le besoin d'un quart d'heure dépassait le nombre de GRC envoyés plus 1, la rotation de la semaine devenait **INFEASIBLE** (aucune plage). Bornes corrigées.
- Correctif : résolution lexicographique en 4 niveaux (plages dues → besoin → équité des lignes de couverture → confort des jours), dans les deux chemins. 4 tests de non-régression ajoutés.
- Comparaison jobs 269 / 273 (4 semaines, mêmes données) : 250 plages dans les deux cas ; manques totaux identiques (45) mais répartis autrement (avant : 4 plages AE + 41 créneaux Livechat ; après : 21 plages AE + 24 créneaux Livechat) ; jours répétés 66 → 61. À lignes de même rang, le solveur arbitre seul entre téléphonie et Livechat : la priorité des lignes doit être fixée par l'utilisateur.

### Transparence : Shifti contre les IA du marché

- **Avantage réel.** Chaque pénalité est lisible et auditable. Une réclamation d'agent ou une question du CSE reçoit une réponse chiffrée. Peopleware (« fairness rules » opaques) et Verint Da Vinci ne montrent pas leur fonction de coût.
- **Nuance.** Transparent ne veut pas dire calibré. Les poids sont codés en dur, empilés en puissances de 10, et mélangent minutes, créneaux, booléens et pourcentages. Réponse attendue d'un éditeur : « vos poids sont arbitraires, les nôtres sont appris ». Il faut qu'ils soient **justifiables** ; aujourd'hui ils ne le sont qu'en partie.
- **Équité myope.** Shifti résout jour par jour (`generateDayForJob`) en réinjectant l'historique (`current_offer_minutes`, `current_realization`, `history_worked_days`). L'équité converge par choix locaux successifs, sans optimisation sur la période entière. Un recuit simulé sur 4 semaines (Timesquare) peut faire mieux sur l'équité de période. Shifti garde l'exactitude jour par jour. Une solution en deux niveaux (attribution sur la période, puis détail par jour) est décrite en section 2.6.

---

## 2. Gap analysis

### 2.1 Rappel : qu'est-ce que le flux ACD ?

L'ACD (Automatic Call Distributor) est le moteur de téléphonie qui distribue les appels aux agents (Genesys Engage derrière Genesys WFM 8.5.2). Il produit deux types de données :

1. **Les statistiques par intervalle** : volumes, DMT, abandons par tranche de 15 minutes. Elles alimentent la prévision.
2. **Les états agents en temps réel** : en appel, en pause, déconnecté, en post-traitement. Ils servent à l'adhérence (planning prévu comparé au réel).

Ce que vendent les éditeurs :

- **Peopleware** : prévision recalée dans la journée selon les volumes réels, alertes quand le besoin dévie.
- **Verint** : adhérence en direct (exemple : un agent planifié en téléphonie est en pause depuis 12 minutes), propositions automatiques de déplacement d'activités ou de pauses.

### 2.2 Écarts revus selon notre contexte

| Fonction éditeur | État dans Shifti | Pertinence chez nous | Faisabilité |
|---|---|---|---|
| Statistiques ACD en continu (Peopleware, Verint) | **Absent.** Historique importé par Excel (`ExcelUploadsController`). | **Faible pour la prévision.** Les données extraites le lendemain de Sideral (Cognos Analytics) sont plus fiables que le flux brut. | Faisable si l'accès est donné. |
| Comparaison flux réel / prévision dans la journée | **Absent.** | **Moyenne.** Utile pour projeter un taux de décroché en fin de journée selon le planning. | Faisable : une collecte toutes les 5 à 15 minutes suffit, via le worker existant. Ne demande pas de temps réel strict. |
| Supervision temps réel | **Absent.** | **Nulle.** Couverte par Pulse, à ne pas remplacer (développement très lourd et redondant). | Hors périmètre. |
| Adhérence intra-jour (Verint) | **Absent.** Aucune occurrence de `adherence` dans le code. | **Moyenne**, à moyen terme. | Faisable : Genesys WFM 8.5.2 reçoit déjà l'état connecté / déconnecté, le flux existe. L'obstacle est l'**accès** aux données, pas Shifti. Rafraîchissement de 1 à 5 minutes suffisant. |
| Ré-optimisation intra-jour automatique | **Absent.** | **Faible, voire contre-productive.** Le planning est publié plus d'un mois à l'avance. Règle sociale : 1 à 2 changements d'offre par agent et par jour au maximum ; remettre au téléphone un agent prévu en back-office est sensible. | Non souhaitée sous la forme vendue par les éditeurs. Voir section 2.3. |
| Re-génération partielle (agents, jour ou période, en figeant le déjà planifié) | **Partiel.** Voir section 2.3. | **Forte.** | Faisable, briques existantes. |
| Bourse d'échange (Verint) | **Absent.** Aucune occurrence de `swap`, `échange`, `bourse`. | **Obligatoire, prévue.** | Faisable dans le portail CakePHP. Voir section 2.4. |
| Application mobile (Verint) | Absent. | **Nulle.** Outils derrière le VPN, pas de smartphone professionnel. | Hors périmètre. |
| Exports | Partiel. `DataExportService` produit des CSV de données historiques agrégées par créneau. | Moyenne, à développer selon les besoins. | Faisable. |
| Paie, SIRH, annualisation (Timesquare) | Absent. | **Nulle.** Pas de besoin paie. Plage fixe 9 h-17 h. Le couplage GTA eTemptation ne servirait qu'à lire le contrat, sans impact sur la planification. | Hors périmètre. À opposer à Timesquare : ces modules ne doivent pas être facturés. |
| Prévision multi-compétences | Partiel. Erlang C par offre, groupes d'offres découpés par ratio, COS mixtes répartis par le solveur. | Moyenne. | Erlang C ignore les abandons (pas d'Erlang A). Pas de simulation du routage, mais la logique GRC prioritaires / COS de reliquat est déjà correcte. Voir section 2.5. Shrinkage = facteur unique. |

### 2.3 Re-génération partielle et ajustement intra-jour

Le besoin réel n'est pas de « ré-optimiser » en continu. Il est de **re-générer un ou plusieurs agents, sur un jour ou une période, en tenant compte du besoin et de ce qui est déjà planifié**, sous contrainte sociale.

Briques déjà présentes :

- `PlanningGenerationJobsController` accepte une liste d'agents (`agent_ids`) : la génération ciblée existe.
- `ScheduleDayGenerationService` (vers la l.1225) calcule une `residualNeedCurve`, le besoin restant après déduction de ce qui est déjà couvert. Aujourd'hui, seule la couverture des agents en rotation est déduite.
- Le modèle `FixedWork` (`data_models.py`) impose à un agent de travailler sur un créneau donné. Il est défini, mais son envoi au solveur de couverture est commenté (« SUPPRIMÉ : agents exclus »).

Reste à faire :

1. **Déduire le déjà planifié des agents non sélectionnés** du besoin. Exemple : on re-génère Paul, Marie couvre déjà l'offre X de 9 h à 12 h ; le solveur ne doit voir que le besoin restant sur X. Non vérifié dans le code actuel : premier point à contrôler.
2. **Figer le passé** pour un ajustement en cours de journée : tout ce qui précède l'heure de re-génération devient du `FixedWork`.
3. **Plafonner les changements** : contrainte dure « au plus N changements d'offre par agent par rapport au planning publié » (N = 1 ou 2), et interdiction (ou forte pénalité) de passer un agent du back-office au téléphone. CP-SAT traite ce genre de règle exactement ; c'est un argument face aux éditeurs, dont la ré-optimisation automatique ignore souvent ce plafond.
4. **Préserver l'équité** : l'historique transmis (`current_offer_minutes`, `current_realization`) doit inclure le déjà planifié figé, sinon l'agent re-généré sera compté « en retard » d'équité à tort.

### 2.4 Bourse d'échange

Faisable dans le portail web CakePHP, accessible par VPN. Composants à construire :

- une table des demandes d'échange avec statut (proposé, accepté, validé, refusé) ;
- une **vérification des règles avant validation**, point critique : couverture, compétences, télétravail, activités fixes. Deux options :
  - **simple** : contrôles en PHP (compétences des deux agents, couverture par offre non dégradée sur les créneaux échangés) ;
  - **robuste** : appel du solveur Python en mode vérification, avec le planning après échange envoyé en `FixedWork`. Réponse « infaisable » = échange refusé, avec la raison.
- **Recommandation : commencer par l'option simple.** Elle répond instantanément et produit des messages clairs (« Paul n'a pas la compétence CESU »). L'option robuste ajoute plusieurs secondes de latence, et le retour brut de `SufficientAssumptionsForInfeasibility` n'est pas lisible par un agent : il faudrait traduire chaque règle en message. Elle peut servir ensuite de contrôle a posteriori, en tâche de fond.
- un circuit de validation manager avec notifications (e-mail ou dans l'application).

Le frein est la dette technique, pas l'architecture : `SchedulesController.php` (159 Ko) devra être découpé pour accueillir cette fonctionnalité proprement.

### 2.5 Prévision et routage : Erlang C, Erlang A, multi-compétences

#### Rappels

- **Erlang C** : formule qui donne le nombre d'agents nécessaires pour décrocher X % des appels en moins de Y secondes, à partir du volume et de la DMT. Elle suppose qu'**aucun appelant ne raccroche** en attendant. Elle surestime donc le besoin.
- **Erlang A** (« A » pour Abandon) : même formule, avec la patience moyenne des appelants. Besoin plus juste, et capacité à **prédire le taux de décroché** selon le planning. Exemple : « avec 18 agents à 10 h, on décroche 88 % ».
- **Multi-compétences** : un agent qui reçoit plusieurs files comble les creux de l'une avec les appels de l'autre. Une file commune demande moins d'agents que deux files séparées (exemple : CESU seul 6, PAJEMPLOI seul 5, les deux réunis 9 ou 10, pas 11).

#### Organisation du routage

| Offre | Pôle prioritaire | Pôle de reliquat |
|---|---|---|
| CESU / PAJEMPLOI | — | Agents sur un profil mixte (les deux offres) |
| ACCUR | GRC, planifiés 3 × 3 h par semaine (exceptions : formation) | COS |
| TI-AE (3698) | GRC AE seuls + GRC TI seuls (choix du SVI), 3 × 3 h par semaine | COS sur un profil TI-AE complet |
| Évolution prévue | — | COS sur un profil ACCUR + TI-AE |

Ratio TI-AE retenu : 55 % AE / 45 % TI. La part exacte n'est plus mesurable depuis la mise en place du VoiceBot.

#### Ce que fait Shifti aujourd'hui

1. **Besoin par offre** : `WfmCalculatorService::generateNeedCurve` applique Erlang C et le shrinkage, offre par offre, au quart d'heure.
2. **Groupes d'offres** (`OfferGroupsNeedService`) :
   - mode **« group »** : Erlang calculé sur la prévision du numéro global, puis découpé entre membres selon `split_ratio_percent` (plus forts restes). Besoin du mixte remis à 0 ;
   - mode **« members »** : Erlang calculé séparément pour chaque membre, besoin du mixte à 0.
3. **Déduction des GRC** (`ScheduleDayGenerationService`, l.1247-1288) : chaque bloc de rotation retire 1 du besoin, par quart d'heure, sur l'offre du bloc, sans descendre sous 0.
4. **Solveur** (`solver_coverage.py`, l.952-970) : les agents sur l'offre mixte ne sont pas pré-affectés. Le solveur les répartit librement entre membres, créneau par créneau, là où il manque du monde.

#### Verdict : TI-AE et ACCUR sont déjà calculés correctement

Exemple à 14 h : besoin total 16, soit 9 AE et 7 TI. GRC planifiés : 6 AE et 8 TI.

| | AE | TI |
|---|---|---|
| Besoin | 9 | 7 |
| GRC déduits | −6 | −8 |
| Reste (plancher 0) | 3 | 0 |

Shifti place **3 COS**, tous sur AE. C'est exactement la règle de débordement juste : max(AE − GRC AE, TI − GRC TI, total − tous les GRC) = max(3, −1, 2) = 3. Les deux méthodes coïncident toujours avec deux sous-flux.

ACCUR : besoin moins GRC planifiés, les COS couvrent le reste. Correct.

#### Impact du ratio 55/45 inconnu : limité

- Tant que chaque pôle GRC est sous sa part, le besoin COS = total − tous les GRC. **Le ratio n'intervient pas.**
- Il ne compte que si un pôle GRC dépasse sa part. Exemple : 8 GRC TI pour une part TI réelle de 40 % au lieu de 45 % : 0,6 COS de trop sur AE. Négligeable.
- L'enjeu réel est le **dimensionnement des pôles GRC**, décision d'organisation.

Pistes pour estimer le ratio :

- demander à l'équipe téléphonie si les choix SVI invisibles aboutissent encore à des **files virtuelles distinctes** dans Genesys. Si oui, le serveur de statistiques les compte ; c'est peut-être Sideral qui ne les expose pas ;
- les appels traités par les GRC AE donnent un **minimum** du volume AE, pour vérifier que 55 % reste plausible.

#### Points à vérifier ou à corriger

1. **Mode du groupe CESU / PAJEMPLOI.** En mode « members », Erlang est calculé séparément puis additionné : **surestimation**, alors que les agents sont sur un profil mixte. Le mode « group » (prévision sur l'offre mixte, historique agrégé) est le bon. À contrôler dans l'écran Groupes d'offres (`forecast_source`).
2. **Offre des blocs de rotation GRC TI-AE.** Les blocs doivent être posés sur « AE » ou « TI ». Un bloc posé sur le mixte « TI-AE » **ne déduit rien** (besoin du mixte = 0 après découpage) : les COS seraient planifiés comme si les GRC n'existaient pas.
3. **Évolution COS sur ACCUR + TI-AE : non configurable en l'état.** Une offre ne peut appartenir qu'à un seul groupe (`offerToBucket[$memberName]` est écrasé). Il faudra soit un groupe « COS » à 3 membres (ACCUR, AE, TI) remplaçant le groupe TI-AE, soit des groupes imbriqués. Les équations du solveur se généralisent sans difficulté.
4. **Approximation du découpage.** Le besoin est calculé sur la file commune puis découpé ; un pôle GRC dédié, plus petit, est un peu moins efficace. Écart d'une fraction d'agent par créneau, compensé par les COS. Sans conséquence pratique.

#### Erlang A : mise en œuvre

- **Localisée mais pas triviale.** Le changement reste dans `WfmCalculatorService` (remplacement de `calculateErlangC`), mais demande plus qu'une formule : calcul par récurrence (pas besoin de fonctions gamma), précautions numériques sur les gros volumes (calcul en logarithmes), et tests sur des valeurs de référence publiées.
- Donnée à fournir par offre : la **patience moyenne**. Pour les appelants décrochés, on ignore combien de temps ils auraient attendu avant de raccrocher (donnée dite « censurée »). L'estimation retenue règle ce problème : nombre d'appels abandonnés ÷ temps total d'attente de **tous** les appelants (abandonnés ou non), sur la même période, extraits de Sideral. C'est l'estimateur standard sous l'hypothèse de patience exponentielle, celle d'Erlang A : l'attente des appelants décrochés entre au dénominateur. Éviter la seule « durée moyenne avant abandon », biaisée.
- Les rappels après abandon ne sont pas modélisés par Erlang A seul.
- **Pas prioritaire avant les présentations.**

#### Recommandation

- **Pas de simulation appel par appel** : le routage (profils, choix SVI, exceptions formation) change trop souvent ; le modèle actuel est la bonne simplification.
- Contrôler le résultat : comparer sur quelques semaines besoin calculé, agents réellement au téléphone (Sideral) et qualité de service obtenue.
- **Ne pas surestimer cet avantage face aux éditeurs.** Un pôle prioritaire suivi d'un pôle de débordement est une fonction standard des outils WFM. L'avantage de Shifti est de savoir exactement comment le calcul est fait, pas d'être le seul à savoir le faire. Le test reste de faire calculer en séance le besoin COS de 14 h sur TI-AE.

| Offre | Calcul actuel | Verdict |
|---|---|---|
| ACCUR | Besoin − GRC, puis COS | Juste |
| TI-AE | Total découpé 55/45, − GRC par sous-flux, COS répartis par le solveur | Juste |
| CESU / PAJEMPLOI | Selon le mode du groupe | À vérifier (« members » surestime) |
| COS ACCUR + TI-AE | Non configurable | À développer avant la bascule |

### 2.6 Équité sur la période : sortir du jour par jour pour les activités fixes

#### Le problème observé

Shifti calcule une cible par agent (temps d'accueil physique dû selon ses disponibilités, congés déduits), puis résout **chaque jour** en réinjectant le cumul réalisé (`current_realization`, `target_quota_minutes`). Chaque jour, la plage va à l'agent le plus « en retard ».

Exemple : Paul est en congé la semaine A. Le lundi de la semaine B, il est très en retard ; le solveur lui donne les plages de lundi, mardi et jeudi. Résultat : 0 plage en semaine A, 3 en semaine B. Le solveur ne sait pas, le lundi, que Paul pourrait rattraper sur les semaines C et D, ni qu'une limite « au plus 2 plages par semaine » serait souhaitable.

Conséquence : des corrections manuelles avec le recul de 4 à 5 semaines (retirer une plage à un agent pour la donner à un autre, transformer 1 + 3 en 2 + 2).

Ce choix initial n'était pas le seul possible, mais il était raisonnable : c'est la méthode la plus simple qui fonctionne, et elle reste correcte pour la couverture téléphonique. Elle atteint ses limites sur les **ressources rares réparties sur plusieurs semaines**, comme l'accueil physique.

#### La solution : découper en deux niveaux

Résoudre 5 semaines d'un bloc au quart d'heure serait trop lourd. On découpe :

1. **Niveau période** : décider **qui fait quelle plage, quel jour**, sur les 4 à 5 semaines, en une seule résolution.
2. **Niveau journée** : le solveur actuel place ensuite pauses, déjeuners et couverture, avec ces attributions comme données imposées.

Le niveau période est petit : une variable oui / non par agent × jour × plage. Exemple : 60 agents × 25 jours × 4 plages = 6 000 variables, résolues en quelques secondes par CP-SAT.

**Contraintes dures :**

- une plage n'est attribuée qu'à un agent disponible (ni congé, ni formation, ni activité incompatible) ;
- chaque plage reçoit le bon nombre d'agents ;
- chaque agent reçoit sa cible sur la période, à une plage près (cible 7,4 → 7 ou 8) ;
- **lissage hebdomadaire** : sur ses semaines disponibles, pas plus d'une plage d'écart entre deux semaines (1 + 3 interdit, 2 + 2 accepté).

**Objectif :**

- minimiser l'écart maximal à la cible entre agents (on réduit d'abord le plus mal servi) ;
- en second rang, répartir équitablement les plages pénibles (vendredi après-midi, ouverture).

#### Impact dans Shifti

- **Nouvel endpoint Python** (par exemple `/solve-fixed-period`), modèle d'attribution simple. Le calcul existant des cibles par disponibilité est réutilisé.
- **Nouvel ordre dans le job de génération** : niveau période d'abord, puis la boucle jour par jour actuelle. Les attributions sont transmises au solveur du jour comme obligations (`FixedWork` ou activités fixes pré-affectées).
- **Inchangé** : la couverture téléphonique reste calculée jour par jour.

#### Limites

1. **Absence ajoutée après génération** : il faut re-générer la période en figeant le passé et les semaines publiées. Même chantier que la re-génération partielle (section 2.3) : à construire ensemble.
2. **Placement intra-journée décidé ensuite** : si une journée devient impossible (réunion ajoutée entre-temps), prévoir une règle de repli (alerte et proposition d'un autre agent).
3. **Arrondis inévitables** : 30 plages pour 7 agents → certains en ont 4, d'autres 5. L'écart est garanti à une plage au plus, et l'historique des périodes précédentes désigne qui reçoit la plage en plus.

#### Question à poser aux éditeurs

« Votre moteur garantit-il qu'un agent n'a pas 1 plage d'accueil une semaine et 3 la suivante ? Est-ce une contrainte dure ou une pénalité ? Sur quel horizon l'équité est-elle optimisée ? »

---

## 3. Ce que Shifti fait mieux que les métaheuristiques

1. **Règles dures garanties.** Recuit simulé (Timesquare) et métaheuristiques (Verint) traitent les règles par pénalités : une violation reste possible si la recherche s'arrête tôt. Dans Shifti, une réponse `FEASIBLE` respecte toutes les contraintes dures.
2. **Diagnostic d'infaisabilité.** `AddAssumptions` puis `SufficientAssumptionsForInfeasibility` (`solver_coverage.py`, l.1131-1143) renvoient **les agents** qui rendent le planning impossible. Une métaheuristique ne prouve jamais une impossibilité ; elle rend le « moins mauvais » planning.
3. **Couverture d'abord, prouvée.** La passe 2 garantit qu'aucune préférence d'équité ne dégrade la couverture de la phase 1.
4. **Télétravail incompatible.** Une activité non compatible est **interdite** sur les créneaux télétravaillés (`solver_fixed.py`, l.329, 402, 449, 484). Contrainte exacte, pas pénalité. Réserves :
   - dépend de `enforce_remote_work_incompatibilities`. La valeur par défaut est `False` dans le modèle Python, mais **`true` côté PHP** (`WfmSetting.php`, l.76), qui l'envoie toujours : le contrôle est donc actif par défaut ;
   - absent du solveur de couverture (`solver_coverage.py`), **volontairement** : `ScheduleDayGenerationService` (l.1327-1328) précise que le télétravail ne doit pas bloquer la passe 2. La téléphonie se fait en télétravail. Ce n'est un problème que si une offre téléphonique est incompatible avec le télétravail.
5. **Quotas d'équité obligatoires ou optionnels par pool.** Un pool contenant une seule activité obligatoire devient entièrement soumis au quota, écart calculé en minutes historique compris. Rarement paramétrable chez un éditeur sans prestation spécifique.
6. **Formats segmentés (« 3x3h »).** Pas de règle nommée « 3x3h » dans le code. Le mécanisme porteur semble être `FixedActivity.blocks` : plusieurs fenêtres pour une même activité secondaire (`solver_fixed.py`, l.748-751). Exemple : 9 h découpées en trois fenêtres de 3 h, chacune couverte exactement créneau par créneau.

---

## 4. Dette technique et points aveugles

1. **Résultats non reproductibles.** `num_search_workers = 8` dans `solver_coverage.py` avec une limite en temps réel : mêmes données, plannings possiblement différents. La phase 1 ne fige que le **total** de manque ; deux lancements peuvent donc donner la même couverture mais une **équité différente** entre agents, gênant en cas de contestation (agent, CSE). Correction possible **sans revenir à 1 cœur** : `interleave_search = True` et `max_deterministic_time` à la place de `max_time_in_seconds`. Le coût en temps de calcul est à mesurer avant adoption. De plus `num_search_workers` est déprécié au profit de `num_workers`.
2. **Écart à l'optimum invisible.** Un statut `FEASIBLE` après temps limite n'indique pas la distance à l'optimum. Exposer `BestObjectiveBound` et l'écart en pourcentage.
3. **Fichiers géants.** `SchedulesController.php` (159 Ko), `ScheduleDayGenerationService.php` (106 Ko), `PlanningGenerationJobsController.php` (105 Ko). Frein à toute évolution (bourse d'échange, intra-jour).
4. **Tests minces sur le cœur.** `solver_coverage.py` (70 Ko) n'a que 4 tests (`test_offer_groups_coverage.py`). Aucun test ne fige les valeurs d'objectif attendues.
5. **Microservice sans authentification.** FastAPI ne vérifie aucun jeton ; `main.py` démarre sur `0.0.0.0` avec `reload=True`. Inacceptable en production si le port est joignable.
6. **Code déprécié :**
   - `Cake\I18n\FrozenDate` / `FrozenTime` dans **46 fichiers** : dépréciés en CakePHP 5, à remplacer par `Cake\I18n\Date` / `Cake\I18n\DateTime` ;
   - Pydantic ≥ 2 (`requirements.txt`) mais `class Config` (syntaxe v1) : passer à `model_config = ConfigDict(...)`.
7. **Poids codés en dur et contradictoires.** Bug de priorité des rotations, plancher de 120 et paramètres de pauses inutilisés : corrigés en phase 0. Reste ouvert : le rééquilibrage des poids de la passe 1 (plan de route, 1.5).
8. **Une seule personne connaît le code.** Ni support, ni astreinte, ni engagement de service. Argument n° 1 des éditeurs ; il se contre par le coût et l'adéquation, pas techniquement.

---

## 5. Scénarios à imposer aux éditeurs (éviter le piège Genesys)

Imposer **vos** scénarios sur **vos** données, avec une réponse chiffrée à chaque test.

1. **Télétravail.** Agent en télétravail mardi 14 h-17 h, activité non compatible à 15 h. Refus attendu **à 100 %**. Contrainte dure ou pénalité ?
2. **Infaisabilité.** Jeu de données impossible : l'outil doit dire **pourquoi** (agents, règles), pas rendre un planning dégradé en silence.
3. **Équité auditable.** Pour un agent donné, montrer le calcul qui explique son volume de créneaux sur une offre sur 3 mois. « L'IA a décidé » = même opacité que Genesys.
4. **Ordre des priorités.** La couverture prime-t-elle strictement sur l'équité, ou est-ce une somme pondérée ? Qui règle les poids : vous ou leurs consultants facturés ?
5. **Activités fixes, rotations, formats segmentés.** Paramétrage **en séance**, sans prestation. Tout ce qui exige « un consultant » reproduit le piège Genesys.
6. **Reproductibilité.** Même entrée, deux lancements : même planning ?
7. **Ajustement sous contrainte sociale.** Re-générer un agent en cours de journée avec au plus 1 changement d'offre par rapport au planning publié, sans le passer du back-office au téléphone. Le plafond est-il une contrainte dure ? Le déjà planifié des autres agents est-il déduit du besoin ?
8. **Bourse d'échange.** Un échange qui casse la couverture ou une compétence : est-il refusé avant validation, et avec quelle explication ?
9. **Connecteurs ACD.** Le connecteur fonctionne-t-il avec **notre** Genesys Engage ? Est-il inclus dans le prix ou facturé en prestation ? Fait-il doublon avec Pulse ?
10. **Modules inutiles.** Paie, annualisation, application mobile : exiger qu'ils soient retirés du devis.

---

## 6. Correctifs proposés avant les présentations

Par ordre de priorité (détail dans `docs/plan-de-route-shifti.md`, phase 0) :

1. **Vérifications de configuration**, sans code : mode « group » du groupe CESU / PAJEMPLOI, blocs de rotation GRC TI-AE posés sur « AE » ou « TI » (section 2.5).
2. `solver_rotation.py` : remettre la couverture devant la rotation des jours, et relire la seconde fonction objectif (l.915). Générer une semaine réelle avant la correction, régénérer après, comparer couverture, nombre de plages par GRC et jours répétés.
3. `solver_coverage.py` : supprimer le plancher `max(..., 120)` sur `W_EQUITY` (une ligne).
4. `solver_fixed.py` : **documenter** les arbitrages de poids de la passe 1, **sans les rééquilibrer** avant les présentations. Tout changement modifie l'ensemble des plannings et aucun test ne fige les résultats attendus : créer d'abord des tests de référence sur des jeux de données réels.
5. `solver_coverage.py` (après les présentations) : reproductibilité (`interleave_search`, `max_deterministic_time` étalonné sur le serveur, `max_time_in_seconds` gardé comme garde-fou et signalé s'il se déclenche), et retour de l'écart à l'optimum.
6. Contrainte télétravail dans `solver_coverage.py` : **uniquement** si une offre téléphonique est incompatible avec le télétravail.

Reportés après les présentations : Erlang A, découpage de `SchedulesController.php`.

## 7. Feuille de route suggérée

_Version détaillée et priorisée : `docs/plan-de-route-shifti.md`._

1. **Phase 0** : vérifications de configuration puis correctifs de la section 6.
2. **Phase 1** : tests de référence, reproductibilité, écart à l'optimum, sécurité du microservice, puis rééquilibrage des poids de la passe 1.
3. **Phase 2** : extraction du besoin restant hors de `ScheduleDayGenerationService.php`, re-génération partielle (déjà planifié déduit, `FixedWork` réactivé, plafond de changements d'offre), niveau période pour les activités fixes (section 2.6).
4. **Phase 3** : découpage de `SchedulesController.php`, puis bourse d'échange avec validation en PHP.
5. **Phase 4** : patience extraite de Sideral, Erlang A, projection du taux de décroché, groupe « COS » à 3 membres avant la bascule des profils COS.
6. **Phase 5** : comparaison flux réel / prévision et adhérence, si accès aux données.
