<x-app-layout title="Finance">

    <div x-data="financePage()" x-init="load()" x-cloak class="space-y-6">

        {{-- KPI principaux --}}
        <template x-if="!loading && summary">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Revenus</p>
                    <p class="font-display text-[20px] font-semibold mt-1 text-sage-600" x-text="formatXOF(summary.revenue_total)"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Dépenses</p>
                    <p class="font-display text-[20px] font-semibold mt-1 text-rust-500" x-text="formatXOF(summary.expense_total)"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Résultat net</p>
                    <p class="font-display text-[20px] font-semibold mt-1" :class="summary.net_result >= 0 ? 'text-sage-600' : 'text-rust-500'" x-text="formatXOF(summary.net_result)"></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Marge ventes</p>
                    <p class="font-display text-[20px] font-semibold mt-1" x-text="summary.sales_margin_percent + '%'"></p>
                </div>
            </div>
        </template>

        {{-- KPI trésorerie / créances / dettes --}}
        <template x-if="!loading && kpis">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-navy-950 rounded-2xl p-5 text-white">
                    <p class="text-[11.5px] font-medium text-white/50 uppercase tracking-wide">Position de trésorerie</p>
                    <p class="font-display text-[24px] font-semibold mt-1" x-text="formatXOF(kpis.cash_position)"></p>
                    <p class="text-[12px] text-white/40 mt-1">Banque + Caisse</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Créances clients</p>
                    <p class="font-display text-[24px] font-semibold mt-1 text-gold-600" x-text="formatXOF(kpis.receivables)"></p>
                    <p class="text-[12px] text-navy-950/40 mt-1">Ventes non encaissées</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Dettes fournisseurs</p>
                    <p class="font-display text-[24px] font-semibold mt-1 text-rust-500" x-text="formatXOF(kpis.payables)"></p>
                    <p class="text-[12px] text-navy-950/40 mt-1">Achats non réglés</p>
                </div>
            </div>
        </template>

        {{-- KPI résultat cumulé / marge nette globale --}}
        <template x-if="!loading && kpis">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Résultat cumulé (depuis le début)</p>
                    <p class="font-display text-[22px] font-semibold mt-1" :class="kpis.cumulative_result >= 0 ? 'text-sage-600' : 'text-rust-500'" x-text="formatXOF(kpis.cumulative_result)"></p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Marge nette globale</p>
                    <p class="font-display text-[22px] font-semibold mt-1" x-text="kpis.net_margin_percent !== null ? kpis.net_margin_percent + '%' : '—'"></p>
                </div>
            </div>
        </template>

        {{-- Graphique tendance CA --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="font-display font-semibold text-[15px] mb-4">Évolution du chiffre d'affaires (6 derniers mois)</h3>
            <div style="position: relative; height: 240px;">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>

        <div class="flex items-center justify-end">
            <button @click="expensePanel = true"
                    class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-navy-950/30 text-navy-950 text-[13px] font-medium px-3.5 py-2 rounded-lg transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Ajouter une dépense
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-display font-semibold text-[15px] mb-4">Rentabilité par produit</h3>
                <template x-if="profitability.length === 0">
                    <p class="text-[13px] text-navy-950/40 py-6 text-center">Aucune donnée sur cette période.</p>
                </template>
                <div class="space-y-3">
                    <template x-for="p in profitability" :key="p.product_id">
                        <div class="flex items-center justify-between text-[13.5px]">
                            <span class="truncate" x-text="p.product_name"></span>
                            <span class="font-medium text-sage-600 shrink-0" x-text="formatXOF(p.margin)"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-display font-semibold text-[15px] mb-4">Répartition des dépenses</h3>
                <template x-if="!kpis || kpis.expense_breakdown.length === 0">
                    <p class="text-[13px] text-navy-950/40 py-6 text-center">Aucune dépense sur cette période.</p>
                </template>
                <div class="space-y-2.5">
                    <template x-for="e in (kpis ? kpis.expense_breakdown : [])" :key="e.category">
                        <div class="flex items-center gap-3">
                            <span class="w-24 text-[12.5px] capitalize text-navy-950/60 truncate shrink-0" x-text="e.category"></span>
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-rust-500/70 rounded-full" :style="'width:' + expenseBarWidth(e.total) + '%'"></div>
                            </div>
                            <span class="text-[12px] text-navy-950/50 shrink-0" x-text="formatXOF(e.total)"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-display font-semibold text-[15px] mb-4">Dernières transactions</h3>
                <template x-if="transactions.length === 0">
                    <p class="text-[13px] text-navy-950/40 py-6 text-center">Aucune transaction pour le moment.</p>
                </template>
                <div class="space-y-3">
                    <template x-for="t in transactions" :key="t.id">
                        <div class="flex items-center justify-between text-[13px]">
                            <div class="min-w-0">
                                <p class="truncate" x-text="t.description"></p>
                                <p class="text-[11px] text-navy-950/35" x-text="new Date(t.transaction_date).toLocaleDateString('fr-FR')"></p>
                            </div>
                            <span class="font-medium shrink-0" :class="t.type === 'revenu' ? 'text-sage-600' : 'text-rust-500'"
                                  x-text="(t.type === 'revenu' ? '+ ' : '− ') + formatXOF(t.amount)"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Volet dépense --}}
        <div x-show="expensePanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="expensePanel = false"></div>
            <div x-show="expensePanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvelle dépense</h2>
                    <button @click="expensePanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="createExpense" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Libellé</label>
                        <input type="text" x-model="expenseForm.label" required placeholder="Loyer, salaires…" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Catégorie</label>
                        <input type="text" x-model="expenseForm.category" placeholder="loyer, transport, fournitures…" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Montant</label>
                        <input type="number" x-model="expenseForm.amount" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Date</label>
                        <input type="date" x-model="expenseForm.expense_date" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <template x-if="error">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>
                    </template>
                    <button type="submit" :disabled="saving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!saving">Enregistrer</span>
                        <span x-show="saving">Enregistrement…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        function financePage() {
            return {
                loading: true, summary: null, kpis: null, profitability: [], transactions: [], chart: null,
                expensePanel: false, saving: false, error: '',
                expenseForm: { label: '', category: '', amount: '', expense_date: new Date().toISOString().slice(0, 10) },
                async load() {
                    this.loading = true;
                    const [sRes, kRes, pRes, tRes] = await Promise.all([
                        apiFetch('/finance/summary'),
                        apiFetch('/finance/kpis'),
                        apiFetch('/finance/profitability/products'),
                        apiFetch('/finance/transactions?per_page=15'),
                    ]);
                    if (sRes && sRes.ok) this.summary = await sRes.json();
                    if (kRes && kRes.ok) this.kpis = await kRes.json();
                    if (pRes && pRes.ok) this.profitability = await pRes.json();
                    if (tRes && tRes.ok) { const data = await tRes.json(); this.transactions = data.data; }
                    this.loading = false;
                    this.$nextTick(() => this.renderChart());
                },
                renderChart() {
                    if (!this.kpis) return;
                    const ctx = document.getElementById('revenueTrendChart');
                    if (this.chart) this.chart.destroy();

                    const labels = this.kpis.revenue_trend.map(r => {
                        const [y, m] = r.month.split('-');
                        return new Date(y, m - 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
                    });

                    this.chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [
                                { label: 'CA', data: this.kpis.revenue_trend.map(r => r.revenue), backgroundColor: '#0B1220', borderRadius: 4 },
                                { label: 'Marge', data: this.kpis.revenue_trend.map(r => r.margin), backgroundColor: '#6366F1', borderRadius: 4 },
                                { label: 'Dépenses', data: this.kpis.revenue_trend.map(r => r.expenses), backgroundColor: '#DC4C4C', borderRadius: 4 },
                            ],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                            scales: { y: { beginAtZero: true, grid: { color: '#F1F2F5' } }, x: { grid: { display: false } } },
                        },
                    });
                },
                expenseBarWidth(total) {
                    if (!this.kpis || this.kpis.expense_breakdown.length === 0) return 0;
                    const max = Math.max(...this.kpis.expense_breakdown.map(e => e.total), 1);
                    return Math.max(6, Math.round((total / max) * 100));
                },
                async createExpense() {
                    this.saving = true; this.error = '';
                    const res = await apiFetch('/expenses', { method: 'POST', body: JSON.stringify(this.expenseForm) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur lors de la création.'; this.saving = false; return; }
                    this.saving = false; this.expensePanel = false;
                    this.expenseForm = { label: '', category: '', amount: '', expense_date: new Date().toISOString().slice(0, 10) };
                    this.load();
                }
            }
        }
    </script>
</x-app-layout>
