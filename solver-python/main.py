# -*- coding: utf-8 -*-
# Point d'entrée FastAPI pour les solveurs

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from routers import solver_fixed, solver_coverage, solver_rotation

app = FastAPI()

app.include_router(solver_fixed.router, prefix="/api/v1", tags=["fixed-activities"])
app.include_router(solver_coverage.router, prefix="/api/v1", tags=["coverage"])
app.include_router(solver_rotation.router, prefix="/api/v1", tags=["rotation"])

@app.exception_handler(RequestValidationError)
async def validation_exception_handler(request: Request, exc: RequestValidationError):
    """Handler personnalisé pour afficher les erreurs de validation en détail."""
    errors = exc.errors()
    print(f"[ERROR] Validation error sur {request.url.path}:")
    for error in errors:
        print(f"  - {error['loc']}: {error['msg']} (type: {error['type']})")
    return JSONResponse(
        status_code=422,
        content={"detail": errors, "body": str(await request.body())[:500]}
    )

@app.get("/health")
def health():
    return {"status": "ok"}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
