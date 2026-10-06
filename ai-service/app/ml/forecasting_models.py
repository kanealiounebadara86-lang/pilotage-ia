"""
Étape 4 du pipeline (section 16) : modèles Machine Learning candidats.
Random Forest et XGBoost sont retenus par défaut (bon compromis
performance/explicabilité — cf. section 51 sur le XAI). Pas de LSTM ici :
le volume de données d'une PME (quelques centaines de jours par produit)
ne le justifie pas (consigne explicite section 16 : ne pas complexifier
sans preuve que ça améliore les résultats).
"""

from dataclasses import dataclass

import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from xgboost import XGBRegressor

FEATURE_COLUMNS = ["day_of_week", "day_of_month", "month", "lag_1", "lag_7", "rolling_mean_7", "rolling_mean_14"]


@dataclass
class TrainedModel:
    name: str
    estimator: object
    feature_importances: dict


def train_random_forest(X: pd.DataFrame, y: pd.Series) -> TrainedModel:
    model = RandomForestRegressor(n_estimators=200, max_depth=8, random_state=42, n_jobs=-1)
    model.fit(X[FEATURE_COLUMNS], y)

    importances = dict(zip(FEATURE_COLUMNS, model.feature_importances_.round(4).tolist()))
    return TrainedModel(name="random_forest", estimator=model, feature_importances=importances)


def train_xgboost(X: pd.DataFrame, y: pd.Series) -> TrainedModel:
    model = XGBRegressor(
        n_estimators=200, max_depth=5, learning_rate=0.08,
        random_state=42, objective="reg:squarederror",
    )
    model.fit(X[FEATURE_COLUMNS], y)

    importances = dict(zip(FEATURE_COLUMNS, model.feature_importances_.round(4).tolist()))
    return TrainedModel(name="xgboost", estimator=model, feature_importances=importances)


def predict_recursive(trained: TrainedModel, history: pd.DataFrame, horizon: int) -> np.ndarray:
    """
    Prédiction récursive jour par jour : comme les features dépendent des
    valeurs précédentes (lag_1, lag_7, moyennes mobiles), chaque prédiction
    nourrit le calcul des features du jour suivant.
    """
    working = history.copy()
    predictions = []

    for _ in range(horizon):
        last_date = working["ds"].iloc[-1]
        next_date = last_date + pd.Timedelta(days=1)

        row = {
            "day_of_week": next_date.dayofweek,
            "day_of_month": next_date.day,
            "month": next_date.month,
            "lag_1": working["y"].iloc[-1],
            "lag_7": working["y"].iloc[-7] if len(working) >= 7 else working["y"].iloc[-1],
            "rolling_mean_7": working["y"].tail(7).mean(),
            "rolling_mean_14": working["y"].tail(14).mean(),
        }
        X_next = pd.DataFrame([row])[FEATURE_COLUMNS]
        pred = max(0.0, float(trained.estimator.predict(X_next)[0]))  # une vente ne peut pas être négative
        predictions.append(pred)

        working = pd.concat([working, pd.DataFrame([{"ds": next_date, "y": pred}])], ignore_index=True)

    return np.array(predictions)
