from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """
    Configuration centralisée du service IA, chargée depuis les variables
    d'environnement (.env). Ne jamais coder une valeur sensible en dur ici.
    """
    db_host: str = "127.0.0.1"
    db_port: int = 3306
    db_database: str = "si_intelligent"
    db_username: str = "root"
    db_password: str = ""

    ai_service_api_key: str = "change-me"
    models_store_path: str = "./models_store"

    # Assistant LLM (Phase 8) — Google Gemini
    gemini_api_key: str = ""
    gemini_model: str = "gemini-3.6-flash"
    laravel_base_url: str = "http://localhost:8000"

    class Config:
        env_file = ".env"

    @property
    def database_url(self) -> str:
        return (
            f"mysql+pymysql://{self.db_username}:{self.db_password}"
            f"@{self.db_host}:{self.db_port}/{self.db_database}"
        )


settings = Settings()
