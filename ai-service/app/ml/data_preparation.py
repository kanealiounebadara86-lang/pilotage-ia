"""
Étape 1 et 2 du pipeline IA (cahier des charges, section 16) :
- Collecte : extraction de l'historique des ventes depuis MySQL.
- Préparation : nettoyage, agrégation journalière, valeurs manquantes,
  détection des valeurs aberrantes.
"""

from datetime import date, timedelta

import numpy as np
import pandas as pd
from sqlalchemy import text

from app.core.db import engine


def fetch_sales_history(product_id: int, min_days: int = 1) -> pd.DataFrame:
    """
    Récupère l'historique de ventes confirmées d'un produit, agrégé par jour.
    Retourne un DataFrame avec deux colonnes : 'ds' (date) et 'y' (quantité vendue).
    """
    query = text(
        """
        SELECT sale_date AS ds, SUM(sale_items.quantity) AS y
        FROM sale_items
        JOIN sales ON sales.id = sale_items.sale_id
        WHERE sale_items.product_id = :product_id
          AND sales.status = 'confirmed'
        GROUP BY sale_date
        ORDER BY sale_date
        """
    )

    with engine.connect() as conn:
        df = pd.read_sql(query, conn, params={"product_id": product_id})

    if df.empty:
        return df

    df["ds"] = pd.to_datetime(df["ds"])
    return df


def clean_and_fill(df: pd.DataFrame) -> pd.DataFrame:
    """
    Complète les jours sans vente par 0 (un jour sans vente est une donnée
    réelle, pas une valeur manquante à imputer autrement), et écrête les
    valeurs aberrantes (méthode IQR) pour éviter qu'une vente exceptionnelle
    ne fausse l'apprentissage du modèle.
    """
    if df.empty:
        return df

    full_range = pd.date_range(start=df["ds"].min(), end=df["ds"].max(), freq="D")
    df = df.set_index("ds").reindex(full_range, fill_value=0).rename_axis("ds").reset_index()

    q1, q3 = df["y"].quantile(0.25), df["y"].quantile(0.75)
    iqr = q3 - q1
    upper_bound = q3 + 3 * iqr  # seuil large : on écrête seulement les extrêmes

    if iqr > 0:
        df["y"] = df["y"].clip(upper=upper_bound)

    return df


def build_features(df: pd.DataFrame) -> pd.DataFrame:
    """
    Étape 2 (suite) : création de variables pour les modèles ML — moyennes
    mobiles, tendance, saisonnalité hebdomadaire. Les features à base de lag
    créent des NaN sur les premières lignes, qu'on retire (le modèle ne peut
    de toute façon pas apprendre sur des données incomplètes).
    """
    df = df.copy()
    df["day_of_week"] = df["ds"].dt.dayofweek
    df["day_of_month"] = df["ds"].dt.day
    df["month"] = df["ds"].dt.month
    df["lag_1"] = df["y"].shift(1)
    df["lag_7"] = df["y"].shift(7)
    df["rolling_mean_7"] = df["y"].shift(1).rolling(window=7).mean()
    df["rolling_mean_14"] = df["y"].shift(1).rolling(window=14).mean()

    return df.dropna().reset_index(drop=True)


def prepare_dataset(product_id: int) -> tuple[pd.DataFrame, pd.DataFrame]:
    """
    Point d'entrée du pipeline de préparation. Retourne :
    - le DataFrame propre agrégé par jour (utilisé pour la baseline)
    - le DataFrame avec features (utilisé pour les modèles ML)
    """
    raw = fetch_sales_history(product_id)
    clean = clean_and_fill(raw)
    featured = build_features(clean) if not clean.empty else clean

    return clean, featured
