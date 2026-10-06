<x-app-layout title="Orchestrateur IA">

    <div x-data="aiHubPage()" x-init="loadProducts(); loadEmployees()" x-cloak class="space-y-6">

        {{-- Bandeau orchestrateur --}}
        <div class="bg-navy-950 rounded-2xl p-6 text-white">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="font-display font-semibold text-[18px] mb-1">Orchestrateur IA</h2>
                    <p class="text-[13px] text-white/50 max-w-md">
                        Déclenche automatiquement la prévision des ventes, le réapprovisionnement
                        et l'analyse RH sur le périmètre sélectionné ci-dessous, en un seul lancement.
                    </p>
                </div>
                <button @click="runOrchestrator" :disabled="orchestrating"
                        class="bg-gold-500 hover:bg-gold-400 disabled:opacity-50 text-navy-950 text-[14px] font-semibold px-5 py-3 rounded-xl transition-colors shrink-0">
                    <span x-show="!orchestrating">Lancer l'orchestrateur</span>
                    <span x-show="orchestrating">Analyse en cours…</span>
                </button>
            </div>
        </div>

        {{-- Résumé orchestrateur --}}
        <template x-if="orchestratorResult">
            <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11px] font-medium text-navy-950/45 uppercase tracking-wide">Produits analysés</p>
                    <p class="font-display text-[20px] font-semibold mt-1" x-text="orchestratorResult.summary.products_analyzed"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11px] font-medium text-navy-950/45 uppercase tracking-wide">À commander</p>
                    <p class="font-display text-[20px] font-semibold mt-1 text-gold-600" x-text="orchestratorResult.summary.products_to_order"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11px] font-medium text-navy-950/45 uppercase tracking-wide">Alertes critiques</p>
                    <p class="font-display text-[20px] font-semibold mt-1 text-rust-500" x-text="orchestratorResult.summary.critical_stock_alerts"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11px] font-medium text-navy-950/45 uppercase tracking-wide">Employés analysés</p>
                    <p class="font-display text-[20px] font-semibold mt-1" x-text="orchestratorResult.summary.employees_analyzed"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11px] font-medium text-navy-950/45 uppercase tracking-wide">RH à surveiller</p>
                    <p class="font-display text-[20px] font-semibold mt-1" :class="orchestratorResult.summary.employees_flagged > 0 ? 'text-gold-600' : 'text-sage-600'" x-text="orchestratorResult.summary.employees_flagged"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11px] font-medium text-navy-950/45 uppercase tracking-wide">Signaux finance</p>
                    <p class="font-display text-[20px] font-semibold mt-1" :class="orchestratorResult.summary.finance_signals > 0 ? 'text-rust-500' : 'text-sage-600'" x-text="orchestratorResult.summary.finance_signals"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11px] font-medium text-navy-950/45 uppercase tracking-wide">Idées marketing</p>
                    <p class="font-display text-[20px] font-semibold mt-1 text-gold-600" x-text="orchestratorResult.summary.marketing_suggestions"></p>
                </div>
            </div>
        </template>

        {{-- Sélection du périmètre --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-display font-semibold text-[15px]">Produits</h3>
                    <label class="flex items-center gap-2 text-[12.5px] text-navy-950/60">
                        <input type="checkbox" x-model="productScopeAll" class="rounded border-gray-300">
                        Tous les produits (<span x-text="products.length"></span>)
                    </label>
                </div>
                <div class="max-h-56 overflow-y-auto space-y-1.5 border-t border-gray-100 pt-3" x-show="!productScopeAll">
                    <template x-for="p in products" :key="p.id">
                        <label class="flex items-center gap-2 text-[13px] py-1">
                            <input type="checkbox" :value="p.id" x-model="selectedProductIds" class="rounded border-gray-300">
                            <span x-text="p.name"></span>
                        </label>
                    </template>
                </div>
                <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                    <button @click="runForecastBulk" :disabled="runningForecast" class="flex-1 bg-white border border-gray-300 hover:border-navy-950/30 text-navy-950 text-[12.5px] font-medium py-2 rounded-lg transition-colors">
                        <span x-show="!runningForecast">Lancer les prévisions</span>
                        <span x-show="runningForecast">…</span>
                    </button>
                    <button @click="runReplenishmentBulk" :disabled="runningReplenishment" class="flex-1 bg-white border border-gray-300 hover:border-navy-950/30 text-navy-950 text-[12.5px] font-medium py-2 rounded-lg transition-colors">
                        <span x-show="!runningReplenishment">Lancer le réappro</span>
                        <span x-show="runningReplenishment">…</span>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-display font-semibold text-[15px]">Employés</h3>
                    <label class="flex items-center gap-2 text-[12.5px] text-navy-950/60">
                        <input type="checkbox" x-model="employeeScopeAll" class="rounded border-gray-300">
                        Tous les employés (<span x-text="employees.length"></span>)
                    </label>
                </div>
                <div class="max-h-56 overflow-y-auto space-y-1.5 border-t border-gray-100 pt-3" x-show="!employeeScopeAll">
                    <template x-for="e in employees" :key="e.id">
                        <label class="flex items-center gap-2 text-[13px] py-1">
                            <input type="checkbox" :value="e.id" x-model="selectedEmployeeIds" class="rounded border-gray-300">
                            <span x-text="e.first_name + ' ' + e.last_name"></span>
                        </label>
                    </template>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <button @click="runHrBulk" :disabled="runningHr" class="w-full bg-white border border-gray-300 hover:border-navy-950/30 text-navy-950 text-[12.5px] font-medium py-2 rounded-lg transition-colors">
                        <span x-show="!runningHr">Lancer l'analyse RH</span>
                        <span x-show="runningHr">…</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Finance & Marketing : pas de sélection de périmètre, analyse toute l'entreprise --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button @click="runFinanceInsights" :disabled="runningFinance" class="bg-white border border-gray-200 hover:border-navy-950/30 rounded-xl px-4 py-3 text-left transition-colors">
                <p class="text-[13.5px] font-medium text-navy-950">Lancer l'IA Finance</p>
                <p class="text-[12px] text-navy-950/40">Risque de trésorerie, anomalies de dépenses</p>
            </button>
            <button @click="runMarketingInsights" :disabled="runningMarketing" class="bg-white border border-gray-200 hover:border-navy-950/30 rounded-xl px-4 py-3 text-left transition-colors">
                <p class="text-[13.5px] font-medium text-navy-950">Lancer l'IA Marketing</p>
                <p class="text-[12px] text-navy-950/40">Suggestions de campagnes basées sur les tendances de vente</p>
            </button>
        </div>

        {{-- Résultats Finance --}}
        <template x-if="financeResult">
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-display font-semibold text-[15px] mb-4">Signaux financiers</h3>
                <template x-if="financeResult.signals.length === 0">
                    <p class="text-[13px] text-sage-600">Rien à signaler — aucun risque détecté.</p>
                </template>
                <div class="space-y-2">
                    <template x-for="s in financeResult.signals" :key="s.label">
                        <div class="flex items-start gap-2.5 bg-rust-500/5 border border-rust-500/20 rounded-lg px-3.5 py-2.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-rust-500 mt-1.5 shrink-0"></span>
                            <p class="text-[13px] text-navy-950/80" x-text="s.label"></p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Résultats Marketing --}}
        <template x-if="marketingResult">
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-display font-semibold text-[15px] mb-4">Suggestions de campagnes</h3>
                <template x-if="marketingResult.suggestions.length === 0">
                    <p class="text-[13px] text-navy-950/40">Aucune tendance significative détectée sur les 30 derniers jours.</p>
                </template>
                <div class="space-y-3">
                    <template x-for="s in marketingResult.suggestions" :key="s.product_id + s.type">
                        <div class="border border-gray-100 rounded-xl p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[13.5px] font-medium" x-text="s.suggested_campaign_name"></p>
                                    <p class="text-[12px] text-navy-950/50 mt-1" x-text="s.rationale"></p>
                                </div>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium shrink-0" :class="s.type === 'relance' ? 'bg-rust-500/10 text-rust-600' : 'bg-sage-500/10 text-sage-600'" x-text="s.type === 'relance' ? 'Relance' : 'Capitaliser'"></span>
                            </div>
                            <div class="flex items-center gap-4 mt-3 text-[12px] text-navy-950/50">
                                <span x-text="'Budget suggéré : ' + formatXOF(s.suggested_budget)"></span>
                                <span x-text="s.suggested_duration_days + ' jours'"></span>
                            </div>
                            <button @click="acceptCampaignSuggestion(s)" class="mt-3 text-[12.5px] font-medium text-gold-600 hover:text-gold-700">
                                Créer cette campagne
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Résultats Réapprovisionnement --}}
        <template x-if="replenishmentResults.length > 0">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="font-display font-semibold text-[15px]">Recommandations de réapprovisionnement</h3>
                </div>
                <table class="w-full text-[13.5px]">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                            <th class="px-5 py-2.5 font-medium">Produit</th>
                            <th class="px-5 py-2.5 font-medium">Décision</th>
                            <th class="px-5 py-2.5 font-medium text-right">Quantité</th>
                            <th class="px-5 py-2.5 font-medium">Priorité</th>
                            <th class="px-5 py-2.5 font-medium text-right">Confiance</th>
                            <th class="px-5 py-2.5 font-medium text-right">Pourquoi ?</th>
                            <th class="px-5 py-2.5 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="r in replenishmentResults" :key="r.product_id">
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-5 py-3" x-text="r.product_name"></td>
                                <td class="px-5 py-3">
                                    <span x-show="r.status !== 'ok'" class="text-navy-950/35 text-[12px]">Pas assez de données</span>
                                    <span x-show="r.status === 'ok'" x-text="r.decision_type === 'commander' ? 'Commander' : 'Ne pas commander'"
                                          :class="r.decision_type === 'commander' ? 'text-gold-600 font-medium' : 'text-navy-950/50'"></span>
                                </td>
                                <td class="px-5 py-3 text-right" x-text="r.status === 'ok' ? r.recommended_quantity : '—'"></td>
                                <td class="px-5 py-3">
                                    <span x-show="r.status === 'ok'" class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium" :class="priorityStyle(r.priority)" x-text="r.priority"></span>
                                </td>
                                <td class="px-5 py-3 text-right" x-text="r.status === 'ok' ? r.confidence + '%' : '—'"></td>
                                <td class="px-5 py-3 text-right">
                                    <button x-show="r.status === 'ok'" @click="openFactors(r)" class="text-[12px] font-medium text-navy-950/40 hover:text-navy-950">Détail</button>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button x-show="r.status === 'ok' && r.decision_type === 'commander' && r.recommendation_id"
                                            @click="acceptRecommendation(r)" class="text-[12px] font-medium text-sage-600 hover:text-sage-700">
                                        Accepter → commander
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        {{-- Résultats RH --}}
        <template x-if="hrResults.length > 0">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="font-display font-semibold text-[15px]">Analyse RH — assiduité et charge de travail</h3>
                </div>
                <div class="divide-y divide-gray-50">
                    <template x-for="r in hrResults" :key="r.employee_id">
                        <div class="px-5 py-3.5 flex items-center justify-between">
                            <div>
                                <p class="text-[13.5px] font-medium" x-text="r.employee_name"></p>
                                <template x-if="r.signals.length === 0">
                                    <p class="text-[12px] text-sage-600">Rien à signaler sur les 30 derniers jours.</p>
                                </template>
                                <template x-for="s in r.signals" :key="s.label">
                                    <p class="text-[12px] text-gold-600" x-text="s.label"></p>
                                </template>
                            </div>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium shrink-0"
                                  :class="r.status === 'a_surveiller' ? 'bg-gold-500/15 text-gold-600' : 'bg-sage-500/10 text-sage-600'"
                                  x-text="r.status === 'a_surveiller' ? 'À surveiller' : 'OK'"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Volet détail des facteurs --}}
        <div x-show="factorsPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="factorsPanel = false"></div>
            <div x-show="factorsPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]" x-text="factorsProduct ? factorsProduct.product_name : ''"></h2>
                    <button @click="factorsPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-if="factorsExplanation">
                        <div class="bg-paper rounded-xl p-4">
                            <p class="text-[13px] text-navy-950/80 leading-relaxed" x-text="factorsExplanation"></p>
                        </div>
                    </template>
                    <template x-if="factorsProduct && factorsProduct.recommendation_id">
                        <button @click="showSupplierMessage(factorsProduct)" class="text-[12.5px] font-medium text-gold-600 hover:text-gold-700">
                            Voir le brouillon de message fournisseur
                        </button>
                    </template>
                    <p class="text-[11px] font-medium text-navy-950/40 uppercase tracking-wide pt-2">Facteurs détaillés</p>
                    <template x-for="f in (factorsProduct ? factorsProduct.factors : [])" :key="f.label">
                        <div class="border border-gray-100 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <p class="text-[13px] font-medium" x-text="f.label"></p>
                                <span class="w-2 h-2 rounded-full shrink-0" :class="f.direction === 'favorable' ? 'bg-sage-500' : 'bg-rust-500'"></span>
                            </div>
                            <p class="text-[12px] text-navy-950/50 mt-1" x-text="f.detail"></p>
                            <p class="text-[12px] text-navy-950/40 mt-0.5" x-text="'Valeur : ' + f.weight"></p>
                        </div>
                    </template>
                </div>

                {{-- Brouillon message fournisseur --}}
                <template x-if="supplierMessage">
                    <div class="mt-5 bg-gold-500/5 border border-gold-500/20 rounded-xl p-4">
                        <p class="text-[11px] font-medium text-gold-700 uppercase tracking-wide mb-2">Brouillon — à copier et envoyer toi-même</p>
                        <p class="text-[12.5px] font-medium mb-1" x-text="supplierMessage.subject"></p>
                        <p class="text-[12.5px] text-navy-950/70 whitespace-pre-line" x-text="supplierMessage.body"></p>
                        <p class="text-[11.5px] text-navy-950/40 mt-2" x-text="supplierMessage.supplier_phone ? 'Tél : ' + supplierMessage.supplier_phone : ''"></p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function aiHubPage() {
            return {
                products: [], employees: [],
                productScopeAll: true, employeeScopeAll: true,
                selectedProductIds: [], selectedEmployeeIds: [],
                runningForecast: false, runningReplenishment: false, runningHr: false, runningFinance: false, runningMarketing: false, orchestrating: false,
                replenishmentResults: [], hrResults: [], financeResult: null, marketingResult: null, orchestratorResult: null,
                factorsPanel: false, factorsProduct: null, factorsExplanation: '', supplierMessage: null,

                async loadProducts() {
                    const res = await apiFetch('/products?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.products = data.data; }
                },
                async loadEmployees() {
                    const res = await apiFetch('/employees?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.employees = data.data; }
                },

                productPayload() {
                    return this.productScopeAll ? { scope: 'all' } : { product_ids: this.selectedProductIds };
                },
                employeePayload() {
                    return this.employeeScopeAll ? { scope: 'all' } : { employee_ids: this.selectedEmployeeIds };
                },

                async runForecastBulk() {
                    this.runningForecast = true;
                    const res = await apiFetch('/ai/forecast/bulk', { method: 'POST', body: JSON.stringify(this.productPayload()) });
                    if (res && res.ok) { await res.json(); }
                    this.runningForecast = false;
                },
                async runReplenishmentBulk() {
                    this.runningReplenishment = true;
                    const res = await apiFetch('/ai/replenishment/run', { method: 'POST', body: JSON.stringify(this.productPayload()) });
                    if (res && res.ok) { const data = await res.json(); this.replenishmentResults = data.results; }
                    this.runningReplenishment = false;
                },
                async runHrBulk() {
                    this.runningHr = true;
                    const res = await apiFetch('/ai/hr/insights', { method: 'POST', body: JSON.stringify(this.employeePayload()) });
                    if (res && res.ok) { const data = await res.json(); this.hrResults = data.results; }
                    this.runningHr = false;
                },
                async runFinanceInsights() {
                    this.runningFinance = true;
                    const res = await apiFetch('/ai/finance/insights', { method: 'POST' });
                    if (res && res.ok) { this.financeResult = await res.json(); }
                    this.runningFinance = false;
                },
                async runMarketingInsights() {
                    this.runningMarketing = true;
                    const res = await apiFetch('/ai/marketing/insights', { method: 'POST' });
                    if (res && res.ok) { this.marketingResult = await res.json(); }
                    this.runningMarketing = false;
                },
                async acceptRecommendation(r) {
                    if (!confirm(`Confirmer : créer une VRAIE commande fournisseur pour ${r.recommended_quantity} unité(s) de "${r.product_name}" ?\n\nCette action crée un brouillon de commande réel (à valider ensuite dans Achats).`)) return;
                    const res = await apiFetch('/ai/replenishment/' + r.recommendation_id + '/decide', { method: 'POST', body: JSON.stringify({ status: 'acceptee' }) });
                    if (res && res.ok) {
                        const data = await res.json();
                        if (data.purchase_order) {
                            alert(`Commande créée : ${data.purchase_order.reference}. Va dans Achats pour la finaliser.`);
                        } else {
                            alert('Recommandation acceptée, mais aucun fournisseur identifié — crée la commande manuellement dans Achats.');
                        }
                    }
                },
                async acceptCampaignSuggestion(s) {
                    if (!confirm(`Créer la campagne "${s.suggested_campaign_name}" avec un budget de ${formatXOF(s.suggested_budget)} ?`)) return;
                    const today = new Date().toISOString().slice(0, 10);
                    const end = new Date(Date.now() + s.suggested_duration_days * 86400000).toISOString().slice(0, 10);
                    const res = await apiFetch('/campaigns', {
                        method: 'POST',
                        body: JSON.stringify({
                            name: s.suggested_campaign_name, description: s.rationale,
                            start_date: today, end_date: end, budget: s.suggested_budget,
                            target_product_id: s.product_id,
                        }),
                    });
                    if (res && res.ok) alert('Campagne créée — retrouve-la dans la page Marketing.');
                },
                async runOrchestrator() {
                    this.orchestrating = true;
                    const payload = {
                        include_products: true, include_employees: true,
                        product_scope: this.productScopeAll ? 'all' : 'selected',
                        product_ids: this.selectedProductIds,
                        employee_scope: this.employeeScopeAll ? 'all' : 'selected',
                        employee_ids: this.selectedEmployeeIds,
                    };
                    const res = await apiFetch('/ai/orchestrate', { method: 'POST', body: JSON.stringify(payload) });
                    if (res && res.ok) {
                        const data = await res.json();
                        this.orchestratorResult = data;
                        this.replenishmentResults = data.products.replenishment;
                        this.hrResults = data.employees;
                        this.financeResult = data.finance;
                        this.marketingResult = data.marketing;
                    }
                    this.orchestrating = false;
                },

                async openFactors(r) {
                    this.factorsProduct = r; this.factorsPanel = true;
                    this.factorsExplanation = ''; this.supplierMessage = null;
                    if (r.recommendation_id) {
                        const res = await apiFetch('/ai/replenishment/' + r.recommendation_id + '/explain');
                        if (res && res.ok) { const data = await res.json(); this.factorsExplanation = data.explanation; }
                    }
                },
                async showSupplierMessage(r) {
                    const res = await apiFetch('/ai/replenishment/' + r.recommendation_id + '/draft-message');
                    if (res && res.ok) { this.supplierMessage = await res.json(); }
                    else { alert('Aucun fournisseur identifié pour cette recommandation.'); }
                },
                priorityStyle(p) {
                    if (p === 'critique') return 'bg-rust-500/10 text-rust-600';
                    if (p === 'elevee') return 'bg-gold-500/15 text-gold-600';
                    if (p === 'moyenne') return 'bg-navy-800/10 text-navy-800';
                    return 'bg-gray-100 text-navy-950/50';
                }
            }
        }
    </script>
</x-app-layout>
