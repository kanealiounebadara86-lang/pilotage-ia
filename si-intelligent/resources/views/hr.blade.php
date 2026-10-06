<x-app-layout title="Ressources humaines">

    <div x-data="hrPage()" x-init="loadAll()" x-cloak class="space-y-5">

        {{-- Onglets --}}
        <div class="flex items-center gap-1 border-b border-gray-200 overflow-x-auto">
            <template x-for="t in ['employes', 'conges', 'avances', 'paie']" :key="t">
                <button @click="tab = t"
                        class="px-4 py-2.5 text-[13.5px] font-medium border-b-2 -mb-px transition-colors whitespace-nowrap"
                        :class="tab === t ? 'border-navy-950 text-navy-950' : 'border-transparent text-navy-950/40 hover:text-navy-950/70'"
                        x-text="{ employes: 'Employés', conges: 'Congés', avances: 'Avances', paie: 'Paie' }[t]"></button>
            </template>
            <a href="{{ route('attendance.index') }}" class="ml-auto px-4 py-2 text-[13px] font-medium text-gold-600 hover:underline whitespace-nowrap">Pointage &amp; heures sup →</a>
        </div>

        {{-- ============ EMPLOYÉS ============ --}}
        <div x-show="tab === 'employes'" class="space-y-4">
            <div class="flex justify-between items-center">
                <button @click="deptPanel = true" class="text-[12.5px] font-medium text-navy-950/50 hover:text-navy-950 flex items-center gap-1.5">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Gérer les départements
                </button>
                <button @click="empPanel = true; resetEmpForm()" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Nouvel employé
                </button>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-[13.5px]">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Poste</th>
                            <th class="px-5 py-3 font-medium">Département</th>
                            <th class="px-5 py-3 font-medium text-right">Salaire de base</th>
                            <th class="px-5 py-3 font-medium text-right">Solde congés</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="employees.length === 0">
                            <tr><td colspan="5" class="px-5 py-8 text-center text-navy-950/40">Aucun employé pour le moment.</td></tr>
                        </template>
                        <template x-for="e in employees" :key="e.id">
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-5 py-3 font-medium" x-text="e.first_name + ' ' + e.last_name"></td>
                                <td class="px-5 py-3 text-navy-950/60" x-text="e.position"></td>
                                <td class="px-5 py-3 text-navy-950/60" x-text="e.department ? e.department.name : '—'"></td>
                                <td class="px-5 py-3 text-right" x-text="formatXOF(e.base_salary)"></td>
                                <td class="px-5 py-3 text-right" x-text="e.leave_balance_days + ' j'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ CONGÉS ============ --}}
        <div x-show="tab === 'conges'" class="space-y-4">
            <div class="flex justify-end">
                <button @click="leavePanel = true; resetLeaveForm()" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Nouvelle demande
                </button>
            </div>
            <div class="space-y-2.5">
                <template x-if="leaves.length === 0">
                    <p class="text-[13px] text-navy-950/40 py-8 text-center">Aucune demande de congé.</p>
                </template>
                <template x-for="l in leaves" :key="l.id">
                    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center justify-between">
                        <div>
                            <p class="text-[13.5px] font-medium" x-text="l.employee.first_name + ' ' + l.employee.last_name"></p>
                            <p class="text-[12px] text-navy-950/40" x-text="l.type + ' · ' + new Date(l.start_date).toLocaleDateString('fr-FR') + ' → ' + new Date(l.end_date).toLocaleDateString('fr-FR')"></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-medium" :class="leaveStatusStyle(l.status)" x-text="l.status"></span>
                            <template x-if="l.status === 'demandee'">
                                <div class="flex items-center gap-1.5">
                                    <button @click="reviewLeave(l.id, 'approuvee')" class="text-[12px] font-medium text-sage-600 hover:text-sage-700">Approuver</button>
                                    <button @click="reviewLeave(l.id, 'refusee')" class="text-[12px] font-medium text-rust-500 hover:text-rust-600">Refuser</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ============ AVANCES ============ --}}
        <div x-show="tab === 'avances'" class="space-y-4">
            <div class="flex justify-end">
                <button @click="advPanel = true; resetAdvForm()" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-lg transition-colors">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Nouvelle avance
                </button>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-[13.5px]">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-[11.5px] uppercase tracking-wide text-navy-950/40">
                            <th class="px-5 py-3 font-medium">Employé</th>
                            <th class="px-5 py-3 font-medium text-right">Montant</th>
                            <th class="px-5 py-3 font-medium text-right">Mensualité</th>
                            <th class="px-5 py-3 font-medium text-right">Solde restant</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="advances.length === 0">
                            <tr><td colspan="5" class="px-5 py-8 text-center text-navy-950/40">Aucune avance accordée.</td></tr>
                        </template>
                        <template x-for="a in advances" :key="a.id">
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-5 py-3" x-text="a.employee.first_name + ' ' + a.employee.last_name"></td>
                                <td class="px-5 py-3 text-right" x-text="formatXOF(a.amount)"></td>
                                <td class="px-5 py-3 text-right" x-text="formatXOF(a.monthly_installment)"></td>
                                <td class="px-5 py-3 text-right font-medium" x-text="formatXOF(a.remaining_amount)"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-medium" :class="a.status === 'soldee' ? 'bg-sage-500/10 text-sage-600' : 'bg-gold-500/15 text-gold-600'" x-text="a.status === 'soldee' ? 'Soldée' : 'En cours'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ PAIE ============ --}}
        <div x-show="tab === 'paie'" class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <input type="month" x-model="newPeriod" class="px-3 py-2 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    <button @click="generatePayroll" :disabled="payrollSaving" class="inline-flex items-center gap-2 bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[13px] font-medium px-3.5 py-2 rounded-lg transition-colors">
                        Générer la paie
                    </button>
                </div>
            </div>
            <template x-if="payrollError">
                <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="payrollError"></p>
            </template>
            <div class="space-y-3">
                <template x-for="run in payrollRuns" :key="run.id">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <p class="font-display font-semibold text-[15px]" x-text="run.period"></p>
                                <p class="text-[12px] text-navy-950/40" x-text="run.items.length + ' bulletin(s)'"></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-display font-semibold text-[16px]" x-text="formatXOF(run.total_amount)"></span>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-medium"
                                      :class="run.status === 'payee' ? 'bg-sage-500/10 text-sage-600' : (run.status === 'validee' ? 'bg-blue-50 text-blue-600' : 'bg-gold-500/15 text-gold-600')"
                                      x-text="{ brouillon: 'Brouillon', validee: 'Validée — à payer', payee: 'Payée' }[run.status]"></span>
                                <select x-show="run.status === 'validee'" x-model="run._method" class="px-2 py-1 text-[12px] border rounded-lg">
                                    <option value="especes">Espèces</option><option value="virement">Virement</option><option value="mobile_money">Mobile money</option><option value="cheque">Chèque</option>
                                </select>
                                <button x-show="run.status === 'validee'" @click="payPayroll(run)" class="text-[12.5px] font-medium text-sage-600 hover:text-sage-700">Payer</button>
                                <button x-show="run.status === 'brouillon'" @click="validatePayroll(run.id)" class="text-[12.5px] font-medium text-gold-600 hover:text-gold-700">Valider</button>
                                <button x-show="run.status === 'brouillon'" @click="deletePayroll(run.id)" class="text-[12.5px] font-medium text-rust-500 hover:text-rust-600">Supprimer</button>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 pt-3 space-y-2">
                            <template x-for="item in run.items" :key="item.id">
                                <div class="flex items-center justify-between text-[12.5px]">
                                    <span x-text="item.employee.first_name + ' ' + item.employee.last_name"></span>
                                    <div class="flex items-center gap-3 text-navy-950/50">
                                        <span x-show="item.overtime_amount > 0" class="text-gold-600" x-text="'+ HS ' + formatXOF(item.overtime_amount)"></span>
                                        <span x-show="item.unpaid_leave_deduction > 0" class="text-rust-500" x-text="'- Absence ' + formatXOF(item.unpaid_leave_deduction)"></span>
                                        <span x-show="item.advance_deduction > 0" class="text-rust-500" x-text="'- Avance ' + formatXOF(item.advance_deduction)"></span>
                                        <span class="font-medium text-navy-950" x-text="formatXOF(item.net_amount)"></span>
                                        <button @click="printPayslip(run, item)" class="text-navy-950/40 hover:text-navy-950" title="Imprimer le bulletin">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M6 9V3h12v6M6 18H4a1 1 0 01-1-1v-6a1 1 0 011-1h16a1 1 0 011 1v6a1 1 0 01-1 1h-2M6 14h12v7H6v-7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ===== Volets ===== --}}

        <div x-show="empPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="empPanel = false"></div>
            <div x-show="empPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvel employé</h2>
                    <button @click="empPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="createEmployee" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Prénom</label>
                            <input type="text" x-model="empForm.first_name" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Nom</label>
                            <input type="text" x-model="empForm.last_name" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Poste</label>
                        <input type="text" x-model="empForm.position" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Département</label>
                        <select x-model.number="empForm.department_id" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="">Aucun</option>
                            <template x-for="d in departments" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Date d'embauche</label>
                            <input type="date" x-model="empForm.hire_date" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Salaire de base</label>
                            <input type="number" x-model="empForm.base_salary" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>
                    <template x-if="empError">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="empError"></p>
                    </template>
                    <button type="submit" :disabled="empSaving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!empSaving">Créer l'employé</span>
                        <span x-show="empSaving">Création…</span>
                    </button>
                </form>
            </div>
        </div>

        <div x-show="deptPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="deptPanel = false"></div>
            <div x-show="deptPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-sm bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Départements</h2>
                    <button @click="deptPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <div class="space-y-2 mb-5">
                    <template x-for="d in departments" :key="d.id">
                        <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-paper text-[13.5px]">
                            <span x-text="d.name"></span>
                            <span class="text-navy-950/40 text-[12px]" x-text="d.employees_count + ' employé(s)'"></span>
                        </div>
                    </template>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="newDeptName" placeholder="Nom du département" class="flex-1 px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    <button @click="createDepartment" class="bg-navy-950 hover:bg-navy-900 text-white text-[13px] font-medium px-3.5 py-2 rounded-lg transition-colors">Ajouter</button>
                </div>
            </div>
        </div>

        <div x-show="leavePanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="leavePanel = false"></div>
            <div x-show="leavePanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvelle demande de congé</h2>
                    <button @click="leavePanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="createLeave" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Employé</label>
                        <select x-model.number="leaveForm.employee_id" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="">Choisir…</option>
                            <template x-for="e in employees" :key="e.id">
                                <option :value="e.id" x-text="e.first_name + ' ' + e.last_name + ' (' + e.leave_balance_days + ' j restants)'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Type</label>
                        <select x-model="leaveForm.type" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="conges_payes">Congés payés</option>
                            <option value="maladie">Maladie</option>
                            <option value="sans_solde">Sans solde</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Du</label>
                            <input type="date" x-model="leaveForm.start_date" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Au</label>
                            <input type="date" x-model="leaveForm.end_date" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>
                    <template x-if="leaveError">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="leaveError"></p>
                    </template>
                    <button type="submit" :disabled="leaveSaving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!leaveSaving">Envoyer la demande</span>
                        <span x-show="leaveSaving">Envoi…</span>
                    </button>
                </form>
            </div>
        </div>

        <div x-show="advPanel" x-cloak class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="advPanel = false"></div>
            <div x-show="advPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 class="relative w-full max-w-md bg-white h-full shadow-xl p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display font-semibold text-[18px]">Nouvelle avance sur salaire</h2>
                    <button @click="advPanel = false" class="text-navy-950/40 hover:text-navy-950">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form @submit.prevent="createAdvance" class="space-y-4">
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Employé</label>
                        <select x-model.number="advForm.employee_id" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                            <option value="">Choisir…</option>
                            <template x-for="e in employees" :key="e.id"><option :value="e.id" x-text="e.first_name + ' ' + e.last_name"></option></template>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Montant</label>
                            <input type="number" x-model.number="advForm.amount" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Mensualité</label>
                            <input type="number" x-model.number="advForm.monthly_installment" required class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Motif</label>
                        <input type="text" x-model="advForm.reason" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40">
                    </div>
                    <template x-if="advError">
                        <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="advError"></p>
                    </template>
                    <button type="submit" :disabled="advSaving" class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors">
                        <span x-show="!advSaving">Accorder l'avance</span>
                        <span x-show="advSaving">Enregistrement…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function hrPage() {
            return {
                tab: 'employes',
                employees: [], departments: [], attendances: [], leaves: [], advances: [], payrollRuns: [],

                empPanel: false, empSaving: false, empError: '', empForm: {},
                deptPanel: false, newDeptName: '',
                leavePanel: false, leaveSaving: false, leaveError: '', leaveForm: {},
                attSaving: false, attError: '', attForm: {},
                attFilterEmployee: '', periodFilter: 'month', attSummary: [],
                advPanel: false, advSaving: false, advError: '', advForm: {},
                newPeriod: new Date().toISOString().slice(0, 7), payrollSaving: false, payrollError: '',

                async loadAll() {
                    await Promise.all([
                        this.loadEmployees(), this.loadDepartments(), this.loadAttendances(),
                        this.loadAttendanceSummary(), this.loadLeaves(), this.loadAdvances(), this.loadPayroll(),
                    ]);
                    this.resetAttForm();
                },

                async loadEmployees() {
                    const res = await apiFetch('/employees?per_page=100');
                    if (res && res.ok) { const data = await res.json(); this.employees = data.data; }
                },
                async loadDepartments() {
                    const res = await apiFetch('/departments');
                    if (res && res.ok) this.departments = await res.json();
                },
                async loadAttendances() {
                    const params = new URLSearchParams({ per_page: 50 });
                    if (this.attFilterEmployee) params.set('employee_id', this.attFilterEmployee);
                    const range = this.periodRange();
                    if (range) { params.set('date_from', range.from); params.set('date_to', range.to); }
                    const res = await apiFetch('/attendances?' + params.toString());
                    if (res && res.ok) { const data = await res.json(); this.attendances = data.data; }
                },
                async loadAttendanceSummary() {
                    const params = new URLSearchParams();
                    const range = this.periodRange();
                    if (range) { params.set('date_from', range.from); params.set('date_to', range.to); }
                    const res = await apiFetch('/attendances/summary?' + params.toString());
                    if (res && res.ok) {
                        const data = await res.json();
                        this.attSummary = this.attFilterEmployee
                            ? data.employees.filter(e => e.employee_id == this.attFilterEmployee)
                            : data.employees;
                    }
                },
                periodRange() {
                    const now = new Date();
                    if (this.periodFilter === 'week') {
                        const day = now.getDay() || 7;
                        const monday = new Date(now); monday.setDate(now.getDate() - day + 1);
                        const sunday = new Date(monday); sunday.setDate(monday.getDate() + 6);
                        return { from: monday.toISOString().slice(0, 10), to: sunday.toISOString().slice(0, 10) };
                    }
                    if (this.periodFilter === 'month') {
                        const first = new Date(now.getFullYear(), now.getMonth(), 1);
                        const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                        return { from: first.toISOString().slice(0, 10), to: last.toISOString().slice(0, 10) };
                    }
                    return null; // 'all' : pas de filtre de date
                },
                setPeriodFilter(period) {
                    this.periodFilter = period;
                    this.loadAttendances();
                    this.loadAttendanceSummary();
                },
                async loadLeaves() {
                    const res = await apiFetch('/leave-requests?per_page=30');
                    if (res && res.ok) { const data = await res.json(); this.leaves = data.data; }
                },
                async loadAdvances() {
                    const res = await apiFetch('/employee-advances?per_page=30');
                    if (res && res.ok) { const data = await res.json(); this.advances = data.data; }
                },
                async loadPayroll() {
                    const res = await apiFetch('/payroll-runs?per_page=20');
                    if (res && res.ok) {
                        const data = await res.json();
                        this.payrollRuns = await Promise.all(data.data.map(async r => {
                            const detail = await apiFetch('/payroll-runs/' + r.id);
                            return { ...(detail && detail.ok ? await detail.json() : r), _method: 'especes' };
                        }));
                    }
                },

                resetEmpForm() { this.empError = ''; this.empForm = { first_name: '', last_name: '', position: '', department_id: '', hire_date: new Date().toISOString().slice(0, 10), base_salary: '' }; },
                async createEmployee() {
                    this.empSaving = true; this.empError = '';
                    const res = await apiFetch('/employees', { method: 'POST', body: JSON.stringify(this.empForm) });
                    const data = await res.json();
                    if (!res.ok) { this.empError = data.message || 'Erreur.'; this.empSaving = false; return; }
                    this.empSaving = false; this.empPanel = false; this.loadEmployees();
                },

                async createDepartment() {
                    if (!this.newDeptName) return;
                    const res = await apiFetch('/departments', { method: 'POST', body: JSON.stringify({ name: this.newDeptName }) });
                    if (res && res.ok) { this.newDeptName = ''; this.loadDepartments(); }
                },

                resetAttForm() { this.attError = ''; this.attForm = { employee_id: '', date: new Date().toISOString().slice(0, 10), clock_in: '', clock_out: '' }; },
                async submitAttendance() {
                    this.attSaving = true; this.attError = '';
                    const res = await apiFetch('/attendances', { method: 'POST', body: JSON.stringify(this.attForm) });
                    const data = await res.json();
                    if (!res.ok) { this.attError = data.message || 'Erreur.'; this.attSaving = false; return; }
                    this.attSaving = false; this.resetAttForm(); this.loadAttendances();
                },
                attStatusStyle(s) {
                    if (s === 'retard') return 'bg-gold-500/15 text-gold-600';
                    if (s === 'conge') return 'bg-navy-800/10 text-navy-800';
                    if (s === 'absent') return 'bg-rust-500/10 text-rust-600';
                    return 'bg-sage-500/10 text-sage-600';
                },

                resetLeaveForm() { this.leaveError = ''; this.leaveForm = { employee_id: '', type: 'conges_payes', start_date: '', end_date: '' }; },
                async createLeave() {
                    this.leaveSaving = true; this.leaveError = '';
                    const res = await apiFetch('/leave-requests', { method: 'POST', body: JSON.stringify(this.leaveForm) });
                    const data = await res.json();
                    if (!res.ok) { this.leaveError = data.message || 'Erreur.'; this.leaveSaving = false; return; }
                    this.leaveSaving = false; this.leavePanel = false; this.loadLeaves();
                },
                async reviewLeave(id, status) {
                    await apiFetch('/leave-requests/' + id + '/review', { method: 'POST', body: JSON.stringify({ status }) });
                    this.loadLeaves(); this.loadEmployees(); this.loadAttendances();
                },
                leaveStatusStyle(s) {
                    if (s === 'approuvee') return 'bg-sage-500/10 text-sage-600';
                    if (s === 'refusee') return 'bg-rust-500/10 text-rust-600';
                    return 'bg-gold-500/15 text-gold-600';
                },

                resetAdvForm() { this.advError = ''; this.advForm = { employee_id: '', amount: '', monthly_installment: '', reason: '' }; },
                async createAdvance() {
                    this.advSaving = true; this.advError = '';
                    const res = await apiFetch('/employee-advances', { method: 'POST', body: JSON.stringify(this.advForm) });
                    const data = await res.json();
                    if (!res.ok) { this.advError = data.message || 'Erreur.'; this.advSaving = false; return; }
                    this.advSaving = false; this.advPanel = false; this.loadAdvances();
                },

                async generatePayroll() {
                    this.payrollSaving = true; this.payrollError = '';
                    const res = await apiFetch('/payroll-runs', { method: 'POST', body: JSON.stringify({ period: this.newPeriod }) });
                    const data = await res.json();
                    if (!res.ok) { this.payrollError = data.message || 'Erreur lors de la génération.'; this.payrollSaving = false; return; }
                    this.payrollSaving = false; this.loadPayroll();
                },
                async payPayroll(run) {
                    if (!confirm('Payer les salaires de ' + run.period + ' (' + formatXOF(run.total_amount) + ') ? La trésorerie sera diminuée.')) return;
                    const res = await apiFetch('/payroll-runs/' + run.id + '/pay', { method: 'POST', body: JSON.stringify({ method: run._method || 'especes' }) });
                    if (res && res.ok) { toast('Paie payée'); this.loadPayroll(); }
                    else { const d = res ? await res.json().catch(() => ({})) : {}; toast(d.message || 'Paiement impossible', 'error'); }
                },
                async validatePayroll(id) {
                    await apiFetch('/payroll-runs/' + id + '/validate', { method: 'POST' });
                    this.loadPayroll(); this.loadAdvances();
                },
                async deletePayroll(id) {
                    if (!confirm('Supprimer ce brouillon de paie ? Tu pourras le régénérer ensuite avec les données à jour.')) return;
                    await apiFetch('/payroll-runs/' + id, { method: 'DELETE' });
                    this.loadPayroll();
                },
                async printPayslip(run, item) {
                    const num = (v) => parseFloat(v) || 0; // sécurité : jamais de NaN, même si une valeur arrive en string ou null
                    const company = (JSON.parse(localStorage.getItem('si_user') || '{}').company) || {};

                    // Récupère le résumé réel des heures/absences de l'employé sur le mois de la paie
                    const [year, month] = run.period.split('-');
                    const periodStart = `${year}-${month}-01`;
                    const periodEnd = new Date(year, month, 0).toISOString().slice(0, 10);
                    let hoursSummary = { days_present: 0, days_late: 0, days_leave: 0, total_hours: 0, overtime_hours: 0 };
                    try {
                        const res = await apiFetch(`/attendances/summary?date_from=${periodStart}&date_to=${periodEnd}`);
                        if (res && res.ok) {
                            const data = await res.json();
                            const found = data.employees.find(e => e.employee_id === item.employee_id || e.employee_id === item.employee.id);
                            if (found) hoursSummary = found;
                        }
                    } catch (e) { /* pas bloquant si l'appel échoue */ }

                    const base = num(item.base_salary), overtime = num(item.overtime_amount), bonuses = num(item.bonuses);
                    const unpaidLeave = num(item.unpaid_leave_deduction), contributions = num(item.social_contributions), advance = num(item.advance_deduction);
                    const gross = base - unpaidLeave + overtime + bonuses;
                    const employeeFull = item.employee.first_name + ' ' + item.employee.last_name;
                    const periodLabel = new Date(year, month - 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
                    const employerContribution = Math.round(gross * 0.14); // charge patronale estimée (indicative, distincte de la retenue salariale)

                    const win = window.open('', '_blank', 'width=850,height=1100');
                    win.document.write(`
                        <html>
                        <head>
                            <meta charset="utf-8">
                            <title>Bulletin de paie — ${employeeFull} — ${run.period}</title>
                            <style>
                                body { font-family: Arial, sans-serif; color: #0B1220; padding: 40px; max-width: 720px; margin: 0 auto; font-size: 13px; }
                                h1 { font-size: 19px; margin: 0 0 4px; }
                                .muted { color: #6b7280; font-size: 12px; }
                                .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #6366F1; padding-bottom: 16px; margin-bottom: 20px; }
                                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; background: #F7F8FB; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; }
                                .info-grid div { font-size: 12.5px; }
                                .info-grid b { color: #6b7280; font-weight: 600; font-size: 10.5px; text-transform: uppercase; display: block; margin-bottom: 1px; }
                                table { width: 100%; border-collapse: collapse; margin-top: 8px; }
                                th, td { text-align: left; padding: 7px 4px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
                                th { color: #6b7280; font-weight: 600; font-size: 10.5px; text-transform: uppercase; }
                                .right { text-align: right; }
                                .total-row td { font-weight: 700; font-size: 15px; border-top: 2px solid #0B1220; border-bottom: none; padding-top: 12px; }
                                .subtotal-row td { font-weight: 600; background: #F7F8FB; }
                                .neg { color: #DC4C4C; } .pos { color: #1F9D6C; }
                                .section-title { font-weight: 700; font-size: 13px; margin: 24px 0 6px; }
                                .footer-note { color: #6b7280; font-size: 11px; margin-top: 20px; line-height: 1.5; border-top: 1px solid #e5e7eb; padding-top: 14px; }
                                @media print { button { display: none; } }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <div>
                                    <h1>${company.name || 'Entreprise'}</h1>
                                    <p class="muted">${company.address || ''}${company.phone ? ' · ' + company.phone : ''}</p>
                                </div>
                                <div style="text-align:right">
                                    <p style="font-weight:700; font-size:15px; margin:0;">Bulletin de paie</p>
                                    <p class="muted" style="text-transform:capitalize;">${periodLabel}</p>
                                    <p class="muted">Édité le ${new Date().toLocaleDateString('fr-FR')}</p>
                                </div>
                            </div>

                            <div class="info-grid">
                                <div><b>Employé</b>${employeeFull}</div>
                                <div><b>Matricule</b>#${String(item.employee_id || item.employee.id).padStart(4, '0')}</div>
                                <div><b>Poste</b>${item.employee.position || '—'}</div>
                                <div><b>Département</b>${item.employee.department ? item.employee.department.name : '—'}</div>
                                <div><b>Date d'embauche</b>${item.employee.hire_date ? new Date(item.employee.hire_date).toLocaleDateString('fr-FR') : '—'}</div>
                                <div><b>Période payée</b>${new Date(periodStart).toLocaleDateString('fr-FR')} au ${new Date(periodEnd).toLocaleDateString('fr-FR')}</div>
                            </div>

                            <p class="section-title">Temps de travail sur la période</p>
                            <table>
                                <thead><tr><th>Jours présents</th><th>Retards</th><th>Jours de congé</th><th class="right">Heures totales</th><th class="right">Dont heures sup</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td>${hoursSummary.days_present}</td>
                                        <td>${hoursSummary.days_late}</td>
                                        <td>${hoursSummary.days_leave}</td>
                                        <td class="right">${hoursSummary.total_hours} h</td>
                                        <td class="right">${hoursSummary.overtime_hours} h</td>
                                    </tr>
                                </tbody>
                            </table>

                            <p class="section-title">Détail de la rémunération</p>
                            <table>
                                <thead><tr><th>Élément</th><th class="right">Montant</th></tr></thead>
                                <tbody>
                                    <tr><td>Salaire de base</td><td class="right">${formatXOF(base)}</td></tr>
                                    ${overtime > 0 ? `<tr><td>Heures supplémentaires (${hoursSummary.overtime_hours} h, majoration 25%)</td><td class="right pos">+ ${formatXOF(overtime)}</td></tr>` : ''}
                                    ${bonuses > 0 ? `<tr><td>Primes</td><td class="right pos">+ ${formatXOF(bonuses)}</td></tr>` : ''}
                                    ${unpaidLeave > 0 ? `<tr><td>Absences non rémunérées (${hoursSummary.days_leave} j)</td><td class="right neg">− ${formatXOF(unpaidLeave)}</td></tr>` : ''}
                                    <tr class="subtotal-row"><td>Salaire brut</td><td class="right">${formatXOF(gross)}</td></tr>
                                    ${contributions > 0 ? `<tr><td>Cotisations sociales salariales (6%)</td><td class="right neg">− ${formatXOF(contributions)}</td></tr>` : ''}
                                    ${advance > 0 ? `<tr><td>Remboursement avance sur salaire</td><td class="right neg">− ${formatXOF(advance)}</td></tr>` : ''}
                                    <tr class="total-row"><td>Net à payer</td><td class="right">${formatXOF(item.net_amount)}</td></tr>
                                </tbody>
                            </table>

                            <p class="footer-note">
                                Charge patronale estimée sur ce bulletin (cotisations employeur, indicative, non déduite du salarié) : ${formatXOF(employerContribution)}.<br>
                                Ce bulletin est généré automatiquement à partir des données de pointage et de gestion RH du système d'information. Cotisation sociale et majoration des heures supplémentaires appliquées selon les taux configurés de l'entreprise. Document à conserver sans limitation de durée.
                            </p>

                            <button onclick="window.print()" style="margin-top:24px; padding:10px 20px; background:#0B1220; color:white; border:none; border-radius:8px; cursor:pointer;">Imprimer / Enregistrer en PDF</button>
                        </body>
                        </html>
                    `);
                    win.document.close();
                }
            }
        }
    </script>
</x-app-layout>
