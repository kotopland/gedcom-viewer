<script setup lang="ts">
import { FileText, Printer, Search } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

defineEmits<{
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
    if (!id) {
        return;
    }

    loading.value = true;

    try {
        const [treeRes, personRes] = await Promise.all([
            fetch(
                `/api/gedcom/tree/${id}?ancestors=${generationDepth.value}&descendants=${generationDepth.value}`,
            ),
            fetch(`/api/gedcom/person/${id}`),
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

watch(
    () => props.rootPersonId,
    (newId) => {
        if (newId) {
            focusId.value = newId;
            fetchReportData(newId);
        }
    },
);

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
            const res = await fetch(
                `/api/gedcom/search?q=${encodeURIComponent(searchInput.value)}&limit=8`,
            );

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
        day: 'numeric',
    });
});

const ancestorGenerations = computed(() => {
    if (!treeData.value?.ancestors?.parents) {
        return [];
    }

    const result: { gen: number; title: string; people: any[] }[] = [];

    const romanNumerals = ['II', 'III', 'IV', 'V', 'VI'];
    const genTitles = [
        'Parents',
        'Grandparents',
        'Great-Grandparents',
        '2nd Great-Grandparents',
        '3rd Great-Grandparents',
    ];

    let currentLevelPeople = treeData.value.ancestors.parents;
    let level = 1;

    while (
        currentLevelPeople &&
        currentLevelPeople.length > 0 &&
        level <= generationDepth.value
    ) {
        result.push({
            gen: level + 1,
            title: `Generation ${romanNumerals[level - 1] || level + 1}: ${genTitles[level - 1] || 'Ancestors'}`,
            people: currentLevelPeople,
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
        <div
            class="space-y-4 rounded-3xl border border-slate-200 bg-white p-4 shadow-xl sm:p-5 dark:border-slate-800 dark:bg-slate-900 print:hidden"
        >
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h2
                        class="flex items-center gap-2 text-base font-extrabold text-slate-900 dark:text-white"
                    >
                        <FileText
                            class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                        />
                        Printable Genealogy Report & Outline
                    </h2>
                    <p
                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        High-readability formatted text report ready for paper
                        printing & export
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="triggerPrint"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                    >
                        <Printer class="h-4 w-4" />
                        Print / Save as PDF
                    </button>
                </div>
            </div>

            <!-- Report Configuration Controls -->
            <div
                class="grid grid-cols-1 gap-3 border-t border-slate-200 pt-3 sm:grid-cols-3 dark:border-slate-800"
            >
                <!-- Person Selector -->
                <div class="relative">
                    <label
                        class="mb-1 block text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Report Focus Individual:
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            v-model="searchInput"
                            @input="onSearchInput"
                            placeholder="Search person..."
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-1.5 pl-8 text-xs text-slate-900 transition-colors focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        <Search
                            class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-400"
                        />
                    </div>

                    <!-- Search Results Dropdown -->
                    <div
                        v-if="showSearchDropdown && searchResults.length > 0"
                        class="absolute top-full right-0 left-0 z-30 mt-1 max-h-48 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <div
                            v-for="res in searchResults"
                            :key="res.id"
                            @click="selectSearchResult(res)"
                            class="flex cursor-pointer items-center justify-between p-2 text-xs hover:bg-indigo-50 dark:hover:bg-slate-700"
                        >
                            <span
                                class="truncate font-bold text-slate-900 dark:text-white"
                                >{{ res.name }}</span
                            >
                            <span
                                class="ml-2 shrink-0 text-[10px] text-slate-400"
                                >{{ res.birth_year || '?' }} –
                                {{ res.death_year || '?' }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Generation Depth Selector -->
                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Generation Depth:
                    </label>
                    <select
                        v-model.number="generationDepth"
                        class="w-full cursor-pointer rounded-xl border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-900 transition-colors focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option :value="1">
                            1 Generation (Focus Person & Parents)
                        </option>
                        <option :value="2">2 Generations (Grandparents)</option>
                        <option :value="3">
                            3 Generations (Great-Grandparents)
                        </option>
                        <option :value="4">4 Generations</option>
                        <option :value="5">5 Generations</option>
                    </select>
                </div>

                <!-- Inclusion Options -->
                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Report Sections:
                    </label>
                    <div class="flex items-center gap-3 pt-1 text-xs">
                        <label
                            class="inline-flex cursor-pointer items-center gap-1.5 text-slate-700 dark:text-slate-300"
                        >
                            <input
                                type="checkbox"
                                v-model="includeEvents"
                                class="rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Events
                        </label>
                        <label
                            class="inline-flex cursor-pointer items-center gap-1.5 text-slate-700 dark:text-slate-300"
                        >
                            <input
                                type="checkbox"
                                v-model="includeNotes"
                                class="rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Notes
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div
            v-if="loading"
            class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
        >
            <div
                class="mx-auto h-8 w-8 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent"
            ></div>
            <p class="mt-3 text-xs font-semibold">
                Generating printable genealogy report...
            </p>
        </div>

        <!-- Printable Document Container -->
        <div
            v-else-if="mainPerson"
            class="space-y-8 rounded-3xl border border-slate-200 bg-white p-8 font-serif text-slate-900 shadow-2xl sm:p-12 dark:border-slate-800 print:m-0 print:space-y-6 print:border-none print:p-0 print:shadow-none"
        >
            <!-- Document Header -->
            <div
                class="flex items-start justify-between gap-4 border-b-2 border-slate-900 pb-6"
            >
                <div>
                    <h1
                        class="font-sans text-2xl font-black tracking-tight text-slate-900 sm:text-3xl"
                    >
                        GENEALOGICAL FAMILY REPORT
                    </h1>
                    <p
                        class="mt-1 font-sans text-sm font-medium text-indigo-800"
                    >
                        Family Archive Record —
                        <span class="font-bold text-slate-900">{{
                            mainPerson.name
                        }}</span>
                    </p>
                </div>
                <div class="text-right font-sans text-xs text-slate-500">
                    <div>
                        Generated:
                        <span class="font-bold text-slate-800">{{
                            formattedCurrentDate
                        }}</span>
                    </div>
                    <div>Source: GEDCOM Family Archive</div>
                </div>
            </div>

            <!-- Section I: Focus Individual Primary Profile -->
            <div class="space-y-3 print:break-inside-avoid">
                <h2
                    class="flex items-center justify-between border-b border-slate-300 pb-1 font-sans text-base font-bold tracking-wider text-slate-900 uppercase"
                >
                    <span>GENERATION I — PRIMARY INDIVIDUAL</span>
                    <span class="text-xs font-normal text-slate-500"
                        >ID: {{ mainPerson.id }}</span
                    >
                </h2>

                <div
                    class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 font-sans text-sm sm:grid-cols-2 print:border-none print:bg-white print:p-0"
                >
                    <div class="space-y-1.5">
                        <div>
                            <span class="font-bold text-slate-700"
                                >Full Name:</span
                            >
                            <span class="font-semibold text-slate-900">{{
                                mainPerson.name
                            }}</span>
                        </div>
                        <div>
                            <span class="font-bold text-slate-700"
                                >Gender:</span
                            >
                            <span>{{
                                mainPerson.sex === 'M'
                                    ? 'Male (♂)'
                                    : mainPerson.sex === 'F'
                                      ? 'Female (♀)'
                                      : 'Unknown'
                            }}</span>
                        </div>
                        <div>
                            <span class="font-bold text-slate-700">Birth:</span>
                            <span
                                >{{ mainPerson.birth_date || 'Unknown' }}
                                <template v-if="mainPerson.birth_place"
                                    >({{ mainPerson.birth_place }})</template
                                ></span
                            >
                        </div>
                        <div>
                            <span class="font-bold text-slate-700">Death:</span>
                            <span
                                >{{
                                    mainPerson.death_date || 'Living / Present'
                                }}
                                <template v-if="mainPerson.death_place"
                                    >({{ mainPerson.death_place }})</template
                                ></span
                            >
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div
                            v-if="
                                focusPersonDetails?.relations?.spouses?.length
                            "
                        >
                            <span class="font-bold text-slate-700"
                                >Spouse(s):</span
                            >
                            <ul
                                class="mt-0.5 list-inside list-disc font-medium"
                            >
                                <li
                                    v-for="sp in focusPersonDetails.relations
                                        .spouses"
                                    :key="sp.id"
                                >
                                    {{ sp.name }}
                                    <template v-if="sp.birth_year"
                                        >({{ sp.birth_year }})</template
                                    >
                                </li>
                            </ul>
                        </div>
                        <div
                            v-if="
                                focusPersonDetails?.relations?.parents?.length
                            "
                        >
                            <span class="font-bold text-slate-700"
                                >Parents:</span
                            >
                            <ul
                                class="mt-0.5 list-inside list-disc font-medium"
                            >
                                <li
                                    v-for="par in focusPersonDetails.relations
                                        .parents"
                                    :key="par.id"
                                >
                                    {{ par.name }}
                                    <template v-if="par.birth_year"
                                        >({{ par.birth_year }})</template
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section II: Chronological Events Timeline (if enabled) -->
            <div
                v-if="
                    includeEvents && focusPersonDetails?.person?.events?.length
                "
                class="space-y-3 print:break-inside-avoid"
            >
                <h2
                    class="border-b border-slate-300 pb-1 font-sans text-base font-bold tracking-wider text-slate-900 uppercase"
                >
                    LIFE EVENTS & CHRONOLOGY
                </h2>

                <div class="overflow-x-auto">
                    <table
                        class="w-full border-collapse border border-slate-200 text-left font-sans text-xs print:border-slate-400"
                    >
                        <thead
                            class="bg-slate-100 text-[10px] font-bold text-slate-800 uppercase print:bg-slate-200"
                        >
                            <tr>
                                <th
                                    class="border border-slate-200 p-2 print:border-slate-400"
                                >
                                    Date / Year
                                </th>
                                <th
                                    class="border border-slate-200 p-2 print:border-slate-400"
                                >
                                    Event Type
                                </th>
                                <th
                                    class="border border-slate-200 p-2 print:border-slate-400"
                                >
                                    Location
                                </th>
                                <th
                                    class="border border-slate-200 p-2 print:border-slate-400"
                                >
                                    Details / Notes
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="ev in focusPersonDetails.person.events"
                                :key="ev.id"
                                class="hover:bg-slate-50 print:hover:bg-transparent"
                            >
                                <td
                                    class="border border-slate-200 p-2 font-bold whitespace-nowrap print:border-slate-400"
                                >
                                    {{ ev.date || ev.year || '—' }}
                                </td>
                                <td
                                    class="border border-slate-200 p-2 font-semibold print:border-slate-400"
                                >
                                    {{ ev.title }}
                                </td>
                                <td
                                    class="border border-slate-200 p-2 print:border-slate-400"
                                >
                                    {{ ev.place || '—' }}
                                </td>
                                <td
                                    class="border border-slate-200 p-2 print:border-slate-400"
                                >
                                    {{ ev.note || ev.value || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section III: Ancestry Lineage Outline (Generations II - V) -->
            <div
                v-if="ancestorGenerations.length > 0"
                class="space-y-5 print:break-inside-avoid"
            >
                <h2
                    class="border-b border-slate-300 pb-1 font-sans text-base font-bold tracking-wider text-slate-900 uppercase"
                >
                    ANCESTRY LINEAGE OUTLINE
                </h2>

                <div class="space-y-6">
                    <div
                        v-for="gen in ancestorGenerations"
                        :key="gen.gen"
                        class="space-y-2"
                    >
                        <h3
                            class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-1 font-sans text-xs font-bold tracking-wide text-indigo-900 uppercase print:border-slate-300 print:bg-slate-100"
                        >
                            {{ gen.title }}
                        </h3>

                        <div
                            class="grid grid-cols-1 gap-3 pl-2 font-sans text-xs sm:grid-cols-2 sm:pl-4"
                        >
                            <div
                                v-for="anc in gen.people"
                                :key="anc.id"
                                class="space-y-1 rounded-xl border border-slate-200 bg-white p-3 print:border-slate-300"
                            >
                                <div
                                    class="flex items-center justify-between text-sm font-bold text-slate-900"
                                >
                                    <span>{{ anc.name }}</span>
                                    <span
                                        class="text-[10px] font-normal text-slate-500"
                                        >ID: {{ anc.id }}</span
                                    >
                                </div>
                                <div class="text-slate-700">
                                    <span class="font-semibold">Birth:</span>
                                    {{
                                        anc.birth_date ||
                                        anc.birth_year ||
                                        'Unknown'
                                    }}
                                    <template v-if="anc.birth_place"
                                        >in {{ anc.birth_place }}</template
                                    >
                                </div>
                                <div class="text-slate-700">
                                    <span class="font-semibold">Death:</span>
                                    {{
                                        anc.death_date ||
                                        anc.death_year ||
                                        'Present'
                                    }}
                                    <template v-if="anc.death_place"
                                        >in {{ anc.death_place }}</template
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section IV: Children & Descendants -->
            <div
                v-if="focusPersonDetails?.relations?.children?.length"
                class="space-y-3 print:break-inside-avoid"
            >
                <h2
                    class="border-b border-slate-300 pb-1 font-sans text-base font-bold tracking-wider text-slate-900 uppercase"
                >
                    CHILDREN & IMMEDIATE DESCENDANTS
                </h2>

                <div
                    class="grid grid-cols-1 gap-3 font-sans text-xs sm:grid-cols-2"
                >
                    <div
                        v-for="child in focusPersonDetails.relations.children"
                        :key="child.id"
                        class="space-y-1 rounded-xl border border-slate-200 bg-white p-3 print:border-slate-300"
                    >
                        <div class="text-sm font-bold text-slate-900">
                            {{ child.name }}
                        </div>
                        <div class="text-slate-700">
                            <span class="font-semibold">Born:</span>
                            {{ child.birth_year || '?' }} –
                            <span class="font-semibold">Died:</span>
                            {{ child.death_year || 'Present' }}
                        </div>
                        <div
                            v-if="child.birth_place"
                            class="text-[11px] text-slate-500"
                        >
                            Place: {{ child.birth_place }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section V: Biographical Notes & Remarks (if enabled) -->
            <div
                v-if="
                    includeNotes &&
                    (mainPerson.notes || focusPersonDetails?.person?.notes)
                "
                class="space-y-2 print:break-inside-avoid"
            >
                <h2
                    class="border-b border-slate-300 pb-1 font-sans text-base font-bold tracking-wider text-slate-900 uppercase"
                >
                    BIOGRAPHICAL NOTES & REMARKS
                </h2>

                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 font-sans text-xs leading-relaxed whitespace-pre-line text-slate-800 print:border-none print:bg-white"
                >
                    {{ mainPerson.notes || focusPersonDetails?.person?.notes }}
                </div>
            </div>

            <!-- Footer Attribution -->
            <div
                class="border-t border-slate-300 pt-4 text-center font-sans text-[11px] text-slate-500"
            >
                End of Genealogical Report for {{ mainPerson.name }} — Topland
                Family Tree Archive
            </div>
        </div>
    </div>
</template>
