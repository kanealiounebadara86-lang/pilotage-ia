"""
Orchestrateur du pipeline complet (section 30 du cahier des charges) :
MySQL -> Extraction -> Nettoyage -> Feature engineering -> Entraînement ->
Évaluation -> Sélection du meilleur modèle -> Sauvegarde -> Prédiction.
"""

import uuid
from datetime import datetime

import joblib
import numpy as np
import pandas as pd

from app.core.config import settings
from app.ml.baseline_models import evaluate_baseline_on_holdout
from app.ml.data_preparation import prepare_dataset
from app.ml.evaluation import compute_metrics, select_best_model
from app.ml.forecasting_models import (
    FEATURE_COLUMNS,
    predict_recursive,
    train_random_forest,
    train_xgboost,
)

MIN_DAYS_FOR_ML = 30  # sous ce seuil, pas assez de données pour un modèle ML fiable
MIN_DAYS_FOR_BASELINE_EVAL = 5  # sous ce seuil, pas assez de jours pour découper un holdout d'évaluation


def run_forecast_pipeline(product_id: int, horizon_days: int) -> dict:
    clean_df, featured_df = prepare_dataset(product_id)

    if clean_df.empty:
        return {
            "status": "no_data",
            "message": "Aucun historique de vente confirmée pour ce produit.",
        }

    total_days = len(clean_df)

    # Historique trop court pour évaluer un modèle sérieusement (il faudrait
    # découper une portion de test, ce qui n'a pas de sens sur 1-4 jours) :
    # on renvoie une prévision naïve honnête plutôt que de forcer un calcul
    # qui n'aurait aucune valeur statistique.
    if total_days < MIN_DAYS_FOR_BASELINE_EVAL:
        from app.ml.baseline_models import naive_forecast

        predictions = naive_forecast(clean_df["y"], horizon_days)
        last_date = clean_df["ds"].max()
        forecast_dates = [last_date + pd.Timedelta(days=i + 1) for i in range(horizon_days)]

        return {
            "status": "ok",
            "product_id": product_id,
            "history_days_used": total_days,
            "best_model": "naive",
            "evaluations": {"naive": {"mae": None, "rmse": None, "wape": None}},
            "feature_importances": {},
            "model_version": None,
            "warning": (
                f"Historique trop court ({total_days} jour(s)) pour évaluer la fiabilité "
                "du modèle — prévision basée sur la dernière valeur connue, à interpréter "
                "avec prudence. Au moins 5 jours de ventes distincts sont recommandés."
            ),
            "predictions": [
                {
                    "date": d.strftime("%Y-%m-%d"),
                    "quantity": round(float(p), 2),
                    "lower_bound": round(float(p), 2),
                    "upper_bound": round(float(p), 2),
                }
                for d, p in zip(forecast_dates, predictions)
            ],
        }

    # ---- Baselines (toujours calculées, servent de référence minimale) ----
    holdout_size = min(14, max(3, total_days // 5))
    holdout_size = min(holdout_size, total_days - 1)  # garantit au moins 1 jour d'entraînement, quel que soit le cas
    baseline_evals = evaluate_baseline_on_holdout(clean_df["y"], holdout_size)

    evaluations = dict(baseline_evals)
    trained_models = {}

    # ---- Modèles ML (seulement si assez de données avec features) ----
    if len(featured_df) >= MIN_DAYS_FOR_ML:
        X = featured_df[FEATURE_COLUMNS]
        y = featured_df["y"]

        ml_holdout = min(14, max(3, len(featured_df) // 5))
        X_train, X_test = X.iloc[:-ml_holdout], X.iloc[-ml_holdout:]
        y_train, y_test = y.iloc[:-ml_holdout], y.iloc[-ml_holdout:]

        for train_fn in (train_random_forest, train_xgboost):
            trained = train_fn(pd.concat([X_train, y_train.rename("y")], axis=1), y_train)
            preds = trained.estimator.predict(X_test)
            preds = np.clip(preds, 0, None)

            evaluations[trained.name] = compute_metrics(y_test.values, preds)
            trained_models[trained.name] = trained

    best_model_name = select_best_model(evaluations)

    # ---- Prévision finale avec le meilleur modèle ----
    if best_model_name in trained_models:
        # Ré-entraîne sur TOUTES les données disponibles (pas seulement le train
        # split) avant la prévision finale, pour exploiter l'historique complet.
        train_fn = train_random_forest if best_model_name == "random_forest" else train_xgboost
        final_model = train_fn(featured_df, featured_df["y"])
        predictions = predict_recursive(final_model, clean_df, horizon_days)
        feature_importances = final_model.feature_importances
        model_object = final_model.estimator
    else:
        # Le meilleur reste une baseline : pas de modèle ML à sauvegarder,
        # la prévision utilise directement la baseline gagnante.
        from app.ml.baseline_models import moving_average_forecast, naive_forecast

        if best_model_name == "naive":
            predictions = naive_forecast(clean_df["y"], horizon_days)
        else:
            predictions = moving_average_forecast(clean_df["y"], horizon_days)
        feature_importances = {}
        model_object = None

    # Intervalle de confiance simple basé sur l'erreur historique du modèle retenu
    error_margin = evaluations[best_model_name]["mae"]
    last_date = clean_df["ds"].max()
    forecast_dates = [last_date + pd.Timedelta(days=i + 1) for i in range(horizon_days)]

    model_version = None
    if model_object is not None:
        model_version = _persist_model(model_object, product_id, best_model_name)

    return {
        "status": "ok",
        "product_id": product_id,
        "history_days_used": total_days,
        "best_model": best_model_name,
        "evaluations": evaluations,
        "feature_importances": feature_importances,
        "model_version": model_version,
        "predictions": [
            {
                "date": d.strftime("%Y-%m-%d"),
                "quantity": round(float(p), 2),
                "lower_bound": round(max(0.0, float(p) - error_margin), 2),
                "upper_bound": round(float(p) + error_margin, 2),
            }
            for d, p in zip(forecast_dates, predictions)
        ],
    }


def _persist_model(model_object, product_id: int, model_type: str) -> str:
    """
    Sauvegarde le modèle entraîné sur disque (module 30 : traçabilité des
    modèles). Le nom de fichier fait office de version ; les métadonnées
    (métriques, date d'entraînement) sont renvoyées à Laravel qui les stocke
    dans la table ml_models — le service Python reste stateless côté métier.
    """
    version = f"{model_type}_p{product_id}_{datetime.utcnow().strftime('%Y%m%d%H%M%S')}_{uuid.uuid4().hex[:6]}"
    path = f"{settings.models_store_path}/{version}.joblib"
    joblib.dump(model_object, path)

    return version
