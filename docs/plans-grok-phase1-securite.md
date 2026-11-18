# Plans d'exécution — Phase 1, tâche 1.4 : sécurité des services Python (pour un agent d'exécution)

_Rédigé le 18/11/2026. Référence : `docs/plan-de-route-shifti.md`, action 1.4._

## Objectif en une phrase

Les services Python (solveur, port 8000 ; Prophet, port 8001) n'acceptent plus que les appels portant le bon jeton, et leur adresse devient réglable, pour pouvoir les installer sur un autre serveur que l'application.

## Principe (à lire avant de commencer)

- Un **jeton partagé** est une chaîne secrète, identique des deux côtés, placée dans la variable d'environnement `SHIFTI_SERVICE_TOKEN`.
- CakePHP envoie ce jeton dans l'en-tête HTTP `X-Shifti-Token` à chaque appel vers Python.
- Python compare l'en-tête au jeton attendu. S'ils diffèrent : réponse **401**.
- **Si la variable est vide, rien n'est vérifié** (un avertissement s'affiche au démarrage). En local et sur le VPS de test, le comportement reste donc identique tant que la variable n'est pas définie.
- `/health` reste public (il ne renvoie que `{"status": "ok"}`).

## Règles impératives pour l'agent

1. Exécuter **une seule tâche à la fois**, dans l'ordre. Ne pas commencer la suivante sans validation de l'utilisateur.
2. Ne modifier **que** les fichiers listés dans la tâche. Aucun refactoring, aucun renommage, aucune correction « en passant ».
3. Chaque remplacement est donné sous la forme **AVANT / APRÈS**. Le texte AVANT doit être trouvé **exactement le nombre de fois indiqué** (une fois par défaut). Sinon : **s'arrêter et le signaler**, ne pas improviser.
4. Ne jamais modifier un test existant pour le faire passer. Si un test échoue : s'arrêter, copier la sortie, la signaler.
5. Messages de commit **en français**. Interdit : `git add .` ou `git add -A`. Les fichiers `docs/bilan_*.md`, `docs/rapport_optuna_horizon_previsions.md` et le dossier `tmp/` ne doivent **pas** être commités.
6. Environnement : Windows, PowerShell. Racine du projet : `c:\wamp64\www\shifti`. Python du solveur : `solver-python\.venv\Scripts\python.exe`.
7. Commande de tests Python (depuis `c:\wamp64\www\shifti\solver-python`) :

   ```powershell
   .\.venv\Scripts\python.exe -m unittest discover -s tests -t . 2>&1 | % { "$_" } | Select-String -Pattern "^Ran |^OK|^FAILED|^FAIL:|^ERROR:"
   ```

   Résultat attendu **avant** la tâche 1 : `Ran 63 tests` et `OK`. **Après** la tâche 1 : `Ran 68 tests` et `OK`.
8. Vérification de syntaxe PHP : `php -l <fichier>` doit afficher `No syntax errors detected`.
9. Ne **pas** installer de paquet (pas de `pip install`, pas de `composer require`).

---

## Tâche 0 — Créer la branche

```powershell
cd c:\wamp64\www\shifti
git branch --show-current
```

Attendu : `main`. Sinon : s'arrêter.

```powershell
git checkout -b feat/securite-services-python
```

Lancer les tests Python (règle 7). Attendu : `Ran 63 tests` et `OK`.

---

## Tâche 1 — Module Python de contrôle du jeton, avec ses tests

**Fichiers :** `solver-python/service_auth.py` (nouveau), `solver-python/tests/test_service_auth.py` (nouveau).

### 1.a Créer `solver-python/service_auth.py` avec exactement ce contenu

```python
# -*- coding: utf-8 -*-
"""Contrôle du jeton partagé entre CakePHP et les services Python."""

import hmac
import os

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

TOKEN_ENV = "SHIFTI_SERVICE_TOKEN"
TOKEN_HEADER = "X-Shifti-Token"
PUBLIC_PATHS = frozenset({"/health"})


def is_authorized(path: str, provided: str, expected: str) -> bool:
    if not expected or path in PUBLIC_PATHS:
        return True
    return hmac.compare_digest(provided.encode("utf-8"), expected.encode("utf-8"))


def install_token_guard(app: FastAPI) -> None:
    expected = os.environ.get(TOKEN_ENV, "")
    if not expected:
        print(f"[WARN] {TOKEN_ENV} vide : aucune vérification de jeton.")
        return

    @app.middleware("http")
    async def _token_guard(request: Request, call_next):
        provided = request.headers.get(TOKEN_HEADER, "")
        if not is_authorized(request.url.path, provided, expected):
            return JSONResponse(status_code=401, content={"detail": "Jeton de service invalide"})
        return await call_next(request)
```

