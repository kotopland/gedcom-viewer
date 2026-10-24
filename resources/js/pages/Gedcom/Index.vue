<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BarChart2,
    ChevronRight,
    FileText,
    FolderArchive,
    GitBranch,
    Image as ImageIcon,
    LogOut,
    Menu,
    Moon,
    Network,
    PieChart,
    ShieldCheck,
    Sparkles,
    Sun,
    Users,
    X,
} from '@lucide/vue';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

import GedcomDirectory from '@/components/Gedcom/GedcomDirectory.vue';
import GedcomFanView from '@/components/Gedcom/GedcomFanView.vue';
import GedcomHeritageView from '@/components/Gedcom/GedcomHeritageView.vue';
import GedcomLineageView from '@/components/Gedcom/GedcomLineageView.vue';
import GedcomMediaGallery from '@/components/Gedcom/GedcomMediaGallery.vue';
import GedcomPersonModal from '@/components/Gedcom/GedcomPersonModal.vue';
import GedcomStatsView from '@/components/Gedcom/GedcomStatsView.vue';
import GedcomTextView from '@/components/Gedcom/GedcomTextView.vue';
import GedcomTreeView from '@/components/Gedcom/GedcomTreeView.vue';
import { useAppearance } from '@/composables/useAppearance';
import { logout } from '@/routes';
import type { User } from '@/types/auth';

type TabType =
    | 'directory'
    | 'tree'
    | 'heritage'
    | 'fan'
    | 'media'
    | 'text'
    | 'stats'
    | 'lineage';
const validTabs: TabType[] = [
    'directory',
    'tree',
    'heritage',
    'fan',
    'media',
    'text',
    'stats',
    'lineage',
];

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
    defaultTab?: TabType;
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const getInitialTab = (): TabType => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab') as TabType | null;

        if (tabParam && validTabs.includes(tabParam)) {
            return tabParam;
        }

        const sessionStored = sessionStorage.getItem(
            'gedcom_active_tab',
        ) as TabType | null;

        if (sessionStored && validTabs.includes(sessionStored)) {
            return sessionStored;
        }

        const localStored = localStorage.getItem(
            'gedcom_active_tab',
        ) as TabType | null;

        if (localStored && validTabs.includes(localStored)) {
            return localStored;
        }
    }

    return props.defaultTab || 'heritage';
};

const activeTab = ref<TabType>(getInitialTab());

watch(
    activeTab,
    (newTab) => {
        if (typeof window !== 'undefined') {
            sessionStorage.setItem('gedcom_active_tab', newTab);
            localStorage.setItem('gedcom_active_tab', newTab);
            const url = new URL(window.location.href);

            if (url.searchParams.get('tab') !== newTab) {
                url.searchParams.set('tab', newTab);
                window.history.replaceState({}, '', url.toString());
            }
        }
    },
    { immediate: true },
);

const handlePopState = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') as TabType | null;

    if (
        tabParam &&
        validTabs.includes(tabParam) &&
        activeTab.value !== tabParam
    ) {
        activeTab.value = tabParam;
    }
};

onMounted(() => {
    window.addEventListener('popstate', handlePopState);
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
});

const selectedPersonId = ref<string | null>(null);

const getInitialFocusId = () => {
    if (typeof window !== 'undefined') {
        const stored = sessionStorage.getItem('gedcom_tree_focus_id');

        if (stored) {
            return stored;
        }
    }

    return props.rootPersonId;
};

const currentRootPersonId = ref<string | null>(getInitialFocusId());
const showReportsModal = ref(false);
const isMobileMenuOpen = ref(false);

const changeRootPerson = (id: string) => {
    currentRootPersonId.value = id;

    if (typeof window !== 'undefined') {
        sessionStorage.setItem('gedcom_tree_focus_id', id);
    }
};

const selectPerson = (id: string) => {
    selectedPersonId.value = id;
};

const openInTree = (id: string) => {
    changeRootPerson(id);
    activeTab.value = 'tree';
    selectedPersonId.value = null;
};

const switchTab = (
    tab:
        | 'tree'
        | 'heritage'
        | 'fan'
        | 'text'
        | 'directory'
        | 'media'
        | 'stats'
        | 'lineage',
) => {
    activeTab.value = tab;
    showReportsModal.value = false;
};
</script>

