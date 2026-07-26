<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import {
    Search, Image as ImageIcon, FileCode, Music, Volume2, FileText,
    ExternalLink, X, ChevronLeft, ChevronRight, Users
} from '@lucide/vue';


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
        if (activeCategory.value !== 'all') params.append('type', activeCategory.value);
        if (searchInput.value) params.append('q', searchInput.value);
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
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 justify-between">
                <!-- Category Tabs -->
                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700/80 text-xs font-semibold">
                    <button
                        @click="activeCategory = 'all'"
                        class="px-3.5 py-2 rounded-lg transition-colors flex items-center gap-1.5"
                        :class="activeCategory === 'all' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        <ImageIcon class="w-3.5 h-3.5" />
                        All Assets
                    </button>
                    <button
                        @click="activeCategory = 'photo'"
                        class="px-3.5 py-2 rounded-lg transition-colors flex items-center gap-1.5"
                        :class="activeCategory === 'photo' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        <ImageIcon class="w-3.5 h-3.5" />
                        Photos
                    </button>
                    <button
                        @click="activeCategory = 'document'"
                        class="px-3.5 py-2 rounded-lg transition-colors flex items-center gap-1.5"
                        :class="activeCategory === 'document' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        <FileCode class="w-3.5 h-3.5" />
                        Documents (PDFs)
                    </button>
                    <button
                        @click="activeCategory = 'audio'"
                        class="px-3.5 py-2 rounded-lg transition-colors flex items-center gap-1.5"
                        :class="activeCategory === 'audio' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        <Music class="w-3.5 h-3.5" />
                        Audio Clips
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative min-w-[240px]">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="searchInput"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Search media titles..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/50"
                    />
                </div>
            </div>
        </div>

        <!-- Meta info -->
        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 px-1">
            <span>Showing {{ mediaItems.length }} of {{ meta.total }} media items</span>
            <span>Page {{ meta.page }} of {{ meta.last_page }}</span>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <div v-for="i in 12" :key="i" class="h-48 bg-slate-100 dark:bg-slate-800/60 rounded-2xl animate-pulse"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="mediaItems.length === 0" class="bg-white dark:bg-slate-900 rounded-2xl p-12 text-center border border-slate-200 dark:border-slate-800">
            <ImageIcon class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">No media items found</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Try switching categories or clearing search keywords.</p>
        </div>

        <!-- Media Grid -->
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <div
                v-for="item in mediaItems"
                :key="item.id"
                @click="activePreviewItem = item"
                class="group relative bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs hover:shadow-lg transition-all cursor-pointer flex flex-col justify-between"
            >
                <div class="relative aspect-square w-full bg-slate-950 flex items-center justify-center overflow-hidden">
                    <img
                        v-if="item.category === 'photo'"
                        :src="item.url"
                        :alt="item.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                    />

                    <div v-else-if="item.category === 'document'" class="w-full h-full flex flex-col items-center justify-center bg-red-950/30 text-red-400 p-4">
                        <FileCode class="w-10 h-10 mb-2" />
                        <span class="text-[10px] font-semibold uppercase tracking-wider bg-red-500/20 px-2 py-0.5 rounded-sm">PDF</span>
                    </div>

                    <div v-else-if="item.category === 'audio'" class="w-full h-full flex flex-col items-center justify-center bg-amber-950/30 text-amber-400 p-4">
                        <Volume2 class="w-10 h-10 mb-2" />
                        <span class="text-[10px] font-semibold uppercase tracking-wider bg-amber-500/20 px-2 py-0.5 rounded-sm">Audio</span>
                    </div>

                    <div v-else class="w-full h-full flex flex-col items-center justify-center bg-slate-800 text-slate-400 p-4">
                        <FileText class="w-10 h-10 mb-2" />
                    </div>

                    <!-- Linked people count badge -->
                    <span
                        v-if="item.people && item.people.length > 0"
                        class="absolute top-2 right-2 px-2 py-0.5 bg-black/60 backdrop-blur-xs text-white rounded-full text-[10px] font-semibold flex items-center gap-1 shadow-xs"
                    >
                        <Users class="w-2.5 h-2.5" />
                        {{ item.people.length }}
                    </span>
                </div>

                <div class="p-3 border-t border-slate-100 dark:border-slate-800/80">
                    <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                        {{ item.title }}
                    </div>
                    <div class="text-[10px] text-slate-400 truncate mt-0.5">
                        {{ item.file }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
            <button
                @click="fetchMedia(meta.page - 1)"
                :disabled="meta.page <= 1"
                class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition-colors"
            >
                <ChevronLeft class="w-4 h-4" />
            </button>
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 px-3">
                Page {{ meta.page }} of {{ meta.last_page }}
            </span>
            <button
                @click="fetchMedia(meta.page + 1)"
                :disabled="meta.page >= meta.last_page"
                class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition-colors"
            >
                <ChevronRight class="w-4 h-4" />
            </button>
        </div>

        <!-- Preview Modal -->
        <div v-if="activePreviewItem" class="fixed inset-0 z-60 bg-black/90 flex flex-col items-center justify-center p-6 animate-in fade-in">
            <button
                @click="activePreviewItem = null"
                class="absolute top-6 right-6 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
            >
                <X class="w-6 h-6" />
            </button>

            <div class="max-w-4xl max-h-[85vh] flex flex-col items-center justify-center w-full">
                <img
                    v-if="activePreviewItem.category === 'photo'"
                    :src="activePreviewItem.url"
                    :alt="activePreviewItem.title"
                    class="max-w-full max-h-[65vh] rounded-xl object-contain shadow-2xl"
                />

                <iframe
                    v-else-if="activePreviewItem.category === 'document'"
                    :src="activePreviewItem.url"
                    class="w-full h-[65vh] rounded-xl border border-slate-800 shadow-2xl"
                ></iframe>

                <div v-else-if="activePreviewItem.category === 'audio'" class="bg-slate-900 p-8 rounded-2xl border border-slate-800 flex flex-col items-center gap-4 w-full max-w-md">
                    <Music class="w-16 h-16 text-amber-400" />
                    <span class="text-white font-medium text-center">{{ activePreviewItem.title }}</span>
                    <audio controls class="w-full">
                        <source :src="activePreviewItem.url" type="audio/mp4" />
                        Your browser does not support audio playback.
                    </audio>
                </div>

                <!-- Linked People Pill Bar -->
                <div v-if="activePreviewItem.people && activePreviewItem.people.length > 0" class="mt-4 flex flex-wrap items-center justify-center gap-2 max-w-2xl">
                    <span class="text-xs text-slate-400 font-medium">Tagged People:</span>
                    <button
                        v-for="p in activePreviewItem.people"
                        :key="p.id"
                        @click="activePreviewItem = null; emit('select-person', p.id)"
                        class="px-3 py-1 bg-indigo-600/30 border border-indigo-500/50 hover:bg-indigo-600 text-indigo-200 hover:text-white rounded-full text-xs font-semibold transition-colors"
                    >
                        {{ p.name }}
                    </button>
                </div>

                <div class="mt-4 flex items-center justify-between w-full text-slate-300 text-sm border-t border-white/10 pt-4">
                    <span class="font-medium truncate max-w-lg">{{ activePreviewItem.title }}</span>
                    <a
                        :href="activePreviewItem.url"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition-colors"
                    >
                        <ExternalLink class="w-4 h-4" />
                        Open Original File
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
