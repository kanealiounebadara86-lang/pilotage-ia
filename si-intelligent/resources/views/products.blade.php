<x-app-layout title="Produits">

    <div x-data="productsPage()" x-init="load(); resetForm()" x-cloak class="space-y-5">

        {{-- Barre d'action --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="relative w-full sm:w-80">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="absolute left-3 top-1/2 -translate-y-1/2 text-navy-950/35"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="text" x-model="search" @input.debounce.400ms="load()" placeholder="Rechercher un produit…"
                       class="w-full pl-9 pr-3 py-2.5 rounded-lg border border-gray-300 bg-white text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40 focus:border-gold-500">
            </div>
            <button @click="openCreate()"
                    class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouveau produit
            </button>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                        <th class="px-5 py-3 font-medium">Produit</th>
                        <th class="px-5 py-3 font-medium">Référence</th>
                        <th class="px-5 py-3 font-medium text-right">Stock</th>
                        <th class="px-5 py-3 font-medium text-right">Prix de vente</th>
                        <th class="px-5 py-3 font-medium text-right">Marge</th>
                        <th class="px-5 py-3 font-medium">Statut</th>
                        <th class="px-5 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="7" class="px-5 py-10 text-center text-navy-950/40">Chargement…</td></tr>
                    </template>
                    <template x-if="!loading && products.length === 0">
                        <tr><td colspan="7" class="px-5 py-10 text-center text-navy-950/40">Aucun produit pour le moment.</td></tr>
                    </template>
                    <template x-for="p in products" :key="p.id">
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-paper/60 transition-colors cursor-pointer" @click="openEdit(p)">
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-navy-950" x-text="p.name"></p>
                                <p class="text-[12px] text-navy-950/40" x-text="p.category ? p.category.name : 'Sans catégorie'"></p>
                            </td>
                            <td class="px-5 py-3.5 text-navy-950/60 font-mono text-[12.5px]" x-text="p.reference"></td>
                            <td class="px-5 py-3.5 text-right font-medium" x-text="(p.stock_level ? p.stock_level.quantity_available : 0) + ' ' + p.unit"></td>
                            <td class="px-5 py-3.5 text-right" x-text="formatXOF(p.sale_price)"></td>
                            <td class="px-5 py-3.5 text-right" x-text="margin(p) + '%'"></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11.5px] font-medium"
                                      :class="stockBadge(p).class">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="stockBadge(p).dot"></span>
                                    <span x-text="stockBadge(p).label"></span>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-[12.5px] font-medium text-navy-950/40">Modifier</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Volet création / édition --}}
        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="font-display font-semibold text-[18px]" x-text="editingId ? 'Modifier le produit' : 'Nouveau produit'"></h2>
                    <button @click="panelOpen = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <p class="text-[12.5px] text-navy-950/40 mb-6" x-show="editingId" x-text="'Référence : ' + form.reference"></p>
                <p class="text-[12.5px] text-navy-950/40 mb-6" x-show="!editingId">La référence sera générée automatiquement.</p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Nom du produit</label>
                        <input type="text" x-model="form.name" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">
                                Unité
                                <span class="text-navy-950/35 font-normal normal-case">(unite, kg, carton…)</span>
                            </label>
                            <input type="text" x-model="form.unit" required placeholder="unite" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Coût unitaire</label>
                            <input type="number" x-model="form.cost" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Prix d'achat</label>
                            <input type="number" x-model="form.purchase_price" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Prix de vente</label>
                            <input type="number" x-model="form.sale_price" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Stock minimum</label>
                            <input type="number" x-model="form.stock_min" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Stock de sécurité</label>
                            <input type="number" x-model="form.safety_stock" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>

                    <template x-if="editingId">
                        <div class="bg-paper rounded-lg p-3.5">
                            <p class="text-[12.5px] text-navy-950/50">
                                Stock actuel : <span class="font-medium text-navy-950" x-text="(editingStockQty ?? 0) + ' ' + form.unit"></span>
                                — modifiable uniquement via une réception d'achat ou un ajustement (page Stocks & Alertes), pas depuis cette fiche.
                            </p>
                        </div>
                    </template>

                    <template x-if="error">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>
                    </template>

                    <button type="submit" :disabled="saving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!saving" x-text="editingId ? 'Enregistrer les modifications' : 'Créer le produit'"></span>
                        <span x-show="saving">Enregistrement…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function productsPage() {
            return {
                products: [], loading: true, search: '', panelOpen: false, saving: false, error: '',
                form: {}, editingId: null, editingStockQty: null,

                async load() {
                    this.loading = true;
                    const res = await apiFetch('/products?search=' + encodeURIComponent(this.search) + '&per_page=50');
                    if (res && res.ok) { const data = await res.json(); this.products = data.data; }
                    this.loading = false;
                },

                resetForm() {
                    this.error = '';
                    this.form = { name: '', unit: 'unite', cost: '', purchase_price: '', sale_price: '', stock_min: 5, safety_stock: 2 };
                },
                openCreate() {
                    this.editingId = null;
                    this.editingStockQty = null;
                    this.resetForm();
                    this.panelOpen = true;
                },
                openEdit(p) {
                    this.editingId = p.id;
                    this.editingStockQty = p.stock_level ? p.stock_level.quantity_available : 0;
                    this.error = '';
                    this.form = {
                        reference: p.reference, name: p.name, unit: p.unit, cost: p.cost,
                        purchase_price: p.purchase_price, sale_price: p.sale_price,
                        stock_min: p.stock_min, safety_stock: p.safety_stock,
                    };
                    this.panelOpen = true;
                },

                async submit() {
                    this.saving = true; this.error = '';
                    const { reference, ...payload } = this.form; // la référence ne se modifie jamais depuis ce formulaire
                    const url = this.editingId ? '/products/' + this.editingId : '/products';
                    const method = this.editingId ? 'PUT' : 'POST';
                    const res = await apiFetch(url, { method, body: JSON.stringify(payload) });
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.message || 'Erreur lors de l\'enregistrement.';
                        this.saving = false;
                        return;
                    }
                    this.saving = false;
                    this.panelOpen = false;
                    this.load();
                },

                margin(p) {
                    if (!p.sale_price || p.sale_price == 0) return 0;
                    return Math.round(((p.sale_price - p.cost) / p.sale_price) * 100);
                },
                stockBadge(p) {
                    const qty = p.stock_level ? p.stock_level.quantity_available : 0;
                    if (qty <= 0) return { label: 'Rupture', class: 'bg-rust-500/10 text-rust-600', dot: 'bg-rust-500' };
                    if (qty <= p.stock_min) return { label: 'Stock bas', class: 'bg-gold-500/15 text-gold-600', dot: 'bg-gold-500' };
                    if (p.stock_max && qty >= p.stock_max) return { label: 'Surstock', class: 'bg-navy-800/10 text-navy-800', dot: 'bg-navy-800' };
                    return { label: 'Sain', class: 'bg-sage-500/10 text-sage-600', dot: 'bg-sage-500' };
                }
            }
        }
    </script>
</x-app-layout>
