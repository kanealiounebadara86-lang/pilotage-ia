from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api import agent, forecast, health, replenishment, voice

app = FastAPI(
    title="SI Intelligent — Service IA",
    description="Service Python/FastAPI de prévision des ventes, optimisation, XAI et orchestration LLM.",
    version="0.1.0",
)

# CORS ouvert en développement uniquement ; en production, restreindre à
# l'origine exacte de l'application Laravel.
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(health.router)
app.include_router(forecast.router, prefix="/api/ai")
app.include_router(replenishment.router, prefix="/api/ai")
app.include_router(agent.router, prefix="/api/ai")
app.include_router(voice.router, prefix="/api/ai")
