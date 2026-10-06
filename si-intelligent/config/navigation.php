<?php

/*
 * Navigation unique de l'application : le menu latéral, les pages "espace"
 * (hubs), les onglets de section et la recherche rapide (Ctrl+K) lisent tous
 * ce fichier. Pour ajouter une page, il suffit de l'ajouter ici.
 */
return [
    'icons' => [
        'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'trend' => 'M4 16l5-6 4 4 7-9M20 5v5h-5',
        'box' => 'M3 8l9-5 9 5-9 5-9-5zM3 8v9l9 5m0-14v14m9-14v9l-9 5',
        'bell' => 'M6 9a6 6 0 1112 0c0 5 2 6 2 6H4s2-1 2-6zM10 20a2 2 0 004 0',
        'cart' => 'M3 4h2l2.4 12.2a2 2 0 002 1.8h7.6a2 2 0 002-1.7L21 8H6',
        'truck' => 'M3 7h11v8H3zM14 11h4l3 3v1h-7zM7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17.5 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
        'users' => 'M8 11a3 3 0 100-6 3 3 0 000 6zM3 20c0-3 2.5-5 5-5s5 2 5 5M16 11a3 3 0 100-6M14 8.3c.6-.2 1.3-.3 2-.3 2.5 0 5 2 5 5',
        'coin' => 'M12 3v18M6 8h9a3 3 0 010 6H9a3 3 0 000 6h9',
        'file' => 'M6 3h9l5 5v13H6zM15 3v5h5M9 13h6M9 16.5h6',
        'undo' => 'M4 10h10a5 5 0 010 10h-1M4 10l4-4M4 10l4 4',
        'warehouse' => 'M3 10l9-6 9 6v10H3zM9 20v-6h6v6',
        'book' => 'M5 4h11a2 2 0 012 2v14H7a2 2 0 00-2 2V4zM5 4a2 2 0 00-2 2v14a2 2 0 002-2',
        'badge' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 3.5-6 8-6s8 2 8 6',
        'clock' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
        'spark' => 'M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M18 6l-2.5 2.5M8.5 15.5L6 18',
        'megaphone' => 'M3 11v2a2 2 0 002 2h1l3 5V4l-3 5H5a2 2 0 00-2 2zM14 8a4 4 0 010 8M17 5a8 8 0 010 14',
        'orchestrator' => 'M12 3v4M12 17v4M4.2 8l3.5 2M16.3 14l3.5 2M4.2 16l3.5-2M16.3 10l3.5-2M12 12m-3 0a3 3 0 106 0 3 3 0 10-6 0',
        'chat' => 'M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z',
        'search' => 'M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.3-4.3',
        'chevron' => 'M9 6l6 6-6 6',
        'logout' => 'M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
        'panel' => 'M4 5h16v14H4zM9 5v14',
    ],

    'sections' => [
        'commercial' => [
            'label' => 'Commercial',
            'icon' => 'trend',
            'desc' => 'Ventes, clients, devis, retours et campagnes marketing.',
            'items' => [
                ['label' => 'Ventes', 'route' => 'sales.index', 'icon' => 'trend', 'perm' => 'sales.view', 'desc' => 'Encaisser, suivre l\'historique et les marges.'],
                ['label' => 'Clients', 'route' => 'customers.index', 'icon' => 'users', 'perm' => 'sales.view', 'desc' => 'Fichier clients et programme de fidélité.'],
                ['label' => 'Devis', 'route' => 'quotes.index', 'icon' => 'file', 'perm' => 'sales.view', 'desc' => 'Créer un devis et le convertir en vente.'],
                ['label' => 'Retours & SAV', 'route' => 'returns.index', 'icon' => 'undo', 'perm' => 'sales.view', 'desc' => 'Retours produits et service après-vente.'],
                ['label' => 'Marketing', 'route' => 'marketing.index', 'icon' => 'megaphone', 'perm' => 'sales.view', 'desc' => 'Campagnes et retour sur investissement.'],
            ],
        ],
        'stock' => [
            'label' => 'Stock & Achats',
            'icon' => 'box',
            'desc' => 'Catalogue, niveaux de stock, commandes et fournisseurs.',
            'items' => [
                ['label' => 'Produits', 'route' => 'products.index', 'icon' => 'box', 'perm' => 'stock.view', 'desc' => 'Catalogue, prix et seuils de stock.'],
                ['label' => 'Stocks & Alertes', 'route' => 'alerts.index', 'icon' => 'bell', 'perm' => 'stock.view', 'desc' => 'Ruptures, surstocks et alertes actives.'],
                ['label' => 'Achats', 'route' => 'purchases.index', 'icon' => 'cart', 'perm' => 'purchases.view', 'desc' => 'Commandes fournisseurs et réceptions.'],
                ['label' => 'Fournisseurs', 'route' => 'suppliers.index', 'icon' => 'truck', 'perm' => 'purchases.view', 'desc' => 'Fournisseurs et score de performance.'],
                ['label' => 'Entrepôts', 'route' => 'warehouses.index', 'icon' => 'warehouse', 'perm' => 'stock.view', 'desc' => 'Sites de stockage et transferts.'],
            ],
        ],
        'finance' => [
            'label' => 'Finance & Compta',
            'icon' => 'coin',
            'desc' => 'Trésorerie, indicateurs financiers et comptabilité.',
            'items' => [
                ['label' => 'Finance', 'route' => 'finance.index', 'icon' => 'coin', 'perm' => 'finance.view', 'desc' => 'Trésorerie, créances, dettes et résultat.'],
                ['label' => 'Comptabilité', 'route' => 'accounting.index', 'icon' => 'book', 'perm' => 'accounting.view', 'desc' => 'Plan comptable, écritures et grand livre.'],
            ],
        ],
        'rh' => [
            'label' => 'Ressources humaines',
            'icon' => 'badge',
            'desc' => 'Employés, pointage, congés, avances et paie.',
            'items' => [
                ['label' => 'Employés & paie', 'route' => 'hr.index', 'icon' => 'badge', 'perm' => 'hr.view', 'desc' => 'Fiches, congés, avances et bulletins de paie.'],
                ['label' => 'Pointage', 'route' => 'attendance.index', 'icon' => 'clock', 'perm' => 'hr.view', 'desc' => 'Pointer les présences, historique et heures sup.'],
            ],
        ],
        'ia' => [
            'label' => 'Intelligence IA',
            'icon' => 'spark',
            'desc' => 'Prévisions, recommandations et assistant conversationnel.',
            'items' => [
                ['label' => 'Prévisions IA', 'route' => 'forecast.index', 'icon' => 'spark', 'perm' => 'ai.view_recommendations', 'desc' => 'Prévision des ventes par produit.'],
                ['label' => 'Orchestrateur IA', 'route' => 'ai-hub.index', 'icon' => 'orchestrator', 'perm' => 'ai.view_recommendations', 'desc' => 'Lancer toutes les IA et valider leurs propositions.'],
                ['label' => 'Assistant IA', 'route' => 'assistant.index', 'icon' => 'chat', 'perm' => 'ai.use_assistant', 'desc' => 'Poser vos questions en langage naturel.'],
            ],
        ],
    ],
];
