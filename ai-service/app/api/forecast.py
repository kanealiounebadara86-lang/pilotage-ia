from fastapi import APIRouter, Depends
from pydantic import BaseModel, Field

from app.core.security import verify_api_key
from app.ml.pipeline import run_forecast_pipeline

router = APIRouter(prefix="/forecast", tags=["forecast"])


class ForecastRequest(BaseModel):
    product_id: int
    horizon_days: int = Field(default=14, ge=1, le=90)


@router.post("", dependencies=[Depends(verify_api_key)])
def forecast(payload: ForecastRequest):
    """
    Prévision des ventes d'un produit sur un horizon donné (module 16).
    Exécute le pipeline complet : extraction -> nettoyage -> baseline ->
    modèles ML -> évaluation -> sélection -> prévision.
    """
    return run_forecast_pipeline(payload.product_id, payload.horizon_days)
