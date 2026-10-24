<script setup lang="ts">
import {
    Search,
    Image as ImageIcon,
    FileCode,
    Music,
    Volume2,
    FileText,
    ExternalLink,
    X,
    ChevronLeft,
    ChevronRight,
    Users,
} from '@lucide/vue';
import { ref, watch, onMounted } from 'vue';

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
}>();

const loading = ref(false);
const activeCategory = ref<'all' | 'photo' | 'document' | 'audio'>('all');
const searchInput = ref('');

const mediaItems = ref<any[]>([]);
const meta = ref({
    total: 0,
    page: 1,
    limit: 24,
    last_page: 1,
});

const activePreviewItem = ref<any>(null);
let debounceTimer: any = null;

const fetchMedia = async (page = 1) => {
    loading.value = true;

    try {
        const params = new URLSearchParams();

        if (activeCategory.value !== 'all') {
            params.append('type', activeCategory.value);
        }

        if (searchInput.value) {
            params.append('q', searchInput.value);
        }

        params.append('page', page.toString());
        params.append('limit', '24');

        const res = await fetch(`/api/gedcom/media?${params.toString()}`);

        if (res.ok) {
            const data = await res.json();
            mediaItems.value = data.data;
            meta.value = data.meta;
        }
    } catch (e) {
        console.error('Failed to fetch media:', e);
    } finally {
        loading.value = false;
    }
};

const onSearchInput = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchMedia(1);
    }, 250);
};

watch(activeCategory, () => {
    fetchMedia(1);
});

