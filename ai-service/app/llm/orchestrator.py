"""
Orchestrateur LLM (module 21, phase 8) — moteur Google Gemini.
Reçoit une question en langage naturel, laisse le modèle décider quels
outils appeler (parmi ceux définis dans tools.py — jamais d'accès direct à
MySQL, jamais d'action d'écriture sensible), exécute ces outils via l'API
Laravel authentifiée avec le token de l'utilisateur, puis renvoie une
réponse en langage naturel basée uniquement sur les données récupérées.
"""

import google.generativeai as genai
from google.generativeai.types import FunctionDeclaration, Tool
from google.protobuf.struct_pb2 import Struct

from app.core.config import settings
from app.llm.tools import TOOL_DEFINITIONS, LaravelToolClient

SYSTEM_PROMPT = """Tu es l'assistant intégré d'un système d'information de gestion d'entreprise.

Règles strictes :
- Tu réponds UNIQUEMENT à partir des données renvoyées par les outils que tu appelles. Tu n'inventes JAMAIS un chiffre.
- Si un outil ne renvoie pas l'information demandée, dis-le clairement plutôt que de deviner.
- Tu ne peux ni créer de commande, ni valider de paiement, ni envoyer de message — si l'utilisateur te le demande, explique-lui que cette action nécessite qu'il clique lui-même sur le bouton correspondant dans l'application (page Achats, Orchestrateur IA, etc.), pour des raisons de contrôle humain sur les décisions engageant de l'argent.
- Réponds en français, de façon concise et directe, avec les chiffres exacts renvoyés par les outils.
- Si la question porte sur un produit ou un employé précis, utilise search_products ou les autres outils pour le retrouver avant de répondre.
- N'utilise JAMAIS de mise en forme Markdown : pas d'astérisques, pas de **gras**, pas de listes à puces avec des tirets ou des étoiles, pas de titres avec #. Écris uniquement en texte brut, avec des phrases complètes et des sauts de ligne simples pour séparer les idées.
"""

MAX_TOOL_ROUNDS = 5


def _build_tool() -> Tool:
    declarations = [
        FunctionDeclaration(
            name=t["name"],
            description=t["description"],
            parameters=t["input_schema"],
        )
        for t in TOOL_DEFINITIONS
    ]
    return Tool(function_declarations=declarations)


def run_agent_query(question: str, laravel_token: str, conversation_history: list | None = None) -> dict:
    if not settings.gemini_api_key:
        return {
            "answer": "L'assistant IA n'est pas encore configuré : il manque une clé API (GEMINI_API_KEY) dans le fichier .env du service ai-service.",
            "tool_calls": [],
            "status": "not_configured",
        }

    genai.configure(api_key=settings.gemini_api_key)
    tool_client = LaravelToolClient(settings.laravel_base_url, laravel_token)

    model = genai.GenerativeModel(
        model_name=settings.gemini_model,
        tools=[_build_tool()],
        system_instruction=SYSTEM_PROMPT,
    )
    chat = model.start_chat(history=conversation_history or [])

    tool_calls_log = []

    try:
        response = chat.send_message(question)
    except Exception as exc:
        return {"answer": f"Erreur de connexion au modèle Gemini : {exc}", "tool_calls": [], "status": "error"}

    for _ in range(MAX_TOOL_ROUNDS):
        part = response.candidates[0].content.parts[0]

        function_call = getattr(part, "function_call", None)
        if not function_call or not function_call.name:
            final_text = "".join(p.text for p in response.candidates[0].content.parts if hasattr(p, "text") and p.text)
            return {"answer": final_text, "tool_calls": tool_calls_log, "status": "ok"}

        tool_input = dict(function_call.args) if function_call.args else {}
        result = tool_client.execute(function_call.name, tool_input)
        tool_calls_log.append({"tool": function_call.name, "input": tool_input})

        result_struct = Struct()
        result_struct.update({"result": str(result)[:4000]})

        response = chat.send_message(
            genai.protos.Content(
                parts=[genai.protos.Part(
                    function_response=genai.protos.FunctionResponse(
                        name=function_call.name,
                        response=result_struct,
                    )
                )]
            )
        )

    return {
        "answer": "Je n'ai pas réussi à conclure après plusieurs appels d'outils — reformule ta question, ou pose-la de façon plus précise.",
        "tool_calls": tool_calls_log,
        "status": "max_rounds_reached",
    }
