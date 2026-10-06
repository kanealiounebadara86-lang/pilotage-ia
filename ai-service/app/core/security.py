from fastapi import Header, HTTPException, status

from app.core.config import settings


async def verify_api_key(x_api_key: str = Header(default=None)) -> None:
    """
    Vérifie la clé de service partagée avec Laravel. Toutes les routes de ce
    service (sauf /health) exigent cet en-tête — le service IA ne doit jamais
    être exposé sans authentification, même en réseau interne.
    """
    if not x_api_key or x_api_key != settings.ai_service_api_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Clé API de service invalide ou manquante.",
        )