<template>
    <Head title="Family Tree Archive" />

    <div
        class="flex min-h-dvh min-h-screen flex-col bg-slate-100 font-sans text-slate-900 transition-colors duration-200 selection:bg-indigo-500 selection:text-white dark:bg-slate-950 dark:text-slate-100"
    >
        <!-- Main Top Bar -->
        <header
            class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 pt-[env(safe-area-inset-top,0px)] backdrop-blur-md transition-colors duration-200 dark:border-slate-800 dark:bg-slate-900/90"
        >
            <div
                class="mx-auto flex h-16 max-w-[96rem] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
            >
                <!-- Left Section: Logo & Title -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 text-white shadow-md"
                        >
                            <FolderArchive class="h-5 w-5" />
                        </div>
                        <div>
                            <h1
                                class="text-sm leading-none font-extrabold tracking-tight text-slate-900 dark:text-white"
                            >
                                Family Tree Archive
                            </h1>
                            <p
                                class="mt-0.5 text-[11px] font-medium text-indigo-600 dark:text-indigo-400"
                            >
                                GEDCOM Genealogy Viewer
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Desktop Navigation Tabs (Hidden on mobile < md) -->
                <nav
                    class="hidden items-center gap-1 rounded-2xl border border-slate-300/80 bg-slate-200/80 p-1.5 text-xs font-bold md:flex dark:border-slate-800 dark:bg-slate-950/80"
                >
                    <button
                        @click="activeTab = 'heritage'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 transition-colors sm:px-4"
                        :class="
                            activeTab === 'heritage'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                        "
                    >
                        <Sparkles class="h-4 w-4 text-amber-300" />
                        <span>Heritage Chart</span>
                    </button>
                    <button
                        @click="activeTab = 'tree'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 transition-colors sm:px-4"
                        :class="
                            activeTab === 'tree'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                        "
                    >
                        <GitBranch class="h-4 w-4" />
                        <span>Family Tree</span>
                    </button>
                    <button
                        @click="activeTab = 'directory'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 transition-colors sm:px-4"
                        :class="
                            activeTab === 'directory'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                        "
                    >
                        <Users class="h-4 w-4" />
                        <span>People Directory</span>
                    </button>
                    <button
                        @click="activeTab = 'media'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 transition-colors sm:px-4"
                        :class="
                            activeTab === 'media'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                        "
                    >
                        <ImageIcon class="h-4 w-4" />
                        <span>Media Explorer</span>
                    </button>
                </nav>

                <!-- Right Section: Views & Reports, Theme Switcher, Mobile Burger Button, Desktop User & Logout -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Views & Reports Button (Desktop) -->
                    <button
                        @click="showReportsModal = true"
                        class="hidden cursor-pointer items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 shadow-xs transition-all hover:bg-indigo-100 sm:inline-flex dark:border-indigo-800/60 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900/80"
                        title="View Archive Reports & Analytics"
                    >
                        <BarChart2
                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                        />
                        <span>Views & Reports</span>
                    </button>

                    <!-- Light / Dark Theme Switcher Button -->
                    <button
                        @click="toggleTheme"
                        class="cursor-pointer rounded-xl border border-slate-300/80 bg-slate-200 p-2 text-slate-700 transition-colors hover:bg-slate-300 hover:text-slate-900 dark:border-slate-700/60 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                        :title="
                            resolvedAppearance === 'dark'
                                ? 'Switch to Light Mode'
                                : 'Switch to Dark Mode'
                        "
                    >
                        <Sun
                            v-if="resolvedAppearance === 'dark'"
                            class="h-4 w-4 text-amber-400"
                        />
                        <Moon
                            v-else
                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                        />
                    </button>

                    <!-- Mobile Burger Menu Toggle Button (< md) -->
                    <button
                        @click="isMobileMenuOpen = !isMobileMenuOpen"
                        class="cursor-pointer rounded-xl border border-slate-300 bg-slate-200 p-2 text-slate-700 transition-colors hover:text-slate-900 md:hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-white"
                        aria-label="Toggle Mobile Menu"
                    >
                        <X
                            v-if="isMobileMenuOpen"
                            class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                        />
                        <Menu
                            v-else
                            class="h-5 w-5 text-slate-700 dark:text-slate-300"
                        />
                    </button>

                    <Link
                        v-if="currentUser?.is_superuser"
                        href="/admin/users"
                        class="hidden items-center gap-1.5 rounded-xl border border-purple-500/30 bg-purple-500/10 px-3 py-1.5 text-xs font-semibold text-purple-700 transition-colors hover:bg-purple-500/20 lg:inline-flex dark:text-purple-300"
                    >
                        <ShieldCheck class="h-3.5 w-3.5" />
                        User Management
                    </Link>

                    <!-- Desktop User Info & Logout (Hidden on mobile < md) -->
                    <div
                        v-if="currentUser"
                        class="hidden items-center gap-2.5 border-l border-slate-300 pl-2 md:flex dark:border-slate-800"
                    >
                        <div class="hidden flex-col text-right lg:flex">
                            <span
                                class="flex items-center justify-end gap-1 text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ currentUser.name }}
                            </span>
                            <span
                                class="text-[10px] leading-none text-slate-500 dark:text-slate-400"
                                >{{ currentUser.email }}</span
                            >
                        </div>

                        <!-- Desktop Logout Button -->
                        <Link
                            :href="logout()"
                            method="post"
                            as="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-300 bg-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-rose-300"
                            title="Log out"
                        >
                            <LogOut class="h-4 w-4" />
                            <span class="hidden sm:inline">Log out</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Mobile Burger Menu Slide-Down Drawer -->
            <div
                v-if="isMobileMenuOpen"
                class="animate-in space-y-3 border-b border-slate-200 bg-white/95 px-4 py-4 shadow-2xl backdrop-blur-xl duration-200 slide-in-from-top-2 md:hidden dark:border-slate-800 dark:bg-slate-900/95"
            >
                <div
                    class="px-2 text-[11px] font-extrabold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                >
                    Navigation & Views
                </div>

                <div class="grid grid-cols-1 gap-1.5">
                    <button
                        @click="
                            activeTab = 'heritage';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'heritage'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <Sparkles class="h-4 w-4 text-amber-300" />
                            <span>Heritage Pedigree Chart</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            activeTab = 'tree';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'tree'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <GitBranch class="h-4 w-4 text-indigo-400" />
                            <span>Family Tree</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            activeTab = 'lineage';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'lineage'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <Network class="h-4 w-4 text-indigo-400" />
                            <span>Full Ancestral Lineage</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            activeTab = 'stats';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'stats'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <BarChart2 class="h-4 w-4 text-amber-400" />
                            <span>Statistics & Demographics</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            activeTab = 'fan';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'fan'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <PieChart class="h-4 w-4 text-sky-400" />
                            <span>Ancestry Fan Chart</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            activeTab = 'text';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'text'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <FileText class="h-4 w-4 text-emerald-400" />
                            <span>Print / Text Report</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            activeTab = 'directory';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'directory'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <Users class="h-4 w-4 text-purple-400" />
                            <span>People Directory</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            activeTab = 'media';
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-4 py-3 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'media'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                        "
                    >
                        <div class="flex items-center gap-3">
                            <ImageIcon class="h-4 w-4 text-rose-400" />
                            <span>Media Explorer</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>

                    <button
                        @click="
                            showReportsModal = true;
                            isMobileMenuOpen = false;
                        "
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs font-bold text-indigo-700 dark:border-indigo-800/60 dark:bg-indigo-950/60 dark:text-indigo-300"
                    >
                        <div class="flex items-center gap-3">
                            <BarChart2
                                class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                            />
                            <span>Views & Reports Dashboard</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </button>
                </div>

                <!-- Mobile User Section & Logout Button -->
                <div
                    v-if="currentUser"
                    class="space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800"
                >
                    <div
                        class="px-2 text-xs font-bold text-slate-800 dark:text-slate-200"
                    >
                        {{ currentUser.name }}
                        <span
                            class="block text-[10px] font-normal text-slate-400"
                            >{{ currentUser.email }}</span
                        >
                    </div>

                    <Link
                        v-if="currentUser?.is_superuser"
                        href="/admin/users"
                        class="flex w-full items-center justify-between rounded-xl border border-purple-500/30 bg-purple-500/10 px-4 py-2.5 text-xs font-bold text-purple-700 dark:text-purple-300"
                    >
                        <div class="flex items-center gap-3">
                            <ShieldCheck class="h-4 w-4" />
                            <span>User Management</span>
                        </div>
                        <ChevronRight class="h-4 w-4 opacity-50" />
                    </Link>

                    <Link
                        :href="logout()"
                        method="post"
                        as="button"
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-2.5 text-xs font-bold text-rose-600 dark:text-rose-400"
                    >
                        <div class="flex items-center gap-3">
                            <LogOut class="h-4 w-4" />
                            <span>Log out</span>
                        </div>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Body Area -->
        <main
            class="mx-auto flex min-h-0 w-full max-w-[96rem] flex-1 flex-col px-2 py-2 pb-[calc(1rem+env(safe-area-inset-bottom,0px))] sm:px-6 sm:py-6 lg:px-8"
        >
            <!-- Active Tab Content -->
            <div class="flex min-h-0 flex-1 flex-col space-y-4 sm:space-y-6">
                <!-- Classic Heritage Pedigree Chart View -->
                <GedcomHeritageView
                    v-if="activeTab === 'heritage'"
                    :root-person-id="currentRootPersonId"
                    @select-person="selectPerson"
                    @change-root="changeRootPerson"
                />

                <!-- Interactive Ancestor/Descendant Family Tree View -->
                <GedcomTreeView
                    v-else-if="activeTab === 'tree'"
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
            @select-person="selectPerson"
            @open-tree="openInTree"
            @open-in-tree="openInTree"
        />

        <!-- Views & Reports Switcher Modal Drawer -->
        <div
            v-if="showReportsModal"
            class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm duration-150 fade-in"
            @click.self="showReportsModal = false"
        >
            <div
                class="w-full max-w-2xl space-y-6 overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-md"
                        >
                            <BarChart2 class="h-5 w-5" />
                        </div>
                        <div>
                            <h2
                                class="text-base font-extrabold text-slate-900 dark:text-white"
                            >
                                Views & Genealogy Reports
                            </h2>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Switch views or access printable reports &
                                analytics
                            </p>
                        </div>
                    </div>
                    <button
                        @click="showReportsModal = false"
                        class="cursor-pointer rounded-xl bg-slate-100 p-2 text-slate-400 transition-colors hover:text-slate-700 dark:bg-slate-800 dark:hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- View Switcher Cards Grid -->
                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <!-- Heritage Pedigree Chart Card -->
                    <button
                        @click="switchTab('heritage')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'heritage'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-amber-400 shadow-md transition-transform group-hover:scale-105 dark:bg-slate-800"
                        >
                            <Sparkles class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <h3
                                    class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                                >
                                    Heritage Pedigree Chart
                                </h3>
                                <span
                                    class="rounded-full border border-amber-500/30 bg-amber-500/20 px-1.5 py-0.5 text-[9px] font-extrabold text-amber-700 uppercase dark:text-amber-300"
                                >
                                    Classic
                                </span>
                            </div>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Classical pedigree chart with circular
                                portraits, dark stylized banners & vital dates.
                            </p>
                        </div>
                    </button>

                    <!-- Family Tree Card -->
                    <button
                        @click="switchTab('tree')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'tree'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md transition-transform group-hover:scale-105"
                        >
                            <GitBranch class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                Interactive Family Tree
                            </h3>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Horizontal ancestor and descendant node graph
                                with spouse & children links.
                            </p>
                        </div>
                    </button>

                    <!-- Full Ancestral Lineage Card -->
                    <button
                        @click="switchTab('lineage')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'lineage'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-700 text-white shadow-md transition-transform group-hover:scale-105"
                        >
                            <Network class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                Full Ancestral Lineage
                            </h3>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Complete Ahnentafel list of all direct ancestors
                                across all recorded generations.
                            </p>
                        </div>
                    </button>

                    <!-- Statistics & Demographics Card -->
                    <button
                        @click="switchTab('stats')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'stats'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-600 text-white shadow-md transition-transform group-hover:scale-105"
                        >
                            <BarChart2 class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                Statistics & Demographics
                            </h3>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Analytics over surnames, countries, oldest
                                person, married age differences & facts.
                            </p>
                        </div>
                    </button>

                    <!-- Ancestry Fan Chart Card -->
                    <button
                        @click="switchTab('fan')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'fan'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-600 text-white shadow-md transition-transform group-hover:scale-105"
                        >
                            <PieChart class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                Ancestry Fan Chart
                            </h3>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Radial arc fan chart showing paternal & maternal
                                lineages up to 5 generations.
                            </p>
                        </div>
                    </button>

                    <!-- Printable Text Report Card -->
                    <button
                        @click="switchTab('text')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'text'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md transition-transform group-hover:scale-105"
                        >
                            <FileText class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                Print / Text Report
                            </h3>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Printable document view formatted for high
                                readability, paper printing & PDF export.
                            </p>
                        </div>
                    </button>

                    <!-- People Directory Card -->
                    <button
                        @click="switchTab('directory')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'directory'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-purple-600 text-white shadow-md transition-transform group-hover:scale-105"
                        >
                            <Users class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                People Directory
                            </h3>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Searchable table of all individuals with vital
                                dates, gender, and parents.
                            </p>
                        </div>
                    </button>

                    <!-- Media Explorer Card -->
                    <button
                        @click="switchTab('media')"
                        class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4 text-left transition-all"
                        :class="
                            activeTab === 'media'
                                ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-950/40'
                                : 'border-slate-200 bg-slate-50 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-700'
                        "
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-white shadow-md transition-transform group-hover:scale-105"
                        >
                            <ImageIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                            >
                                Media Explorer
                            </h3>
                            <p
                                class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Gallery of historical photos, census documents,
                                and sound files.
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
