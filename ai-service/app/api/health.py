from fastapi import APIRouter

router = APIRouter(tags=["health"])


@router.get("/health")
def health():
    """Endpoint public (pas d'authentification) pour vérifier que le service tourne."""
    return {"status": "ok", "service": "ai-service"}
