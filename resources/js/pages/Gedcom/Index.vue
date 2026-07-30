<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Users, Image as ImageIcon, GitBranch, FolderArchive, ArrowLeft, RefreshCw, LogOut, ShieldCheck, Shield
} from '@lucide/vue';

import GedcomDirectory from '@/components/Gedcom/GedcomDirectory.vue';
import GedcomTreeView from '@/components/Gedcom/GedcomTreeView.vue';
import GedcomMediaGallery from '@/components/Gedcom/GedcomMediaGallery.vue';
import GedcomPersonModal from '@/components/Gedcom/GedcomPersonModal.vue';
import { logout } from '@/routes';
import type { User } from '@/types/auth';

const props = defineProps<{
    stats: {
        total_individuals: number;
        total_families: number;
        total_media: number;
        media_types: {
            photos: number;
            documents: number;
            audio: number;
            other: number;
        };
        top_surnames: Record<string, number>;
    };
    rootPersonId: string | null;
    defaultTab?: 'directory' | 'tree' | 'media';
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);

const activeTab = ref<'directory' | 'tree' | 'media'>(props.defaultTab || 'directory');
const selectedPersonId = ref<string | null>(null);
const currentRootPersonId = ref<string | null>(props.rootPersonId);
const isReimporting = ref(false);

const reimportArchive = async () => {
    if (!confirm('Re-importing will wipe current extracted media and re-parse the active ZIP archive in storage/app/private. Continue?')) {
        return;
    }
    isReimporting.value = true;
    try {
        const res = await fetch('/api/gedcom/reimport', { method: 'POST' });
        if (res.ok) {
            router.reload();
        } else {
            alert('Failed to reimport GEDCOM archive.');
        }
    } catch (e) {
        console.error('Reimport failed:', e);
    } finally {
        isReimporting.value = false;
    }
};

const selectPerson = (id: string) => {
    selectedPersonId.value = id;
};

const openInTree = (id: string) => {
    currentRootPersonId.value = id;
    activeTab.value = 'tree';
};

const changeRootPerson = (id: string) => {
    currentRootPersonId.value = id;
};
</script>

<template>
    <Head title="Family Tree Archive" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-indigo-500 selection:text-white">
        <!-- Main Top Bar -->
        <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800">
            <div class="max-w-[96rem] mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <!-- Left Section: Back, Logo, Re-import -->
                <div class="flex items-center gap-3">
                    <a
                        href="/dashboard"
                        class="p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                        title="Back to Dashboard"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </a>
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 flex items-center justify-center text-white shadow-md">
                            <FolderArchive class="w-5 h-5" />
                        </div>
                        <div class="hidden sm:block">
                            <h1 class="text-sm font-extrabold tracking-tight text-white leading-none">
                                Family Tree Archive
                            </h1>
                            <p class="text-[11px] font-medium text-indigo-400 mt-0.5">
                                GEDCOM Genealogy Viewer
                            </p>
                        </div>
                    </div>

                    <button
                        @click="reimportArchive"
                        :disabled="isReimporting"
                        class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-slate-300 hover:text-white text-xs font-semibold transition-colors border border-slate-700 shadow-xs ml-1"
                        title="Wipe old media and re-import active ZIP file"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isReimporting }" />
                        {{ isReimporting ? 'Re-importing...' : 'Re-import ZIP' }}
                    </button>
                </div>

                <!-- Navigation Tabs -->
                <nav class="flex items-center gap-1 bg-slate-950/80 p-1.5 rounded-2xl border border-slate-800 text-xs font-bold">
                    <button
                        @click="activeTab = 'directory'"
                        class="px-3 sm:px-4 py-2 rounded-xl transition-colors flex items-center gap-2"
                        :class="activeTab === 'directory' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200'"
                    >
                        <Users class="w-4 h-4" />
                        <span class="hidden sm:inline">People</span> Directory
                    </button>
                    <button
                        @click="activeTab = 'tree'"
                        class="px-3 sm:px-4 py-2 rounded-xl transition-colors flex items-center gap-2"
                        :class="activeTab === 'tree' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200'"
                    >
                        <GitBranch class="w-4 h-4" />
                        Family Tree
                    </button>
                    <button
                        @click="activeTab = 'media'"
                        class="px-3 sm:px-4 py-2 rounded-xl transition-colors flex items-center gap-2"
                        :class="activeTab === 'media' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200'"
                    >
                        <ImageIcon class="w-4 h-4" />
                        Media Explorer
                    </button>
                </nav>

                <!-- Right Section: User Badge & Logout Button -->
                <div class="flex items-center gap-3">
                    <Link
                        v-if="currentUser?.is_superuser"
                        href="/admin/users"
                        class="hidden lg:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 text-xs font-semibold border border-purple-500/30 transition-colors"
                    >
                        <ShieldCheck class="w-3.5 h-3.5" />
                        User Management
                    </Link>

                    <div v-if="currentUser" class="flex items-center gap-2.5 pl-2 border-l border-slate-800">
                        <div class="hidden lg:flex flex-col text-right">
                            <span class="text-xs font-semibold text-slate-200 flex items-center justify-end gap-1">
                                {{ currentUser.name }}
                            </span>
                            <span class="text-[10px] text-slate-400 leading-none">{{ currentUser.email }}</span>
                        </div>

                        <!-- Logout Button -->
                        <Link
                            :href="logout()"
                            method="post"
                            as="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-600/20 text-slate-300 hover:text-rose-300 border border-slate-700 hover:border-rose-500/30 transition-colors text-xs font-semibold cursor-pointer"
                            title="Log out"
                        >
                            <LogOut class="w-4 h-4" />
                            <span class="hidden sm:inline">Log out</span>
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <main :class="activeTab === 'tree' ? 'max-w-[96rem] mx-auto px-2 sm:px-4 lg:px-6 py-6 space-y-6' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8'">
            <!-- Active View Container -->
            <div>
                <!-- Directory View -->
                <GedcomDirectory
                    v-if="activeTab === 'directory'"
                    :top-surnames="stats.top_surnames"
                    @select-person="selectPerson"
                    @open-in-tree="openInTree"
                />

                <!-- Family Tree View -->
                <GedcomTreeView
                    v-if="activeTab === 'tree'"
                    :root-person-id="currentRootPersonId"
                    @select-person="selectPerson"
                    @change-root="changeRootPerson"
                />

                <!-- Media Gallery View -->
                <GedcomMediaGallery
                    v-if="activeTab === 'media'"
                    @select-person="selectPerson"
                />
            </div>
        </main>

        <!-- Individual Profile Drawer Modal -->
        <GedcomPersonModal
            :person-id="selectedPersonId"
            @close="selectedPersonId = null"
            @select-person="selectPerson"
            @open-in-tree="openInTree"
        />
    </div>
</template>
