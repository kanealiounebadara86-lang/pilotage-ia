<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;

class SupplierMessageService
{
    /**
     * Génère un brouillon de message de commande à destination d'un
     * fournisseur — texte prêt à copier/envoyer. Le système ne dispose
     * d'aucun canal de communication réel (email/SMS) vers les fournisseurs :
     * c'est un choix assumé, pas une limitation technique — l'envoi reste
     * un geste humain volontaire, cohérent avec la règle de validation du
     * cahier des charges (section 22).
     */
    public function draftOrderMessage(Supplier $supplier, Product $product, int $quantity): array
    {
        $body = "Bonjour {$supplier->contact_name},\n\n"
            ."Nous souhaitons passer une commande pour le produit suivant :\n\n"
            ."- Produit : {$product->name} (réf. {$product->reference})\n"
            ."- Quantité souhaitée : {$quantity} {$product->unit}\n\n"
            ."Merci de nous confirmer la disponibilité et le délai de livraison.\n\n"
            ."Cordialement.";

        return [
            'supplier_name' => $supplier->name,
            'supplier_contact' => $supplier->contact_name,
            'supplier_phone' => $supplier->phone,
            'subject' => "Commande — {$product->name}",
            'body' => $body,
        ];
    }
}
