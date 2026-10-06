<x-app-layout title="Devis">

    <div x-data="quotesPage()" x-init="load(); loadProducts()" x-cloak class="space-y-5">

        <div class="flex items-center justify-between">
            <p class="text-[13.5px] text-navy-950/50">Un devis accepté se convertit en vente en un clic.</p>
            <button @click="panelOpen = true; resetForm()" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouveau devis
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                        <th class="px-5 py-3 font-medium">Référence</th>
                        <th class="px-5 py-3 font-medium">Validité</th>
                        <th class="px-5 py-3 font-medium text-right">Montant</th>
                        <th class="px-5 py-3 font-medium">Statut</th>
                        <th class="px-5 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="quotes.length === 0">
                        <tr><td colspan="5" class="px-5 py-10 text-center text-navy-950/40">Aucun devis pour le moment.</td></tr>
                    </template>
                    <template x-for="q in quotes" :key="q.id">
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3.5 font-mono text-[12.5px] text-navy-950/70" x-text="q.reference"></td>
                            <td class="px-5 py-3.5 text-navy-950/60" x-text="q.valid_until ? new Date(q.valid_until).toLocaleDateString('fr-FR') : '—'"></td>
                            <td class="px-5 py-3.5 text-right font-medium" x-text="formatXOF(q.total_amount)"></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-medium" :class="statusStyle(q.status)" x-text="q.status"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button x-show="!['converti'].includes(q.status)" @click="convert(q.id)" class="text-[12.5px] font-medium text-gold-600 hover:text-gold-700">
                                    Convertir en vente
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-lg bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouveau devis</h2>
                    <button @click="panelOpen = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <div class="space-y-3 mb-4">
                    <template x-for="(line, idx) in cart" :key="idx">
                        <div class="flex items-center gap-2">
                            <select x-model.number="line.product_id" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-[13px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                                <option value="">Choisir un produit…</option>
                                <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                            </select>
                            <input type="number" x-model.number="line.quantity" min="1" class="w-20 px-2.5 py-2 rounded-lg border border-gray-300 text-[13px] text-center focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <button @click="cart.splice(idx, 1)" class="text-navy-950/30 hover:text-rust-500 shrink-0">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                    </template>
                    <button @click="cart.push({ product_id: '', quantity: 1 })" class="text-[13px] text-gold-600 font-medium hover:text-gold-700 flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Ajouter une ligne
                    </button>
                </div>
                <template x-if="error">
                    <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5 mb-4" x-text="error"></p>
                </template>
                <button @click="submit" :disabled="saving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                    <span x-show="!saving">Créer le devis</span>
                    <span x-show="saving">Création…</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        function quotesPage() {
            return {
                quotes: [], products: [], panelOpen: false, saving: false, error: '', cart: [],
                async load() {
                    const res = await apiFetch('/quotes?per_page=30');
                    if (res && res.ok) { const data = await res.json(); this.quotes = data.data; }
                },
                async loadProducts() {
                    const res = await apiFetch('/products?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.products = data.data; }
                },
                resetForm() { this.error = ''; this.cart = [{ product_id: '', quantity: 1 }]; },
                async submit() {
                    this.saving = true; this.error = '';
                    const items = this.cart.filter(l => l.product_id && l.quantity > 0).map(l => ({ product_id: l.product_id, quantity: l.quantity }));
                    if (items.length === 0) { this.error = 'Ajoute au moins une ligne.'; this.saving = false; return; }
                    const res = await apiFetch('/quotes', { method: 'POST', body: JSON.stringify({ items }) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur.'; this.saving = false; return; }
                    this.saving = false; this.panelOpen = false; this.load();
                },
                async convert(id) {
                    await apiFetch('/quotes/' + id + '/convert', { method: 'POST' });
                    this.load();
                },
                statusStyle(s) {
                    if (s === 'converti') return 'bg-sage-500/10 text-sage-600';
                    if (s === 'refuse') return 'bg-rust-500/10 text-rust-600';
                    return 'bg-gold-500/15 text-gold-600';
                }
            }
        }
    </script>
</x-app-layout>
