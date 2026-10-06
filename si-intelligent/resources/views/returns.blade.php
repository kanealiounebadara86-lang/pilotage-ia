<x-app-layout title="Retours & SAV">

    <div x-data="returnsPage()" x-init="load(); loadSales()" x-cloak class="space-y-5">

        <div class="flex items-center justify-between">
            <p class="text-[13.5px] text-navy-950/50">Un retour remis en stock, ou un remboursement pour un produit défectueux.</p>
            <button @click="panelOpen = true; resetForm()" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouveau retour
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                        <th class="px-5 py-3 font-medium">Vente</th>
                        <th class="px-5 py-3 font-medium">Produit</th>
                        <th class="px-5 py-3 font-medium">Motif</th>
                        <th class="px-5 py-3 font-medium text-right">Remboursement</th>
                        <th class="px-5 py-3 font-medium">Remis en stock</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="returns.length === 0">
                        <tr><td colspan="5" class="px-5 py-10 text-center text-navy-950/40">Aucun retour enregistré.</td></tr>
                    </template>
                    <template x-for="r in returns" :key="r.id">
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3.5 font-mono text-[12.5px] text-navy-950/70" x-text="r.sale.reference"></td>
                            <td class="px-5 py-3.5" x-text="r.sale_item.product.name + ' ×' + r.quantity"></td>
                            <td class="px-5 py-3.5 text-navy-950/60" x-text="reasonLabel(r.reason)"></td>
                            <td class="px-5 py-3.5 text-right font-medium text-rust-500" x-text="formatXOF(r.refund_amount)"></td>
                            <td class="px-5 py-3.5" x-text="r.restocked ? 'Oui' : 'Non'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouveau retour</h2>
                    <button @click="panelOpen = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Ligne de vente</label>
                        <select x-model.number="form.sale_item_id" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="">Choisir…</option>
                            <template x-for="item in saleItems" :key="item.id">
                                <option :value="item.id" x-text="item.saleRef + ' — ' + item.product.name + ' (×' + item.quantity + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Quantité retournée</label>
                        <input type="number" x-model.number="form.quantity" min="1" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Motif</label>
                        <select x-model="form.reason" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="defectueux">Produit défectueux</option>
                            <option value="erreur_commande">Erreur de commande</option>
                            <option value="insatisfaction">Insatisfaction client</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Mode de remboursement (si le client avait déjà payé)</label>
                        <select x-model="form.refund_method" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px]">
                            <option value="especes">Espèces</option><option value="mobile_money">Mobile money</option>
                            <option value="carte">Carte</option><option value="virement">Virement</option><option value="cheque">Chèque</option>
                        </select>
                        <p class="text-[12px] text-navy-950/45 mt-1.5">Le chiffre d'affaires, la trésorerie, la comptabilité, le stock et les points fidélité sont mis à jour automatiquement.</p>
                    </div>
                    <label class="flex items-center gap-2 text-[13px]">
                        <input type="checkbox" x-model="form.restock" class="rounded border-gray-300">
                        Remettre le produit en stock
                    </label>
                    <template x-if="error">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>
                    </template>
                    <button type="submit" :disabled="saving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!saving">Enregistrer le retour</span>
                        <span x-show="saving">Enregistrement…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function returnsPage() {
            return {
                returns: [], saleItems: [], panelOpen: false, saving: false, error: '', form: {},
                async load() {
                    const res = await apiFetch('/sale-returns?per_page=30');
                    if (res && res.ok) { const data = await res.json(); this.returns = data.data; }
                },
                async loadSales() {
                    const res = await apiFetch('/sales?per_page=30');
                    if (res && res.ok) {
                        const data = await res.json();
                        this.saleItems = data.data.flatMap(s => s.items.map(i => ({ ...i, saleRef: s.reference })));
                    }
                },
                resetForm() { this.error = ''; this.form = { sale_item_id: '', quantity: 1, reason: 'defectueux', restock: true, refund_method: 'especes' }; },
                async submit() {
                    this.saving = true; this.error = '';
                    const res = await apiFetch('/sale-returns', { method: 'POST', body: JSON.stringify(this.form) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur.'; this.saving = false; return; }
                    this.saving = false; this.panelOpen = false; this.load();
                    toast(data.cash_refunded > 0 ? 'Retour enregistré — ' + formatXOF(data.cash_refunded) + ' remboursé au client' : 'Retour enregistré — créance client réduite');
                },
                reasonLabel(r) {
                    return { defectueux: 'Défectueux', erreur_commande: 'Erreur commande', insatisfaction: 'Insatisfaction', autre: 'Autre' }[r] || r;
                }
            }
        }
    </script>
</x-app-layout>
