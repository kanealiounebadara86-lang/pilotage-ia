"""
Étape 5 du pipeline (section 16) : mesures d'évaluation et comparaison
des modèles. MAE, RMSE et MAPE (ou WAPE si la série contient des zéros,
ce qui est fréquent en petites quantités vendues par jour).
"""

import numpy as np


def compute_metrics(y_true: np.ndarray, y_pred: np.ndarray) -> dict:
    y_true = np.asarray(y_true, dtype=float)
    y_pred = np.asarray(y_pred, dtype=float)

    mae = float(np.mean(np.abs(y_true - y_pred)))
    rmse = float(np.sqrt(np.mean((y_true - y_pred) ** 2)))

    # MAPE explose ou devient infini si y_true contient des zéros (fréquent
    # avec de petites quantités vendues par jour) — on utilise donc WAPE
    # (Weighted Absolute Percentage Error), plus stable dans ce cas.
    total_true = np.sum(np.abs(y_true))
    wape = float(np.sum(np.abs(y_true - y_pred)) / total_true) if total_true > 0 else None

    return {
        "mae": round(mae, 3),
        "rmse": round(rmse, 3),
        "wape": round(wape, 4) if wape is not None else None,
    }


def select_best_model(evaluations: dict) -> str:
    """
    Choisit le modèle avec le MAE le plus bas parmi tous les candidats évalués
    (baselines incluses) — critère simple et explicable, cohérent avec la
    consigne du cahier des charges de ne pas complexifier sans justification.
    """
    return min(evaluations, key=lambda name: evaluations[name]["mae"])
