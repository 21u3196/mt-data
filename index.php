<?php
$page_title = "Fast Virtual Data & Face Biometrics";
include_once("includes/header.php");
include_once("includes/navbar.php");
?>

<!-- Hero Section -->
<section class="relative overflow-hidden pt-16 pb-20 lg:pt-24 lg:pb-32 bg-zinc-50/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">

        <!-- Main Heading -->
        <h1 class="font-heading text-4xl sm:text-6xl lg:text-7xl font-extrabold text-zinc-900 tracking-tight max-w-4xl mx-auto leading-[1.1] mb-6">
            Instant Data Top-Up with <br class="hidden sm:inline" />
            <span class="text-zinc-700">
                Biometric Face ID
            </span>
        </h1>

        <!-- Subtitle -->
        <p class="text-base sm:text-xl text-zinc-600 max-w-2xl mx-auto mb-10 leading-relaxed">
            Automated virtual top-ups for MTN, Airtel, Glo, and 9mobile with instant 1-click passwordless biometric authentication and digital wallet management.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 max-w-md mx-auto">
            <?php if (is_logged_in()): ?>
                <a href="user/dashboard.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-base shadow-sm hover:scale-[1.02] transition-all">
                    <i class="fa-solid fa-gauge-high"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="user/login.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-7 py-4 rounded-2xl bg-zinc-900 text-white font-bold text-base shadow-sm hover:bg-zinc-800 hover:scale-[1.02] transition-all">
                    <i class="fa-solid fa-face-viewfinder text-zinc-300"></i> Sign In
                </a>
                <a href="user/register.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-4 rounded-2xl bg-zinc-200 hover:bg-zinc-300 text-zinc-900 font-bold text-base shadow-sm hover:scale-[1.02] transition-all">
                    <i class="fa-solid fa-bolt"></i> Get Started
                </a>
            <?php endif; ?>
        </div>

        <!-- Trust Badges -->
        <div class="mt-14 pt-8 border-t border-slate-200/60 flex flex-wrap items-center justify-center gap-8 text-xs font-semibold text-slate-500 uppercase tracking-wider">
            <span class="flex items-center gap-2"><i class="fa-solid fa-bolt text-brand-500"></i> 1-Second Automated API</span>
            <span class="flex items-center gap-2"><i class="fa-solid fa-shield-halved text-emerald-500"></i> Bcrypt Encryption</span>
            <span class="flex items-center gap-2"><i class="fa-solid fa-camera text-accent-500"></i> 128-D Biometrics</span>
            <span class="flex items-center gap-2"><i class="fa-solid fa-headset text-indigo-500"></i> 24/7 Availability</span>
        </div>

    </div>
</section>

<!-- Features Grid Section -->
<section class="py-20 bg-white border-y border-slate-200/80" id="features">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="font-heading text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-4">
                Designed for Absolute Speed & Bank-Grade Security
            </h2>
            <p class="text-slate-600 text-base">
                Everything you need to purchase data bundles, top up airtime, and renew cable subscriptions effortlessly.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <!-- Card 1 -->
            <div class="p-6 rounded-3xl bg-slate-50 border border-slate-200/80 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-500/5 transition-all group">
                <div class="w-12 h-12 rounded-2xl bg-brand-100 text-brand-600 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-face-viewfinder"></i>
                </div>
                <h3 class="font-heading text-lg font-bold text-slate-900 mb-2">Face ID Biometrics</h3>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Enroll your face profile once and sign into your account instantaneously using AI facial feature matching.
                </p>
            </div>

            <!-- Card 2 -->
            <div class="p-6 rounded-3xl bg-slate-50 border border-slate-200/80 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-500/5 transition-all group">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-wifi"></i>
                </div>
                <h3 class="font-heading text-lg font-bold text-slate-900 mb-2">Automated Data Bundles</h3>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Enjoy high-speed automated SME, Gifting, and Corporate data vending across all major Nigerian telecommunications.
                </p>
            </div>

            <!-- Card 3 -->
            <div class="p-6 rounded-3xl bg-slate-50 border border-slate-200/80 hover:border-accent-200 hover:shadow-xl hover:shadow-accent-500/5 transition-all group">
                <div class="w-12 h-12 rounded-2xl bg-pink-100 text-accent-600 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="font-heading text-lg font-bold text-slate-900 mb-2">Bcrypt Password Hashing</h3>
                <p class="text-sm text-slate-600 leading-relaxed">
                    State-of-the-art credential encryption with automatic password upgrade for legacy users on login.
                </p>
            </div>

            <!-- Card 4 -->
            <div class="p-6 rounded-3xl bg-slate-50 border border-slate-200/80 hover:border-amber-200 hover:shadow-xl hover:shadow-amber-500/5 transition-all group">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl mb-6 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <h3 class="font-heading text-lg font-bold text-slate-900 mb-2">Digital Wallet Engine</h3>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Fast wallet top-ups with ACID transactional protection against duplicate billing and race conditions.
                </p>
            </div>

        </div>

    </div>
</section>

<?php include_once("includes/footer.php"); ?>