### 1.b Créer `solver-python/tests/test_service_auth.py` avec exactement ce contenu

```python
# -*- coding: utf-8 -*-
import unittest

from service_auth import is_authorized


class IsAuthorizedTests(unittest.TestCase):
    def test_bon_jeton(self):
        self.assertTrue(is_authorized("/api/v1/solve-schedule", "secret", "secret"))

    def test_mauvais_jeton(self):
        self.assertFalse(is_authorized("/api/v1/solve-schedule", "autre", "secret"))

    def test_jeton_absent(self):
        self.assertFalse(is_authorized("/api/v1/solve-schedule", "", "secret"))

    def test_health_reste_public(self):
        self.assertTrue(is_authorized("/health", "", "secret"))

    def test_sans_jeton_attendu_tout_passe(self):
        self.assertTrue(is_authorized("/api/v1/solve-schedule", "", ""))


if __name__ == "__main__":
    unittest.main()
```

### 1.c Vérifier

Tests Python (règle 7). Attendu : `Ran 68 tests` et `OK`.

### 1.d Commiter

```powershell
cd c:\wamp64\www\shifti
git add solver-python/service_auth.py solver-python/tests/test_service_auth.py
git commit -m "Services Python : module de contrôle du jeton partagé et tests"
```

---

## Tâche 2 — Brancher le contrôle dans les deux services et rendre l'écoute réglable

**Fichiers :** `solver-python/main.py`, `solver-python/forecast_prophet.py`.

### 2.a `solver-python/main.py` — import et activation

AVANT :

```python
from routers import solver_fixed, solver_coverage, solver_rotation

app = FastAPI()
```

APRÈS :

```python
from routers import solver_fixed, solver_coverage, solver_rotation
from service_auth import install_token_guard

app = FastAPI()
install_token_guard(app)
```

### 2.b `solver-python/main.py` — démarrage direct

AVANT :

```python
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
```

APRÈS :

```python
if __name__ == "__main__":
    import os
    import uvicorn
    uvicorn.run(
        "main:app",
        host=os.environ.get("SOLVER_HOST", "127.0.0.1"),
        port=8000,
        reload=os.environ.get("SOLVER_RELOAD") == "1",
    )
```

### 2.c `solver-python/forecast_prophet.py` — import et activation

AVANT :

```python
app = FastAPI(title="Prophet Forecast Service", version="1.0.0")
```

APRÈS :

```python
from service_auth import install_token_guard

app = FastAPI(title="Prophet Forecast Service", version="1.0.0")
install_token_guard(app)
```

### 2.d `solver-python/forecast_prophet.py` — démarrage direct

AVANT :

```python
    print("Mode: Auto-reload activé")
    print("=" * 60)
    uvicorn.run(
        "forecast_prophet:app",
        host="127.0.0.1",
        port=8001,
        reload=True,
        log_level="info"
    )
```

APRÈS :

```python
    print("=" * 60)
    uvicorn.run(
        "forecast_prophet:app",
        host=os.environ.get("FORECAST_HOST", "127.0.0.1"),
        port=8001,
        reload=os.environ.get("FORECAST_RELOAD") == "1",
        log_level="info"
    )
```

