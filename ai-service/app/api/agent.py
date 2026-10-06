from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.security import verify_api_key
from app.llm.orchestrator import run_agent_query

router = APIRouter(prefix="/agent", tags=["agent"])


class AgentQueryRequest(BaseModel):
    question: str
    laravel_token: str  # le token Sanctum de l'utilisateur qui pose la question


@router.post("/query", dependencies=[Depends(verify_api_key)])
def agent_query(payload: AgentQueryRequest):
    """
    Point d'entrée de l'assistant conversationnel (module 21, phase 8).
    Le token utilisateur est transmis pour que chaque outil appelé respecte
    exactement les permissions de son rôle — le LLM ne peut jamais voir plus
    de données que l'utilisateur lui-même n'y aurait accès dans l'application.
    """
    result = run_agent_query(payload.question, payload.laravel_token)
    return {"answer": result["answer"], "tool_calls": result["tool_calls"], "status": result["status"]}
