<x-app-layout title="Ventes">

    <div x-data="salesPage()" x-init="load(); loadProducts()" x-cloak class="space-y-5">

        <div class="flex items-center justify-between">
            <p class="text-[13.5px] text-navy-950/50">Historique des ventes confirmées, marge calculée automatiquement.</p>
            <div class="flex items-center gap-2">
            <button @click="collectAll()" class="inline-flex items-center gap-2 border border-gray-200 bg-white hover:border-gray-300 text-navy-950 text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors shrink-0">
                Encaisser tout
            </button>
            <button @click="panelOpen = true; resetForm()"
                    class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouvelle vente
            </button>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                        <th class="px-5 py-3 font-medium">Référence</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                        <th class="px-5 py-3 font-medium text-right">Montant</th>
                        <th class="px-5 py-3 font-medium">Paiement</th>
                        <th class="px-5 py-3 font-medium text-right">Marge</th>
                        <th class="px-5 py-3 font-medium">Statut</th>
                        <th class="px-5 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="5" class="px-5 py-10 text-center text-navy-950/40">Chargement…</td></tr>
                    </template>
                    <template x-if="!loading && sales.length === 0">
                        <tr><td colspan="5" class="px-5 py-10 text-center text-navy-950/40">Aucune vente enregistrée.</td></tr>
                    </template>
                    <template x-for="s in sales" :key="s.id">
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-paper/60 transition-colors">
                            <td class="px-5 py-3.5 font-mono text-[12.5px] text-navy-950/70" x-text="s.reference"></td>
                            <td class="px-5 py-3.5 text-navy-950/60" x-text="new Date(s.sale_date).toLocaleDateString('fr-FR')"></td>
                            <td class="px-5 py-3.5 text-right font-medium" x-text="formatXOF(s.total_amount)"></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-medium" :class="paymentStyle(s.payment_status)" x-text="paymentLabel(s.payment_status)"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right text-sage-600 font-medium" x-text="formatXOF(s.margin_amount) + ' (' + s.margin_percent + '%)'"></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11.5px] font-medium"
                                      :class="s.status === 'confirmed' ? 'bg-sage-500/10 text-sage-600' : 'bg-rust-500/10 text-rust-600'"
                                      x-text="s.status === 'confirmed' ? 'Confirmée' : 'Annulée'"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button x-show="s.payment_status !== 'payee' && s.status === 'confirmed'" @click="openPayment(s)" class="text-[12.5px] font-medium text-gold-600 hover:text-gold-700">
                                    Encaisser
                                </button>
                                <button x-show="s.status === 'confirmed' && !(s.returned_amount > 0)" @click="cancelSale(s)" class="ml-3 text-[12.5px] font-medium text-rust-500 hover:text-rust-600">
                                    Annuler
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Volet nouvelle vente --}}
        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-lg bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvelle vente</h2>
                    <button @click="panelOpen = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Campagne marketing (optionnel)</label>
                    <select x-model.number="saleCampaignId" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        <option value="">Aucune</option>
                        <template x-for="c in campaigns" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                </div>
                <div class="space-y-3 mb-4">
                    <template x-for="(line, idx) in cart" :key="idx">
                        <div class="flex items-center gap-2">
                            <select x-model.number="line.product_id" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-[13px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                                <option value="">Choisir un produit…</option>
                                <template x-for="p in availableProducts" :key="p.id">
                                    <option :value="p.id" x-text="p.name + ' — ' + formatXOF(p.sale_price)"></option>
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

                <div class="mb-5 rounded-xl border border-gray-200 p-4 space-y-3">
                    <label class="flex items-center gap-2 text-[13.5px] font-medium">
                        <input type="checkbox" x-model="payNow" class="rounded border-gray-300">
                        Encaisser maintenant
                    </label>
                    <div x-show="payNow">
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Mode de paiement</label>
                        <select x-model="payMethod" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px]">
                            <option value="especes">Espèces</option><option value="mobile_money">Mobile money</option>
                            <option value="carte">Carte</option><option value="virement">Virement</option><option value="cheque">Chèque</option>
                        </select>
                    </div>
                    <p class="text-[12px] text-navy-950/45" x-show="!payNow">Sans encaissement, la vente reste une créance : elle n'augmente pas le chiffre d'affaires tant qu'elle n'est pas payée.</p>
                </div>

                <div class="bg-paper rounded-xl p-4 mb-5 space-y-1.5">
                    <div class="flex justify-between text-[13px]"><span class="text-navy-950/50">Total estimé</span><span class="font-medium" x-text="formatXOF(estimatedTotal())"></span></div>
                    <div class="flex justify-between text-[13px]"><span class="text-navy-950/50">Marge estimée</span><span class="font-medium text-sage-600" x-text="formatXOF(estimatedMargin())"></span></div>
                </div>

                <template x-if="error">
                    <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5 mb-4" x-text="error"></p>
                </template>

                <button @click="submitSale" :disabled="saving || cart.length === 0"
                        class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                    <span x-show="!saving">Enregistrer la vente</span>
                    <span x-show="saving">Enregistrement…</span>
                </button>
            </div>
        </div>

        {{-- Volet encaissement --}}
        <div x-show="paymentPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="paymentPanel = false"></div>
            <div x-show="paymentPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="font-display font-semibold text-[18px]">Encaisser <span x-text="payingSale ? payingSale.reference : ''"></span></h2>
                    <button @click="paymentPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <p class="text-[13px] text-navy-950/50 mb-6" x-text="'Solde restant dû : ' + formatXOF(payingSale ? payingSale.total_amount - (payingSale.amount_paid || 0) : 0)"></p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Montant encaissé</label>
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
                    <span x-show="!paymentSaving">Enregistrer l'encaissement</span>
                    <span x-show="paymentSaving">Enregistrement…</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        function salesPage() {
            return {
                sales: [], loading: true, availableProducts: [], campaigns: [], saleCampaignId: '', payNow: true, payMethod: 'especes', panelOpen: false, saving: false, error: '', cart: [],
                paymentPanel: false, paymentSaving: false, paymentError: '', payingSale: null, paymentForm: {},
                async load() {
                    this.loading = true;
                    const res = await apiFetch('/sales?per_page=50');
                    if (res && res.ok) { const data = await res.json(); this.sales = data.data; }
                    this.loading = false;
                },
                async loadProducts() {
                    const res = await apiFetch('/products?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.availableProducts = data.data; }
                    const campRes = await apiFetch('/campaigns');
                    if (campRes && campRes.ok) this.campaigns = await campRes.json();
                },
                resetForm() { this.error = ''; this.cart = [{ product_id: '', quantity: 1 }]; this.saleCampaignId = ''; this.payNow = true; this.payMethod = 'especes'; },
                productById(id) { return this.availableProducts.find(p => p.id === id); },
                estimatedTotal() {
                    return this.cart.reduce((sum, l) => { const p = this.productById(l.product_id); return sum + (p ? p.sale_price * (l.quantity || 0) : 0); }, 0);
                },
                estimatedMargin() {
                    return this.cart.reduce((sum, l) => { const p = this.productById(l.product_id); return sum + (p ? (p.sale_price - p.cost) * (l.quantity || 0) : 0); }, 0);
                },
                async submitSale() {
                    this.saving = true; this.error = '';
                    const items = this.cart.filter(l => l.product_id && l.quantity > 0)
                        .map(l => ({ product_id: l.product_id, quantity: l.quantity }));
                    if (items.length === 0) { this.error = 'Ajoute au moins une ligne valide.'; this.saving = false; return; }
                    const payload = { items };
                    if (this.saleCampaignId) payload.campaign_id = this.saleCampaignId;
                    if (this.payNow) { payload.pay_now = true; payload.payment_method = this.payMethod; }
                    const res = await apiFetch('/sales', { method: 'POST', body: JSON.stringify(payload) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur lors de la vente.'; this.saving = false; return; }
                    this.saving = false;
                    this.panelOpen = false;
                    this.load();
                    this.loadProducts();
                },
                async cancelSale(sale) {
                    const paid = parseFloat(sale.amount_paid || 0);
                    if (!confirm('Annuler la vente ' + sale.reference + ' ? Le stock sera remis' + (paid > 0 ? ' et ' + formatXOF(paid) + ' remboursé au client.' : '.'))) return;
                    const res = await apiFetch('/sales/' + sale.id + '/cancel', { method: 'POST' });
                    const data = res ? await res.json().catch(() => ({})) : {};
                    if (res && res.ok) { toast('Vente annulée'); this.load(); }
                    else toast(data.message || 'Annulation impossible', 'error');
                },
                async collectAll() {
                    if (!confirm('Encaisser en espèces toutes les ventes non payées ou partiellement payées ?')) return;
                    const res = await apiFetch('/sales/collect-all', { method: 'POST', body: JSON.stringify({ method: 'especes' }) });
                    const data = res ? await res.json() : {};
                    if (res && res.ok) { toast(data.count + ' vente(s) encaissée(s) : ' + formatXOF(data.total)); this.load(); }
                    else toast(data.message || 'Action refusée', 'error');
                },
                openPayment(sale) {
                    this.payingSale = sale;
                    this.paymentError = '';
                    this.paymentForm = { amount: sale.total_amount - (sale.amount_paid || 0), method: 'especes' };
                    this.paymentPanel = true;
                },
                async submitPayment() {
                    this.paymentSaving = true; this.paymentError = '';
                    const res = await apiFetch('/sales/' + this.payingSale.id + '/payments', { method: 'POST', body: JSON.stringify(this.paymentForm) });
                    const data = await res.json();
                    if (!res.ok) { this.paymentError = data.message || 'Erreur lors de l\'encaissement.'; this.paymentSaving = false; return; }
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
