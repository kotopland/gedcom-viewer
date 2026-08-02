<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
    GitBranch, User, Calendar, MapPin, Search, ChevronRight, Layers, Award, Clock, ArrowUpRight
} from '@lucide/vue';

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
    if (!id) return;
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

watch(() => props.rootPersonId, (newId) => {
    if (newId) {
        focusId.value = newId;
        fetchLineage(newId);
    }
});

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
        const res = await fetch(`/api/gedcom/search?q=${encodeURIComponent(searchInput.value)}&limit=8`);
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
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-extrabold border border-indigo-500/30">
                            <GitBranch class="w-3.5 h-3.5" />
                            <span>Complete Ahnentafel Lineage Report</span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                            Full Ancestral Lineage View
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300 max-w-2xl leading-relaxed">
                            Comprehensive, generation-by-generation tree listing all direct ancestors of the selected individual using standardized Ahnentafel numbering.
                        </p>
                    </div>

                    <!-- Search Focus Person Input -->
                    <div class="relative w-full md:w-80">
                        <div class="relative">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" />
                            <input
                                v-model="searchInput"
                                @input="handleSearch"
                                type="text"
                                placeholder="Change focus individual..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-white/10 dark:bg-slate-800/80 border border-white/20 dark:border-slate-700 text-white placeholder-slate-400 text-xs font-semibold focus:outline-hidden focus:ring-2 focus:ring-indigo-500 backdrop-blur-md"
                            />
                        </div>

                        <!-- Search Dropdown Results -->
                        <div
                            v-if="searchResults.length > 0"
                            class="absolute left-0 right-0 top-12 z-30 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-2 space-y-1 max-h-60 overflow-y-auto"
                        >
                            <button
                                v-for="p in searchResults"
                                :key="'s-' + p.id"
                                @click="selectFocusPerson(p)"
                                class="w-full p-2 rounded-xl text-left hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition-colors flex items-center justify-between group cursor-pointer"
                            >
                                <span class="font-bold text-xs text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                    {{ p.name }}
                                </span>
                                <span class="text-[10px] text-slate-400 font-semibold">
                                    b.{{ p.birth_year || '?' }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- KPI Stats Summary Bar -->
                <div v-if="lineageData" class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2 border-t border-white/10">
                    <div class="bg-white/10 dark:bg-slate-800/60 backdrop-blur-md p-3 rounded-2xl border border-white/10">
                        <div class="text-[10px] uppercase font-bold text-indigo-300">Total Ancestors Found</div>
                        <div class="text-xl font-black text-white mt-0.5">
                            {{ lineageData.total_ancestors_count }} <span class="text-xs font-normal opacity-75">ancestors</span>
                        </div>
                    </div>

                    <div class="bg-white/10 dark:bg-slate-800/60 backdrop-blur-md p-3 rounded-2xl border border-white/10">
                        <div class="text-[10px] uppercase font-bold text-indigo-300">Max Depth Tracked</div>
                        <div class="text-xl font-black text-white mt-0.5">
                            {{ lineageData.max_generations_depth }} <span class="text-xs font-normal opacity-75">generations</span>
                        </div>
                    </div>

                    <div class="col-span-2 sm:col-span-1 bg-white/10 dark:bg-slate-800/60 backdrop-blur-md p-3 rounded-2xl border border-white/10 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-indigo-300">Focus Root</div>
                            <div class="text-xs font-black text-white truncate max-w-[160px] mt-0.5">
                                {{ lineageData.root_person?.name }}
                            </div>
                        </div>
                        <button
                            @click="emit('change-root', lineageData.root_person.id)"
                            class="px-2.5 py-1 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-bold transition-all cursor-pointer shadow-md"
                        >
                            Open Tree
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-slate-400">
            <div class="w-10 h-10 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-4 text-xs font-semibold text-slate-600 dark:text-slate-300">Generating complete ancestral lineage report...</p>
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
                        <div class="px-3 py-1 rounded-xl bg-indigo-600 text-white text-xs font-extrabold shadow-md shrink-0">
                            Gen {{ genGroup.generation }}
                        </div>
                        <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                            {{ genGroup.generation_title }} ({{ genGroup.ancestors.length }})
                        </h3>
                        <div class="flex-1 h-px bg-slate-200 dark:border-slate-800"></div>
                    </div>

                    <!-- Ancestors Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5">
                        <div
                            v-for="anc in genGroup.ancestors"
                            :key="'anc-' + anc.ahnentafel_number"
                            @click="emit('select-person', anc.id)"
                            @dblclick="emit('change-root', anc.id)"
                            class="bg-white dark:bg-slate-900 rounded-3xl p-4 border border-slate-200 dark:border-slate-800 shadow-md hover:border-indigo-500 dark:hover:border-indigo-500 transition-all cursor-pointer group flex flex-col justify-between space-y-3 relative overflow-hidden"
                        >
                            <!-- Ahnentafel Badge -->
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-extrabold text-[10px] border border-indigo-200 dark:border-indigo-800/60">
                                    Ahnentafel #{{ anc.ahnentafel_number }}
                                </span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">
                                    {{ anc.relationship_title }}
                                </span>
                            </div>

                            <!-- Person Photo + Info -->
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="anc.primary_media?.url"
                                    :src="anc.primary_media.url"
                                    class="w-12 h-12 rounded-2xl object-cover border border-slate-200 dark:border-slate-700 shrink-0 shadow-sm group-hover:scale-105 transition-transform"
                                />
                                <div v-else class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <User class="w-6 h-6" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h4 class="font-extrabold text-xs text-slate-900 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                        {{ anc.name }}
                                    </h4>
                                    <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 mt-0.5">
                                        <span v-if="anc.birth_year || anc.birth_date">b.{{ anc.birth_year || anc.birth_date }}</span>
                                        <span v-if="anc.death_year || anc.death_date"> – d.{{ anc.death_year || anc.death_date }}</span>
                                    </div>
                                    <div v-if="anc.birth_place" class="text-[10px] text-slate-400 truncate mt-0.5 flex items-center gap-1">
                                        <MapPin class="w-3 h-3 text-indigo-400 shrink-0" />
                                        <span class="truncate">{{ anc.birth_place }}</span>
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
