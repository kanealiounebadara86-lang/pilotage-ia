<x-app-layout title="Achats">

    <div x-data="purchasesPage()" x-init="load(); loadProducts(); loadSuppliers()" x-cloak class="space-y-5">

        <div class="flex items-center justify-between">
            <p class="text-[13.5px] text-navy-950/50">Commandes fournisseurs et réceptions.</p>
            <button @click="panelOpen = true; resetForm()"
                    class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouvelle commande
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                        <th class="px-5 py-3 font-medium">Référence</th>
                        <th class="px-5 py-3 font-medium">Fournisseur</th>
                        <th class="px-5 py-3 font-medium text-right">Montant</th>
                        <th class="px-5 py-3 font-medium">Paiement</th>
                        <th class="px-5 py-3 font-medium">Statut</th>
                        <th class="px-5 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="6" class="px-5 py-10 text-center text-navy-950/40">Chargement…</td></tr>
                    </template>
                    <template x-if="!loading && orders.length === 0">
                        <tr><td colspan="6" class="px-5 py-10 text-center text-navy-950/40">Aucune commande enregistrée.</td></tr>
                    </template>
                    <template x-for="o in orders" :key="o.id">
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-paper/60 transition-colors">
                            <td class="px-5 py-3.5 font-mono text-[12.5px] text-navy-950/70" x-text="o.reference"></td>
                            <td class="px-5 py-3.5" x-text="o.supplier ? o.supplier.name : '—'"></td>
                            <td class="px-5 py-3.5 text-right font-medium" x-text="formatXOF(o.total_amount)"></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-medium" :class="paymentStyle(o.payment_status)" x-text="paymentLabel(o.payment_status)"></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11.5px] font-medium" :class="statusStyle(o.status)" x-text="statusLabel(o.status)"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-2">
                                <button x-show="['demande','commande','reception_partielle'].includes(o.status)" @click="openReceive(o)" class="text-[12.5px] font-medium text-gold-600 hover:text-gold-700">
                                    Réceptionner
                                </button>
                                <button x-show="o.payment_status !== 'payee' && o.status !== 'annulee'" @click="openPayment(o)" class="text-[12.5px] font-medium text-navy-800 hover:text-navy-950">
                                    Régler
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Volet nouvelle commande --}}
        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-lg bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvelle commande</h2>
                    <button @click="panelOpen = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Fournisseur</label>
                    <select x-model.number="form.supplier_id" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        <option value="">Choisir un fournisseur…</option>
                        <template x-for="s in suppliers" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                </div>

                <div class="space-y-3 mb-4">
                    <template x-for="(line, idx) in cart" :key="idx">
                        <div class="flex items-center gap-2">
                            <select x-model.number="line.product_id" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-[13px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                                <option value="">Choisir un produit…</option>
                                <template x-for="p in products" :key="p.id">
                                    <option :value="p.id" x-text="p.name"></option>
                                </template>
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

                <button @click="submitOrder" :disabled="saving"
                        class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                    <span x-show="!saving">Créer la commande</span>
                    <span x-show="saving">Création…</span>
                </button>
            </div>
        </div>

        {{-- Volet réception --}}
        <div x-show="receivePanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="receivePanel = false"></div>
            <div x-show="receivePanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-lg bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Réceptionner <span x-text="receivingOrder ? receivingOrder.reference : ''"></span></h2>
                    <button @click="receivePanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="space-y-3 mb-5">
                    <template x-for="item in (receivingOrder ? receivingOrder.items : [])" :key="item.id">
                        <div class="flex items-center justify-between gap-3 border border-gray-100 rounded-lg p-3">
                            <div>
                                <p class="text-[13.5px] font-medium" x-text="item.product.name"></p>
                                <p class="text-[12px] text-navy-950/40" x-text="'Commandé: ' + item.quantity_ordered + ' · Déjà reçu: ' + item.quantity_received"></p>
                            </div>
                            <input type="number" min="0" :max="item.quantity_ordered - item.quantity_received"
                                   x-model.number="receiveQuantities[item.id]" class="w-20 px-2.5 py-2 rounded-lg border border-gray-300 text-[13px] text-center focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </template>
                </div>

                <template x-if="error">
                    <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5 mb-4" x-text="error"></p>
                </template>

                <button @click="submitReceive" :disabled="saving"
                        class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                    <span x-show="!saving">Confirmer la réception</span>
                    <span x-show="saving">Enregistrement…</span>
                </button>
            </div>
        </div>

        {{-- Volet règlement fournisseur --}}
        <div x-show="paymentPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="paymentPanel = false"></div>
            <div x-show="paymentPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="font-display font-semibold text-[18px]">Régler <span x-text="payingOrder ? payingOrder.reference : ''"></span></h2>
                    <button @click="paymentPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <p class="text-[13px] text-navy-950/50 mb-6" x-text="'Solde restant dû : ' + formatXOF(payingOrder ? payingOrder.total_amount - (payingOrder.amount_paid || 0) : 0)"></p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Montant réglé</label>
                        <input type="number" x-model.number="paymentForm.amount" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Moyen de paiement</label>
                        <select x-model="paymentForm.method" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="especes">Espèces</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="virement">Virement</option>
                            <option value="cheque">Chèque</option>
                            <option value="carte">Carte</option>
                        </select>
                    </div>
                </div>
                <template x-if="paymentError">
                    <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5 mt-4" x-text="paymentError"></p>
                </template>
                <button @click="submitPayment" :disabled="paymentSaving" class="w-full mt-5 bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                    <span x-show="!paymentSaving">Enregistrer le règlement</span>
                    <span x-show="paymentSaving">Enregistrement…</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        function purchasesPage() {
            return {
                orders: [], products: [], suppliers: [], loading: true,
                panelOpen: false, receivePanel: false, saving: false, error: '',
                form: {}, cart: [], receivingOrder: null, receiveQuantities: {},
                paymentPanel: false, paymentSaving: false, paymentError: '', payingOrder: null, paymentForm: {},
                async load() {
                    this.loading = true;
                    const res = await apiFetch('/purchase-orders?per_page=50');
                    if (res && res.ok) { const data = await res.json(); this.orders = data.data; }
                    this.loading = false;
                },
                async loadProducts() {
                    const res = await apiFetch('/products?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.products = data.data; }
                },
                async loadSuppliers() {
                    const res = await apiFetch('/suppliers?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.suppliers = data.data; }
                },
                resetForm() { this.error = ''; this.form = { supplier_id: '' }; this.cart = [{ product_id: '', quantity: 1 }]; },
                async submitOrder() {
                    this.saving = true; this.error = '';
                    const items = this.cart.filter(l => l.product_id && l.quantity > 0).map(l => ({ product_id: l.product_id, quantity: l.quantity }));
                    if (!this.form.supplier_id || items.length === 0) { this.error = 'Fournisseur et au moins une ligne requis.'; this.saving = false; return; }
                    const res = await apiFetch('/purchase-orders', { method: 'POST', body: JSON.stringify({ supplier_id: this.form.supplier_id, items }) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur lors de la création.'; this.saving = false; return; }
                    this.saving = false; this.panelOpen = false; this.load();
                },
                openReceive(order) {
                    this.receivingOrder = order;
                    this.receiveQuantities = {};
                    order.items.forEach(i => { this.receiveQuantities[i.id] = i.quantity_ordered - i.quantity_received; });
                    this.error = '';
                    this.receivePanel = true;
                },
                async submitReceive() {
                    this.saving = true; this.error = '';
                    const lines = Object.entries(this.receiveQuantities)
                        .filter(([_, qty]) => qty > 0)
                        .map(([id, qty]) => ({ purchase_item_id: parseInt(id), quantity_received: qty }));
                    if (lines.length === 0) { this.error = 'Indique au moins une quantité à recevoir.'; this.saving = false; return; }
                    const res = await apiFetch('/purchase-orders/' + this.receivingOrder.id + '/receive', { method: 'POST', body: JSON.stringify({ lines }) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur lors de la réception.'; this.saving = false; return; }
                    this.saving = false; this.receivePanel = false; this.load();
                },
                statusLabel(s) {
                    return { demande: 'Demande', commande: 'Commandée', reception_partielle: 'Réception partielle', reception_complete: 'Reçue', annulee: 'Annulée' }[s] || s;
                },
                statusStyle(s) {
                    if (s === 'reception_complete') return 'bg-sage-500/10 text-sage-600';
                    if (s === 'annulee') return 'bg-rust-500/10 text-rust-600';
                    if (s === 'reception_partielle') return 'bg-gold-500/15 text-gold-600';
                    return 'bg-navy-800/10 text-navy-800';
                },
                openPayment(order) {
                    this.payingOrder = order;
                    this.paymentError = '';
                    this.paymentForm = { amount: order.total_amount - (order.amount_paid || 0), method: 'especes' };
                    this.paymentPanel = true;
                },
                async submitPayment() {
                    this.paymentSaving = true; this.paymentError = '';
                    const res = await apiFetch('/purchase-orders/' + this.payingOrder.id + '/payments', { method: 'POST', body: JSON.stringify(this.paymentForm) });
                    const data = await res.json();
                    if (!res.ok) { this.paymentError = data.message || 'Erreur lors du règlement.'; this.paymentSaving = false; return; }
                    this.paymentSaving = false; this.paymentPanel = false; this.load();
                },
                paymentLabel(s) {
                    return { non_payee: 'Non payée', partielle: 'Partielle', payee: 'Payée' }[s] || s;
                },
                paymentStyle(s) {
                    if (s === 'payee') return 'bg-sage-500/10 text-sage-600';
                    if (s === 'partielle') return 'bg-gold-500/15 text-gold-600';
                    return 'bg-rust-500/10 text-rust-600';
                }
            }
        }
    </script>
</x-app-layout>
