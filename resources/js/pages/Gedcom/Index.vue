<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Users, Image as ImageIcon, GitBranch, FolderArchive, ArrowLeft, RefreshCw, LogOut, ShieldCheck, Shield, Sun, Moon,
    BarChart2, FileText, X, ChevronRight, Sparkles, Layers
} from '@lucide/vue';

import GedcomDirectory from '@/components/Gedcom/GedcomDirectory.vue';
import GedcomTreeView from '@/components/Gedcom/GedcomTreeView.vue';
import GedcomMediaGallery from '@/components/Gedcom/GedcomMediaGallery.vue';
import GedcomPersonModal from '@/components/Gedcom/GedcomPersonModal.vue';
import { useAppearance } from '@/composables/useAppearance';
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
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const activeTab = ref<'directory' | 'tree' | 'media'>(props.defaultTab || 'tree');
const selectedPersonId = ref<string | null>(null);
const currentRootPersonId = ref<string | null>(props.rootPersonId);
const isReimporting = ref(false);
const showReportsModal = ref(false);

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
    selectedPersonId.value = null;
};

const changeRootPerson = (id: string) => {
    currentRootPersonId.value = id;
};

const switchTab = (tab: 'tree' | 'directory' | 'media') => {
    activeTab.value = tab;
    showReportsModal.value = false;
};
</script>

