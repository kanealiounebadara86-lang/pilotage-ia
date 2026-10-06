from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

from app.core.config import settings

# Le service IA se connecte à la même base MySQL que Laravel, mais uniquement
# en lecture pour l'extraction des données d'entraînement (module 16 du cahier
# des charges). Il n'écrit jamais dans les tables métier de Laravel ; ses
# propres résultats (modèles, prévisions) sont renvoyés à Laravel par API,
# qui décide lui-même de les persister dans ml_models / forecasts / forecast_results.
engine = create_engine(settings.database_url, pool_pre_ping=True, pool_recycle=280)
SessionLocal = sessionmaker(bind=engine, autoflush=False, autocommit=False)
