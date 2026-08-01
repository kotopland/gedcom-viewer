<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
    Printer, Search, User, Layers, Calendar, MapPin, Heart, GitBranch,
    FileText, Check, ChevronDown, Sparkles, BookOpen
} from '@lucide/vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'open-in-tree', id: string): void;
}>();

const focusId = ref<string | null>(props.rootPersonId);
const generationDepth = ref<number>(3);
const includeNotes = ref<boolean>(true);
const includeEvents = ref<boolean>(true);

const loading = ref<boolean>(false);
const treeData = ref<any>(null);
const focusPersonDetails = ref<any>(null);
const searchInput = ref<string>('');
const searchResults = ref<any[]>([]);
const showSearchDropdown = ref<boolean>(false);

let searchTimer: any = null;

const fetchReportData = async (id: string) => {
    if (!id) return;
    loading.value = true;
    try {
        const [treeRes, personRes] = await Promise.all([
            fetch(`/api/gedcom/tree/${id}?ancestors=${generationDepth.value}&descendants=${generationDepth.value}`),
            fetch(`/api/gedcom/person/${id}`)
        ]);

        if (treeRes.ok) {
            treeData.value = await treeRes.json();
        }
        if (personRes.ok) {
            focusPersonDetails.value = await personRes.json();
        }
    } catch (e) {
        console.error('Failed to load report data:', e);
    } finally {
        loading.value = false;
    }
};

watch(() => props.rootPersonId, (newId) => {
    if (newId) {
        focusId.value = newId;
        fetchReportData(newId);
    }
});

watch(generationDepth, () => {
    if (focusId.value) {
        fetchReportData(focusId.value);
    }
});

onMounted(() => {
    if (focusId.value) {
        fetchReportData(focusId.value);
    }
});

