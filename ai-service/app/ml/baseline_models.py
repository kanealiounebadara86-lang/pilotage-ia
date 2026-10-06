"""
Étape 3 du pipeline (section 16) : baseline obligatoire — moyenne mobile et
modèle naïf. Sert de référence : un modèle ML n'est retenu que s'il fait
significativement mieux que ces baselines simples.
"""

import numpy as np
import pandas as pd


def naive_forecast(y: pd.Series, horizon: int) -> np.ndarray:
    """Modèle naïf : prédit que demain = aujourd'hui (dernière valeur observée)."""
    last_value = y.iloc[-1]
    return np.full(horizon, last_value)


def moving_average_forecast(y: pd.Series, horizon: int, window: int = 7) -> np.ndarray:
    """Moyenne mobile des N derniers jours, répétée sur tout l'horizon."""
    avg = y.tail(window).mean()
    return np.full(horizon, avg)


def evaluate_baseline_on_holdout(y: pd.Series, holdout_size: int) -> dict:
    """
    Évalue les deux baselines sur les derniers `holdout_size` jours connus
    (jamais vus par le "modèle" pendant l'estimation), pour comparaison
    honnête avec les modèles ML (même protocole d'évaluation).
    """
    train, test = y.iloc[:-holdout_size], y.iloc[-holdout_size:]

    naive_pred = naive_forecast(train, holdout_size)
    ma_pred = moving_average_forecast(train, holdout_size)

    from app.ml.evaluation import compute_metrics

    return {
        "naive": compute_metrics(test.values, naive_pred),
        "moving_average": compute_metrics(test.values, ma_pred),
    }
