<script setup lang="ts">
import { GitBranch, MapPin, Search, User } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const loading = ref<boolean>(true);
const lineageData = ref<any>(null);
const focusId = ref<string | null>(props.rootPersonId);

// Person Search Auto-complete
const searchInput = ref<string>('');
const isSearching = ref<boolean>(false);
const searchResults = ref<any[]>([]);

const fetchLineage = async (id: string) => {
    if (!id) {
        return;
    }

    loading.value = true;

    try {
        const res = await fetch(`/api/gedcom/lineage/${id}`);

        if (res.ok) {
            lineageData.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to fetch full lineage data:', e);
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.rootPersonId,
    (newId) => {
        if (newId) {
            focusId.value = newId;
            fetchLineage(newId);
        }
    },
);

onMounted(() => {
    if (focusId.value) {
        fetchLineage(focusId.value);
    }
});

const handleSearch = async () => {
    if (!searchInput.value.trim()) {
        searchResults.value = [];

        return;
    }

    isSearching.value = true;

    try {
        const res = await fetch(
            `/api/gedcom/search?q=${encodeURIComponent(searchInput.value)}&limit=8`,
        );

        if (res.ok) {
            const data = await res.json();
            searchResults.value = data.data || [];
        }
    } catch (e) {
        console.error('Lineage search error:', e);
    } finally {
        isSearching.value = false;
    }
};

const selectFocusPerson = (person: any) => {
    focusId.value = person.id;
    fetchLineage(person.id);
    searchInput.value = '';
    searchResults.value = [];
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header Banner & Person Selector -->
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 text-white shadow-2xl sm:p-8"
        >
            <div
                class="pointer-events-none absolute -right-10 -bottom-10 h-64 w-64 rounded-full bg-indigo-600/10 blur-3xl"
            ></div>

            <div class="relative z-10 space-y-4">
                <div
                    class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
                >
                    <div class="space-y-1">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/20 px-3 py-1 text-xs font-extrabold text-indigo-300"
                        >
                            <GitBranch class="h-3.5 w-3.5" />
                            <span>Complete Ahnentafel Lineage Report</span>
                        </div>
                        <h2
                            class="text-2xl font-black tracking-tight text-white sm:text-3xl"
                        >
                            Full Ancestral Lineage View
                        </h2>
                        <p
                            class="max-w-2xl text-xs leading-relaxed text-slate-300 sm:text-sm"
                        >
                            Comprehensive, generation-by-generation tree listing
                            all direct ancestors of the selected individual
                            using standardized Ahnentafel numbering.
                        </p>
                    </div>

                    <!-- Search Focus Person Input -->
                    <div class="relative w-full md:w-80">
                        <div class="relative">
                            <Search
                                class="absolute top-3 left-3.5 h-4 w-4 text-slate-400"
                            />
                            <input
                                v-model="searchInput"
                                @input="handleSearch"
                                type="text"
                                placeholder="Change focus individual..."
                                class="w-full rounded-2xl border border-white/20 bg-white/10 py-2.5 pr-4 pl-10 text-xs font-semibold text-white placeholder-slate-400 backdrop-blur-md focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800/80"
                            />
                        </div>

                        <!-- Search Dropdown Results -->
                        <div
                            v-if="searchResults.length > 0"
                            class="absolute top-12 right-0 left-0 z-30 max-h-60 space-y-1 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                        >
                            <button
                                v-for="p in searchResults"
                                :key="'s-' + p.id"
                                @click="selectFocusPerson(p)"
                                class="group flex w-full cursor-pointer items-center justify-between rounded-xl p-2 text-left transition-colors hover:bg-indigo-50 dark:hover:bg-indigo-950/60"
                            >
                                <span
                                    class="text-xs font-bold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                                >
                                    {{ p.name }}
                                </span>
                                <span
                                    class="text-[10px] font-semibold text-slate-400"
                                >
                                    b.{{ p.birth_year || '?' }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- KPI Stats Summary Bar -->
                <div
                    v-if="lineageData"
                    class="grid grid-cols-2 gap-3 border-t border-white/10 pt-2 sm:grid-cols-3"
                >
                    <div
                        class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-md dark:bg-slate-800/60"
                    >
                        <div
                            class="text-[10px] font-bold text-indigo-300 uppercase"
                        >
                            Total Ancestors Found
                        </div>
                        <div class="mt-0.5 text-xl font-black text-white">
                            {{ lineageData.total_ancestors_count }}
                            <span class="text-xs font-normal opacity-75"
                                >ancestors</span
                            >
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-md dark:bg-slate-800/60"
                    >
                        <div
                            class="text-[10px] font-bold text-indigo-300 uppercase"
                        >
                            Max Depth Tracked
                        </div>
                        <div class="mt-0.5 text-xl font-black text-white">
                            {{ lineageData.max_generations_depth }}
                            <span class="text-xs font-normal opacity-75"
                                >generations</span
                            >
                        </div>
                    </div>

                    <div
                        class="col-span-2 flex items-center justify-between rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-md sm:col-span-1 dark:bg-slate-800/60"
                    >
                        <div>
                            <div
                                class="text-[10px] font-bold text-indigo-300 uppercase"
                            >
                                Focus Root
                            </div>
                            <div
                                class="mt-0.5 max-w-[160px] truncate text-xs font-black text-white"
                            >
                                {{ lineageData.root_person?.name }}
                            </div>
                        </div>
                        <button
                            @click="
                                emit('change-root', lineageData.root_person.id)
                            "
                            class="cursor-pointer rounded-xl bg-indigo-600 px-2.5 py-1 text-[10px] font-bold text-white shadow-md transition-all hover:bg-indigo-500"
                        >
                            Open Tree
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="loading"
            class="flex flex-col items-center justify-center py-20 text-slate-400"
        >
            <div
                class="h-10 w-10 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent"
            ></div>
            <p
                class="mt-4 text-xs font-semibold text-slate-600 dark:text-slate-300"
            >
                Generating complete ancestral lineage report...
            </p>
        </div>

        <template v-else-if="lineageData && lineageData.generations">
            <!-- Generations Timeline Stack -->
            <div class="space-y-8">
                <div
                    v-for="genGroup in lineageData.generations"
                    :key="'gen-' + genGroup.generation"
                    class="space-y-3"
                >
                    <!-- Generation Header Divider -->
                    <div class="flex items-center gap-3">
                        <div
                            class="shrink-0 rounded-xl bg-indigo-600 px-3 py-1 text-xs font-extrabold text-white shadow-md"
                        >
                            Gen {{ genGroup.generation }}
                        </div>
                        <h3
                            class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                        >
                            {{ genGroup.generation_title }} ({{
                                genGroup.ancestors.length
                            }})
                        </h3>
                        <div
                            class="h-px flex-1 bg-slate-200 dark:border-slate-800"
                        ></div>
                    </div>

                    <!-- Ancestors Grid -->
                    <div
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <div
                            v-for="anc in genGroup.ancestors"
                            :key="'anc-' + anc.ahnentafel_number"
                            @click="emit('select-person', anc.id)"
                            @dblclick="emit('change-root', anc.id)"
                            class="group relative flex cursor-pointer flex-col justify-between space-y-3 overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-md transition-all hover:border-indigo-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500"
                        >
                            <!-- Ahnentafel Badge -->
                            <div class="flex items-center justify-between">
                                <span
                                    class="rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-0.5 text-[10px] font-extrabold text-indigo-700 dark:border-indigo-800/60 dark:bg-indigo-950 dark:text-indigo-300"
                                >
                                    Ahnentafel #{{ anc.ahnentafel_number }}
                                </span>
                                <span
                                    class="text-[10px] font-bold text-slate-400 uppercase"
                                >
                                    {{ anc.relationship_title }}
                                </span>
                            </div>

                            <!-- Person Photo + Info -->
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="anc.primary_media?.url"
                                    :src="
                                        anc.portrait_url ||
                                        anc.primary_media.portrait_url ||
                                        anc.primary_media.url
                                    "
                                    class="h-12 w-12 shrink-0 rounded-2xl border border-slate-200 object-cover shadow-sm transition-transform group-hover:scale-105 dark:border-slate-700"
                                />
                                <div
                                    v-else
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-slate-800 dark:text-indigo-400"
                                >
                                    <User class="h-6 w-6" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h4
                                        class="truncate text-xs font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                                    >
                                        {{ anc.name }}
                                    </h4>
                                    <div
                                        class="mt-0.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                    >
                                        <span
                                            v-if="
                                                anc.birth_year || anc.birth_date
                                            "
                                            >b.{{
                                                anc.birth_year || anc.birth_date
                                            }}</span
                                        >
                                        <span
                                            v-if="
                                                anc.death_year || anc.death_date
                                            "
                                        >
                                            – d.{{
                                                anc.death_year || anc.death_date
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        v-if="anc.birth_place"
                                        class="mt-0.5 flex items-center gap-1 truncate text-[10px] text-slate-400"
                                    >
                                        <MapPin
                                            class="h-3 w-3 shrink-0 text-indigo-400"
                                        />
                                        <span class="truncate">{{
                                            anc.birth_place
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