onMounted(() => {
    fetchMedia(1);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Search & Filter Bar -->
        <div
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex flex-col items-stretch justify-between gap-4 sm:flex-row sm:items-center"
            >
                <!-- Category Tabs -->
                <div
                    class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 p-1.5 text-xs font-semibold dark:border-slate-700/80 dark:bg-slate-800/80"
                >
                    <button
                        @click="activeCategory = 'all'"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-2 transition-colors"
                        :class="
                            activeCategory === 'all'
                                ? 'bg-white text-indigo-600 shadow-2xs dark:bg-slate-700 dark:text-indigo-400'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <ImageIcon class="h-3.5 w-3.5" />
                        All Assets
                    </button>
                    <button
                        @click="activeCategory = 'photo'"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-2 transition-colors"
                        :class="
                            activeCategory === 'photo'
                                ? 'bg-white text-indigo-600 shadow-2xs dark:bg-slate-700 dark:text-indigo-400'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <ImageIcon class="h-3.5 w-3.5" />
                        Photos
                    </button>
                    <button
                        @click="activeCategory = 'document'"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-2 transition-colors"
                        :class="
                            activeCategory === 'document'
                                ? 'bg-white text-indigo-600 shadow-2xs dark:bg-slate-700 dark:text-indigo-400'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <FileCode class="h-3.5 w-3.5" />
                        Documents (PDFs)
                    </button>
                    <button
                        @click="activeCategory = 'audio'"
                        class="flex items-center gap-1.5 rounded-lg px-3.5 py-2 transition-colors"
                        :class="
                            activeCategory === 'audio'
                                ? 'bg-white text-indigo-600 shadow-2xs dark:bg-slate-700 dark:text-indigo-400'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <Music class="h-3.5 w-3.5" />
                        Audio Clips
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative min-w-[240px]">
                    <Search
                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchInput"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Search media titles..."
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pr-4 pl-10 text-xs text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500/50 focus:outline-hidden dark:border-slate-700/80 dark:bg-slate-800/80 dark:text-white"
                    />
                </div>
            </div>
        </div>

        <!-- Meta info -->
        <div
            class="flex items-center justify-between px-1 text-xs text-slate-500 dark:text-slate-400"
        >
            <span
                >Showing {{ mediaItems.length }} of {{ meta.total }} media
                items</span
            >
            <span>Page {{ meta.page }} of {{ meta.last_page }}</span>
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
        >
            <div
                v-for="i in 12"
                :key="i"
                class="h-48 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800/60"
            ></div>
        </div>

        <!-- Empty -->
        <div
            v-else-if="mediaItems.length === 0"
            class="rounded-2xl border border-slate-200 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900"
        >
            <ImageIcon
                class="mx-auto mb-3 h-12 w-12 text-slate-300 dark:text-slate-600"
            />
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                No media items found
            </h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Try switching categories or clearing search keywords.
            </p>
        </div>

        <!-- Media Grid -->
        <div
            v-else
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
        >
            <div
                v-for="item in mediaItems"
                :key="item.id"
                @click="activePreviewItem = item"
                class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs transition-all hover:shadow-lg dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="relative flex aspect-square w-full items-center justify-center overflow-hidden bg-slate-950"
                >
                    <img
                        v-if="item.category === 'photo'"
                        :src="item.url"
                        :alt="item.title"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    />

                    <div
                        v-else-if="item.category === 'document'"
                        class="flex h-full w-full flex-col items-center justify-center bg-red-950/30 p-4 text-red-400"
                    >
                        <FileCode class="mb-2 h-10 w-10" />
                        <span
                            class="rounded-sm bg-red-500/20 px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase"
                            >PDF</span
                        >
                    </div>

                    <div
                        v-else-if="item.category === 'audio'"
                        class="flex h-full w-full flex-col items-center justify-center bg-amber-950/30 p-4 text-amber-400"
                    >
                        <Volume2 class="mb-2 h-10 w-10" />
                        <span
                            class="rounded-sm bg-amber-500/20 px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase"
                            >Audio</span
                        >
                    </div>

                    <div
                        v-else
                        class="flex h-full w-full flex-col items-center justify-center bg-slate-800 p-4 text-slate-400"
                    >
                        <FileText class="mb-2 h-10 w-10" />
                    </div>

                    <!-- Linked people count badge -->
                    <span
                        v-if="item.people && item.people.length > 0"
                        class="absolute top-2 right-2 flex items-center gap-1 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-semibold text-white shadow-xs backdrop-blur-xs"
                    >
                        <Users class="h-2.5 w-2.5" />
                        {{ item.people.length }}
                    </span>
                </div>

                <div
                    class="border-t border-slate-100 p-3 dark:border-slate-800/80"
                >
                    <div
                        class="truncate text-xs font-bold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                    >
                        {{ item.title }}
                    </div>
                    <div class="mt-0.5 truncate text-[10px] text-slate-400">
                        {{ item.file }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div
            v-if="meta.last_page > 1"
            class="flex items-center justify-center gap-2 pt-4"
        >
            <button
                @click="fetchMedia(meta.page - 1)"
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
                @click="fetchMedia(meta.page + 1)"
                :disabled="meta.page >= meta.last_page"
                class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
        </div>

        <!-- Preview Modal -->
        <div
            v-if="activePreviewItem"
            class="fixed inset-0 z-60 flex animate-in flex-col items-center justify-center bg-black/90 p-6 fade-in"
        >
            <button
                @click="activePreviewItem = null"
                class="absolute top-6 right-6 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20"
            >
                <X class="h-6 w-6" />
            </button>

            <div
                class="flex max-h-[85vh] w-full max-w-4xl flex-col items-center justify-center"
            >
                <img
                    v-if="activePreviewItem.category === 'photo'"
                    :src="activePreviewItem.url"
                    :alt="activePreviewItem.title"
                    class="max-h-[65vh] max-w-full rounded-xl object-contain shadow-2xl"
                />

                <iframe
                    v-else-if="activePreviewItem.category === 'document'"
                    :src="activePreviewItem.url"
                    class="h-[65vh] w-full rounded-xl border border-slate-800 shadow-2xl"
                ></iframe>

                <div
                    v-else-if="activePreviewItem.category === 'audio'"
                    class="flex w-full max-w-md flex-col items-center gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-8"
                >
                    <Music class="h-16 w-16 text-amber-400" />
                    <span class="text-center font-medium text-white">{{
                        activePreviewItem.title
                    }}</span>
                    <audio controls class="w-full">
                        <source :src="activePreviewItem.url" type="audio/mp4" />
                        Your browser does not support audio playback.
                    </audio>
                </div>

                <!-- Linked People Pill Bar -->
                <div
                    v-if="
                        activePreviewItem.people &&
                        activePreviewItem.people.length > 0
                    "
                    class="mt-4 flex max-w-2xl flex-wrap items-center justify-center gap-2"
                >
                    <span class="text-xs font-medium text-slate-400"
                        >Tagged People:</span
                    >
                    <button
                        v-for="p in activePreviewItem.people"
                        :key="p.id"
                        @click="
                            activePreviewItem = null;
                            emit('select-person', p.id);
                        "
                        class="inline-flex items-center gap-1.5 rounded-full border border-indigo-500/50 bg-indigo-600/30 px-3 py-1 text-xs font-semibold text-indigo-200 transition-colors hover:bg-indigo-600 hover:text-white"
                    >
                        <img
                            v-if="p.portrait_url"
                            :src="p.portrait_url"
                            class="h-4 w-4 rounded-full border border-white/30 object-cover"
                        />
                        <span>{{ p.name }}</span>
                    </button>
                </div>

                <div
                    class="mt-4 flex w-full items-center justify-between border-t border-white/10 pt-4 text-sm text-slate-300"
                >
                    <span class="max-w-lg truncate font-medium">{{
                        activePreviewItem.title
                    }}</span>
                    <a
                        :href="activePreviewItem.url"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-indigo-500"
                    >
                        <ExternalLink class="h-4 w-4" />
                        Open Original File
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