<template>
    <Head title="Family Tree Archive" />

    <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans selection:bg-indigo-500 selection:text-white transition-colors duration-200">
        <!-- Main Top Bar -->
        <header class="sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 transition-colors duration-200">
            <div class="max-w-[96rem] mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <!-- Left Section: Back, Logo -->
                <div class="flex items-center gap-3">
                    <a
                        href="/dashboard"
                        class="p-2 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-300 dark:hover:bg-slate-700 transition-colors"
                        title="Back to Dashboard"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </a>
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 flex items-center justify-center text-white shadow-md">
                            <FolderArchive class="w-5 h-5" />
                        </div>
                        <div class="hidden sm:block">
                            <h1 class="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white leading-none">
                                Family Tree Archive
                            </h1>
                            <p class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 mt-0.5">
                                GEDCOM Genealogy Viewer
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs (Tree Entrance First) -->
                <nav class="flex items-center gap-1 bg-slate-200/80 dark:bg-slate-950/80 p-1.5 rounded-2xl border border-slate-300/80 dark:border-slate-800 text-xs font-bold">
                    <button
                        @click="activeTab = 'tree'"
                        class="px-3 sm:px-4 py-2 rounded-xl transition-colors flex items-center gap-2 cursor-pointer"
                        :class="activeTab === 'tree' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                    >
                        <GitBranch class="w-4 h-4" />
                        Family Tree
                    </button>
                    <button
                        @click="activeTab = 'directory'"
                        class="px-3 sm:px-4 py-2 rounded-xl transition-colors flex items-center gap-2 cursor-pointer"
                        :class="activeTab === 'directory' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                    >
                        <Users class="w-4 h-4" />
                        <span class="hidden sm:inline">People</span> Directory
                    </button>
                    <button
                        @click="activeTab = 'media'"
                        class="px-3 sm:px-4 py-2 rounded-xl transition-colors flex items-center gap-2 cursor-pointer"
                        :class="activeTab === 'media' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                    >
                        <ImageIcon class="w-4 h-4" />
                        Media Explorer
                    </button>
                </nav>

                <!-- Right Section: Views & Reports Button, Theme Switcher, User & Logout -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Views & Reports Button -->
                    <button
                        @click="showReportsModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 transition-all text-xs font-bold shadow-xs cursor-pointer"
                        title="View Archive Reports & Analytics"
                    >
                        <BarChart2 class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        <span class="hidden md:inline">Views & Reports</span>
                    </button>

                    <!-- Light / Dark Theme Switcher Button -->
                    <button
                        @click="toggleTheme"
                        class="p-2 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-300 dark:hover:bg-slate-700 transition-colors cursor-pointer border border-slate-300/80 dark:border-slate-700/60"
                        :title="resolvedAppearance === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <Sun v-if="resolvedAppearance === 'dark'" class="w-4 h-4 text-amber-400" />
                        <Moon v-else class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    </button>

                    <Link
                        v-if="currentUser?.is_superuser"
                        href="/admin/users"
                        class="hidden lg:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-700 dark:text-purple-300 text-xs font-semibold border border-purple-500/30 transition-colors"
                    >
                        <ShieldCheck class="w-3.5 h-3.5" />
                        User Management
                    </Link>

                    <div v-if="currentUser" class="flex items-center gap-2.5 pl-2 border-l border-slate-300 dark:border-slate-800">
                        <div class="hidden lg:flex flex-col text-right">
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center justify-end gap-1">
                                {{ currentUser.name }}
                            </span>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 leading-none">{{ currentUser.email }}</span>
                        </div>

                        <!-- Logout Button -->
                        <Link
                            :href="logout()"
                            method="post"
                            as="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-rose-500/10 text-slate-700 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-300 border border-slate-300 dark:border-slate-700 hover:border-rose-500/30 transition-colors text-xs font-semibold cursor-pointer"
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
                <!-- Family Tree View (Default Entrance) -->
                <GedcomTreeView
                    v-if="activeTab === 'tree'"
                    :root-person-id="currentRootPersonId"
                    @select-person="selectPerson"
                    @change-root="changeRootPerson"
                />

                <!-- Directory View -->
                <GedcomDirectory
                    v-if="activeTab === 'directory'"
                    :top-surnames="stats.top_surnames"
                    @select-person="selectPerson"
                    @open-in-tree="openInTree"
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

        <!-- Views & Reports Modal -->
        <div
            v-if="showReportsModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/75 backdrop-blur-xs animate-in fade-in duration-200"
            @click.self="showReportsModal = false"
        >
            <div class="relative w-full max-w-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-850/50">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                            <BarChart2 class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 dark:text-white">
                                Views & Genealogy Reports
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Switch views and review family archive statistics
                            </p>
                        </div>
                    </div>

                    <button
                        @click="showReportsModal = false"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 overflow-y-auto space-y-6">
                    <!-- Views Switcher Cards -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                            Archive Views
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- Family Tree -->
                            <div
                                @click="switchTab('tree')"
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer flex flex-col justify-between gap-3 group"
                                :class="activeTab === 'tree' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40' : 'border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-600 bg-white dark:bg-slate-850'"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                        <GitBranch class="w-5 h-5" />
                                    </div>
                                    <span v-if="activeTab === 'tree'" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-indigo-600 text-white">Active</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        Family Tree
                                    </h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Interactive multi-generational visual lineage chart.
                                    </p>
                                </div>
                            </div>

                            <!-- People Directory -->
                            <div
                                @click="switchTab('directory')"
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer flex flex-col justify-between gap-3 group"
                                :class="activeTab === 'directory' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40' : 'border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-600 bg-white dark:bg-slate-850'"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                        <Users class="w-5 h-5" />
                                    </div>
                                    <span v-if="activeTab === 'directory'" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-indigo-600 text-white">Active</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        People Directory
                                    </h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Searchable index of all individuals & surnames.
                                    </p>
                                </div>
                            </div>

                            <!-- Media Explorer -->
                            <div
                                @click="switchTab('media')"
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer flex flex-col justify-between gap-3 group"
                                :class="activeTab === 'media' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40' : 'border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-600 bg-white dark:bg-slate-850'"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                        <ImageIcon class="w-5 h-5" />
                                    </div>
                                    <span v-if="activeTab === 'media'" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-indigo-600 text-white">Active</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        Media Explorer
                                    </h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Photos, archival documents, & audio recordings.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Summary -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                            Archive Statistics & Metrics
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                                    {{ stats.total_individuals }}
                                </div>
                                <div class="text-xs font-semibold text-slate-600 dark:text-slate-400 mt-0.5">
                                    Total Individuals
                                </div>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                                    {{ stats.total_families }}
                                </div>
                                <div class="text-xs font-semibold text-slate-600 dark:text-slate-400 mt-0.5">
                                    Total Families
                                </div>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                                    {{ stats.total_media }}
                                </div>
                                <div class="text-xs font-semibold text-slate-600 dark:text-slate-400 mt-0.5">
                                    Media Files
                                </div>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                                    {{ stats.media_types?.photos || 0 }}
                                </div>
                                <div class="text-xs font-semibold text-slate-600 dark:text-slate-400 mt-0.5">
                                    Photos & Portraits
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Surnames -->
                    <div v-if="stats.top_surnames && Object.keys(stats.top_surnames).length > 0">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                            Top Family Surnames
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="(count, surname) in stats.top_surnames"
                                :key="surname"
                                @click="switchTab('directory')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 text-slate-800 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 text-xs font-semibold border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
                            >
                                <span>{{ surname }}</span>
                                <span class="px-1.5 py-0.2 rounded-md bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 text-[10px] font-bold">
                                    {{ count }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
