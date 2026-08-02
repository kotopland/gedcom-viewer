<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Users, Image as ImageIcon, GitBranch, FolderArchive, RefreshCw, LogOut, ShieldCheck, Shield, Sun, Moon,
    BarChart2, FileText, X, ChevronRight, Sparkles, Layers, PieChart, Menu, TrendingUp, Network
} from '@lucide/vue';

import GedcomDirectory from '@/components/Gedcom/GedcomDirectory.vue';
import GedcomTreeView from '@/components/Gedcom/GedcomTreeView.vue';
import GedcomFanView from '@/components/Gedcom/GedcomFanView.vue';
import GedcomTextView from '@/components/Gedcom/GedcomTextView.vue';
import GedcomStatsView from '@/components/Gedcom/GedcomStatsView.vue';
import GedcomLineageView from '@/components/Gedcom/GedcomLineageView.vue';
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
    defaultTab?: 'directory' | 'tree' | 'fan' | 'media' | 'text' | 'stats' | 'lineage';
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const activeTab = ref<'directory' | 'tree' | 'fan' | 'media' | 'text' | 'stats' | 'lineage'>(props.defaultTab || 'tree');
const selectedPersonId = ref<string | null>(null);
const currentRootPersonId = ref<string | null>(props.rootPersonId);
const isReimporting = ref(false);
const showReportsModal = ref(false);
const isMobileMenuOpen = ref(false);

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

const switchTab = (tab: 'tree' | 'fan' | 'text' | 'directory' | 'media' | 'stats' | 'lineage') => {
    activeTab.value = tab;
    showReportsModal.value = false;
};
</script>

