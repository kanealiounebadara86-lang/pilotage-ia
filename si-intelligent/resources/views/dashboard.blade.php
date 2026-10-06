<x-app-layout title="Tableau de bord">

    <div x-data="dashboardPage()" x-init="load()" x-cloak>

        {{-- État de chargement --}}
        <template x-if="loading">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <template x-for="i in 6">
                    <div class="h-32 rounded-2xl bg-gray-100 animate-pulse"></div>
                </template>
            </div>
        </template>

        <template x-if="!loading && data">
            <div class="space-y-6">

                {{-- Ligne 1 : jauges signature --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-5">
                        <div class="relative w-20 h-20 shrink-0">
                            <svg viewBox="0 0 80 80" class="w-20 h-20 -rotate-90">
                                <circle cx="40" cy="40" r="34" fill="none" stroke="#EEF0F4" stroke-width="7"/>
                                <circle class="kpi-ring" cx="40" cy="40" r="34" fill="none" stroke="#6366F1" stroke-width="7"
                                        stroke-linecap="round"
                                        :stroke-dasharray="2 * Math.PI * 34"
                                        :stroke-dashoffset="2 * Math.PI * 34 * (1 - Math.min(data.commercial.margin_percent, 100) / 100)"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center font-display font-semibold text-[15px]" x-text="data.commercial.margin_percent + '%'"></div>
                        </div>
                        <div>
                            <p class="text-[12.5px] text-navy-950/50 font-medium">Marge commerciale</p>
                            <p class="font-display text-[20px] font-semibold mt-0.5" x-text="formatXOF(data.commercial.margin)"></p>
                            <p class="text-[12px] text-navy-950/40 mt-0.5" x-text="data.commercial.sales_count + ' vente(s) sur la période'"></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-5">
                        <div class="relative w-20 h-20 shrink-0">
                            @php $healthColor = "stockHealth() >= 80 ? '#1F9D6C' : (stockHealth() >= 50 ? '#6366F1' : '#DC4C4C')"; @endphp
                            <svg viewBox="0 0 80 80" class="w-20 h-20 -rotate-90">
                                <circle cx="40" cy="40" r="34" fill="none" stroke="#EEF0F4" stroke-width="7"/>
                                <circle class="kpi-ring" cx="40" cy="40" r="34" fill="none" :stroke="{{ $healthColor }}" stroke-width="7"
                                        stroke-linecap="round"
                                        :stroke-dasharray="2 * Math.PI * 34"
                                        :stroke-dashoffset="2 * Math.PI * 34 * (1 - stockHealth() / 100)"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center font-display font-semibold text-[15px]" x-text="stockHealth() + '%'"></div>
                        </div>
                        <div>
                            <p class="text-[12.5px] text-navy-950/50 font-medium">Santé du stock</p>
                            <p class="font-display text-[20px] font-semibold mt-0.5" x-text="data.stock.total_products + ' produit(s)'"></p>
                            <p class="text-[12px] text-navy-950/40 mt-0.5">
                                <span x-text="data.stock.products_in_rupture"></span> rupture(s) ·
                                <span x-text="data.stock.products_at_risk"></span> à risque
                            </p>
                        </div>
                    </div>

                    <div class="bg-navy-950 rounded-2xl p-5 flex items-center gap-5 text-white">
                        <div class="w-20 h-20 shrink-0 rounded-full bg-white/5 border border-white/10 flex items-center justify-center">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" class="text-gold-500"><path d="M6 9a6 6 0 1112 0c0 5 2 6 2 6H4s2-1 2-6zM10 20a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <p class="text-[12.5px] text-white/50 font-medium">Alertes actives</p>
                            <p class="font-display text-[20px] font-semibold mt-0.5" x-text="alertsTotal() + ' au total'"></p>
                            <p class="text-[12px] text-white/40 mt-0.5">
                                <span class="text-rust-500 font-medium" x-text="data.alerts_summary.critique"></span> critique(s) ·
                                <span class="text-gold-400 font-medium" x-text="data.alerts_summary.elevee"></span> élevée(s)
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Ligne 2 : KPI compacts --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">CA encaissé</p>
                        <p class="font-display text-[22px] font-semibold mt-1" x-text="formatXOF(data.commercial.revenue)"></p>
                        <p class="text-[11.5px] text-navy-950/40 mt-0.5" x-text="'À encaisser : ' + formatXOF(data.commercial.receivables)"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Valeur du stock</p>
                        <p class="font-display text-[22px] font-semibold mt-1" x-text="formatXOF(data.stock.total_stock_value)"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Achats sur la période</p>
                        <p class="font-display text-[22px] font-semibold mt-1" x-text="formatXOF(data.purchases.total_amount)"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Résultat net</p>
                        <p class="font-display text-[22px] font-semibold mt-1" :class="data.finance.net_result >= 0 ? 'text-sage-600' : 'text-rust-500'" x-text="formatXOF(data.finance.net_result)"></p>
                    </div>
                </div>

                {{-- Ligne 2bis : KPI RH --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Effectif actif</p>
                        <p class="font-display text-[22px] font-semibold mt-1" x-text="data.hr.active_employees"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Masse salariale (dernière paie)</p>
                        <p class="font-display text-[22px] font-semibold mt-1" x-text="data.hr.last_payroll_total !== null ? formatXOF(data.hr.last_payroll_total) : '—'"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Taux de retard</p>
                        <p class="font-display text-[22px] font-semibold mt-1" x-text="data.hr.lateness_rate_percent + '%'"></p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide">Dernière paie validée</p>
                        <p class="font-display text-[22px] font-semibold mt-1" x-text="data.hr.last_payroll_period || '—'"></p>
                    </div>
                </div>

                {{-- Ligne 3 : tops --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="font-display font-semibold text-[15px] mb-4">Produits les plus vendus</h3>
                        <template x-if="data.commercial.top_products.length === 0">
                            <p class="text-[13px] text-navy-950/40 py-6 text-center">Aucune vente sur cette période.</p>
                        </template>
                        <div class="space-y-3">
                            <template x-for="p in data.commercial.top_products" :key="p.id">
                                <div class="flex items-center justify-between text-[13.5px]">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-7 h-7 rounded-lg bg-gold-500/10 flex items-center justify-center text-gold-600 font-display font-semibold text-[12px] shrink-0" x-text="p.quantity_sold"></div>
                                        <span class="truncate" x-text="p.name"></span>
                                    </div>
                                    <span class="font-medium shrink-0" x-text="formatXOF(p.revenue)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="font-display font-semibold text-[15px] mb-4">Meilleurs clients</h3>
                        <template x-if="data.commercial.top_customers.length === 0">
                            <p class="text-[13px] text-navy-950/40 py-6 text-center">Aucune vente sur cette période.</p>
                        </template>
                        <div class="space-y-3">
                            <template x-for="c in data.commercial.top_customers" :key="c.customer_id">
                                <div class="flex items-center justify-between text-[13.5px]">
                                    <span class="truncate" x-text="c.customer_name"></span>
                                    <span class="font-medium shrink-0" x-text="formatXOF(c.revenue)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
        function dashboardPage() {
            return {
                loading: true,
                data: null,
                async load() {
                    const res = await apiFetch('/dashboard');
                    if (res && res.ok) this.data = await res.json();
                    this.loading = false;
                },
                stockHealth() {
                    if (!this.data || this.data.stock.total_products === 0) return 100;
                    const healthy = this.data.stock.total_products - this.data.stock.products_in_rupture - this.data.stock.products_at_risk;
                    return Math.round((healthy / this.data.stock.total_products) * 100);
                },
                alertsTotal() {
                    const a = this.data.alerts_summary;
                    return a.critique + a.elevee + a.moyenne + a.faible;
                }
            }
        }
    </script>
</x-app-layout>
