<?php

namespace App\Services;

use App\Models\ReplenishmentRecommendation;

class ExplanationService
{
    /**
     * IA explicable (module 20) : transforme les facteurs bruts d'une
     * recommandation en un paragraphe en langage naturel, compréhensible
     * par un décideur qui n'a pas besoin de lire un tableau de chiffres.
     * Reste factuel — chaque phrase provient d'un facteur réellement stocké,
     * rien n'est inventé (cohérent avec l'interdiction section 38 : pas de
     * chiffre halluciné).
     */
    public function explainRecommendation(ReplenishmentRecommendation $recommendation): string
    {
        $recommendation->loadMissing('factors', 'product', 'supplier');
        $factors = $recommendation->factors;
        $product = $recommendation->product;

        if ($recommendation->decision_type === 'ne_pas_commander') {
            $stockFactor = $factors->firstWhere('label', 'Stock actuel');
            return "Le système ne recommande pas de commander « {$product->name} » actuellement. "
                .($stockFactor ? "Le stock disponible ({$stockFactor->weight} unités) reste suffisant compte tenu de la demande prévue. " : '')
                ."Priorité : {$recommendation->priority}.";
        }

        $sentences = ["Le système recommande de commander {$recommendation->recommended_quantity} unité(s) de « {$product->name} »."];

        $demandFactor = $factors->first(fn ($f) => str_contains($f->label, 'Demande prévue'));
        if ($demandFactor) {
            $sentences[] = "La demande prévue sur la période est d'environ {$demandFactor->weight} unité(s) ({$demandFactor->detail}).";
        }

        $stockFactor = $factors->firstWhere('label', 'Stock actuel');
        if ($stockFactor) {
            $sentences[] = $stockFactor->direction === 'defavorable'
                ? "Le stock actuel ({$stockFactor->weight} unités) est déjà sous le seuil minimum configuré."
                : "Le stock actuel est de {$stockFactor->weight} unité(s).";
        }

        $safetyFactor = $factors->firstWhere('label', 'Stock de sécurité');
        if ($safetyFactor && $safetyFactor->weight > 0) {
            $sentences[] = "Un stock de sécurité de {$safetyFactor->weight} unité(s) est intégré au calcul, pour absorber un éventuel pic de demande imprévu.";
        }

        $leadTimeFactor = $factors->firstWhere('label', 'Délai fournisseur');
        if ($leadTimeFactor) {
            $sentences[] = $leadTimeFactor->direction === 'defavorable'
                ? "Le délai fournisseur de {$leadTimeFactor->weight} jour(s) est relativement long — mieux vaut ne pas trop tarder à commander."
                : "Le délai fournisseur est de {$leadTimeFactor->weight} jour(s), ce qui laisse une marge de manœuvre raisonnable.";
        }

        $priorityLabel = ['critique' => 'critique', 'elevee' => 'élevée', 'moyenne' => 'moyenne', 'faible' => 'faible'][$recommendation->priority] ?? $recommendation->priority;
        $sentences[] = "Priorité de cette recommandation : {$priorityLabel}, avec un niveau de confiance de {$recommendation->confidence}%.";

        if ($recommendation->supplier) {
            $sentences[] = "Fournisseur suggéré : {$recommendation->supplier->name}.";
        }

        return implode(' ', $sentences);
    }
}
