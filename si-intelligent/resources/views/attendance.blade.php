<x-app-layout title="Pointage" subtitle="Pointez les présences en un clic, suivez l'historique et validez les heures supplémentaires.">
<div x-data="attendancePage()" x-init="init()" x-cloak class="space-y-5">

    {{-- Onglets de la page --}}
    <div class="flex items-center gap-2">
        <template x-for="t in [['today','Aujourd\'hui'],['history','Historique'],['overtime','Heures supplémentaires']]" :key="t[0]">
            <button @click="setView(t[0])" class="px-4 py-2 rounded-full text-[13px] font-medium border transition-colors"
                    :class="view === t[0] ? 'bg-navy-950 text-white border-transparent' : 'bg-white text-navy-950/60 border-gray-200 hover:border-gray-300'">
                <span x-text="t[1]"></span>
                <span x-show="t[0] === 'overtime' && pendingList.length" class="ml-1.5 px-1.5 py-0.5 rounded-full bg-gold-500/15 text-gold-600 text-[11px]" x-text="pendingList.length"></span>
            </button>
        </template>
    </div>

    <p x-show="error" class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>

    {{-- ================= AUJOURD'HUI ================= --}}
    <div x-show="view === 'today'" class="space-y-5">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <template x-for="k in todayKpis()" :key="k.label">
                <button type="button" @click="cardFilter = (cardFilter === k.key ? '' : k.key)"
                        class="text-left bg-white rounded-2xl border p-4 transition-all hover:-translate-y-0.5 hover:shadow-md"
                        :class="cardFilter === k.key && k.key !== '' ? 'border-gold-500 ring-2 ring-gold-500/20' : 'border-gray-200'">
                    <p class="text-[11.5px] font-medium text-navy-950/45 uppercase tracking-wide" x-text="k.label"></p>
                    <p class="font-display text-[24px] font-semibold mt-1" :class="k.cls" x-text="k.value"></p>
                    <p class="text-[11px] text-navy-950/35 mt-0.5" x-text="cardFilter === k.key && k.key !== '' ? 'Filtre actif — cliquer pour retirer' : 'Cliquer pour filtrer'"></p>
                </button>
            </template>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-wrap items-center gap-3">
            <div class="font-display text-[22px] font-semibold tabular-nums" x-text="clock"></div>
            <span class="text-[13px] text-navy-950/45 capitalize" x-text="longDate"></span>
            <div class="flex-1"></div>
            <input type="search" x-model="search" placeholder="Rechercher un employé…" class="w-52 px-3 py-2 text-[13px] border">
            <select x-model="deptFilter" class="px-3 py-2 text-[13px] border">
                <option value="">Tous les départements</option>
                <template x-for="d in departments()" :key="d"><option :value="d" x-text="d"></option></template>
            </select>
            <select x-model="stateFilter" class="px-3 py-2 text-[13px] border">
                <option value="">Tous les états</option><option value="non_pointe">Non pointés</option>
                <option value="present">Présents</option><option value="parti">Partis</option><option value="conge">En congé</option>
            </select>
            <button @click="punchAll()" :disabled="busyAll || notPunched() === 0"
                    class="bg-navy-950 disabled:opacity-50 text-white text-[13px] font-medium px-4 py-2.5">Pointer l'arrivée de tous (<span x-text="notPunched()"></span>)</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="e in filteredToday()" :key="e.employee_id">
                <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-gold-500/10 text-gold-600 flex items-center justify-center font-display font-semibold text-[14px]" x-text="initials(e.name)"></div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-[14px] truncate" x-text="e.name"></p>
                            <p class="text-[12px] text-navy-950/45 truncate" x-text="[e.position, e.department].filter(Boolean).join(' · ') || '—'"></p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11.5px] font-medium" :class="stateBadge(e).cls" x-text="stateBadge(e).label"></span>
                    </div>
                    <p x-show="e.state === 'conge'" class="text-[12.5px] text-blue-600 bg-blue-50 rounded-lg px-3 py-2">
                        En congé<span x-show="e.leave_until"> jusqu'au <span x-text="e.leave_until"></span></span> — pointage impossible.
                    </p>
                    <div x-show="e.state !== 'conge'" class="grid grid-cols-3 gap-2 text-center bg-gray-50 rounded-xl py-2.5">
                        <div><p class="text-[10.5px] uppercase text-navy-950/40">Arrivée</p><p class="text-[14px] font-semibold tabular-nums" x-text="e.clock_in || '—'"></p></div>
                        <div><p class="text-[10.5px] uppercase text-navy-950/40">Départ</p><p class="text-[14px] font-semibold tabular-nums" x-text="e.clock_out || '—'"></p></div>
                        <div><p class="text-[10.5px] uppercase text-navy-950/40">Heures</p><p class="text-[14px] font-semibold tabular-nums" x-text="e.state === 'parti' ? e.hours_worked + ' h' : '—'"></p></div>
                    </div>
                    <button @click="punch(e)" :disabled="e.state === 'conge' || e.state === 'parti' || busy === e.employee_id"
                            class="w-full text-[13.5px] font-medium py-2.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="e.state === 'present' ? 'bg-sage-500 hover:bg-sage-600 text-white' : (e.state === 'non_pointe' ? 'bg-navy-950 text-white' : 'bg-gray-100 text-navy-950/50')"
                            x-text="busy === e.employee_id ? '…' : (e.state === 'non_pointe' ? 'Pointer l\'arrivée' : e.state === 'present' ? 'Pointer le départ' : e.state === 'parti' ? 'Journée terminée' : 'En congé')"></button>
                </div>
            </template>
            <p x-show="!filteredToday().length" class="col-span-full text-center py-10 text-[13.5px] text-navy-950/40">Aucun employé à afficher.</p>
        </div>
    </div>

    {{-- ================= HISTORIQUE ================= --}}
    <div x-show="view === 'history'" class="space-y-5">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[12px] font-medium text-navy-950/60 mb-1.5">Employé</label>
                <select x-model="hEmployee" @change="loadHistory()" class="px-3 py-2 text-[13px] border w-52">
                    <option value="">Tous</option>
                    <template x-for="e in today" :key="e.employee_id"><option :value="e.employee_id" x-text="e.name"></option></template>
                </select>
            </div>
            <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                <template x-for="m in [['week','Semaine'],['month','Mois'],['custom','Personnalisé']]" :key="m[0]">
                    <button @click="hMode = m[0]; hOffset = 0; loadHistory()" class="px-3.5 py-2 text-[13px] font-medium"
                            :class="hMode === m[0] ? 'bg-navy-950 text-white' : 'bg-white text-navy-950/60 hover:bg-gray-50'" x-text="m[1]"></button>
                </template>
            </div>
            <div x-show="hMode !== 'custom'" class="flex items-center gap-1">
                <button @click="hOffset--; loadHistory()" class="w-9 h-9 rounded-lg border border-gray-200 hover:bg-gray-50">‹</button>
                <span class="px-3 text-[13px] font-medium min-w-[170px] text-center" x-text="rangeLabel()"></span>
                <button @click="hOffset++; loadHistory()" :disabled="hOffset >= 0" class="w-9 h-9 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-40">›</button>
            </div>
            <div x-show="hMode === 'custom'" class="flex items-center gap-2">
                <input type="date" x-model="hFrom" @change="loadHistory()" class="px-3 py-2 text-[13px] border">
                <span class="text-navy-950/40">→</span>
                <input type="date" x-model="hTo" @change="loadHistory()" class="px-3 py-2 text-[13px] border">
            </div>
            <div class="flex-1"></div>
            <button @click="manualOpen = true" class="bg-navy-950 text-white text-[13px] font-medium px-4 py-2.5">Saisie manuelle</button>
        </div>

        {{-- Récapitulatif par employé --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-display font-semibold text-[15px]">Récapitulatif par employé</h3></div>
            <div class="overflow-x-auto"><table class="w-full text-[13.5px]">
                <thead><tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                    <th class="px-5 py-3 font-medium">Employé</th><th class="px-5 py-3 font-medium text-right">Jours présent</th><th class="px-5 py-3 font-medium text-right">Retards</th>
                    <th class="px-5 py-3 font-medium text-right">Congés</th><th class="px-5 py-3 font-medium text-right">Heures</th><th class="px-5 py-3 font-medium text-right">H. sup validées</th><th class="px-5 py-3 font-medium text-right">H. sup en attente</th>
                </tr></thead>
                <tbody>
                    <template x-for="r in summary" :key="r.employee_id">
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3 font-medium" x-text="r.employee_name"></td>
                            <td class="px-5 py-3 text-right" x-text="r.days_present"></td>
                            <td class="px-5 py-3 text-right" :class="r.days_late ? 'text-rust-500 font-medium' : ''" x-text="r.days_late"></td>
                            <td class="px-5 py-3 text-right" x-text="r.days_leave"></td>
                            <td class="px-5 py-3 text-right" x-text="r.total_hours + ' h'"></td>
                            <td class="px-5 py-3 text-right font-medium" x-text="r.overtime_hours + ' h'"></td>
                            <td class="px-5 py-3 text-right text-gold-600" x-text="r.overtime_pending ? r.overtime_pending + ' h' : '—'"></td>
                        </tr>
                    </template>
                    <tr x-show="!summary.length"><td colspan="7" class="px-5 py-10 text-center text-navy-950/40">Aucun pointage sur cette période.</td></tr>
                </tbody>
            </table></div>
        </div>

        {{-- Détail des pointages --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-display font-semibold text-[15px]">Détail des pointages</h3></div>
            <div class="overflow-x-auto"><table class="w-full text-[13.5px]">
                <thead><tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                    <th class="px-5 py-3 font-medium">Date</th><th class="px-5 py-3 font-medium">Employé</th><th class="px-5 py-3 font-medium">Arrivée</th><th class="px-5 py-3 font-medium">Départ</th>
                    <th class="px-5 py-3 font-medium text-right">Heures</th><th class="px-5 py-3 font-medium text-right">H. sup</th><th class="px-5 py-3 font-medium">Statut</th>
                </tr></thead>
                <tbody>
                    <template x-for="a in records" :key="a.id">
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3" x-text="new Date(a.date).toLocaleDateString('fr-FR', {weekday:'short', day:'numeric', month:'short'})"></td>
                            <td class="px-5 py-3 font-medium" x-text="a.employee ? a.employee.first_name + ' ' + a.employee.last_name : '—'"></td>
                            <td class="px-5 py-3 tabular-nums" x-text="(a.clock_in || '—').slice(0,5)"></td>
                            <td class="px-5 py-3 tabular-nums" x-text="(a.clock_out || '—').slice(0,5)"></td>
                            <td class="px-5 py-3 text-right" x-text="a.hours_worked > 0 ? a.hours_worked + ' h' : '—'"></td>
                            <td class="px-5 py-3 text-right" x-text="a.overtime_hours > 0 ? a.overtime_hours + ' h' : '—'"></td>
                            <td class="px-5 py-3"><span class="px-2.5 py-1 rounded-full text-[11.5px] font-medium" :class="statusBadge(a.status)" x-text="{present:'Présent',retard:'Retard',absent:'Absent',conge:'Congé'}[a.status]"></span><span x-show="a.note" class="ml-2 text-[12px] text-navy-950/40" x-text="a.note"></span></td>
                        </tr>
                    </template>
                    <tr x-show="!records.length"><td colspan="7" class="px-5 py-10 text-center text-navy-950/40">Aucun pointage.</td></tr>
                </tbody>
            </table></div>
        </div>
    </div>

    {{-- ================= HEURES SUP ================= --}}
    <div x-show="view === 'overtime'" class="space-y-4">
        <div class="bg-gold-500/5 border border-gold-500/20 rounded-xl px-4 py-3 text-[13px] text-navy-950/70">
            Le système <strong>suggère</strong> les heures au-delà de 8 h/jour. Seules les heures <strong>validées par la RH</strong> sont payées sur le bulletin. Ajustez le nombre d'heures si besoin, puis validez (0 pour refuser).
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto"><table class="w-full text-[13.5px]">
                <thead><tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                    <th class="px-5 py-3 font-medium">Date</th><th class="px-5 py-3 font-medium">Employé</th><th class="px-5 py-3 font-medium">Horaires</th>
                    <th class="px-5 py-3 font-medium text-right">Suggérées</th><th class="px-5 py-3 font-medium text-right">À valider</th><th class="px-5 py-3"></th>
                </tr></thead>
                <tbody>
                    <template x-for="a in pendingList" :key="a.id">
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3" x-text="new Date(a.date).toLocaleDateString('fr-FR')"></td>
                            <td class="px-5 py-3 font-medium" x-text="a.employee ? a.employee.first_name + ' ' + a.employee.last_name : '—'"></td>
                            <td class="px-5 py-3 tabular-nums" x-text="(a.clock_in||'').slice(0,5) + ' → ' + (a.clock_out||'').slice(0,5)"></td>
                            <td class="px-5 py-3 text-right" x-text="a.overtime_suggested + ' h'"></td>
                            <td class="px-5 py-3 text-right"><input type="number" step="0.25" min="0" max="16" x-model.number="a._hours" class="w-20 px-2.5 py-1.5 text-[13px] text-center border"></td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <button @click="validateOvertime(a)" class="bg-navy-950 text-white text-[12.5px] font-medium px-3.5 py-2">Valider</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!pendingList.length"><td colspan="6" class="px-5 py-10 text-center text-navy-950/40">Aucune heure supplémentaire en attente de validation.</td></tr>
                </tbody>
            </table></div>
        </div>
    </div>

    {{-- ================= Saisie manuelle ================= --}}
    <div x-show="manualOpen" x-cloak class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="manualOpen = false"></div>
        <div class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-display font-semibold text-[18px]">Saisie manuelle</h2>
                <button @click="manualOpen = false" class="text-navy-950/40 hover:text-navy-950">✕</button>
            </div>
            <p class="text-[12.5px] text-navy-950/50 mb-4">Pour corriger ou ajouter un pointage oublié sur une date passée.</p>
            <form @submit.prevent="saveManual()" class="space-y-4">
                <div><label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Employé</label>
                    <select x-model.number="manual.employee_id" required class="w-full px-3.5 py-2.5 text-[13.5px] border"><option value="">Choisir…</option>
                        <template x-for="e in today" :key="e.employee_id"><option :value="e.employee_id" x-text="e.name"></option></template></select></div>
                <div><label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Date</label><input type="date" x-model="manual.date" required class="w-full px-3.5 py-2.5 text-[13.5px] border"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Arrivée</label><input type="time" x-model="manual.clock_in" class="w-full px-3.5 py-2.5 text-[13.5px] border"></div>
                    <div><label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Départ</label><input type="time" x-model="manual.clock_out" class="w-full px-3.5 py-2.5 text-[13.5px] border"></div>
                </div>
                <button type="submit" class="w-full bg-navy-950 text-white text-[14px] font-medium py-2.5">Enregistrer</button>
            </form>
        </div>
    </div>
</div>

<script>
function attendancePage() {
    const iso = d => { const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return z.toISOString().slice(0, 10); };
    return {
        view: 'today', error: '', today: [], serverDate: '', clock: '--:--:--', longDate: '',
        search: '', deptFilter: '', stateFilter: '', cardFilter: '', busy: null, busyAll: false,
        hEmployee: '', hMode: 'week', hOffset: 0, hFrom: iso(new Date()), hTo: iso(new Date()),
        records: [], summary: [], pendingList: [], manualOpen: false,
        manual: { employee_id: '', date: iso(new Date()), clock_in: '', clock_out: '' },

        async init() {
            this.tick(); setInterval(() => this.tick(), 1000);
            await this.loadToday();
            await Promise.all([this.loadHistory(), this.loadPending()]);
        },
        tick() {
            const n = new Date();
            this.clock = n.toLocaleTimeString('fr-FR');
            this.longDate = n.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },
        setView(v) { this.view = v; if (v === 'history') this.loadHistory(); if (v === 'overtime') this.loadPending(); },

        // ----- Aujourd'hui -----
        async loadToday() {
            const res = await apiFetch('/attendances/today');
            if (res && res.ok) { const d = await res.json(); this.today = d.employees; this.serverDate = d.date; }
        },
        departments() { return [...new Set(this.today.map(e => e.department).filter(Boolean))]; },
        filteredToday() {
            const q = this.search.trim().toLowerCase();
            const card = {
                '': () => true,
                present: e => e.state === 'present' || e.state === 'parti',
                late: e => e.late && e.state !== 'conge',
                conge: e => e.state === 'conge',
                non_pointe: e => e.state === 'non_pointe',
            }[this.cardFilter] || (() => true);
            return this.today.filter(e => card(e) && (!q || e.name.toLowerCase().includes(q)) && (!this.deptFilter || e.department === this.deptFilter) && (!this.stateFilter || e.state === this.stateFilter));
        },
        notPunched() { return this.today.filter(e => e.state === 'non_pointe').length; },
        todayKpis() {
            const c = s => this.today.filter(e => e.state === s).length;
            return [
                { key: '', label: 'Effectif', value: this.today.length },
                { key: 'present', label: 'Présents', value: c('present') + c('parti'), cls: 'text-sage-600' },
                { key: 'late', label: 'En retard', value: this.today.filter(e => e.late && e.state !== 'conge').length, cls: this.today.some(e => e.late && e.state !== 'conge') ? 'text-rust-500' : '' },
                { key: 'conge', label: 'En congé', value: c('conge'), cls: c('conge') ? 'text-blue-600' : '' },
                { key: 'non_pointe', label: 'Non pointés', value: c('non_pointe'), cls: c('non_pointe') ? 'text-gold-600' : '' },
            ];
        },
        initials(n) { return n.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase(); },
        stateBadge(e) {
            if (e.state === 'conge') return { label: 'En congé', cls: 'bg-blue-50 text-blue-600' };
            if (e.state === 'parti') return { label: 'Parti', cls: 'bg-gray-100 text-navy-950/60' };
            if (e.state === 'present') return e.late ? { label: 'Présent · retard', cls: 'bg-rust-500/10 text-rust-600' } : { label: 'Présent', cls: 'bg-sage-500/10 text-sage-600' };
            return { label: 'Non pointé', cls: 'bg-gold-500/10 text-gold-600' };
        },
        statusBadge(s) { return { present: 'bg-sage-500/10 text-sage-600', retard: 'bg-rust-500/10 text-rust-600', absent: 'bg-gray-100 text-navy-950/60', conge: 'bg-blue-50 text-blue-600' }[s] || ''; },

        async punch(e) {
            this.error = ''; this.busy = e.employee_id;
            const res = await apiFetch('/attendances/punch', { method: 'POST', body: JSON.stringify({ employee_id: e.employee_id }) });
            this.busy = null;
            if (res && res.ok) {
                const d = await res.json();
                toast(e.name + (d.action === 'arrivee' ? ' : arrivée pointée' : ' : départ pointé'));
                await this.loadToday(); this.loadPending();
            } else if (res) { const d = await res.json().catch(() => ({})); this.error = d.message || 'Pointage impossible.'; toast(this.error, 'error'); }
        },
        async punchAll() {
            if (!confirm('Pointer l\'arrivée de tous les employés non pointés (hors congés) ?')) return;
            this.busyAll = true;
            const res = await apiFetch('/attendances/punch-all', { method: 'POST' });
            this.busyAll = false;
            if (res && res.ok) { const d = await res.json(); toast(d.punched + ' arrivée(s) pointée(s)'); await this.loadToday(); }
            else if (res) toast('Action refusée', 'error');
        },

        // ----- Historique -----
        range() {
            const now = new Date();
            if (this.hMode === 'week') {
                const day = now.getDay() || 7;
                const mon = new Date(now); mon.setDate(now.getDate() - day + 1 + this.hOffset * 7);
                const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
                return { from: iso(mon), to: iso(sun) };
            }
            if (this.hMode === 'month') {
                const first = new Date(now.getFullYear(), now.getMonth() + this.hOffset, 1);
                const last = new Date(first.getFullYear(), first.getMonth() + 1, 0);
                return { from: iso(first), to: iso(last) };
            }
            return { from: this.hFrom, to: this.hTo };
        },
        rangeLabel() {
            const r = this.range(), f = d => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
            return this.hMode === 'month' ? new Date(r.from).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }) : f(r.from) + ' → ' + f(r.to);
        },
        async loadHistory() {
            const r = this.range();
            const p = new URLSearchParams({ date_from: r.from, date_to: r.to, per_page: 200 });
            if (this.hEmployee) p.set('employee_id', this.hEmployee);
            const [a, s] = await Promise.all([apiFetch('/attendances?' + p), apiFetch('/attendances/summary?date_from=' + r.from + '&date_to=' + r.to)]);
            if (a && a.ok) this.records = (await a.json()).data;
            if (s && s.ok) { const d = await s.json(); this.summary = this.hEmployee ? d.employees.filter(x => x.employee_id == this.hEmployee) : d.employees; }
        },

        // ----- Heures sup -----
        async loadPending() {
            const from = iso(new Date(Date.now() - 62 * 86400000));
            const res = await apiFetch('/attendances?date_from=' + from + '&per_page=500');
            if (res && res.ok) {
                this.pendingList = (await res.json()).data
                    .filter(a => parseFloat(a.overtime_suggested) > 0 && !a.overtime_validated_at)
                    .map(a => ({ ...a, _hours: parseFloat(a.overtime_suggested) }));
            }
        },
        async validateOvertime(a) {
            const res = await apiFetch('/attendances/' + a.id + '/overtime', { method: 'PATCH', body: JSON.stringify({ overtime_hours: a._hours || 0 }) });
            if (res && res.ok) { toast('Heures supplémentaires validées'); await this.loadPending(); this.loadHistory(); }
            else toast('Validation impossible', 'error');
        },

        // ----- Saisie manuelle -----
        async saveManual() {
            const body = { ...this.manual, clock_in: this.manual.clock_in || null, clock_out: this.manual.clock_out || null };
            const res = await apiFetch('/attendances', { method: 'POST', body: JSON.stringify(body) });
            if (res && res.ok) { this.manualOpen = false; toast('Pointage enregistré'); await this.loadToday(); this.loadHistory(); this.loadPending(); }
            else if (res) { const d = await res.json().catch(() => ({})); toast(d.message || 'Données invalides', 'error'); }
        },
    };
}
</script>
</x-app-layout>
