<x-app-layout title="Prévisions IA">

    <div x-data="forecastPage()" x-init="loadProducts()" x-cloak class="space-y-6">

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div class="sm:col-span-2">
                    <label class="block text-[12px] font-medium text-navy-950/60 mb-1.5">Produit</label>
                    <select x-model.number="productId" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        <option value="">Choisir un produit…</option>
                        <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-medium text-navy-950/60 mb-1.5">Horizon (jours)</label>
                    <input type="number" x-model.number="horizon" min="1" max="90" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                </div>
                <button @click="runForecast" :disabled="!productId || loading"
                        class="bg-navy-950 hover:bg-navy-900 disabled:opacity-50 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                    <span x-show="!loading">Lancer la prévision</span>
                    <span x-show="loading">Calcul en cours…</span>
                </button>
            </div>
            <template x-if="result && result.warning">
            <div class="bg-gold-500/10 border border-gold-500/30 rounded-xl px-4 py-3 flex items-start gap-2.5">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gold-600 shrink-0 mt-0.5"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <p class="text-[13px] text-navy-950/70" x-text="result.warning"></p>
            </div>
        </template>

        <template x-if="error">
                <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5 mt-4" x-text="error"></p>
            </template>
        </div>

        <template x-if="result">
            <div class="space-y-6">

                {{-- KPI du modèle retenu --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Modèle retenu</p>
                        <p class="font-display text-[18px] font-semibold mt-1 capitalize" x-text="modelLabel(result.best_model)"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Historique utilisé</p>
                        <p class="font-display text-[18px] font-semibold mt-1" x-text="result.history_days_used + ' jours'"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Erreur moyenne (MAE)</p>
                        <p class="font-display text-[18px] font-semibold mt-1" x-text="bestMetrics().mae + ' unités/jour'"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Erreur relative (WAPE)</p>
                        <p class="font-display text-[18px] font-semibold mt-1" x-text="bestMetrics().wape !== null ? Math.round(bestMetrics().wape * 100) + '%' : '—'"></p>
                    </div>
                </div>

                {{-- Graphique --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-display font-semibold text-[15px] mb-4">Prévision des ventes journalières</h3>
                    <div style="position: relative; height: 280px;">
                        <canvas id="forecastChart"></canvas>
                    </div>
                </div>

                {{-- Comparaison des modèles évalués --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-display font-semibold text-[15px] mb-4">Comparaison des modèles (validation croisée sur historique récent)</h3>
                    <p class="text-[12.5px] text-navy-950/45 mb-4">Le meilleur modèle (MAE le plus bas) est retenu automatiquement — la baseline sert de référence pour juger l'apport réel du machine learning.</p>
                    <div class="space-y-2">
                        <template x-for="(metrics, name) in result.evaluations" :key="name">
                            <div class="flex items-center gap-3">
                                <span class="w-32 text-[13px] shrink-0 capitalize" :class="name === result.best_model ? 'font-semibold text-gold-600' : 'text-navy-950/60'" x-text="modelLabel(name)"></span>
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" :class="name === result.best_model ? 'bg-gold-500' : 'bg-navy-800/30'"
                                         :style="'width:' + maeBarWidth(metrics.mae) + '%'"></div>
                                </div>
                                <span class="w-20 text-right text-[12.5px] text-navy-950/50 shrink-0" x-text="'MAE ' + metrics.mae"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!result && !loading">
            <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                <p class="text-[13.5px] text-navy-950/40">Choisis un produit et lance une prévision pour voir apparaître le graphique.</p>
            </div>
        </template>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        function forecastPage() {
            return {
                products: [], productId: '', horizon: 14, loading: false, error: '', result: null, chart: null,

                async loadProducts() {
                    const res = await apiFetch('/products?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.products = data.data; }
                },

                async runForecast() {
                    this.loading = true; this.error = ''; this.result = null;
                    const res = await apiFetch('/ai/forecast', {
                        method: 'POST',
                        body: JSON.stringify({ product_id: this.productId, horizon_days: this.horizon }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.message || "Pas assez d'historique de ventes pour ce produit (essaie avec un produit ayant plusieurs ventes sur plusieurs jours différents).";
                        this.loading = false;
                        return;
                    }
                    this.result = data;
                    this.loading = false;
                    this.$nextTick(() => this.renderChart());
                },

                renderChart() {
                    const ctx = document.getElementById('forecastChart');
                    if (this.chart) this.chart.destroy();

                    const labels = this.result.predictions.map(p => new Date(p.date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }));
                    const values = this.result.predictions.map(p => p.quantity);
                    const lower = this.result.predictions.map(p => p.lower_bound);
                    const upper = this.result.predictions.map(p => p.upper_bound);

                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [
                                {
                                    label: 'Borne haute', data: upper, borderWidth: 0,
                                    backgroundColor: 'rgba(217,164,65,0.12)', fill: '+1', pointRadius: 0, tension: 0.3,
                                },
                                {
                                    label: 'Borne basse', data: lower, borderWidth: 0,
                                    backgroundColor: 'rgba(217,164,65,0.12)', fill: false, pointRadius: 0, tension: 0.3,
                                },
                                {
                                    label: 'Prévision', data: values, borderColor: '#0B1220', backgroundColor: '#0B1220',
                                    borderWidth: 2, pointRadius: 3, tension: 0.3,
                                },
                            ],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#F1F2F5' } },
                                x: { grid: { display: false } },
                            },
                        },
                    });
                },

                bestMetrics() {
                    return this.result.evaluations[this.result.best_model];
                },
                maeBarWidth(mae) {
                    const maxMae = Math.max(...Object.values(this.result.evaluations).map(m => m.mae), 0.01);
                    return Math.max(6, Math.round((mae / maxMae) * 100));
                },
                modelLabel(name) {
                    return { naive: 'Modèle naïf', moving_average: 'Moyenne mobile', random_forest: 'Random Forest', xgboost: 'XGBoost' }[name] || name;
                }
            }
        }
    </script>
</x-app-layout>
