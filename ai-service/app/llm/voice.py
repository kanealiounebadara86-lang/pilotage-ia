"""
Compréhension des commandes vocales (français) — moteur Gemini.

Ce module ne fait QU'UNE chose : transformer une phrase ("encaisse tout en
espèces") en une intention structurée (JSON). Il n'exécute rien et n'a aucun
accès à la base : c'est Laravel qui résout les noms (produit, client,
employé), vérifie les permissions de l'utilisateur, demande confirmation
pour les actions sensibles, puis exécute via ses services métier.
"""

import json
import re

import google.generativeai as genai

from app.core.config import settings

INTENTS = [
    "create_product", "create_customer", "create_supplier", "record_sale",
    "collect_payment", "cancel_sale", "punch_employee", "punch_all", "navigate", "unknown",
]

SYSTEM_PROMPT = """Tu es le module de compréhension vocale d'un logiciel de gestion d'entreprise (français, XOF).
Tu reçois UNE phrase dictée (parfois avec des erreurs de reconnaissance vocale) et tu réponds UNIQUEMENT par un objet JSON, sans texte autour.

Format : {"intent": "...", "params": {...}}

Intentions possibles et paramètres :
- create_product : {"name": str, "sale_price": number|null, "purchase_price": number|null, "unit": str|null, "stock_min": number|null}
  (ex : "ajoute le produit ventilateur à 25000", "crée un produit chargeur prix de vente 6000 prix d'achat 2500")
- create_customer : {"name": str, "phone": str|null}  (ex : "ajoute le client Moussa Diop")
- create_supplier : {"name": str, "phone": str|null}  (ex : "ajoute le fournisseur Dakar Import")
- record_sale : {"items": [{"product_name": str, "quantity": number}], "customer_name": str|null, "payment_method": "especes"|"mobile_money"|"carte"|"virement"|"cheque"|null, "pay_now": bool}
  (ex : "enregistre une vente de deux Redmi Note 12 payée en espèces". pay_now=true si la phrase dit payé, encaissé, cash, comptant ou donne un mode de paiement)
- collect_payment : {"scope": "all"|"customer"|"sale", "customer_name": str|null, "sale_reference": str|null, "method": "especes"|"mobile_money"|"carte"|"virement"|"cheque"}
  (ex : "encaisse tout en espèces" -> scope all ; "encaisse les ventes de Moussa par mobile money" -> scope customer ; "encaisse la vente VTE-AB12CD34" -> scope sale. Mode par défaut : especes)
- cancel_sale : {"sale_reference": str}  (utilise "last" pour "la dernière vente")
- punch_employee : {"employee_name": str}  (ex : "pointe Awa Diop", "Moussa est arrivé", "Fatou part")
- punch_all : {}  (ex : "pointe tout le monde")
- navigate : {"page": str}  (ex : "ouvre les ventes", "va à la comptabilité", "montre-moi le pointage")
- unknown : {} si la phrase ne correspond à aucune intention ou si c'est une simple question.

Règles : convertis les nombres dits en lettres en chiffres ("vingt-cinq mille" -> 25000, "deux" -> 2). Ne devine jamais un nom absent de la phrase (mets null). Les mots "espèces", "cash" -> especes ; "wave", "orange money", "mobile" -> mobile_money."""


def interpret_voice(transcript: str) -> dict:
    if not settings.gemini_api_key:
        return {"intent": "unknown", "params": {}, "status": "not_configured",
                "message": "Il manque la clé GEMINI_API_KEY dans le fichier .env du service ai-service."}

    genai.configure(api_key=settings.gemini_api_key)
    model = genai.GenerativeModel(
        model_name=settings.gemini_model,
        system_instruction=SYSTEM_PROMPT,
        generation_config={"response_mime_type": "application/json", "temperature": 0},
    )

    try:
        response = model.generate_content(transcript)
        raw = response.text.strip()
    except Exception as exc:  # réseau, quota, modèle indisponible…
        return {"intent": "unknown", "params": {}, "status": "error",
                "message": f"Erreur de connexion au modèle Gemini : {exc}"}

    # Certains modèles entourent le JSON de ```json … ``` malgré la consigne.
    raw = re.sub(r"^```(?:json)?|```$", "", raw, flags=re.MULTILINE).strip()
    try:
        data = json.loads(raw)
    except json.JSONDecodeError:
        return {"intent": "unknown", "params": {}, "status": "error", "message": "Réponse du modèle illisible."}

    intent = data.get("intent") if data.get("intent") in INTENTS else "unknown"
    params = data.get("params") if isinstance(data.get("params"), dict) else {}
    return {"intent": intent, "params": params, "status": "ok"}
