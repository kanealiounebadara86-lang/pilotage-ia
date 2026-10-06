<x-app-layout title="Clients">

    <div x-data="customersPage()" x-init="load()" x-cloak class="space-y-5">

        <div class="flex items-center justify-between">
            <div class="relative w-full sm:w-80">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="absolute left-3 top-1/2 -translate-y-1/2 text-navy-950/35"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="text" x-model="search" @input.debounce.400ms="load()" placeholder="Rechercher un client…"
                       class="w-full pl-9 pr-3 py-2.5 rounded-lg border border-gray-300 bg-white text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40 focus:border-gold-500">
            </div>
            <button @click="panelOpen = true; resetForm()"
                    class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouveau client
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                        <th class="px-5 py-3 font-medium">Nom</th>
                        <th class="px-5 py-3 font-medium">Type</th>
                        <th class="px-5 py-3 font-medium">Téléphone</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="3" class="px-5 py-10 text-center text-navy-950/40">Chargement…</td></tr>
                    </template>
                    <template x-if="!loading && customers.length === 0">
                        <tr><td colspan="3" class="px-5 py-10 text-center text-navy-950/40">Aucun client pour le moment.</td></tr>
                    </template>
                    <template x-for="c in customers" :key="c.id">
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-paper/60 transition-colors">
                            <td class="px-5 py-3.5 font-medium" x-text="c.name"></td>
                            <td class="px-5 py-3.5 text-navy-950/60 capitalize" x-text="c.type"></td>
                            <td class="px-5 py-3.5 text-navy-950/60" x-text="c.phone || '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Volet création --}}
        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouveau client</h2>
                    <button @click="panelOpen = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="create" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Type</label>
                        <select x-model="form.type" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="particulier">Particulier</option>
                            <option value="entreprise">Entreprise</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Nom</label>
                        <input type="text" x-model="form.name" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Téléphone</label>
                        <input type="text" x-model="form.phone" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <template x-if="error">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>
                    </template>
                    <button type="submit" :disabled="saving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!saving">Créer le client</span>
                        <span x-show="saving">Création…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function customersPage() {
            return {
                customers: [], loading: true, search: '', panelOpen: false, saving: false, error: '', form: {},
                async load() {
                    this.loading = true;
                    const res = await apiFetch('/customers?search=' + encodeURIComponent(this.search) + '&per_page=50');
                    if (res && res.ok) { const data = await res.json(); this.customers = data.data; }
                    this.loading = false;
                },
                resetForm() { this.error = ''; this.form = { type: 'particulier', name: '', phone: '' }; },
                async create() {
                    this.saving = true; this.error = '';
                    const res = await apiFetch('/customers', { method: 'POST', body: JSON.stringify(this.form) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur lors de la création.'; this.saving = false; return; }
                    this.saving = false; this.panelOpen = false; this.load();
                }
            }
        }
    </script>
</x-app-layout>
