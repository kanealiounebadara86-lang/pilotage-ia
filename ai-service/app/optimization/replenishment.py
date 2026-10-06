"""
Moteur d'optimisation du réapprovisionnement (module 17 du cahier des charges).
Une IA dédiée par produit : combine la prévision de demande (module ML),
le stock actuel, le délai fournisseur et le stock de sécurité pour
recommander quoi commander, quand, et en quelle quantité.

Approche volontairement simple et explicable (programmation par règles sur
une prévision ML) plutôt qu'un optimiseur combinatoire complexe — cohérent
avec la consigne du cahier des charges de ne pas complexifier sans preuve
que ça améliore les résultats (section 17).
"""

from datetime import timedelta

import pandas as pd
from sqlalchemy import text

from app.core.db import engine
from app.ml.pipeline import run_forecast_pipeline


def fetch_product_context(product_id: int) -> dict | None:
    """Récupère stock actuel, seuils, et infos fournisseur principal."""
    query = text(
        """
        SELECT
            p.id, p.name, p.stock_min, p.stock_max, p.safety_stock,
            COALESCE(sl.quantity_available, 0) AS current_stock,
            p.primary_supplier_id,
            sp.average_lead_time_days, sp.min_order_quantity, sp.price AS supplier_price
        FROM products p
        LEFT JOIN stock_levels sl ON sl.product_id = p.id
        LEFT JOIN supplier_products sp ON sp.product_id = p.id AND sp.supplier_id = p.primary_supplier_id
        WHERE p.id = :product_id
        """
    )
    with engine.connect() as conn:
        row = conn.execute(query, {"product_id": product_id}).mappings().first()
    return dict(row) if row else None


def compute_replenishment(product_id: int) -> dict:
    context = fetch_product_context(product_id)
    if not context:
        return {"status": "no_data", "message": "Produit introuvable."}

    lead_time = int(context["average_lead_time_days"] or 7)  # 7 jours par défaut si aucun fournisseur renseigné
    forecast = run_forecast_pipeline(product_id, horizon_days=max(lead_time, 3))

    if forecast.get("status") == "no_data":
        return {"status": "no_data", "message": "Pas assez d'historique de ventes pour recommander un réapprovisionnement."}

    demand_over_lead_time = sum(p["quantity"] for p in forecast["predictions"][:lead_time])
    current_stock = context["current_stock"]
    safety_stock = context["safety_stock"] or 0

    recommended_quantity = max(0, round(demand_over_lead_time + safety_stock - current_stock))
    if context["min_order_quantity"] and recommended_quantity > 0:
        recommended_quantity = max(recommended_quantity, context["min_order_quantity"])

    if current_stock <= 0:
        priority = "critique"
    elif current_stock <= context["stock_min"]:
        priority = "elevee"
    elif recommended_quantity > 0:
        priority = "moyenne"
    else:
        priority = "faible"

    decision_type = "commander" if recommended_quantity > 0 else "ne_pas_commander"

    # Confiance dérivée de la qualité du modèle de prévision utilisé en amont
    confidence_map = {"random_forest": 82.0, "xgboost": 85.0, "moving_average": 60.0, "naive": 45.0}
    confidence = confidence_map.get(forecast["best_model"], 50.0)
    if forecast.get("warning"):
        confidence = min(confidence, 40.0)  # historique court = confiance plafonnée

    factors = [
        {
            "label": f"Demande prévue sur {lead_time} jour(s) (délai fournisseur)",
            "weight": round(demand_over_lead_time, 1),
            "direction": "favorable" if decision_type == "commander" else "defavorable",
            "detail": f"Modèle utilisé : {forecast['best_model']}",
        },
        {
            "label": "Stock actuel",
            "weight": current_stock,
            "direction": "defavorable" if current_stock <= context["stock_min"] else "favorable",
            "detail": f"Seuil minimum configuré : {context['stock_min']}",
        },
        {
            "label": "Stock de sécurité",
            "weight": safety_stock,
            "direction": "favorable",
            "detail": "Marge supplémentaire visée au-delà de la demande prévue",
        },
        {
            "label": "Délai fournisseur",
            "weight": lead_time,
            "direction": "defavorable" if lead_time > 10 else "favorable",
            "detail": "Délai moyen observé (ou 7 jours par défaut si non renseigné)",
        },
    ]

    return {
        "status": "ok",
        "product_id": product_id,
        "product_name": context["name"],
        "decision_type": decision_type,
        "recommended_quantity": recommended_quantity,
        "recommended_in_days": 0 if priority == "critique" else max(0, lead_time - 2),
        "priority": priority,
        "confidence": confidence,
        "estimated_impact": round(recommended_quantity * (context["supplier_price"] or 0), 2),
        "supplier_id": context["primary_supplier_id"],
        "factors": factors,
        "forecast_summary": {
            "best_model": forecast["best_model"],
            "history_days_used": forecast["history_days_used"],
            "warning": forecast.get("warning"),
        },
    }
