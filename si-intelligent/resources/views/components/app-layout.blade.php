@props(['title' => null, 'subtitle' => null])
@php
    $icons = config('navigation.icons');
    $routeName = request()->route()?->getName();
    $hubParam = $routeName === 'hub' ? request()->route('section') : null;
    $activeSection = null;

    $nav = [];
    foreach (config('navigation.sections') as $key => $section) {
        $items = [];
        foreach ($section['items'] as $item) {
            $isActive = $routeName === $item['route'];
            if ($isActive) { $activeSection = $key; }
            $items[] = [
                'label' => $item['label'], 'url' => route($item['route']), 'icon' => $item['icon'],
                'perm' => $item['perm'] ?? null, 'desc' => $item['desc'] ?? '', 'active' => $isActive,
            ];
        }
        if ($hubParam === $key) { $activeSection = $key; }
        $nav[] = [
            'key' => $key, 'label' => $section['label'], 'icon' => $section['icon'],
            'url' => route('hub', ['section' => $key]), 'hub_active' => $hubParam === $key, 'items' => $items,
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Pilotage' }} · Pilotage.IA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#0B1220',
                        navy: { 950: '#0B1220', 900: '#101A30', 800: '#16223E', 700: '#1D2C4D' },
                        gold: { 400: '#818CF8', 500: '#6366F1', 600: '#4F46E5' },
                        sage: { 500: '#1F9D6C', 600: '#187D57' },
                        rust: { 500: '#DC4C4C', 600: '#B93D3D' },
                        paper: '#F6F7FB',
                    },
                    fontFamily: { display: ['"Space Grotesk"', 'sans-serif'], body: ['"Inter"', 'sans-serif'] },
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        html { -webkit-font-smoothing: antialiased; }
        body { font-family: 'Inter', sans-serif; background: #F6F7FB; }
        .font-display { font-family: 'Space Grotesk', sans-serif; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #D5D9E4; border-radius: 999px; }
        .kpi-ring circle { transition: stroke-dashoffset 0.6s ease; }

        /* ===== Polish global : s'applique à toutes les pages sans les réécrire ===== */
        main .bg-white.rounded-2xl, main .bg-white.rounded-xl {
            border-color: #E7E9F2; box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 1px 3px rgba(16, 24, 40, .04);
        }
        main .bg-white.rounded-2xl { border-radius: 16px; }
        main .bg-white.rounded-xl.hover\:shadow-md:hover, main a.bg-white:hover { box-shadow: 0 8px 24px rgba(79, 70, 229, .10); }
        main input, main select, main textarea {
            border-color: #DADEEA; border-radius: 10px; background: #fff; transition: border-color .15s, box-shadow .15s;
        }
        main input:focus, main select:focus, main textarea:focus {
            border-color: #6366F1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .16); outline: none;
        }
        main button.bg-navy-950, main a.bg-navy-950 {
            background: linear-gradient(180deg, #6366F1, #4F46E5); border-radius: 10px;
            box-shadow: 0 1px 2px rgba(79, 70, 229, .35), inset 0 1px 0 rgba(255, 255, 255, .18);
        }
        main button.bg-navy-950:hover, main a.bg-navy-950:hover { background: linear-gradient(180deg, #5B5EF0, #4338CA); }
        main thead tr { background: #F8F9FC; }
        main table tbody tr { transition: background .12s; }
        main table tbody tr:hover { background: #FAFBFE; }
        main .fixed.inset-0 .bg-black\/30 { backdrop-filter: blur(3px); background: rgba(11, 18, 32, .35); }
        main .fixed.inset-0 .shadow-xl { box-shadow: -20px 0 50px rgba(11, 18, 32, .18); border-radius: 18px 0 0 18px; }
        @keyframes pageIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .page-in { animation: pageIn .28s ease-out; }
        .nav-link { transition: background .15s, color .15s; }
        .tab-link { position: relative; transition: color .15s; }
        .tab-link.active::after { content: ''; position: absolute; left: 10px; right: 10px; bottom: -1px; height: 2px; background: #6366F1; border-radius: 2px; }
        .toast-enter { animation: pageIn .2s ease-out; }
    </style>
</head>
<body class="text-navy-950 antialiased">

<div x-data="shell()" x-init="init()" @keydown.window.ctrl.k.prevent="openPalette()" @keydown.window.meta.k.prevent="openPalette()"
     @keydown.window.escape="paletteOpen = false; userMenu = false; notifOpen = false" @toast.window="pushToast($event.detail)"
     class="min-h-screen flex">

    {{-- ============ Sidebar ============ --}}
    <aside :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-[76px]' : 'lg:w-[272px]']"
           class="fixed lg:sticky lg:top-0 lg:h-screen inset-y-0 left-0 z-40 w-[272px] bg-navy-950 text-white flex flex-col transition-all duration-200 ease-out shrink-0">

        <div class="h-16 flex items-center gap-2.5 px-5 border-b border-white/10 shrink-0">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-gold-400 to-gold-600 flex items-center justify-center shrink-0 shadow-lg shadow-indigo-900/40">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M4 18 L4 10 M9.5 18 L9.5 6 M15 18 L15 13 M20.5 18 L20.5 4" stroke="white" stroke-width="2.4" stroke-linecap="round"/></svg>
            </div>
            <span x-show="!collapsed" class="font-display font-semibold text-[16px] tracking-tight leading-none">Pilotage<span class="text-gold-400">.IA</span></span>
        </div>

        <button @click="openPalette()" x-show="!collapsed"
                class="mx-3 mt-4 flex items-center gap-2.5 px-3 py-2 rounded-lg bg-white/[0.06] hover:bg-white/10 text-white/50 text-[13px] transition-colors">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="{{ $icons['search'] }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="flex-1 text-left">Rechercher…</span>
            <kbd class="text-[10.5px] px-1.5 py-0.5 rounded bg-white/10 text-white/40 font-sans">Ctrl K</kbd>
        </button>

        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            {{-- Tableau de bord --}}
            <a href="{{ route('dashboard') }}" title="Tableau de bord"
               class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-lg text-[13.5px] font-medium {{ $routeName === 'dashboard' ? 'bg-white/10 text-white' : 'text-white/60 hover:text-white hover:bg-white/5' }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="shrink-0 {{ $routeName === 'dashboard' ? 'text-gold-400' : 'text-white/40 group-hover:text-white/70' }}"><path d="{{ $icons['grid'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span x-show="!collapsed">Tableau de bord</span>
            </a>

            <p x-show="!collapsed" class="px-3 pt-4 pb-1.5 text-[10.5px] uppercase tracking-[.12em] text-white/30 font-semibold">Espaces</p>
            <div x-show="collapsed" class="my-3 border-t border-white/10"></div>

            <template x-for="s in visibleSections()" :key="s.key">
                <div>
                    <div class="flex items-center rounded-lg" :class="s.key === activeKey ? 'bg-white/10' : 'hover:bg-white/5'">
                        <a :href="s.url" :title="s.label"
                           class="nav-link group flex-1 flex items-center gap-3 px-3 py-2.5 text-[13.5px] font-medium min-w-0"
                           :class="s.key === activeKey ? 'text-white' : 'text-white/60 hover:text-white'">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="shrink-0" :class="s.key === activeKey ? 'text-gold-400' : 'text-white/40 group-hover:text-white/70'">
                                <path :d="ICONS[s.icon]" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span x-show="!collapsed" class="truncate" x-text="s.label"></span>
                        </a>
                        <button x-show="!collapsed" @click="open[s.key] = !open[s.key]" class="px-2.5 py-2.5 text-white/35 hover:text-white" :title="open[s.key] ? 'Replier' : 'Déplier'">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="transition-transform" :class="open[s.key] ? 'rotate-90' : ''"><path d="{{ $icons['chevron'] }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                    <div x-show="open[s.key] && !collapsed" x-collapse.duration.150ms class="ml-[22px] pl-3 mt-0.5 mb-1 border-l border-white/10 space-y-0.5">
                        <template x-for="i in s.items" :key="i.url">
                            <a :href="i.url" x-show="can(i.perm)"
                               class="nav-link block px-3 py-1.5 rounded-md text-[13px]"
                               :class="i.active ? 'text-white bg-white/10 font-medium' : 'text-white/50 hover:text-white hover:bg-white/5'" x-text="i.label"></a>
                        </template>
                    </div>
                </div>
            </template>
        </nav>

        <div class="p-3 border-t border-white/10 shrink-0">
            <button @click="toggleCollapse()" class="hidden lg:flex w-full items-center gap-3 px-3 py-2 rounded-lg text-[12.5px] text-white/40 hover:text-white hover:bg-white/5 transition-colors">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0"><path d="{{ $icons['panel'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span x-show="!collapsed">Réduire le menu</span>
            </button>
        </div>
    </aside>

    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

    {{-- ============ Colonne principale ============ --}}
    <div class="flex-1 flex flex-col min-w-0">

        <header class="h-16 bg-white/85 backdrop-blur border-b border-gray-200/80 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-20">
            <div class="flex items-center gap-3 min-w-0">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-navy-900">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="{{ $icons['menu'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
                <nav class="flex items-center gap-1.5 text-[13px] min-w-0">
                    <a href="{{ route('dashboard') }}" class="text-navy-950/40 hover:text-navy-950 hidden sm:block">Accueil</a>
                    @if ($activeSection)
                        <span class="text-navy-950/25 hidden sm:block">/</span>
                        <a href="{{ route('hub', ['section' => $activeSection]) }}" class="text-navy-950/50 hover:text-navy-950 truncate">{{ config('navigation.sections.'.$activeSection.'.label') }}</a>
                    @endif
                    @if ($title && $routeName !== 'hub')
                        <span class="text-navy-950/25">/</span>
                        <span class="font-medium text-navy-950 truncate">{{ $title }}</span>
                    @endif
                </nav>
            </div>

            <div class="flex items-center gap-1.5">
                <button @click="openPalette()" class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-gray-300 text-[12.5px] text-navy-950/45 transition-colors mr-1">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="{{ $icons['search'] }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Recherche rapide <kbd class="text-[10.5px] px-1.5 py-0.5 rounded bg-gray-100 text-navy-950/40 font-sans">Ctrl K</kbd>
                </button>

                {{-- Notifications --}}
                <div class="relative">
                    <button @click="notifOpen = !notifOpen; userMenu = false" class="relative w-9 h-9 rounded-lg hover:bg-gray-100 flex items-center justify-center text-navy-950/60 transition-colors">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="{{ $icons['bell'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span x-show="alertTotal > 0" x-cloak class="absolute top-1 right-1 min-w-[16px] h-4 px-1 rounded-full bg-rust-500 text-white text-[10px] font-semibold flex items-center justify-center" x-text="alertTotal > 99 ? '99+' : alertTotal"></span>
                    </button>
                    <div x-show="notifOpen" x-cloak @click.outside="notifOpen = false" x-transition.opacity
                         class="absolute right-0 mt-2 w-80 bg-white rounded-xl border border-gray-200 shadow-xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <p class="font-display font-semibold text-[14px]">Alertes</p>
                            <a href="{{ route('alerts.index') }}" class="text-[12px] text-gold-600 font-medium">Tout voir</a>
                        </div>
                        <template x-if="alertItems.length === 0"><p class="px-4 py-6 text-center text-[13px] text-navy-950/40">Aucune alerte active.</p></template>
                        <template x-for="a in alertItems" :key="a.id">
                            <a href="{{ route('alerts.index') }}" class="block px-4 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                <p class="text-[12.5px] text-navy-950 leading-snug" x-text="a.message"></p>
                                <p class="text-[11px] mt-1 uppercase tracking-wide font-semibold" :class="a.severity === 'critique' ? 'text-rust-500' : 'text-navy-950/35'" x-text="a.severity"></p>
                            </a>
                        </template>
                    </div>
                </div>

                {{-- Menu utilisateur --}}
                <div class="relative">
                    <button @click="userMenu = !userMenu; notifOpen = false" class="flex items-center gap-2.5 pl-1.5 pr-2.5 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-gold-400 to-gold-600 text-white flex items-center justify-center font-display text-[13px] font-semibold" x-text="initial()"></div>
                        <div class="hidden sm:block text-left leading-tight">
                            <p class="text-[12.5px] font-semibold" x-text="user.name || '—'"></p>
                            <p class="text-[11px] text-navy-950/40" x-text="(user.role || {}).label || '—'"></p>
                        </div>
                    </button>
                    <div x-show="userMenu" x-cloak @click.outside="userMenu = false" x-transition.opacity
                         class="absolute right-0 mt-2 w-56 bg-white rounded-xl border border-gray-200 shadow-xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-[13px] font-semibold" x-text="user.name"></p>
                            <p class="text-[11.5px] text-navy-950/45 truncate" x-text="user.email"></p>
                        </div>
                        <button @click="logout()" class="w-full flex items-center gap-2.5 px-4 py-3 text-[13px] text-rust-600 hover:bg-rust-500/5 transition-colors">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="{{ $icons['logout'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Se déconnecter
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 lg:p-8 page-in">
            {{-- En-tête de page + onglets de la section --}}
            <div class="mb-6">
                <h1 class="font-display font-semibold text-[26px] leading-tight tracking-tight">{{ $title }}</h1>
                @if ($subtitle)<p class="text-[13.5px] text-navy-950/50 mt-1">{{ $subtitle }}</p>@endif

                @if ($activeSection)
                    <div class="mt-4 flex items-center gap-1 border-b border-gray-200 overflow-x-auto">
                        <a href="{{ route('hub', ['section' => $activeSection]) }}"
                           class="tab-link px-3.5 py-2.5 text-[13.5px] font-medium whitespace-nowrap {{ $routeName === 'hub' ? 'active text-navy-950' : 'text-navy-950/45 hover:text-navy-950' }}">Vue d'ensemble</a>
                        <template x-for="i in (sections.find(s => s.key === activeKey) || {items: []}).items" :key="i.url">
                            <a :href="i.url" x-show="can(i.perm)" class="tab-link px-3.5 py-2.5 text-[13.5px] font-medium whitespace-nowrap"
                               :class="i.active ? 'active text-navy-950' : 'text-navy-950/45 hover:text-navy-950'" x-text="i.label"></a>
                        </template>
                    </div>
                @endif
            </div>

            {{ $slot }}
        </main>
    </div>

    {{-- ============ Recherche rapide (Ctrl+K) ============ --}}
    <div x-show="paletteOpen" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center pt-[14vh] px-4">
        <div class="absolute inset-0 bg-navy-950/40 backdrop-blur-sm" @click="paletteOpen = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden" x-transition>
            <div class="flex items-center gap-3 px-4 border-b border-gray-100">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="text-navy-950/35"><path d="{{ $icons['search'] }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <input x-ref="paletteInput" x-model="query" @keydown.enter.prevent="go(filtered()[cursor])" @keydown.arrow-down.prevent="cursor = Math.min(cursor + 1, filtered().length - 1)"
                       @keydown.arrow-up.prevent="cursor = Math.max(cursor - 1, 0)" @input="cursor = 0"
                       placeholder="Aller à une page…" class="flex-1 py-4 text-[14.5px] !border-0 !shadow-none focus:!shadow-none outline-none">
                <kbd class="text-[10.5px] px-1.5 py-0.5 rounded bg-gray-100 text-navy-950/40">Échap</kbd>
            </div>
            <div class="max-h-80 overflow-y-auto p-2">
                <template x-for="(r, idx) in filtered()" :key="r.url">
                    <a :href="r.url" @mouseenter="cursor = idx" class="flex items-center gap-3 px-3 py-2.5 rounded-lg" :class="cursor === idx ? 'bg-gold-500/10' : ''">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="text-gold-600 shrink-0"><path :d="ICONS[r.icon]" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <div class="min-w-0">
                            <p class="text-[13.5px] font-medium" x-text="r.label"></p>
                            <p class="text-[11.5px] text-navy-950/40 truncate" x-text="r.group"></p>
                        </div>
                    </a>
                </template>
                <p x-show="filtered().length === 0" class="px-3 py-6 text-center text-[13px] text-navy-950/40">Aucun résultat.</p>
            </div>
        </div>
    </div>


    {{-- ============ Assistant vocal ============ --}}
    <div class="fixed bottom-5 left-1/2 -translate-x-1/2 lg:left-auto lg:translate-x-0 lg:right-6 z-[65] flex flex-col items-end gap-3">
        <div x-show="voice.open" x-cloak x-transition class="w-[min(92vw,380px)] bg-white rounded-2xl border border-gray-200 shadow-2xl overflow-hidden">
            <div class="px-4 py-3 bg-navy-950 text-white flex items-center justify-between">
                <div>
                    <p class="font-display font-semibold text-[14px]">Assistant vocal</p>
                    <p class="text-[11.5px] text-white/50" x-text="voice.listening ? (voice.mode === 'confirm' ? 'Dites « oui » ou « non »…' : 'Je vous écoute…') : (voice.busy ? 'Je traite…' : 'Prêt')"></p>
                </div>
                <button @click="closeVoice()" class="text-white/60 hover:text-white">✕</button>
            </div>

            <div class="p-4 space-y-3">
                <p x-show="!voice.supported" class="text-[12.5px] text-rust-600 bg-rust-500/10 rounded-lg px-3 py-2">La dictée vocale n'est pas disponible sur ce navigateur (utilisez Chrome ou Edge). Vous pouvez taper la commande ci-dessous.</p>

                <div x-show="voice.transcript" class="text-[13.5px] bg-gray-50 rounded-xl px-3.5 py-2.5">
                    <span class="text-navy-950/40 text-[11px] uppercase tracking-wide block mb-0.5">Vous avez dit</span>
                    <span x-text="voice.transcript"></span>
                </div>

                <div x-show="voice.plan && voice.plan.intent !== 'error'" class="rounded-xl border px-3.5 py-3"
                     :class="voice.plan && voice.plan.needs_confirmation ? 'border-gold-500/40 bg-gold-500/5' : 'border-gray-200'">
                    <p class="text-[13.5px]" x-text="voice.plan ? voice.plan.summary : ''"></p>
                    <div x-show="voice.plan && voice.plan.needs_confirmation && !voice.done" class="flex gap-2 mt-3">
                        <button @click="confirmVoice()" :disabled="voice.busy" class="flex-1 bg-navy-950 text-white text-[13px] font-medium py-2.5 rounded-lg disabled:opacity-50">Confirmer</button>
                        <button @click="cancelVoice()" class="px-4 border border-gray-200 text-[13px] rounded-lg hover:bg-gray-50">Annuler</button>
                    </div>
                </div>

                <p x-show="voice.message" class="text-[13px] rounded-xl px-3.5 py-2.5" :class="voice.error ? 'bg-rust-500/10 text-rust-600' : 'bg-sage-500/10 text-sage-600'" x-text="voice.message"></p>

                <form @submit.prevent="sendVoice(voice.typed); voice.typed = ''" class="flex gap-2">
                    <input x-model="voice.typed" placeholder="Ou tapez une commande…" class="flex-1 px-3 py-2 text-[13px] border">
                    <button type="submit" class="px-3.5 text-[13px] font-medium bg-gray-100 hover:bg-gray-200 rounded-lg">OK</button>
                </form>

                <div x-show="!voice.plan && !voice.message" class="flex flex-wrap gap-1.5">
                    <template x-for="ex in ['Encaisse tout en espèces', 'Pointe tout le monde', 'Ouvre les ventes', 'Ajoute le produit ventilateur à 25000']" :key="ex">
                        <button @click="sendVoice(ex)" class="text-[11.5px] px-2.5 py-1.5 rounded-full border border-gray-200 hover:border-gold-500 hover:text-gold-600 text-navy-950/60 transition-colors" x-text="ex"></button>
                    </template>
                </div>
            </div>
        </div>

        <button @click="toggleVoice()" title="Assistant vocal"
                class="w-14 h-14 rounded-full shadow-xl flex items-center justify-center text-white transition-all"
                :class="voice.listening ? 'bg-rust-500 scale-110 animate-pulse' : 'bg-gradient-to-br from-gold-400 to-gold-600 hover:scale-105'">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3zM19 11v1a7 7 0 01-14 0v-1M12 19v3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </div>

    {{-- ============ Notifications toast ============ --}}
    <div class="fixed bottom-24 right-5 z-[70] space-y-2">
        <template x-for="t in toasts" :key="t.id">
            <div class="toast-enter flex items-center gap-3 pl-4 pr-3 py-3 rounded-xl shadow-xl text-[13.5px] text-white max-w-sm"
                 :class="t.t === 'error' ? 'bg-rust-600' : 'bg-navy-900'">
                <span class="w-2 h-2 rounded-full shrink-0" :class="t.t === 'error' ? 'bg-white' : 'bg-sage-500'"></span>
                <span x-text="t.m"></span>
            </div>
        </template>
    </div>
</div>

<script>
    const ICONS = @json($icons);
    const NAV = @json($nav);

    // Garde d'authentification commune à toutes les pages protégées.
    if (!localStorage.getItem('si_token') && window.location.pathname !== '{{ route('login') }}') {
        window.location.href = '{{ route('login') }}';
    }

    function shell() {
        return {
            sections: NAV,
            activeKey: @json($activeSection),
            user: {}, collapsed: false, sidebarOpen: false,
            open: {}, paletteOpen: false, query: '', cursor: 0,
            userMenu: false, notifOpen: false, alertTotal: 0, alertItems: [], toasts: [],

            init() {
                try { this.user = JSON.parse(localStorage.getItem('si_user') || '{}'); } catch (e) { this.user = {}; }
                this.collapsed = localStorage.getItem('si_sidebar_collapsed') === '1';
                this.sections.forEach(s => this.open[s.key] = s.key === this.activeKey);
                this.loadAlerts();
            },
            can(perm) {
                if (!perm) return true;
                const role = this.user.role || {};
                if (role.name === 'admin') return true;
                return (role.permissions || []).some(p => p.name === perm);
            },
            visibleSections() { return this.sections.filter(s => s.items.some(i => this.can(i.perm))); },
            initial() { return (this.user.name || '?').charAt(0).toUpperCase(); },
            toggleCollapse() { this.collapsed = !this.collapsed; localStorage.setItem('si_sidebar_collapsed', this.collapsed ? '1' : '0'); },
            logout() { localStorage.removeItem('si_token'); localStorage.removeItem('si_user'); window.location.href = '{{ route('login') }}'; },

            async loadAlerts() {
                try {
                    const res = await window.apiFetch('/alerts');
                    if (res && res.ok) { const d = await res.json(); this.alertTotal = d.total || 0; this.alertItems = (d.data || []).slice(0, 5); }
                } catch (e) {}
            },

            openPalette() { this.paletteOpen = true; this.query = ''; this.cursor = 0; this.$nextTick(() => this.$refs.paletteInput.focus()); },
            allResults() {
                const out = [{ label: 'Tableau de bord', url: '{{ route('dashboard') }}', icon: 'grid', group: 'Accueil' }];
                this.sections.forEach(s => {
                    out.push({ label: s.label + ' — vue d\'ensemble', url: s.url, icon: s.icon, group: 'Espace' });
                    s.items.filter(i => this.can(i.perm)).forEach(i => out.push({ label: i.label, url: i.url, icon: i.icon, group: s.label }));
                });
                return out;
            },
            filtered() {
                const q = this.query.trim().toLowerCase();
                return this.allResults().filter(r => !q || (r.label + ' ' + r.group).toLowerCase().includes(q)).slice(0, 12);
            },
            go(r) { if (r) window.location.href = r.url; },

            // ======================= Assistant vocal =======================
            voice: { open: false, supported: !!(window.SpeechRecognition || window.webkitSpeechRecognition), listening: false, busy: false,
                     mode: 'command', transcript: '', typed: '', plan: null, message: '', error: false, done: false },
            rec: null,

            toggleVoice() {
                if (this.voice.open && this.voice.listening) { this.stopRec(); return; }
                this.voice.open = true;
                this.resetVoice();
                this.listen('command');
            },
            closeVoice() { this.stopRec(); window.speechSynthesis && window.speechSynthesis.cancel(); this.voice.open = false; },
            resetVoice() { Object.assign(this.voice, { transcript: '', plan: null, message: '', error: false, done: false, busy: false }); },
            stopRec() { try { this.rec && this.rec.stop(); } catch (e) {} this.voice.listening = false; },
            speak(text) {
                if (!window.speechSynthesis) return;
                window.speechSynthesis.cancel();
                const u = new SpeechSynthesisUtterance(text); u.lang = 'fr-FR'; u.rate = 1.02;
                window.speechSynthesis.speak(u);
            },
            listen(mode) {
                const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SR) return;
                this.voice.mode = mode;
                let finalText = '';
                const rec = new SR();
                rec.lang = 'fr-FR'; rec.interimResults = true; rec.continuous = false;
                rec.onstart = () => { this.voice.listening = true; };
                rec.onresult = (e) => {
                    const t = Array.from(e.results).map(r => r[0].transcript).join('');
                    if (mode === 'command') this.voice.transcript = t;
                    if (e.results[e.results.length - 1].isFinal) finalText = t;
                };
                rec.onerror = () => { this.voice.listening = false; };
                rec.onend = () => {
                    this.voice.listening = false;
                    if (!finalText) return;
                    if (mode === 'confirm') this.answerConfirm(finalText); else this.sendVoice(finalText);
                };
                this.rec = rec;
                try { rec.start(); } catch (e) {}
            },
            async sendVoice(text) {
                text = (text || '').trim();
                if (!text) return;
                this.voice.open = true; this.voice.transcript = text; this.voice.plan = null; this.voice.message = ''; this.voice.error = false; this.voice.done = false; this.voice.busy = true;
                const res = await window.apiFetch('/voice/interpret', { method: 'POST', body: JSON.stringify({ transcript: text }) });
                this.voice.busy = false;
                if (!res || !res.ok) { this.voiceFail("Je n'ai pas pu traiter la commande."); return; }
                const plan = await res.json();
                if (plan.intent === 'error') { this.voiceFail(plan.summary); return; }
                this.voice.plan = plan;
                if (plan.needs_confirmation) {
                    this.speak(plan.summary + ' Dites oui pour confirmer, ou non pour annuler.');
                    setTimeout(() => this.listen('confirm'), 600);
                } else {
                    await this.runPlan();
                }
            },
            answerConfirm(text) {
                const t = text.toLowerCase();
                if (/\b(oui|confirme|confirmer|ok|d'accord|vas-y|valide|allez)\b/.test(t)) this.confirmVoice();
                else if (/\b(non|annule|annuler|stop|laisse)\b/.test(t)) this.cancelVoice();
                else { this.speak('Je n\'ai pas compris. Dites oui ou non.'); setTimeout(() => this.listen('confirm'), 700); }
            },
            async confirmVoice() { this.stopRec(); await this.runPlan(); },
            cancelVoice() { this.stopRec(); this.voice.plan = null; this.voice.message = 'Commande annulée.'; this.voice.error = false; this.speak('Commande annulée.'); },
            async runPlan() {
                this.voice.busy = true;
                const res = await window.apiFetch('/voice/execute', { method: 'POST', body: JSON.stringify({ intent: this.voice.plan.intent, payload: this.voice.plan.payload }) });
                this.voice.busy = false;
                const data = res ? await res.json().catch(() => ({})) : {};
                if (!res || !res.ok) { this.voiceFail(data.message || "L'action a échoué."); return; }
                this.voice.done = true; this.voice.message = data.message; this.voice.error = false;
                this.speak(data.message); window.toast(data.message);
                if (data.redirect) { setTimeout(() => window.location.href = data.redirect, 700); }
                else { setTimeout(() => window.location.reload(), 2200); }
            },
            voiceFail(msg) { this.voice.message = msg; this.voice.error = true; this.voice.plan = null; this.speak(msg); },


            pushToast(d) {
                const t = { id: Date.now() + Math.random(), m: d.m, t: d.t };
                this.toasts.push(t);
                setTimeout(() => this.toasts = this.toasts.filter(x => x.id !== t.id), 3800);
            },
        };
    }

    // Petit utilitaire fetch authentifié, réutilisé par toutes les pages.
    window.apiFetch = async (path, options = {}) => {
        const token = localStorage.getItem('si_token');
        const res = await fetch('/api' + path, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token,
                ...(options.headers || {}),
            },
        });
        if (res.status === 401) {
            localStorage.removeItem('si_token');
            window.location.href = '{{ route('login') }}';
            return;
        }
        return res;
    };

    window.toast = (m, t = 'success') => window.dispatchEvent(new CustomEvent('toast', { detail: { m, t } }));
    window.formatXOF = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0) + ' XOF';
</script>

</body>
</html>
