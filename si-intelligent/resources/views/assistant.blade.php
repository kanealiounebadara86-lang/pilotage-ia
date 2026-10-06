<x-app-layout title="Assistant IA">

    <div x-data="assistantPage()" x-init="init()" x-cloak class="flex flex-col h-[calc(100vh-160px)]">

        <div class="flex-1 overflow-y-auto space-y-4 pb-4" x-ref="scrollArea">
            <template x-if="messages.length === 0">
                <div class="h-full flex flex-col items-center justify-center text-center px-6">
                    <div class="w-12 h-12 rounded-full bg-gold-500/10 flex items-center justify-center mb-4">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" class="text-gold-600"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M18 6l-2.5 2.5M8.5 15.5L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <p class="text-[14px] text-navy-950/50 max-w-sm">
                        Pose une question sur tes ventes, ton stock, tes finances ou tes recommandations —
                        l'assistant va chercher les vraies données via les outils du système.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-6 max-w-lg">
                        <template x-for="suggestion in suggestions" :key="suggestion">
                            <button @click="question = suggestion; send()" class="text-left text-[12.5px] px-3.5 py-2.5 rounded-lg border border-gray-200 hover:border-gold-500/40 text-navy-950/70 transition-colors">
                                <span x-text="suggestion"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-for="(msg, idx) in messages" :key="idx">
                <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[75%]">
                        <div class="rounded-2xl px-4 py-2.5 text-[13.5px] leading-relaxed"
                             :class="msg.role === 'user' ? 'bg-navy-950 text-white' : 'bg-white border border-gray-200 text-navy-950'">
                            <span x-text="msg.content" class="whitespace-pre-line"></span>
                        </div>
                        <template x-if="msg.tool_calls && msg.tool_calls.length > 0">
                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                <template x-for="t in msg.tool_calls" :key="t.tool">
                                    <span class="text-[10.5px] px-2 py-0.5 rounded-full bg-gold-500/10 text-gold-700" x-text="toolLabel(t.tool)"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="loading">
                <div class="flex justify-start">
                    <div class="bg-white border border-gray-200 rounded-2xl px-4 py-2.5 text-[13.5px] text-navy-950/40">
                        L'assistant réfléchit…
                    </div>
                </div>
            </template>

            <template x-if="notConfigured">
                <div class="bg-gold-500/10 border border-gold-500/30 rounded-xl px-4 py-3 text-[13px] text-navy-950/70">
                    L'assistant n'est pas encore configuré côté serveur (clé API manquante dans le service IA).
                    Les autres modules IA (prévision, réapprovisionnement, RH, finance, marketing) restent
                    utilisables normalement depuis <a href="{{ route('ai-hub.index') }}" class="text-gold-600 underline">Orchestrateur IA</a>.
                </div>
            </template>
        </div>

        <form @submit.prevent="send" class="flex items-center gap-2 border-t border-gray-200 pt-4">
            <input type="text" x-model="question" placeholder="Pose ta question…" :disabled="loading"
                   class="flex-1 px-4 py-3 rounded-xl border border-gray-300 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-gold-500/40 focus:border-gold-500">
            <button type="submit" :disabled="loading || !question.trim()"
                    class="bg-navy-950 hover:bg-navy-900 disabled:opacity-50 text-white px-5 py-3 rounded-xl transition-colors shrink-0">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M4 12h16M14 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </form>
    </div>

    <script>
        function assistantPage() {
            return {
                messages: [], question: '', loading: false, notConfigured: false,
                suggestions: [
                    "Quel est le chiffre d'affaires de ce mois ?",
                    "Quels produits risquent d'être en rupture ?",
                    "Y a-t-il des signaux financiers à surveiller ?",
                    "Des suggestions de campagnes marketing ?",
                ],

                init() {},

                async send() {
                    const q = this.question.trim();
                    if (!q || this.loading) return;

                    this.messages.push({ role: 'user', content: q });
                    this.question = '';
                    this.loading = true;
                    this.$nextTick(() => this.scrollToBottom());

                    const res = await apiFetch('/ai/assistant', { method: 'POST', body: JSON.stringify({ question: q }) });
                    this.loading = false;

                    if (!res || !res.ok) {
                        this.messages.push({ role: 'assistant', content: "Une erreur est survenue — réessaie dans un instant." });
                        this.$nextTick(() => this.scrollToBottom());
                        return;
                    }

                    const data = await res.json();
                    if (data.status === 'not_configured') this.notConfigured = true;

                    this.messages.push({ role: 'assistant', content: data.answer, tool_calls: data.tool_calls || [] });
                    this.$nextTick(() => this.scrollToBottom());
                },

                scrollToBottom() {
                    this.$refs.scrollArea.scrollTop = this.$refs.scrollArea.scrollHeight;
                },

                toolLabel(tool) {
                    const labels = {
                        get_dashboard_summary: 'Tableau de bord', search_products: 'Recherche produit',
                        get_sales_forecast: 'Prévision ventes', get_replenishment_recommendation: 'Réapprovisionnement',
                        get_finance_insights: 'Signaux finance', get_marketing_suggestions: 'Suggestions marketing',
                        get_hr_insights: 'Analyse RH',
                    };
                    return labels[tool] || tool;
                }
            }
        }
    </script>
</x-app-layout>
