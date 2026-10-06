<x-app-layout title="Stocks & Alertes">

    <div x-data="alertsPage()" x-init="load()" x-cloak class="space-y-5">

        <div class="flex items-center gap-2">
            <template x-for="opt in [
                { key: null, label: 'Toutes' }, { key: 'critique', label: 'Critique' },
                { key: 'elevee', label: 'Élevée' }, { key: 'moyenne', label: 'Moyenne' }, { key: 'faible', label: 'Faible' }
            ]" :key="opt.label">
                <button @click="filter = opt.key; load()"
                        class="px-3.5 py-1.5 rounded-full text-[12.5px] font-medium border transition-colors"
                        :class="filter === opt.key ? 'bg-navy-950 text-white border-navy-950' : 'bg-white text-navy-950/60 border-gray-200 hover:border-navy-950/30'"
                        x-text="opt.label"></button>
            </template>
        </div>

        <div class="space-y-2.5">
            <template x-if="loading">
                <p class="text-[13px] text-navy-950/40 py-10 text-center">Chargement…</p>
            </template>
            <template x-if="!loading && alerts.length === 0">
                <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
                    <p class="text-[14px] text-navy-950/50">Aucune alerte active — le stock est sous contrôle.</p>
                </div>
            </template>
            <template x-for="a in alerts" :key="a.id">
                <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                    <div class="w-1.5 self-stretch rounded-full shrink-0" :class="severityColor(a.severity).bar"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full" :class="severityColor(a.severity).badge" x-text="a.severity"></span>
                            <span class="text-[11.5px] text-navy-950/35" x-text="new Date(a.created_at).toLocaleDateString('fr-FR')"></span>
                        </div>
                        <p class="text-[13.5px] text-navy-950" x-text="a.message"></p>
                    </div>
                    <button @click="resolve(a.id)" class="shrink-0 text-[12.5px] font-medium text-navy-950/50 hover:text-sage-600 border border-gray-200 hover:border-sage-500/40 rounded-lg px-3 py-1.5 transition-colors">
                        Résoudre
                    </button>
                </div>
            </template>
        </div>
    </div>

    <script>
        function alertsPage() {
            return {
                alerts: [], loading: true, filter: null,
                async load() {
                    this.loading = true;
                    const qs = this.filter ? '?severity=' + this.filter : '';
                    const res = await apiFetch('/alerts' + qs);
                    if (res && res.ok) { const data = await res.json(); this.alerts = data.data; }
                    this.loading = false;
                },
                async resolve(id) {
                    await apiFetch('/alerts/' + id + '/resolve', { method: 'POST' });
                    this.load();
                },
                severityColor(sev) {
                    const map = {
                        critique: { bar: 'bg-rust-500', badge: 'bg-rust-500/10 text-rust-600' },
                        elevee: { bar: 'bg-gold-500', badge: 'bg-gold-500/15 text-gold-600' },
                        moyenne: { bar: 'bg-navy-800', badge: 'bg-navy-800/10 text-navy-800' },
                        faible: { bar: 'bg-gray-300', badge: 'bg-gray-100 text-navy-950/50' },
                    };
                    return map[sev] || map.faible;
                }
            }
        }
    </script>
</x-app-layout>