(`os` est déjà importé en haut de `forecast_prophet.py` : ne pas l'ajouter.)

### 2.e Vérifier

1. Tests Python (règle 7). Attendu : `Ran 68 tests` et `OK`.
2. Les deux modules se chargent :

   ```powershell
   cd c:\wamp64\www\shifti\solver-python
   .\.venv\Scripts\python.exe -c "import main, forecast_prophet; print('OK')"
   ```

   Attendu : deux lignes `[WARN] SHIFTI_SERVICE_TOKEN vide : aucune vérification de jeton.` puis `OK`. Toute erreur : s'arrêter.

### 2.f Commiter

```powershell
cd c:\wamp64\www\shifti
git add solver-python/main.py solver-python/forecast_prophet.py
git commit -m "Services Python : contrôle du jeton, écoute locale par défaut, rechargement automatique sur option"
```

> Note pour l'utilisateur : en développement, pour retrouver le rechargement automatique avec `python main.py`, définir `SOLVER_RELOAD=1` (et `FORECAST_RELOAD=1` pour Prophet).

---

## Tâche 3 — Configuration CakePHP et classe d'en-tête

**Fichiers :** `config/app.php`, `src/Service/PythonServiceAuth.php` (nouveau), `tests/TestCase/Service/PythonServiceAuthTest.php` (nouveau).

### 3.a `config/app.php`

AVANT :

```php
    'Session' => [
        'defaults' => 'php',
    ],
];
```

APRÈS :

```php
    'Session' => [
        'defaults' => 'php',
    ],

    /*
     * Services Python (solveur OR-Tools, prévisions Prophet).
     * Le jeton doit être identique à SHIFTI_SERVICE_TOKEN côté Python ; vide = aucun contrôle.
     */
    'PythonSolver' => [
        'url' => env('PYTHON_SOLVER_URL', 'http://127.0.0.1:8000'),
    ],
    'PythonForecast' => [
        'url' => env('PYTHON_FORECAST_URL', 'http://127.0.0.1:8001'),
    ],
    'PythonServices' => [
        'token' => env('SHIFTI_SERVICE_TOKEN', ''),
    ],
];
```

### 3.b Créer `src/Service/PythonServiceAuth.php` avec exactement ce contenu

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;

/**
 * En-tête d'authentification envoyé aux services Python (solveur, Prophet).
 */
final class PythonServiceAuth
{
    public const HEADER = 'X-Shifti-Token';

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $token = (string)Configure::read('PythonServices.token', '');

        return $token === '' ? [] : [self::HEADER => $token];
    }
}
```

### 3.c Créer `tests/TestCase/Service/PythonServiceAuthTest.php` avec exactement ce contenu

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PythonServiceAuth;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

class PythonServiceAuthTest extends TestCase
{
    public function testHeadersVidesSansJeton(): void
    {
        Configure::write('PythonServices.token', '');
        $this->assertSame([], PythonServiceAuth::headers());
    }

    public function testHeadersAvecJeton(): void
    {
        Configure::write('PythonServices.token', 'abc123');
        $this->assertSame(['X-Shifti-Token' => 'abc123'], PythonServiceAuth::headers());
    }
}
```

### 3.d Vérifier

```powershell
cd c:\wamp64\www\shifti
php -l config/app.php
php -l src/Service/PythonServiceAuth.php
php -l tests/TestCase/Service/PythonServiceAuthTest.php
php vendor/bin/phpunit tests/TestCase/Service/PythonServiceAuthTest.php
```

Attendu : trois fois `No syntax errors detected`, puis `OK (2 tests, 2 assertions)`.

### 3.e Commiter

```powershell
git add config/app.php src/Service/PythonServiceAuth.php tests/TestCase/Service/PythonServiceAuthTest.php
git commit -m "Configuration : adresses et jeton des services Python par variables d'environnement"
```

---

## Tâche 4 — Envoyer le jeton dans les 9 appels HTTP vers Python

**Fichiers :** `src/Service/ScheduleDayGenerationService.php`, `src/Controller/SchedulesController.php`, `src/Service/ProphetForecastHelper.php`.

`ScheduleDayGenerationService` et `ProphetForecastHelper` sont dans le namespace `App\Service` : **aucun `use` à ajouter**. Seul `SchedulesController` a besoin d'un `use`.

### 4.a `src/Service/ScheduleDayGenerationService.php` — passes 1 et 2

AVANT (présent **exactement 2 fois** : appels `solve-fixed-activities` et `solve-schedule`) :

```php
                ['type' => 'application/json'],
```

APRÈS (remplacer **les 2** occurrences) :

```php
                ['type' => 'application/json', 'headers' => PythonServiceAuth::headers()],
```

Attention : l'indentation diffère peut-être entre les deux occurrences. Chercher la chaîne `['type' => 'application/json'],` : elle doit apparaître exactement 2 fois dans ce fichier. Remplacer chacune en gardant son indentation.

### 4.b `src/Service/ScheduleDayGenerationService.php` — rotation

AVANT :

```php
                        json_encode($rotationPayload),
                        ['type' => 'json']
```

APRÈS :

```php
                        json_encode($rotationPayload),
                        ['type' => 'json', 'headers' => PythonServiceAuth::headers()]
```

### 4.c `src/Controller/SchedulesController.php` — `use`

AVANT :

```php
use App\Service\PlanningDayHistoryService;
```

APRÈS :

```php
use App\Service\PlanningDayHistoryService;
use App\Service\PythonServiceAuth;
```

### 4.d `src/Controller/SchedulesController.php` — passes 1 et 2

Même règle qu'en 4.a : la chaîne `['type' => 'application/json'],` doit apparaître **exactement 2 fois** dans ce fichier (appels `solve-fixed-activities` et `solve-schedule`). Remplacer chacune, en gardant son indentation, par :

```php
['type' => 'application/json', 'headers' => PythonServiceAuth::headers()],
```

