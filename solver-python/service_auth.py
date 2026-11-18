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
