<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { RefreshCw, FolderArchive, Users, ShieldCheck, CheckCircle2, AlertCircle } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: AppLayout,
});

const isReimporting = ref(false);
const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const reimportArchive = async () => {
    if (!confirm('Re-importing will wipe current extracted media and re-parse the active ZIP archive in storage/app/private. Continue?')) {
        return;
    }
    isReimporting.value = true;
    statusMessage.value = null;
    errorMessage.value = null;

    try {
        const res = await fetch('/api/gedcom/reimport', { method: 'POST' });
        const json = await res.json();
        if (res.ok) {
            statusMessage.value = json.message || 'GEDCOM archive re-imported successfully!';
        } else {
            errorMessage.value = json.error || 'Failed to re-import GEDCOM archive.';
        }
    } catch (e: any) {
        console.error('Reimport failed:', e);
        errorMessage.value = e.message || 'An unexpected error occurred during re-import.';
    } finally {
        isReimporting.value = false;
    }
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Header Title Banner -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 rounded-2xl border border-indigo-500/20 shadow-xl text-white">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                    <ShieldCheck class="w-3.5 h-3.5" />
                    Superuser Control Panel
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Superuser Dashboard</h1>
                <p class="text-xs text-slate-300">System administration, user verification, and GEDCOM data management.</p>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    href="/admin/users"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-all shadow-md active:scale-95"
                >
                    <Users class="w-4 h-4" />
                    Manage Users
                </Link>
                <Link
                    href="/gedcom"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md active:scale-95"
                >
                    <FolderArchive class="w-4 h-4" />
                    View Family Tree
                </Link>
            </div>
        </div>

        <!-- Status Alerts -->
        <div v-if="statusMessage" class="flex items-center gap-2 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
            <CheckCircle2 class="w-4 h-4 shrink-0" />
            <span>{{ statusMessage }}</span>
        </div>

        <div v-if="errorMessage" class="flex items-center gap-2 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-semibold">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>

        <!-- Admin Actions Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- GEDCOM ZIP Re-import Admin Card -->
            <div class="p-6 rounded-2xl bg-card border border-sidebar-border/70 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-500">
                        <FolderArchive class="w-5 h-5" />
                    </div>
                    <h2 class="text-base font-bold text-foreground">GEDCOM Data Maintenance</h2>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Re-extract active ZIP archives in <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px]">storage/app/private</code>, clear old media cache, and re-parse genealogical records. Only Superusers can perform this operation.
                    </p>
                </div>

                <div class="pt-2 border-t border-sidebar-border/50">
                    <Button
                        @click="reimportArchive"
                        :disabled="isReimporting"
                        class="w-full h-10 gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition-all active:scale-98"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isReimporting }" />
                        {{ isReimporting ? 'Re-importing ZIP Archive...' : 'Re-import ZIP Archive' }}
                    </Button>
                </div>
            </div>

            <!-- User Verification Quick Card -->
            <div class="p-6 rounded-2xl bg-card border border-sidebar-border/70 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-500">
                        <Users class="w-5 h-5" />
                    </div>
                    <h2 class="text-base font-bold text-foreground">User Management & Lineage</h2>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Review newly registered accounts, verify pending normal users, configure superuser privileges, and set Start Person lineage restrictions for verified users.
                    </p>
                </div>

                <div class="pt-2 border-t border-sidebar-border/50">
                    <Link
                        href="/admin/users"
                        class="inline-flex items-center justify-center w-full h-10 gap-2 rounded-md bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md transition-all active:scale-98"
                    >
                        <Users class="w-4 h-4" />
                        Open User Management
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
