<x-app-layout title="Comptabilité">

    <div x-data="accountingPage()" x-init="load()" x-cloak class="space-y-6">

        {{-- Plan comptable --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <template x-for="a in accounts" :key="a.id">
                <button @click="openLedger(a.code)"
                        class="text-left bg-white rounded-xl border border-gray-200 p-4 hover:border-gold-500/40 transition-colors">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono text-[11.5px] text-navy-950/40" x-text="a.code"></span>
                        <span class="text-[10.5px] font-medium uppercase tracking-wide px-1.5 py-0.5 rounded"
                              :class="classBadge(a.class)" x-text="a.class"></span>
                    </div>
                    <p class="text-[13.5px] font-medium truncate" x-text="a.label"></p>
                    <p class="font-display text-[17px] font-semibold mt-1" :class="a.balance >= 0 ? 'text-navy-950' : 'text-rust-500'" x-text="formatXOF(a.balance)"></p>
                </button>
            </template>
        </div>

        {{-- Écritures récentes --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <h3 class="font-display font-semibold text-[15px]">Écritures récentes</h3>
            </div>
            <table class="w-full text-[13.5px]">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                        <th class="px-5 py-2.5 font-medium">Référence</th>
                        <th class="px-5 py-2.5 font-medium">Libellé</th>
                        <th class="px-5 py-2.5 font-medium">Date</th>
                        <th class="px-5 py-2.5 font-medium text-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="entries.length === 0">
                        <tr><td colspan="4" class="px-5 py-8 text-center text-navy-950/40">Aucune écriture pour le moment.</td></tr>
                    </template>
                    <template x-for="e in entries" :key="e.id">
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3 font-mono text-[12px] text-navy-950/60" x-text="e.reference"></td>
                            <td class="px-5 py-3" x-text="e.label"></td>
                            <td class="px-5 py-3 text-navy-950/50" x-text="new Date(e.entry_date).toLocaleDateString('fr-FR')"></td>
                            <td class="px-5 py-3 text-right font-medium" x-text="formatXOF(e.lines.reduce((s, l) => s + parseFloat(l.debit), 0))"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Volet grand livre --}}
        <div x-show="ledgerPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="ledgerPanel = false"></div>
            <div x-show="ledgerPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-lg bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="font-display font-semibold text-[18px]" x-text="ledger ? ledger.account.label : ''"></h2>
                    <button @click="ledgerPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <p class="text-[12px] text-navy-950/40 font-mono mb-6" x-text="ledger ? ledger.account.code : ''"></p>

                <template x-if="ledger">
                    <div class="space-y-2">
                        <template x-for="(l, i) in ledger.lines" :key="i">
                            <div class="flex items-center justify-between text-[13px] border-b border-gray-50 pb-2">
                                <div class="min-w-0">
                                    <p class="truncate" x-text="l.label"></p>
                                    <p class="text-[11px] text-navy-950/35" x-text="l.reference + ' · ' + new Date(l.date).toLocaleDateString('fr-FR')"></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span x-show="l.debit > 0" class="text-navy-950" x-text="'D ' + formatXOF(l.debit)"></span>
                                    <span x-show="l.credit > 0" class="text-gold-600" x-text="'C ' + formatXOF(l.credit)"></span>
                                </div>
                            </div>
                        </template>
                        <div class="pt-3 flex justify-between font-medium text-[14px]">
                            <span>Solde</span>
                            <span x-text="formatXOF(ledger.final_balance)"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function accountingPage() {
            return {
                accounts: [], entries: [], ledger: null, ledgerPanel: false,
                async load() {
                    const [aRes, eRes] = await Promise.all([apiFetch('/accounting/accounts'), apiFetch('/accounting/entries?per_page=15')]);
                    if (aRes && aRes.ok) this.accounts = await aRes.json();
                    if (eRes && eRes.ok) { const data = await eRes.json(); this.entries = data.data; }
                },
                async openLedger(code) {
                    const res = await apiFetch('/accounting/ledger/' + code);
                    if (res && res.ok) { this.ledger = await res.json(); this.ledgerPanel = true; }
                },
                classBadge(c) {
                    const map = {
                        charge: 'bg-rust-500/10 text-rust-600', produit: 'bg-sage-500/10 text-sage-600',
                        actif: 'bg-navy-800/10 text-navy-800', passif: 'bg-gold-500/15 text-gold-600',
                        tresorerie: 'bg-gray-100 text-navy-950/60',
                    };
                    return map[c] || map.tresorerie;
                }
            }
        }
    </script>
</x-app-layout>