### 4.e `src/Service/ProphetForecastHelper.php` — test de santé

AVANT :

```php
            $response = $this->httpClient->get($this->serviceUrl . '/health', [], [
                'timeout' => 5
            ]);
```

APRÈS :

```php
            $response = $this->httpClient->get($this->serviceUrl . '/health', [], [
                'timeout' => 5,
                'headers' => PythonServiceAuth::headers(),
            ]);
```

### 4.f `src/Service/ProphetForecastHelper.php` — prévisions unitaire et par lot

AVANT (présent **exactement 2 fois** : `/forecast/generate` et `/forecast/batch`) :

```php
                    'type' => 'json',
                    'timeout' => $this->timeout
```

APRÈS (remplacer **les 2** occurrences) :

```php
                    'type' => 'json',
                    'timeout' => $this->timeout,
                    'headers' => PythonServiceAuth::headers(),
```

### 4.g `src/Service/ProphetForecastHelper.php` — test Prophet

AVANT :

```php
            $response = $this->httpClient->get($this->serviceUrl . '/forecast/test', [], [
                'timeout' => 10
            ]);
```

APRÈS :

```php
            $response = $this->httpClient->get($this->serviceUrl . '/forecast/test', [], [
                'timeout' => 10,
                'headers' => PythonServiceAuth::headers(),
            ]);
```

### 4.h Vérifier

```powershell
cd c:\wamp64\www\shifti
php -l src/Service/ScheduleDayGenerationService.php
php -l src/Controller/SchedulesController.php
php -l src/Service/ProphetForecastHelper.php
rg -c "PythonServiceAuth::headers\(\)" src
```

Attendu : trois fois `No syntax errors detected`, puis exactement :

```
src\Service\ScheduleDayGenerationService.php:3
src\Controller\SchedulesController.php:2
src\Service\ProphetForecastHelper.php:4
```

(L'ordre des lignes peut varier. Les compteurs 3, 2 et 4 sont obligatoires. Sinon : s'arrêter.)

### 4.i Commiter

```powershell
git add src/Service/ScheduleDayGenerationService.php src/Controller/SchedulesController.php src/Service/ProphetForecastHelper.php
git commit -m "Appels vers les services Python : envoi du jeton partagé"
```

---

## Tâche 5 — Test manuel (fait par l'utilisateur, pas par l'agent)

L'agent s'arrête ici et donne cette liste à l'utilisateur.

1. **Sans jeton (comportement actuel).** Redémarrer le solveur et Prophet. Relancer un job de génération (ex. le 273). Attendu : il passe comme avant.
2. **Avec jeton des deux côtés.** Définir la même valeur de `SHIFTI_SERVICE_TOKEN` pour PHP (Apache / WAMP) **et** pour les deux services Python, redémarrer tout. Relancer le job. Attendu : il passe.
3. **Jeton d'un seul côté** (Python seulement). Relancer. Attendu : échec visible avec un code HTTP 401 dans le rapport du job. Puis remettre le jeton côté PHP.
4. L'écran de santé des services reste vert dans tous les cas (`/health` est public).

Si tout est conforme : fusionner `feat/securite-services-python` dans `main`.

---

## Mise en production sur deux serveurs (actions manuelles, hors agent)

**Serveur des services Python (solveur + Prophet + worker de tuning Prophet) :**

- Lancer uvicorn avec `--host <IP privée du serveur>`, pas `0.0.0.0`. Exemple : `uvicorn main:app --host 10.0.0.12 --port 8000 --workers 1 --timeout-keep-alive 300`.
- Pare-feu : ports 8000 et 8001 ouverts **uniquement** depuis l'IP du serveur d'application.
- Variables : `SHIFTI_SERVICE_TOKEN`, et pour Prophet `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` (Prophet lit la base directement, via `prophet_common.py`).

**Serveur de l'application et de la base :**

- Variables : `SHIFTI_SERVICE_TOKEN` (même valeur), `PYTHON_SOLVER_URL=http://<IP serveur Python>:8000`, `PYTHON_FORECAST_URL=http://<IP serveur Python>:8001`.
- MySQL : créer un utilisateur dédié à Prophet, autorisé seulement depuis l'IP du serveur Python, au lieu de `root` (valeur par défaut de `prophet_common.py`).

**Générer le jeton** (64 caractères aléatoires) : `openssl rand -hex 32`.

**Transport :** le jeton circule en clair en HTTP. Acceptable sur un réseau privé entre les deux serveurs. Si le trafic passe par un réseau partagé : mettre un proxy TLS (nginx) devant uvicorn.