<template>
    <Head title="Family Tree Archive" />

    <div class="min-h-screen min-h-dvh bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans selection:bg-indigo-500 selection:text-white transition-colors duration-200 flex flex-col">
        <!-- Main Top Bar -->
        <header class="sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 transition-colors duration-200 pt-[env(safe-area-inset-top,0px)]">
            <div class="max-w-[96rem] mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <!-- Left Section: Logo & Title -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 flex items-center justify-center text-white shadow-md">
                            <FolderArchive class="w-5 h-5" />
                        </div>
                        <div>
                            <h1 class="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white leading-none">
                                Family Tree Archive
                            </h1>
                            <p class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 mt-0.5">
                                GEDCOM Genealogy Viewer
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Desktop Navigation Tabs (Hidden on mobile < md) -->
                <nav class="hidden md:flex items-center gap-1 bg-slate-200/80 dark:bg-slate-950/80 p-1.5 rounded-2xl border border-slate-300/80 dark:border-slate-800 text-xs font-bold">
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
                        <span>People Directory</span>
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

                <!-- Right Section: Views & Reports, Theme Switcher, Mobile Burger Button, Desktop User & Logout -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Views & Reports Button (Desktop) -->
                    <button
                        @click="showReportsModal = true"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 transition-all text-xs font-bold shadow-xs cursor-pointer"
                        title="View Archive Reports & Analytics"
                    >
                        <BarChart2 class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        <span>Views & Reports</span>
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

                    <!-- Mobile Burger Menu Toggle Button (< md) -->
                    <button
                        @click="isMobileMenuOpen = !isMobileMenuOpen"
                        class="md:hidden p-2 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer border border-slate-300 dark:border-slate-700"
                        aria-label="Toggle Mobile Menu"
                    >
                        <X v-if="isMobileMenuOpen" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        <Menu v-else class="w-5 h-5 text-slate-700 dark:text-slate-300" />
                    </button>

                    <Link
                        v-if="currentUser?.is_superuser"
                        href="/admin/users"
                        class="hidden lg:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-700 dark:text-purple-300 text-xs font-semibold border border-purple-500/30 transition-colors"
                    >
                        <ShieldCheck class="w-3.5 h-3.5" />
                        User Management
                    </Link>

                    <!-- Desktop User Info & Logout (Hidden on mobile < md) -->
                    <div v-if="currentUser" class="hidden md:flex items-center gap-2.5 pl-2 border-l border-slate-300 dark:border-slate-800">
                        <div class="hidden lg:flex flex-col text-right">
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center justify-end gap-1">
                                {{ currentUser.name }}
                            </span>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 leading-none">{{ currentUser.email }}</span>
                        </div>

                        <!-- Desktop Logout Button -->
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

            <!-- Mobile Burger Menu Slide-Down Drawer -->
            <div
                v-if="isMobileMenuOpen"
                class="md:hidden bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border-b border-slate-200 dark:border-slate-800 px-4 py-4 space-y-3 shadow-2xl animate-in slide-in-from-top-2 duration-200"
            >
                <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-2">
                    Navigation & Views
                </div>

                <div class="grid grid-cols-1 gap-1.5">
                    <button
                        @click="activeTab = 'tree'; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeTab === 'tree' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        <div class="flex items-center gap-3">
                            <GitBranch class="w-4 h-4 text-indigo-400" />
                            <span>Family Tree</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>

                    <button
                        @click="activeTab = 'lineage'; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeTab === 'lineage' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        <div class="flex items-center gap-3">
                            <Network class="w-4 h-4 text-indigo-400" />
                            <span>Full Ancestral Lineage</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>

                    <button
                        @click="activeTab = 'stats'; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeTab === 'stats' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        <div class="flex items-center gap-3">
                            <BarChart2 class="w-4 h-4 text-amber-400" />
                            <span>Statistics & Demographics</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>

                    <button
                        @click="activeTab = 'fan'; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeTab === 'fan' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        <div class="flex items-center gap-3">
                            <PieChart class="w-4 h-4 text-sky-400" />
                            <span>Ancestry Fan Chart</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>

                    <button
                        @click="activeTab = 'text'; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeTab === 'text' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        <div class="flex items-center gap-3">
                            <FileText class="w-4 h-4 text-emerald-400" />
                            <span>Print / Text Report</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>

                    <button
                        @click="activeTab = 'directory'; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeTab === 'directory' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        <div class="flex items-center gap-3">
                            <Users class="w-4 h-4 text-purple-400" />
                            <span>People Directory</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>

                    <button
                        @click="activeTab = 'media'; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeTab === 'media' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    >
                        <div class="flex items-center gap-3">
                            <ImageIcon class="w-4 h-4 text-rose-400" />
                            <span>Media Explorer</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>

                    <button
                        @click="showReportsModal = true; isMobileMenuOpen = false"
                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 cursor-pointer"
                    >
                        <div class="flex items-center gap-3">
                            <BarChart2 class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                            <span>Views & Reports Dashboard</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </button>
                </div>

                <!-- Mobile User Section & Logout Button -->
                <div v-if="currentUser" class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="px-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                        {{ currentUser.name }}
                        <span class="block text-[10px] text-slate-400 font-normal">{{ currentUser.email }}</span>
                    </div>

                    <Link
                        v-if="currentUser?.is_superuser"
                        href="/admin/users"
                        class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-xs font-bold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/30"
                    >
                        <div class="flex items-center gap-3">
                            <ShieldCheck class="w-4 h-4" />
                            <span>User Management</span>
                        </div>
                        <ChevronRight class="w-4 h-4 opacity-50" />
                    </Link>

                    <Link
                        :href="logout()"
                        method="post"
                        as="button"
                        class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 cursor-pointer"
                    >
                        <div class="flex items-center gap-3">
                            <LogOut class="w-4 h-4" />
                            <span>Log out</span>
                        </div>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Body Area -->
        <main class="w-full max-w-[96rem] mx-auto px-2 sm:px-6 lg:px-8 py-2 sm:py-6 pb-[calc(1rem+env(safe-area-inset-bottom,0px))] flex-1 flex flex-col min-h-0">
            <!-- Active Tab Content -->
            <div class="space-y-4 sm:space-y-6 flex-1 flex flex-col min-h-0">
                <!-- Interactive Ancestor/Descendant Family Tree View -->
                <GedcomTreeView
                    v-if="activeTab === 'tree'"
                    :root-person-id="currentRootPersonId"
                    @select-person="selectPerson"
                    @change-root="changeRootPerson"
                />

                <!-- Full Ancestral Lineage Report View -->
                <GedcomLineageView
                    v-else-if="activeTab === 'lineage'"
                    :root-person-id="currentRootPersonId"
                    @select-person="selectPerson"
                    @change-root="changeRootPerson"
                />

                <!-- Permission-Scoped Statistics & Demographics Page -->
                <GedcomStatsView
                    v-else-if="activeTab === 'stats'"
                    @select-person="selectPerson"
                />

                <!-- Radial Ancestry Fan Chart View -->
                <GedcomFanView
                    v-else-if="activeTab === 'fan'"
                    :root-person-id="currentRootPersonId"
                    @select-person="selectPerson"
                    @change-root="changeRootPerson"
                />

                <!-- Printable Text & Outline Report View -->
                <GedcomTextView
                    v-else-if="activeTab === 'text'"
                    :root-person-id="currentRootPersonId"
                    @select-person="selectPerson"
                />

                <!-- People Directory & Search Table -->
                <GedcomDirectory
                    v-else-if="activeTab === 'directory'"
                    @select-person="selectPerson"
                />

                <!-- Media Gallery Explorer -->
                <GedcomMediaGallery
                    v-else-if="activeTab === 'media'"
                    @select-person="selectPerson"
                />
            </div>
        </main>

        <!-- Individual Detail Modal Drawer -->
        <GedcomPersonModal
            :person-id="selectedPersonId"
            @close="selectedPersonId = null"
            @open-tree="openInTree"
        />

        <!-- Views & Reports Switcher Modal Drawer -->
        <div
            v-if="showReportsModal"
            class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 animate-in fade-in duration-150"
            @click.self="showReportsModal = false"
        >
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-2xl w-full p-6 space-y-6 overflow-hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-md">
                            <BarChart2 class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 dark:text-white">
                                Views & Genealogy Reports
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Switch views or access printable reports & analytics
                            </p>
                        </div>
                    </div>
                    <button
                        @click="showReportsModal = false"
                        class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- View Switcher Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Family Tree Card -->
                    <button
                        @click="switchTab('tree')"
                        class="p-4 rounded-2xl border transition-all text-left flex items-start gap-3.5 group cursor-pointer"
                        :class="activeTab === 'tree' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700'"
                    >
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform">
                            <GitBranch class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                Interactive Family Tree
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                Horizontal ancestor and descendant node graph with spouse & children links.
                            </p>
                        </div>
                    </button>

                    <!-- Full Ancestral Lineage Card -->
                    <button
                        @click="switchTab('lineage')"
                        class="p-4 rounded-2xl border transition-all text-left flex items-start gap-3.5 group cursor-pointer"
                        :class="activeTab === 'lineage' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700'"
                    >
                        <div class="w-9 h-9 rounded-xl bg-indigo-700 text-white flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform">
                            <Network class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                Full Ancestral Lineage
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                Complete Ahnentafel list of all direct ancestors across all recorded generations.
                            </p>
                        </div>
                    </button>

                    <!-- Statistics & Demographics Card -->
                    <button
                        @click="switchTab('stats')"
                        class="p-4 rounded-2xl border transition-all text-left flex items-start gap-3.5 group cursor-pointer"
                        :class="activeTab === 'stats' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700'"
                    >
                        <div class="w-9 h-9 rounded-xl bg-amber-600 text-white flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform">
                            <BarChart2 class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                Statistics & Demographics
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                Analytics over surnames, countries, oldest person, married age differences & facts.
                            </p>
                        </div>
                    </button>

                    <!-- Ancestry Fan Chart Card -->
                    <button
                        @click="switchTab('fan')"
                        class="p-4 rounded-2xl border transition-all text-left flex items-start gap-3.5 group cursor-pointer"
                        :class="activeTab === 'fan' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700'"
                    >
                        <div class="w-9 h-9 rounded-xl bg-sky-600 text-white flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform">
                            <PieChart class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                Ancestry Fan Chart
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                Radial arc fan chart showing paternal & maternal lineages up to 5 generations.
                            </p>
                        </div>
                    </button>

                    <!-- Printable Text Report Card -->
                    <button
                        @click="switchTab('text')"
                        class="p-4 rounded-2xl border transition-all text-left flex items-start gap-3.5 group cursor-pointer"
                        :class="activeTab === 'text' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700'"
                    >
                        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform">
                            <FileText class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                Print / Text Report
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                Printable document view formatted for high readability, paper printing & PDF export.
                            </p>
                        </div>
                    </button>

                    <!-- People Directory Card -->
                    <button
                        @click="switchTab('directory')"
                        class="p-4 rounded-2xl border transition-all text-left flex items-start gap-3.5 group cursor-pointer"
                        :class="activeTab === 'directory' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700'"
                    >
                        <div class="w-9 h-9 rounded-xl bg-purple-600 text-white flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform">
                            <Users class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                People Directory
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                Searchable table of all individuals with vital dates, gender, and parents.
                            </p>
                        </div>
                    </button>

                    <!-- Media Explorer Card -->
                    <button
                        @click="switchTab('media')"
                        class="p-4 rounded-2xl border transition-all text-left flex items-start gap-3.5 group cursor-pointer"
                        :class="activeTab === 'media' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700'"
                    >
                        <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-md group-hover:scale-105 transition-transform">
                            <ImageIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                Media Explorer
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                Gallery of historical photos, census documents, and sound files.
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
