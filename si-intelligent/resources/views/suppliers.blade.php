<x-app-layout title="Fournisseurs">

    <div x-data="suppliersPage()" x-init="load()" x-cloak class="space-y-5">

        <div class="flex items-center justify-between">
            <p class="text-[13.5px] text-navy-950/50">Score pondéré : prix 30% · délai 25% · qualité 20% · fiabilité 25%.</p>
            <button @click="panelOpen = true; resetForm()"
                    class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Nouveau fournisseur
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-if="loading">
                <p class="text-[13px] text-navy-950/40 py-10 col-span-full text-center">Chargement…</p>
            </template>
            <template x-if="!loading && suppliers.length === 0">
                <p class="text-[13px] text-navy-950/40 py-10 col-span-full text-center">Aucun fournisseur pour le moment.</p>
            </template>
            <template x-for="s in suppliers" :key="s.id">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="font-display font-semibold text-[15px]" x-text="s.name"></p>
                            <p class="text-[12px] text-navy-950/40" x-text="s.contact_name || 'Contact non renseigné'"></p>
                        </div>
                        <div class="w-11 h-11 rounded-full flex items-center justify-center font-display font-semibold text-[13px] shrink-0"
                             :class="parseFloat(s.score_global) >= 70 ? 'bg-sage-500/10 text-sage-600' : (parseFloat(s.score_global) >= 40 ? 'bg-gold-500/15 text-gold-600' : 'bg-rust-500/10 text-rust-600')"
                             x-text="Math.round(s.score_global)"></div>
                    </div>
                    <p class="text-[12.5px] text-navy-950/50" x-text="s.phone || '—'"></p>
                </div>
            </template>
        </div>

        {{-- Volet création --}}
        <div x-show="panelOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="panelOpen = false"></div>
            <div x-show="panelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouveau fournisseur</h2>
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
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Contact</label>
                        <input type="text" x-model="form.contact_name" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Téléphone</label>
                        <input type="text" x-model="form.phone" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <template x-if="error">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>
                    </template>
                    <button type="submit" :disabled="saving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!saving">Créer le fournisseur</span>
                        <span x-show="saving">Création…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function suppliersPage() {
            return {
                suppliers: [], loading: true, panelOpen: false, saving: false, error: '', form: {},
                async load() {
                    this.loading = true;
                    const res = await apiFetch('/suppliers?per_page=50');
                    if (res && res.ok) { const data = await res.json(); this.suppliers = data.data; }
                    this.loading = false;
                },
                resetForm() { this.error = ''; this.form = { name: '', contact_name: '', phone: '' }; },
                async create() {
                    this.saving = true; this.error = '';
                    const res = await apiFetch('/suppliers', { method: 'POST', body: JSON.stringify(this.form) });
                    const data = await res.json();
                    if (!res.ok) { this.error = data.message || 'Erreur lors de la création.'; this.saving = false; return; }
                    this.saving = false; this.panelOpen = false; this.load();
                }
            }
        }
    </script>
</x-app-layout>
