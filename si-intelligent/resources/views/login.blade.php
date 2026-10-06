<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion · Pilotage.IA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    navy: { 950: '#0B1220', 900: '#101A30', 800: '#16223E' },
                    gold: { 400: '#818CF8', 500: '#6366F1', 600: '#4F46E5' },
                    rust: { 500: '#DC4C4C' },
                },
                fontFamily: { display: ['"Space Grotesk"', 'sans-serif'], body: ['"Inter"', 'sans-serif'] },
            } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Space Grotesk', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-navy-950 text-white flex">

    {{-- Panneau gauche : identité --}}
    <div class="hidden lg:flex lg:w-[46%] flex-col justify-between p-12 relative overflow-hidden">
        <div class="absolute -right-24 -top-24 w-96 h-96 rounded-full bg-gold-500/[0.07] blur-3xl"></div>
        <div class="absolute -left-16 bottom-0 w-72 h-72 rounded-full bg-gold-500/[0.05] blur-3xl"></div>

        <div class="flex items-center gap-2.5 relative">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="text-gold-500 shrink-0">
                <path d="M4 18 L4 10 M9.5 18 L9.5 6 M15 18 L15 13 M20.5 18 L20.5 4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
            <span class="font-display font-semibold text-lg tracking-tight">Pilotage<span class="text-gold-500">.IA</span></span>
        </div>

        <div class="relative max-w-md">
            <p class="font-display text-[34px] leading-[1.15] font-medium">
                Chaque décision,<br/>éclairée par la donnée<br/>et expliquée simplement.
            </p>
            <p class="mt-5 text-white/50 text-[14.5px] leading-relaxed">
                Prévision des ventes, réapprovisionnement, pilotage financier —
                un seul système, connecté à ce qui se passe réellement dans l'entreprise.
            </p>
        </div>

        <p class="relative text-[12px] text-white/30">Mémoire M2 MIAGE — Système d'information intelligent</p>
    </div>

    {{-- Panneau droit : formulaire --}}
    <div class="flex-1 flex items-center justify-center p-6 bg-paper text-navy-950" style="background-color:#F7F8FB">
        <div x-data="loginForm()" x-cloak class="w-full max-w-[380px]">

            <div class="lg:hidden flex items-center gap-2.5 mb-10 justify-center">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" class="text-gold-600">
                    <path d="M4 18 L4 10 M9.5 18 L9.5 6 M15 18 L15 13 M20.5 18 L20.5 4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
                <span class="font-display font-semibold text-lg text-navy-950">Pilotage<span class="text-gold-600">.IA</span></span>
            </div>

            <h1 class="font-display text-[24px] font-semibold text-navy-950">Connexion</h1>
            <p class="text-[13.5px] text-navy-950/50 mt-1 mb-8">Accède à ton espace de pilotage.</p>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Adresse e-mail</label>
                    <input type="email" x-model="email" required autofocus
                           class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 bg-white text-[14px] focus:outline-none focus:ring-2 focus:ring-gold-500/40 focus:border-gold-500 transition"
                           placeholder="toi@entreprise.com">
                </div>
                <div>
                    <label class="block text-[12.5px] font-medium text-navy-950/70 mb-1.5">Mot de passe</label>
                    <input type="password" x-model="password" required
                           class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 bg-white text-[14px] focus:outline-none focus:ring-2 focus:ring-gold-500/40 focus:border-gold-500 transition"
                           placeholder="••••••••">
                </div>

                <template x-if="error">
                    <p class="text-[13px] text-rust-500 bg-rust-500/10 rounded-lg px-3.5 py-2.5" x-text="error"></p>
                </template>

                <button type="submit" :disabled="loading"
                        class="w-full bg-navy-950 hover:bg-navy-900 disabled:opacity-60 text-white text-[14px] font-medium py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <span x-show="!loading">Se connecter</span>
                    <span x-show="loading">Connexion…</span>
                </button>
            </form>

            <p class="text-[12px] text-navy-950/35 mt-8 text-center">
                Système d'information intelligent d'aide au pilotage de l'entreprise
            </p>
        </div>
    </div>

<script>
    function loginForm() {
        return {
            email: '', password: '', error: '', loading: false,
            async submit() {
                this.loading = true; this.error = '';
                try {
                    const res = await fetch('/api/auth/login', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ email: this.email, password: this.password }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.message || 'Identifiants invalides.';
                        this.loading = false;
                        return;
                    }
                    localStorage.setItem('si_token', data.token);
                    localStorage.setItem('si_user', JSON.stringify(data.user));
                    window.location.href = '{{ route('dashboard') }}';
                } catch (e) {
                    this.error = 'Impossible de joindre le serveur.';
                    this.loading = false;
                }
            }
        }
    }
</script>
</body>
</html>
