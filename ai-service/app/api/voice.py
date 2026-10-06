from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.security import verify_api_key
from app.llm.voice import interpret_voice

router = APIRouter(prefix="/voice", tags=["voice"])


class VoiceIntentRequest(BaseModel):
    transcript: str


@router.post("/intent", dependencies=[Depends(verify_api_key)])
def voice_intent(payload: VoiceIntentRequest):
    """Phrase dictée -> intention structurée. N'exécute RIEN (c'est Laravel qui décide)."""
    return interpret_voice(payload.transcript[:500])
