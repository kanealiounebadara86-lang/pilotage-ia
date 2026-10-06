"""
Outils exposés au LLM (module 21 du cahier des charges). Chaque outil appelle
l'API Laravel EN UTILISANT LE TOKEN DE L'UTILISATEUR QUI POSE LA QUESTION —
donc les permissions de son rôle s'appliquent exactement comme s'il naviguait
lui-même dans l'application. Le LLM ne touche jamais MySQL directement et ne
peut PAS créer/modifier/supprimer quoi que ce soit : uniquement des outils de
lecture et de calcul (prévision, recommandations) — jamais de commande, de
paiement, ou d'envoi de message, conformément à la section 22.
"""

import httpx

from app.core.config import settings

TOOL_DEFINITIONS = [
    {
        "name": "get_dashboard_summary",
        "description": "Renvoie les KPI globaux de l'entreprise : chiffre d'affaires, marge, stock, achats, finance, RH, alertes actives, sur la période en cours.",
        "input_schema": {"type": "object", "properties": {}},
    },
    {
        "name": "search_products",
        "description": "Recherche des produits par nom (recherche partielle). Retourne leur stock actuel, prix, et statut.",
        "input_schema": {
            "type": "object",
            "properties": {"query": {"type": "string", "description": "Terme de recherche, ex: 'iPhone'"}},
            "required": ["query"],
        },
    },
    {
        "name": "get_sales_forecast",
        "description": "Lance une prévision de ventes pour un produit précis (identifié par son ID) sur un horizon donné, avec le modèle ML utilisé et sa fiabilité.",
        "input_schema": {
            "type": "object",
            "properties": {
                "product_id": {"type": "integer"},
                "horizon_days": {"type": "integer", "description": "Nombre de jours à prévoir, défaut 14"},
            },
            "required": ["product_id"],
        },
    },
    {
        "name": "get_replenishment_recommendation",
        "description": "Calcule la recommandation de réapprovisionnement (quoi/combien/quand commander) pour un produit précis, avec l'explication des facteurs.",
        "input_schema": {
            "type": "object",
            "properties": {"product_id": {"type": "integer"}},
            "required": ["product_id"],
        },
    },
    {
        "name": "get_finance_insights",
        "description": "Renvoie les signaux financiers actuels : risque de trésorerie, anomalies de dépenses.",
        "input_schema": {"type": "object", "properties": {}},
    },
    {
        "name": "get_marketing_suggestions",
        "description": "Renvoie les suggestions de campagnes marketing basées sur les tendances de vente récentes.",
        "input_schema": {"type": "object", "properties": {}},
    },
    {
        "name": "get_hr_insights",
        "description": "Renvoie l'analyse RH : employés à surveiller (retards, heures supplémentaires excessives) sur les 30 derniers jours.",
        "input_schema": {"type": "object", "properties": {}},
    },
]


class LaravelToolClient:
    """Exécute les outils en appelant l'API Laravel avec le token de l'utilisateur courant."""

    def __init__(self, laravel_base_url: str, user_token: str):
        self.base_url = laravel_base_url.rstrip("/")
        self.headers = {"Authorization": f"Bearer {user_token}", "Accept": "application/json"}

    def _get(self, path: str, params: dict | None = None) -> dict:
        with httpx.Client(timeout=90) as client:
            resp = client.get(f"{self.base_url}{path}", headers=self.headers, params=params)
            return resp.json() if resp.status_code < 500 else {"error": "Le serveur a répondu avec une erreur."}

    def _post(self, path: str, json: dict | None = None) -> dict:
        with httpx.Client(timeout=90) as client:
            resp = client.post(f"{self.base_url}{path}", headers=self.headers, json=json or {})
            return resp.json() if resp.status_code < 500 else {"error": "Le serveur a répondu avec une erreur."}

    def execute(self, tool_name: str, tool_input: dict) -> dict:
        if tool_name == "get_dashboard_summary":
            return self._get("/api/dashboard")
        if tool_name == "search_products":
            return self._get("/api/products", {"search": tool_input.get("query", ""), "per_page": 10})
        if tool_name == "get_sales_forecast":
            return self._post("/api/ai/forecast", {
                "product_id": tool_input["product_id"],
                "horizon_days": tool_input.get("horizon_days", 14),
            })
        if tool_name == "get_replenishment_recommendation":
            result = self._post("/api/ai/replenishment/run", {"product_ids": [tool_input["product_id"]]})
            return result.get("results", [{}])[0] if result.get("results") else result
        if tool_name == "get_finance_insights":
            return self._post("/api/ai/finance/insights")
        if tool_name == "get_marketing_suggestions":
            return self._post("/api/ai/marketing/insights")
        if tool_name == "get_hr_insights":
            return self._post("/api/ai/hr/insights", {"scope": "all"})

        return {"error": f"Outil inconnu : {tool_name}"}
