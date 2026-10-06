<x-app-layout title="Marketing">

    <div x-data="marketingPage()" x-init="load(); loadProducts()" x-cloak class="space-y-6">

        <div class="flex items-center justify-between">
            <p class="text-[13.5px] text-navy-950/50">Le ROI est calculé à partir des ventes réellement attribuées à chaque campagne.</p>
            <button @click="panelOpen = true; resetForm()" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouvelle campagne
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <template x-if="campaigns.length === 0">
                <p class="text-[13px] text-navy-950/40 py-10 col-span-full text-center">Aucune campagne pour le moment.</p>
            </template>
            <template x-for="c in campaigns" :key="c.id">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="font-display font-semibold text-[15px]" x-text="c.name"></p>
                            <p class="text-[12px] text-navy-950/40" x-text="new Date(c.start_date).toLocaleDateString('fr-FR') + ' → ' + new Date(c.end_date).toLocaleDateString('fr-FR')"></p>
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium" :class="statusStyle(c.status)" x-text="statusLabel(c.status)"></span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-3">
                        <div>
                            <p class="text-[10.5px] font-medium text-navy-950/40 uppercase tracking-wide">Budget</p>
                            <p class="text-[13.5px] font-medium mt-0.5" x-text="formatXOF(c.budget)"></p>
                        </div>
                        <div>
                            <p class="text-[10.5px] font-medium text-navy-950/40 uppercase tracking-wide">CA généré</p>
                            <p class="text-[13.5px] font-medium mt-0.5" x-text="formatXOF(c.performance.revenue)"></p>
                        </div>
                        <div>
                            <p class="text-[10.5px] font-medium text-navy-950/40 uppercase tracking-wide">ROI</p>
                            <p class="text-[13.5px] font-semibold mt-0.5" :class="roiColor(c.performance.roi_percent)" x-text="c.performance.roi_percent !== null ? c.performance.roi_percent + '%' : '—'"></p>
                        </div>
                    </div>

                    <p class="text-[12px] text-navy-950/40" x-text="c.performance.orders_count + ' vente(s) attribuée(s) · marge ' + formatXOF(c.performance.margin)"></p>

                    <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-100" x-show="c.status !== 'terminee'">
                        <button x-show="c.status === 'planifiee'" @click="updateStatus(c.id, 'active')" class="text-[12px] font-medium text-gold-600 hover:text-gold-700">Activer</button>
                        <button x-show="c.status === 'active'" @click="updateStatus(c.id, 'terminee')" class="text-[12px] font-medium text-navy-950/50 hover:text-navy-950">Clôturer</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Volet création --}}
        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvelle campagne</h2>
                    <button @click="panelOpen = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="create" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Nom</label>
                        <input type="text" x-model="form.name" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Description</label>
                        <textarea x-model="form.description" rows="2" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Début</label>
                            <input type="date" x-model="form.start_date" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Fin</label>
                            <input type="date" x-model="form.end_date" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Budget</label>
                        <input type="number" x-model="form.budget" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Produit ciblé (optionnel)</label>
                        <select x-model.number="form.target_product_id" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="">Aucun en particulier</option>
                            <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                        </select>
                    </div>
                    <template x-if="error">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>
                    </template>
                    <button type="submit" :disabled="saving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!saving">Créer la campagne</span>
                        <span x-show="saving">Création…</span>
                    </button>
                </form>
                <p class="text-[11.5px] text-navy-950/35 mt-4">
                    Pour attribuer une vente à cette campagne, choisis-la lors de la création d'une vente (page Ventes).
                </p>
            </div>
        </div>
    </div>

    <script>
        function marketingPage() {
            return {
                campaigns: [], products: [], panelOpen: false, saving: false, error: '', form: {},
                async load() {
                    const res = await apiFetch('/campaigns');
                    if (res && res.ok) this.campaigns = await res.json();
                },
                async loadProducts() {
                    const res = await apiFetch('/products?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.products = data.data; }
                },
                resetForm() { this.error = ''; this.form = { name: '', description: '', start_date: '', end_date: '', budget: '', target_product_id: '' }; },
                async create() {
                    this.saving = true; this.error = '';
                    const res = await apiFetch('/campaigns', { method: 'POST', body: JSON.stringify(this.form) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur.'; this.saving = false; return; }
                    this.saving = false; this.panelOpen = false; this.load();
                },
                async updateStatus(id, status) {
                    await apiFetch('/campaigns/' + id + '/status', { method: 'PUT', body: JSON.stringify({ status }) });
                    this.load();
                },
                statusLabel(s) {
                    return { planifiee: 'Planifiée', active: 'Active', terminee: 'Terminée' }[s] || s;
                },
                statusStyle(s) {
                    if (s === 'active') return 'bg-sage-500/10 text-sage-600';
                    if (s === 'terminee') return 'bg-navy-800/10 text-navy-800';
                    return 'bg-gold-500/15 text-gold-600';
                },
                roiColor(roi) {
                    if (roi === null) return 'text-navy-950/40';
                    return roi >= 0 ? 'text-sage-600' : 'text-rust-500';
                }
            }
        }
    </script>
</x-app-layout>
