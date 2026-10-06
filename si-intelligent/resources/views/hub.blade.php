@php $section = config('navigation.sections.'.$sectionKey); @endphp
<x-app-layout :title="$section['label']" :subtitle="$section['desc']">
    <div x-data="hubPage(@js($sectionKey))" x-init="load()" x-cloak class="space-y-6">

        {{-- Indicateurs clés de l'espace --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <template x-for="k in kpis" :key="k.label">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide" x-text="k.label"></p>
                    <p class="font-display text-[22px] font-semibold mt-1.5" :class="k.tone === 'bad' ? 'text-rust-500' : ''" x-text="k.value"></p>
                    <p class="text-[12px] text-navy-950/40 mt-0.5" x-text="k.hint"></p>
                </div>
            </template>
            <template x-if="loading"><div class="col-span-full h-24 rounded-2xl bg-gray-100 animate-pulse"></div></template>
        </div>

        {{-- Modules de l'espace --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($section['items'] as $item)
                <a href="{{ route($item['route']) }}" x-show="can('{{ $item['perm'] }}')"
                   class="group bg-white rounded-2xl border border-gray-200 p-5 flex gap-4 transition-all hover:-translate-y-0.5">
                    <div class="w-11 h-11 rounded-xl bg-gold-500/10 text-gold-600 flex items-center justify-center shrink-0 group-hover:bg-gold-500 group-hover:text-white transition-colors">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="{{ config('navigation.icons.'.$item['icon']) }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-display font-semibold text-[15.5px]">{{ $item['label'] }}</p>
                        <p class="text-[13px] text-navy-950/50 mt-1 leading-snug">{{ $item['desc'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <script>
        function hubPage(key) {
            const f = window.formatXOF;
            return {
                loading: true, kpis: [],
                can(p) { try { const u = JSON.parse(localStorage.getItem('si_user') || '{}'); const r = u.role || {}; return !p || r.name === 'admin' || (r.permissions || []).some(x => x.name === p); } catch (e) { return true; } },
                async load() {
                    try {
                        const res = await apiFetch('/dashboard');
                        if (res && res.ok) this.kpis = this.build(key, await res.json());
                    } catch (e) {}
                    this.loading = false;
                },
                build(k, d) {
                    const c = d.commercial || {}, s = d.stock || {}, p = d.purchases || {}, fi = d.finance || {}, h = d.hr || {}, a = d.alerts_summary || {};
                    const map = {
                        commercial: [
                            { label: 'CA encaissé', value: f(c.revenue), hint: (c.sales_count || 0) + ' vente(s) ce mois' },
                            { label: 'Marge encaissée', value: f(c.margin), hint: (c.margin_percent || 0) + ' % de marge' },
                            { label: 'Reste à encaisser', value: f(c.receivables), hint: 'ventes non payées', tone: c.receivables > 0 ? 'bad' : '' },
                            { label: 'Remboursements', value: f(c.refunds), hint: 'rendus aux clients' },
                        ],
                        stock: [
                            { label: 'Unités en stock', value: s.total_units_in_stock ?? 0, hint: (s.total_products || 0) + ' produit(s)' },
                            { label: 'Valeur du stock', value: f(s.total_stock_value), hint: 'au coût d\'achat' },
                            { label: 'Ruptures', value: s.products_in_rupture ?? 0, hint: (s.products_at_risk || 0) + ' à risque', tone: s.products_in_rupture > 0 ? 'bad' : '' },
                            { label: 'Commandes en cours', value: p.pending_orders ?? 0, hint: (p.late_orders || 0) + ' en retard' },
                        ],
                        finance: [
                            { label: 'Revenus', value: f(fi.revenue_total), hint: 'transactions du mois' },
                            { label: 'Dépenses', value: f(fi.expense_total), hint: 'transactions du mois' },
                            { label: 'Résultat net', value: f(fi.net_result), hint: 'période en cours', tone: fi.net_result < 0 ? 'bad' : '' },
                            { label: 'Marge nette ventes', value: (fi.sales_margin_percent || 0) + ' %', hint: f(fi.sales_margin) },
                        ],
                        rh: [
                            { label: 'Employés actifs', value: h.active_employees ?? 0, hint: 'effectif' },
                            { label: 'Dernière paie', value: h.last_payroll_total ? f(h.last_payroll_total) : '—', hint: 'masse salariale validée' },
                            { label: 'Taux de retard', value: (h.lateness_rate_percent ?? 0) + ' %', hint: 'sur la période' },
                            { label: 'Période de paie', value: h.last_payroll_period || '—', hint: 'dernière paie validée' },
                        ],
                        ia: [
                            { label: 'Alertes critiques', value: a.critique ?? 0, hint: 'à traiter', tone: a.critique > 0 ? 'bad' : '' },
                            { label: 'Alertes élevées', value: a.elevee ?? 0, hint: 'à surveiller' },
                            { label: 'Alertes moyennes', value: a.moyenne ?? 0, hint: '' },
                            { label: 'Alertes faibles', value: a.faible ?? 0, hint: '' },
                        ],
                    };
                    return map[k] || [];
                },
            };
        }
    </script>
</x-app-layout>
