<x-app-layout title="Entrepôts">

    <div x-data="warehousesPage()" x-init="load(); loadProducts()" x-cloak class="space-y-6">

        <div class="flex items-center justify-between">
            <p class="text-[13.5px] text-navy-950/50">Localisations physiques et transferts de stock entre entrepôts.</p>
            <button @click="whPanel = true" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouvel entrepôt
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <template x-for="w in warehouses" :key="w.id">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <p class="font-display font-semibold text-[15px]" x-text="w.name"></p>
                        <span x-show="w.is_default" class="text-[10.5px] font-medium px-1.5 py-0.5 rounded bg-gold-500/15 text-gold-600">Par défaut</span>
                    </div>
                    <p class="text-[12.5px] text-navy-950/40" x-text="w.address || 'Adresse non renseignée'"></p>
                </div>
            </template>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="font-display font-semibold text-[15px] mb-4">Transférer du stock</h3>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-[12px] font-medium text-navy-950/60 mb-1.5">Produit</label>
                    <select x-model.number="transferForm.product_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        <option value="">Choisir…</option>
                        <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-medium text-navy-950/60 mb-1.5">De</label>
                    <select x-model.number="transferForm.from_warehouse_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        <option value="">Choisir…</option>
                        <template x-for="w in warehouses" :key="w.id"><option :value="w.id" x-text="w.name"></option></template>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-medium text-navy-950/60 mb-1.5">Vers</label>
                    <select x-model.number="transferForm.to_warehouse_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        <option value="">Choisir…</option>
                        <template x-for="w in warehouses" :key="w.id"><option :value="w.id" x-text="w.name"></option></template>
                    </select>
                </div>
                <div class="flex gap-2">
                    <input type="number" x-model.number="transferForm.quantity" min="1" placeholder="Qté" class="w-20 px-2.5 py-2 rounded-lg border border-gray-300 text-[13px] text-center focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    <button @click="submitTransfer" :disabled="transferSaving" class="flex-1 bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[13px] font-medium px-3 py-2 rounded-lg transition-colors">Transférer</button>
                </div>
            </div>
            <template x-if="transferError">
                <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5 mt-3" x-text="transferError"></p>
            </template>
            <template x-if="transferSuccess">
                <p class="text-[13px] text-sage-600 bg-sage-500/10 rounded-lg px-3.5 py-2.5 mt-3">Transfert enregistré.</p>
            </template>
        </div>

        <div x-show="whPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="whPanel = false"></div>
            <div x-show="whPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvel entrepôt</h2>
                    <button @click="whPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="createWarehouse" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Nom</label>
                        <input type="text" x-model="whForm.name" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Adresse</label>
                        <input type="text" x-model="whForm.address" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <button type="submit" :disabled="whSaving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!whSaving">Créer l'entrepôt</span>
                        <span x-show="whSaving">Création…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function warehousesPage() {
            return {
                warehouses: [], products: [], whPanel: false, whSaving: false, whForm: {},
                transferForm: {}, transferSaving: false, transferError: '', transferSuccess: false,
                async load() {
                    const res = await apiFetch('/warehouses');
                    if (res && res.ok) this.warehouses = await res.json();
                },
                async loadProducts() {
                    const res = await apiFetch('/products?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.products = data.data; }
                },
                async createWarehouse() {
                    this.whSaving = true;
                    const res = await apiFetch('/warehouses', { method: 'POST', body: JSON.stringify(this.whForm) });
                    if (res && res.ok) { this.whSaving = false; this.whPanel = false; this.whForm = {}; this.load(); }
                },
                async submitTransfer() {
                    this.transferSaving = true; this.transferError = ''; this.transferSuccess = false;
                    const res = await apiFetch('/stock-transfers', { method: 'POST', body: JSON.stringify(this.transferForm) });
                    const data = await res.json();
                    if (!res.ok) { this.transferError = data.message || 'Erreur lors du transfert.'; this.transferSaving = false; return; }
                    this.transferSaving = false; this.transferSuccess = true; this.transferForm = {};
                    setTimeout(() => this.transferSuccess = false, 3000);
                }
            }
        }
    </script>
</x-app-layout>
