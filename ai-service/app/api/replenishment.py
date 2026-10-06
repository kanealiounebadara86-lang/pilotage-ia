from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.security import verify_api_key
from app.optimization.replenishment import compute_replenishment

router = APIRouter(prefix="/replenishment", tags=["replenishment"])


class ReplenishmentRequest(BaseModel):
    product_id: int


@router.post("", dependencies=[Depends(verify_api_key)])
def replenishment(payload: ReplenishmentRequest):
    """
    Recommandation de réapprovisionnement pour un produit (module 17).
    Combine prévision de demande, stock actuel, délai fournisseur et
    stock de sécurité — une IA dédiée par produit.
    """
    return compute_replenishment(payload.product_id)