const onSearchInput = () => {
    clearTimeout(searchTimer);
    if (!searchInput.value.trim()) {
        searchResults.value = [];
        showSearchDropdown.value = false;
        return;
    }
    searchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/api/gedcom/search?q=${encodeURIComponent(searchInput.value)}&limit=8`);
            if (res.ok) {
                const data = await res.json();
                searchResults.value = data.data;
                showSearchDropdown.value = true;
            }
        } catch (e) {
            console.error(e);
        }
    }, 250);
};

const selectSearchResult = (person: any) => {
    focusId.value = person.id;
    searchInput.value = person.name;
    showSearchDropdown.value = false;
    fetchReportData(person.id);
};

const triggerPrint = () => {
    window.print();
};

const mainPerson = computed(() => {
    if (focusPersonDetails.value?.person) {
        return focusPersonDetails.value.person;
    }
    return treeData.value?.ancestors || treeData.value?.descendants || null;
});

const formattedCurrentDate = computed(() => {
    return new Date().toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
});

const ancestorGenerations = computed(() => {
    if (!treeData.value?.ancestors?.parents) return [];

    const result: { gen: number; title: string; people: any[] }[] = [];

    const romanNumerals = ['II', 'III', 'IV', 'V', 'VI'];
    const genTitles = ['Parents', 'Grandparents', 'Great-Grandparents', '2nd Great-Grandparents', '3rd Great-Grandparents'];

    let currentLevelPeople = treeData.value.ancestors.parents;
    let level = 1;

    while (currentLevelPeople && currentLevelPeople.length > 0 && level <= generationDepth.value) {
        result.push({
            gen: level + 1,
            title: `Generation ${romanNumerals[level - 1] || level + 1}: ${genTitles[level - 1] || 'Ancestors'}`,
            people: currentLevelPeople
        });

        const nextLevelPeople: any[] = [];
        for (const p of currentLevelPeople) {
            if (p.parents && p.parents.length > 0) {
                nextLevelPeople.push(...p.parents);
            }
        }
        currentLevelPeople = nextLevelPeople;
        level++;
    }

    return result;
});
</script>

<template>
    <div class="space-y-6">
        <!-- Control Bar (Hidden when printing) -->
        <div class="print:hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <FileText class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        Printable Genealogy Report & Outline
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        High-readability formatted text report ready for paper printing & export
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="triggerPrint"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md transition-all active:scale-95 cursor-pointer"
                    >
                        <Printer class="w-4 h-4" />
                        Print / Save as PDF
                    </button>
                </div>
            </div>

            <!-- Report Configuration Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                <!-- Person Selector -->
                <div class="relative">
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
                        Report Focus Individual:
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            v-model="searchInput"
                            @input="onSearchInput"
                            placeholder="Search person..."
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-hidden focus:border-indigo-500 transition-colors pl-8"
                        />
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                    </div>

                    <!-- Search Results Dropdown -->
                    <div
                        v-if="showSearchDropdown && searchResults.length > 0"
                        class="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-30 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700"
                    >
                        <div
                            v-for="res in searchResults"
                            :key="res.id"
                            @click="selectSearchResult(res)"
                            class="p-2 hover:bg-indigo-50 dark:hover:bg-slate-700 cursor-pointer text-xs flex items-center justify-between"
                        >
                            <span class="font-bold text-slate-900 dark:text-white truncate">{{ res.name }}</span>
                            <span class="text-[10px] text-slate-400 shrink-0 ml-2">{{ res.birth_year || '?' }} – {{ res.death_year || '?' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Generation Depth Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
                        Generation Depth:
                    </label>
                    <select
                        v-model.number="generationDepth"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-900 dark:text-white focus:outline-hidden focus:border-indigo-500 transition-colors cursor-pointer"
                    >
                        <option :value="1">1 Generation (Focus Person & Parents)</option>
                        <option :value="2">2 Generations (Grandparents)</option>
                        <option :value="3">3 Generations (Great-Grandparents)</option>
                        <option :value="4">4 Generations</option>
                        <option :value="5">5 Generations</option>
                    </select>
                </div>

                <!-- Inclusion Options -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
                        Report Sections:
                    </label>
                    <div class="flex items-center gap-3 pt-1 text-xs">
                        <label class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input type="checkbox" v-model="includeEvents" class="rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Events
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input type="checkbox" v-model="includeNotes" class="rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Notes
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-12 text-center text-slate-500 dark:text-slate-400">
            <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <p class="mt-3 text-xs font-semibold">Generating printable genealogy report...</p>
        </div>

        <!-- Printable Document Container -->
        <div
            v-else-if="mainPerson"
            class="bg-white text-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 sm:p-12 shadow-2xl space-y-8 print:shadow-none print:border-none print:p-0 print:m-0 print:space-y-6 font-serif"
        >
            <!-- Document Header -->
            <div class="border-b-2 border-slate-900 pb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black font-sans tracking-tight text-slate-900">
                        GENEALOGICAL FAMILY REPORT
                    </h1>
                    <p class="text-sm font-sans font-medium text-indigo-800 mt-1">
                        Family Archive Record — <span class="font-bold text-slate-900">{{ mainPerson.name }}</span>
                    </p>
                </div>
                <div class="text-right text-xs font-sans text-slate-500">
                    <div>Generated: <span class="font-bold text-slate-800">{{ formattedCurrentDate }}</span></div>
                    <div>Source: GEDCOM Family Archive</div>
                </div>
            </div>

            <!-- Section I: Focus Individual Primary Profile -->
            <div class="space-y-3 print:break-inside-avoid">
                <h2 class="text-base font-sans font-bold uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1 flex items-center justify-between">
                    <span>GENERATION I — PRIMARY INDIVIDUAL</span>
                    <span class="text-xs font-normal text-slate-500">ID: {{ mainPerson.id }}</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm font-sans bg-slate-50 p-4 rounded-2xl border border-slate-200 print:bg-white print:p-0 print:border-none">
                    <div class="space-y-1.5">
                        <div><span class="font-bold text-slate-700">Full Name:</span> <span class="font-semibold text-slate-900">{{ mainPerson.name }}</span></div>
                        <div><span class="font-bold text-slate-700">Gender:</span> <span>{{ mainPerson.sex === 'M' ? 'Male (♂)' : mainPerson.sex === 'F' ? 'Female (♀)' : 'Unknown' }}</span></div>
                        <div><span class="font-bold text-slate-700">Birth:</span> <span>{{ mainPerson.birth_date || 'Unknown' }} <template v-if="mainPerson.birth_place">({{ mainPerson.birth_place }})</template></span></div>
                        <div><span class="font-bold text-slate-700">Death:</span> <span>{{ mainPerson.death_date || 'Living / Present' }} <template v-if="mainPerson.death_place">({{ mainPerson.death_place }})</template></span></div>
                    </div>
                    <div class="space-y-1.5">
                        <div v-if="focusPersonDetails?.relations?.spouses?.length">
                            <span class="font-bold text-slate-700">Spouse(s):</span>
                            <ul class="list-disc list-inside mt-0.5 font-medium">
                                <li v-for="sp in focusPersonDetails.relations.spouses" :key="sp.id">
                                    {{ sp.name }} <template v-if="sp.birth_year">({{ sp.birth_year }})</template>
                                </li>
                            </ul>
                        </div>
                        <div v-if="focusPersonDetails?.relations?.parents?.length">
                            <span class="font-bold text-slate-700">Parents:</span>
                            <ul class="list-disc list-inside mt-0.5 font-medium">
                                <li v-for="par in focusPersonDetails.relations.parents" :key="par.id">
                                    {{ par.name }} <template v-if="par.birth_year">({{ par.birth_year }})</template>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section II: Chronological Events Timeline (if enabled) -->
            <div v-if="includeEvents && focusPersonDetails?.person?.events?.length" class="space-y-3 print:break-inside-avoid">
                <h2 class="text-base font-sans font-bold uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1">
                    LIFE EVENTS & CHRONOLOGY
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs font-sans text-left border-collapse border border-slate-200 print:border-slate-400">
                        <thead class="bg-slate-100 print:bg-slate-200 text-slate-800 uppercase text-[10px] font-bold">
                            <tr>
                                <th class="p-2 border border-slate-200 print:border-slate-400">Date / Year</th>
                                <th class="p-2 border border-slate-200 print:border-slate-400">Event Type</th>
                                <th class="p-2 border border-slate-200 print:border-slate-400">Location</th>
                                <th class="p-2 border border-slate-200 print:border-slate-400">Details / Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="ev in focusPersonDetails.person.events" :key="ev.id" class="hover:bg-slate-50 print:hover:bg-transparent">
                                <td class="p-2 font-bold border border-slate-200 print:border-slate-400 whitespace-nowrap">{{ ev.date || ev.year || '—' }}</td>
                                <td class="p-2 font-semibold border border-slate-200 print:border-slate-400">{{ ev.title }}</td>
                                <td class="p-2 border border-slate-200 print:border-slate-400">{{ ev.place || '—' }}</td>
                                <td class="p-2 border border-slate-200 print:border-slate-400">{{ ev.note || ev.value || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section III: Ancestry Lineage Outline (Generations II - V) -->
            <div v-if="ancestorGenerations.length > 0" class="space-y-5 print:break-inside-avoid">
                <h2 class="text-base font-sans font-bold uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1">
                    ANCESTRY LINEAGE OUTLINE
                </h2>

                <div class="space-y-6">
                    <div v-for="gen in ancestorGenerations" :key="gen.gen" class="space-y-2">
                        <h3 class="text-xs font-sans font-bold uppercase tracking-wide text-indigo-900 bg-indigo-50 print:bg-slate-100 px-3 py-1 rounded-lg border border-indigo-100 print:border-slate-300">
                            {{ gen.title }}
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pl-2 sm:pl-4 font-sans text-xs">
                            <div
                                v-for="anc in gen.people"
                                :key="anc.id"
                                class="p-3 rounded-xl border border-slate-200 bg-white print:border-slate-300 space-y-1"
                            >
                                <div class="font-bold text-sm text-slate-900 flex items-center justify-between">
                                    <span>{{ anc.name }}</span>
                                    <span class="text-[10px] text-slate-500 font-normal">ID: {{ anc.id }}</span>
                                </div>
                                <div class="text-slate-700">
                                    <span class="font-semibold">Birth:</span> {{ anc.birth_date || anc.birth_year || 'Unknown' }}
                                    <template v-if="anc.birth_place">in {{ anc.birth_place }}</template>
                                </div>
                                <div class="text-slate-700">
                                    <span class="font-semibold">Death:</span> {{ anc.death_date || anc.death_year || 'Present' }}
                                    <template v-if="anc.death_place">in {{ anc.death_place }}</template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section IV: Children & Descendants -->
            <div v-if="focusPersonDetails?.relations?.children?.length" class="space-y-3 print:break-inside-avoid">
                <h2 class="text-base font-sans font-bold uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1">
                    CHILDREN & IMMEDIATE DESCENDANTS
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-sans text-xs">
                    <div
                        v-for="child in focusPersonDetails.relations.children"
                        :key="child.id"
                        class="p-3 rounded-xl border border-slate-200 bg-white print:border-slate-300 space-y-1"
                    >
                        <div class="font-bold text-sm text-slate-900">
                            {{ child.name }}
                        </div>
                        <div class="text-slate-700">
                            <span class="font-semibold">Born:</span> {{ child.birth_year || '?' }} –
                            <span class="font-semibold">Died:</span> {{ child.death_year || 'Present' }}
                        </div>
                        <div v-if="child.birth_place" class="text-slate-500 text-[11px]">
                            Place: {{ child.birth_place }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section V: Biographical Notes & Remarks (if enabled) -->
            <div v-if="includeNotes && (mainPerson.notes || focusPersonDetails?.person?.notes)" class="space-y-2 print:break-inside-avoid">
                <h2 class="text-base font-sans font-bold uppercase tracking-wider text-slate-900 border-b border-slate-300 pb-1">
                    BIOGRAPHICAL NOTES & REMARKS
                </h2>

                <div class="text-xs font-sans leading-relaxed text-slate-800 bg-slate-50 print:bg-white p-4 rounded-xl border border-slate-200 print:border-none whitespace-pre-line">
                    {{ mainPerson.notes || focusPersonDetails?.person?.notes }}
                </div>
            </div>

            <!-- Footer Attribution -->
            <div class="border-t border-slate-300 pt-4 text-center text-[11px] font-sans text-slate-500">
                End of Genealogical Report for {{ mainPerson.name }} — Topland Family Tree Archive
            </div>
        </div>
    </div>
</template>
