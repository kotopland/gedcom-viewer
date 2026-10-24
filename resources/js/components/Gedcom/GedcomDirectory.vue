<script setup lang="ts">
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    Image as ImageIcon,
    LayoutGrid,
    List,
    MapPin,
    RefreshCw,
    Search,
    User,
} from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';

defineProps<{
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

        if (searchInput.value) {
            params.append('q', searchInput.value);
        }

        if (surnameFilter.value) {
            params.append('surname', surnameFilter.value);
        }

        if (genderFilter.value) {
            params.append('gender', genderFilter.value);
        }

        if (mediaFilter.value) {
            params.append('has_media', '1');
        }

        if (minYear.value) {
            params.append('min_year', minYear.value.toString());
        }

        if (maxYear.value) {
            params.append('max_year', maxYear.value.toString());
        }

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
        <div
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex flex-col items-stretch justify-between gap-4 lg:flex-row lg:items-center"
            >
                <!-- Search bar -->
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchInput"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Search by first name, surname, birthplace..."
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500/50 focus:outline-hidden dark:border-slate-700/80 dark:bg-slate-800/80 dark:text-white"
                    />
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <!-- Surname Dropdown -->
                    <select
                        v-model="surnameFilter"
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-medium text-slate-700 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <option value="">All Surnames</option>
                        <option
                            v-for="(count, name) in topSurnames"
                            :key="name"
                            :value="name"
                        >
                            {{ name }} ({{ count }})
                        </option>
                    </select>

                    <!-- Gender Filter -->
                    <select
                        v-model="genderFilter"
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-medium text-slate-700 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <option value="">All Genders</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>

                    <!-- Has Media Toggle -->
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-medium text-slate-700 select-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <input
                            type="checkbox"
                            v-model="mediaFilter"
                            class="rounded-sm border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Has Media Only
                    </label>

                    <!-- View Switcher -->
                    <div
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-100 p-1 dark:border-slate-700/80 dark:bg-slate-800"
                    >
                        <button
                            @click="viewMode = 'grid'"
                            class="rounded-lg p-1.5 transition-colors"
                            :class="
                                viewMode === 'grid'
                                    ? 'bg-white text-indigo-600 shadow-2xs dark:bg-slate-700 dark:text-indigo-400'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200'
                            "
                            title="Grid View"
                        >
                            <LayoutGrid class="h-4 w-4" />
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            class="rounded-lg p-1.5 transition-colors"
                            :class="
                                viewMode === 'list'
                                    ? 'bg-white text-indigo-600 shadow-2xs dark:bg-slate-700 dark:text-indigo-400'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200'
                            "
                            title="List View"
                        >
                            <List class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Reset -->
                    <button
                        @click="resetFilters"
                        class="p-2 text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-200"
                        title="Reset Filters"
                    >
                        <RefreshCw class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Info -->
        <div
            class="flex items-center justify-between px-1 text-xs text-slate-500 dark:text-slate-400"
        >
            <span
                >Showing {{ people.length }} of
                {{ meta.total }} individuals</span
            >
            <span>Page {{ meta.page }} of {{ meta.last_page }}</span>
        </div>

        <!-- Loading State -->
        <div
            v-if="loading"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="i in 8"
                :key="i"
                class="h-44 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800/60"
            ></div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="people.length === 0"
            class="rounded-2xl border border-slate-200 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900"
        >
            <User
                class="mx-auto mb-3 h-12 w-12 text-slate-300 dark:text-slate-600"
            />
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                No individuals found
            </h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Try adjusting your search keywords or active filters.
            </p>
            <button
                @click="resetFilters"
                class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500"
            >
                Reset Filters
            </button>
        </div>

        <!-- Grid View -->
        <div
            v-else-if="viewMode === 'grid'"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="person in people"
                :key="person.id"
                @click="emit('select-person', person.id)"
                class="group flex cursor-pointer flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition-all hover:border-indigo-300 hover:bg-indigo-50/50 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-600/50 dark:hover:bg-slate-800/80"
            >
                <div class="flex items-start gap-3.5">
                    <img
                        v-if="person.primary_media"
                        :src="
                            person.portrait_url ||
                            person.primary_media.portrait_url ||
                            person.primary_media.url
                        "
                        :alt="person.name"
                        class="h-14 w-14 shrink-0 rounded-xl border border-slate-200 bg-slate-100 object-cover dark:border-slate-700 dark:bg-slate-800"
                    />
                    <div
                        v-else
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <User class="h-7 w-7" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h4
                            class="truncate text-sm font-bold text-slate-900 transition-colors group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                        >
                            {{ person.name }}
                        </h4>

                        <p
                            class="mt-1 flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            <Calendar
                                class="h-3.5 w-3.5 shrink-0 text-indigo-500"
                            />
                            <span
                                >{{ person.birth_year || '?' }} –
                                {{ person.death_year || 'Present' }}</span
                            >
                        </p>

                        <p
                            v-if="person.birth_place"
                            class="mt-1 flex items-center gap-1 truncate text-[11px] text-slate-400 dark:text-slate-500"
                        >
                            <MapPin class="h-3 w-3 shrink-0 text-slate-400" />
                            <span class="truncate">{{
                                person.birth_place
                            }}</span>
                        </p>
                    </div>
                </div>

                <div
                    class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs dark:border-slate-800/80"
                >
                    <span
                        class="rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase"
                        :class="
                            person.sex === 'M'
                                ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                : person.sex === 'F'
                                  ? 'bg-pink-500/10 text-pink-600 dark:text-pink-400'
                                  : 'bg-slate-500/10 text-slate-500'
                        "
                    >
                        {{
                            person.sex === 'M'
                                ? 'Male'
                                : person.sex === 'F'
                                  ? 'Female'
                                  : 'Unknown'
                        }}
                    </span>

                    <span
                        v-if="person.media_count > 0"
                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400"
                    >
                        <ImageIcon class="h-3 w-3" />
                        {{ person.media_count }}
                        {{ person.media_count === 1 ? 'media' : 'media' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- List View -->
        <div
            v-else
            class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                v-for="person in people"
                :key="person.id"
                @click="emit('select-person', person.id)"
                class="flex cursor-pointer items-center justify-between p-3.5 transition-colors hover:bg-indigo-50/50 dark:hover:bg-slate-800/80"
            >
                <div class="flex min-w-0 items-center gap-3.5">
                    <img
                        v-if="person.primary_media"
                        :src="
                            person.portrait_url ||
                            person.primary_media.portrait_url ||
                            person.primary_media.url
                        "
                        class="h-10 w-10 shrink-0 rounded-lg object-cover"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800"
                    >
                        <User class="h-5 w-5" />
                    </div>

                    <div class="min-w-0">
                        <div
                            class="truncate text-sm font-bold text-slate-900 dark:text-white"
                        >
                            {{ person.name }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{ person.birth_year || '?' }} –
                            {{ person.death_year || 'Present' }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <span
                        v-if="person.birth_place"
                        class="hidden max-w-xs truncate text-xs text-slate-400 md:block"
                    >
                        {{ person.birth_place }}
                    </span>
                    <span
                        v-if="person.media_count > 0"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                    >
                        <ImageIcon class="h-3.5 w-3.5" />
                        {{ person.media_count }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div
            v-if="meta.last_page > 1"
            class="flex items-center justify-center gap-2 pt-4"
        >
            <button
                @click="fetchPeople(meta.page - 1)"
                :disabled="meta.page <= 1"
                class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>
            <span
                class="px-3 text-xs font-semibold text-slate-700 dark:text-slate-300"
            >
                Page {{ meta.page }} of {{ meta.last_page }}
            </span>
            <button
                @click="fetchPeople(meta.page + 1)"
                :disabled="meta.page >= meta.last_page"
                class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
