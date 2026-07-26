<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import {
    Search, Filter, User, Calendar, MapPin, Image as ImageIcon,
    ChevronLeft, ChevronRight, RefreshCw, LayoutGrid, List
} from '@lucide/vue';


const props = defineProps<{
    topSurnames?: Record<string, number>;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'open-in-tree', id: string): void;
}>();

const loading = ref(false);
const viewMode = ref<'grid' | 'list'>('grid');
const searchInput = ref('');
const surnameFilter = ref('');
const genderFilter = ref('');
const mediaFilter = ref(false);
const minYear = ref<number | null>(null);
const maxYear = ref<number | null>(null);

const people = ref<any[]>([]);
const meta = ref({
    total: 0,
    page: 1,
    limit: 24,
    last_page: 1,
});

let debounceTimer: any = null;

const fetchPeople = async (page = 1) => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (searchInput.value) params.append('q', searchInput.value);
        if (surnameFilter.value) params.append('surname', surnameFilter.value);
        if (genderFilter.value) params.append('gender', genderFilter.value);
        if (mediaFilter.value) params.append('has_media', '1');
        if (minYear.value) params.append('min_year', minYear.value.toString());
        if (maxYear.value) params.append('max_year', maxYear.value.toString());
        params.append('page', page.toString());
        params.append('limit', '24');

        const res = await fetch(`/api/gedcom/search?${params.toString()}`);
        if (res.ok) {
            const data = await res.json();
            people.value = data.data;
            meta.value = data.meta;
        }
    } catch (e) {
        console.error('Failed to fetch people:', e);
    } finally {
        loading.value = false;
    }
};

const onSearchInput = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchPeople(1);
    }, 250);
};

const resetFilters = () => {
    searchInput.value = '';
    surnameFilter.value = '';
    genderFilter.value = '';
    mediaFilter.value = false;
    minYear.value = null;
    maxYear.value = null;
    fetchPeople(1);
};

watch([surnameFilter, genderFilter, mediaFilter], () => {
    fetchPeople(1);
});

onMounted(() => {
    fetchPeople(1);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Search & Toolbar -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-4 justify-between">
                <!-- Search bar -->
                <div class="relative flex-1">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="searchInput"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Search by first name, surname, birthplace..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/50"
                    />
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <!-- Surname Dropdown -->
                    <select
                        v-model="surnameFilter"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 font-medium focus:outline-hidden"
                    >
                        <option value="">All Surnames</option>
                        <option v-for="(count, name) in topSurnames" :key="name" :value="name">
                            {{ name }} ({{ count }})
                        </option>
                    </select>

                    <!-- Gender Filter -->
                    <select
                        v-model="genderFilter"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 font-medium focus:outline-hidden"
                    >
                        <option value="">All Genders</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>

                    <!-- Has Media Toggle -->
                    <label class="inline-flex items-center gap-2 px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer select-none font-medium text-slate-700 dark:text-slate-200">
                        <input
                            type="checkbox"
                            v-model="mediaFilter"
                            class="rounded-sm border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Has Media Only
                    </label>

                    <!-- View Switcher -->
                    <div class="inline-flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700/80">
                        <button
                            @click="viewMode = 'grid'"
                            class="p-1.5 rounded-lg transition-colors"
                            :class="viewMode === 'grid' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200'"
                            title="Grid View"
                        >
                            <LayoutGrid class="w-4 h-4" />
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            class="p-1.5 rounded-lg transition-colors"
                            :class="viewMode === 'list' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200'"
                            title="List View"
                        >
                            <List class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Reset -->
                    <button
                        @click="resetFilters"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                        title="Reset Filters"
                    >
                        <RefreshCw class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Info -->
        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 px-1">
            <span>Showing {{ people.length }} of {{ meta.total }} individuals</span>
            <span>Page {{ meta.page }} of {{ meta.last_page }}</span>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="i in 8" :key="i" class="h-44 bg-slate-100 dark:bg-slate-800/60 rounded-2xl animate-pulse"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="people.length === 0" class="bg-white dark:bg-slate-900 rounded-2xl p-12 text-center border border-slate-200 dark:border-slate-800">
            <User class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">No individuals found</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Try adjusting your search keywords or active filters.</p>
            <button @click="resetFilters" class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold">
                Reset Filters
            </button>
        </div>

        <!-- Grid View -->
        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="person in people"
                :key="person.id"
                @click="emit('select-person', person.id)"
                class="group bg-white dark:bg-slate-900 hover:bg-indigo-50/50 dark:hover:bg-slate-800/80 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 hover:border-indigo-300 dark:hover:border-indigo-600/50 transition-all cursor-pointer shadow-xs hover:shadow-md flex flex-col justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <img
                        v-if="person.primary_media"
                        :src="person.primary_media.url"
                        :alt="person.name"
                        class="w-14 h-14 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 dark:bg-slate-800"
                    />
                    <div
                        v-else
                        class="w-14 h-14 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400 shrink-0"
                    >
                        <User class="w-7 h-7" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                            {{ person.name }}
                        </h4>
                        
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1 font-medium">
                            <Calendar class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                            <span>{{ person.birth_year || '?' }} – {{ person.death_year || 'Present' }}</span>
                        </p>

                        <p v-if="person.birth_place" class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 truncate flex items-center gap-1">
                            <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
                            <span class="truncate">{{ person.birth_place }}</span>
                        </p>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
                    <span
                        class="px-2 py-0.5 rounded-md font-semibold text-[10px] uppercase"
                        :class="person.sex === 'M' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' : (person.sex === 'F' ? 'bg-pink-500/10 text-pink-600 dark:text-pink-400' : 'bg-slate-500/10 text-slate-500')"
                    >
                        {{ person.sex === 'M' ? 'Male' : (person.sex === 'F' ? 'Female' : 'Unknown') }}
                    </span>

                    <span v-if="person.media_count > 0" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-semibold text-[11px]">
                        <ImageIcon class="w-3 h-3" />
                        {{ person.media_count }} {{ person.media_count === 1 ? 'media' : 'media' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- List View -->
        <div v-else class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
            <div
                v-for="person in people"
                :key="person.id"
                @click="emit('select-person', person.id)"
                class="flex items-center justify-between p-3.5 hover:bg-indigo-50/50 dark:hover:bg-slate-800/80 cursor-pointer transition-colors"
            >
                <div class="flex items-center gap-3.5 min-w-0">
                    <img v-if="person.primary_media" :src="person.primary_media.url" class="w-10 h-10 rounded-lg object-cover shrink-0" />
                    <div v-else class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 shrink-0">
                        <User class="w-5 h-5" />
                    </div>

                    <div class="min-w-0">
                        <div class="font-bold text-sm text-slate-900 dark:text-white truncate">
                            {{ person.name }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{ person.birth_year || '?' }} – {{ person.death_year || 'Present' }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <span v-if="person.birth_place" class="hidden md:block text-xs text-slate-400 max-w-xs truncate">
                        {{ person.birth_place }}
                    </span>
                    <span v-if="person.media_count > 0" class="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                        <ImageIcon class="w-3.5 h-3.5" />
                        {{ person.media_count }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
            <button
                @click="fetchPeople(meta.page - 1)"
                :disabled="meta.page <= 1"
                class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition-colors"
            >
                <ChevronLeft class="w-4 h-4" />
            </button>
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 px-3">
                Page {{ meta.page }} of {{ meta.last_page }}
            </span>
            <button
                @click="fetchPeople(meta.page + 1)"
                :disabled="meta.page >= meta.last_page"
                class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition-colors"
            >
                <ChevronRight class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
