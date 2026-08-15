<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import {
    ZoomIn, ZoomOut, Maximize2, RefreshCcw, SlidersHorizontal,
    Expand, Shrink, ChevronRight, ChevronLeft, Search, Plus, Minus,
    ArrowUp, ArrowDown, Sparkles
} from '@lucide/vue';
import GedcomHeritageNode from './GedcomHeritageNode.vue';
import GedcomHeritageAncestorNode from './GedcomHeritageAncestorNode.vue';
import GedcomHeritageDescendantNode from './GedcomHeritageDescendantNode.vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const loading = ref(false);
const treeData = ref<any>(null);
const focusId = ref<string | null>(props.rootPersonId);
const ancestorLevels = ref(2);
const descendantLevels = ref(2);

// Zoom and Pan
const zoomLevel = ref(0.85);
const panX = ref(0);
const panY = ref(0);
const isFullscreen = ref(false);
const isControlsCollapsed = ref(true);
const isMobile = ref(false);

const canvasContainerRef = ref<HTMLElement | null>(null);
const treeContentRef = ref<HTMLElement | null>(null);

// Search input
const searchInput = ref('');
const isSearching = ref(false);
const searchResults = ref<any[]>([]);

// Drag and Touch interactions
const isDragging = ref(false);
const isPointerDown = ref(false);
let startX = 0;
let startY = 0;
let startPanX = 0;
let startPanY = 0;
let dragMoved = false;

// Pinch zoom
let initialPinchDistance: number | null = null;
let initialPinchZoom = 1;
let initialPinchFocalX = 0;
let initialPinchFocalY = 0;
let initialPinchPanX = 0;
let initialPinchPanY = 0;

const checkMobile = () => {
    isMobile.value = window.innerWidth < 640;
};

const centerTree = () => {
    if (canvasContainerRef.value && treeContentRef.value) {
        const containerW = canvasContainerRef.value.clientWidth;
        const containerH = canvasContainerRef.value.clientHeight;
        const contentW = treeContentRef.value.scrollWidth || 1200;
        const contentH = treeContentRef.value.scrollHeight || 1000;

        const scaledW = contentW * zoomLevel.value;
        const scaledH = contentH * zoomLevel.value;

        panX.value = Math.max(20, Math.round((containerW - scaledW) / 2));
        panY.value = Math.max(30, Math.round((containerH - scaledH) / 4));
    }
};

const onPointerDown = (e: PointerEvent) => {
    if (e.button !== 0 && e.pointerType === 'mouse') return;
    const target = e.target as HTMLElement;
    if (target.closest('button, select, input, a, [data-clickable]')) return;

    isPointerDown.value = true;
    dragMoved = false;
    startX = e.clientX;
    startY = e.clientY;
    startPanX = panX.value;
    startPanY = panY.value;

    try {
        (e.currentTarget as HTMLElement)?.setPointerCapture?.(e.pointerId);
    } catch (_) {}
};

const onPointerMove = (e: PointerEvent) => {
    if (!isPointerDown.value) return;
    if (initialPinchDistance !== null) return;

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if (!dragMoved && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) {
        dragMoved = true;
        isDragging.value = true;
    }

    if (dragMoved) {
        panX.value = startPanX + dx;
        panY.value = startPanY + dy;
    }
};

const onPointerUp = (e?: PointerEvent) => {
    if (e && e.currentTarget) {
        try {
            (e.currentTarget as HTMLElement)?.releasePointerCapture?.(e.pointerId);
        } catch (_) {}
    }
    isPointerDown.value = false;
    setTimeout(() => {
        isDragging.value = false;
        dragMoved = false;
    }, 50);
};

const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length === 1) {
        const target = e.target as HTMLElement;
        if (target.closest('button, select, input, a, [data-clickable]')) return;
        const t = e.touches[0];
        isPointerDown.value = true;
        dragMoved = false;
        startX = t.clientX;
        startY = t.clientY;
        startPanX = panX.value;
        startPanY = panY.value;
    } else if (e.touches.length === 2 && canvasContainerRef.value) {
        const rect = canvasContainerRef.value.getBoundingClientRect();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const focalX = (t1.clientX + t2.clientX) / 2 - rect.left;
        const focalY = (t1.clientY + t2.clientY) / 2 - rect.top;

        initialPinchDistance = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        initialPinchZoom = zoomLevel.value;
        initialPinchFocalX = focalX;
        initialPinchFocalY = focalY;
        initialPinchPanX = panX.value;
        initialPinchPanY = panY.value;
    }
};

const onTouchMove = (e: TouchEvent) => {
    if (e.touches.length === 1 && isPointerDown.value && initialPinchDistance === null) {
        e.preventDefault();
        const t = e.touches[0];
        const dx = t.clientX - startX;
        const dy = t.clientY - startY;

        if (!dragMoved && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) {
            dragMoved = true;
            isDragging.value = true;
        }

        if (dragMoved) {
            panX.value = startPanX + dx;
            panY.value = startPanY + dy;
        }
    } else if (e.touches.length === 2 && initialPinchDistance && canvasContainerRef.value) {
        e.preventDefault();
        const rect = canvasContainerRef.value.getBoundingClientRect();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const currentDistance = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        const currentFocalX = (t1.clientX + t2.clientX) / 2 - rect.left;
        const currentFocalY = (t1.clientY + t2.clientY) / 2 - rect.top;

        const scaleRatio = currentDistance / initialPinchDistance;
        const newZoom = Math.min(2.5, Math.max(0.2, initialPinchZoom * scaleRatio));

        const contentX = (initialPinchFocalX - initialPinchPanX) / initialPinchZoom;
        const contentY = (initialPinchFocalY - initialPinchPanY) / initialPinchZoom;

        panX.value = Math.round(currentFocalX - contentX * newZoom);
        panY.value = Math.round(currentFocalY - contentY * newZoom);
        zoomLevel.value = Math.round(newZoom * 100) / 100;
    }
};

const onTouchEnd = (e: TouchEvent) => {
    if (e.touches.length === 0) {
        isPointerDown.value = false;
        initialPinchDistance = null;
        setTimeout(() => {
            isDragging.value = false;
            dragMoved = false;
        }, 50);
    } else if (e.touches.length === 1) {
        initialPinchDistance = null;
        const t = e.touches[0];
        startX = t.clientX;
        startY = t.clientY;
        startPanX = panX.value;
        startPanY = panY.value;
    }
};

const onWheel = (e: WheelEvent) => {
    if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        if (!canvasContainerRef.value) return;

        const rect = canvasContainerRef.value.getBoundingClientRect();
        const cursorX = e.clientX - rect.left;
        const cursorY = e.clientY - rect.top;

        const zoomFactor = e.deltaY < 0 ? 1.15 : 0.85;
        const newZoom = Math.min(2.5, Math.max(0.2, Math.round(zoomLevel.value * zoomFactor * 100) / 100));

        const contentX = (cursorX - panX.value) / zoomLevel.value;
        const contentY = (cursorY - panY.value) / zoomLevel.value;

        panX.value = Math.round(cursorX - contentX * newZoom);
        panY.value = Math.round(cursorY - contentY * newZoom);
        zoomLevel.value = newZoom;
    }
};

const applyZoomFromCenter = (newZoom: number) => {
    if (!canvasContainerRef.value) return;
    const rect = canvasContainerRef.value.getBoundingClientRect();
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;

    const contentX = (centerX - panX.value) / zoomLevel.value;
    const contentY = (centerY - panY.value) / zoomLevel.value;

    panX.value = Math.round(centerX - contentX * newZoom);
    panY.value = Math.round(centerY - contentY * newZoom);
    zoomLevel.value = newZoom;
};

const zoomIn = () => {
    const newZoom = Math.min(2.5, Math.round((zoomLevel.value + 0.15) * 100) / 100);
    applyZoomFromCenter(newZoom);
};

const zoomOut = () => {
    const newZoom = Math.max(0.2, Math.round((zoomLevel.value - 0.15) * 100) / 100);
    applyZoomFromCenter(newZoom);
};

const resetZoom = () => {
    const maxGen = Math.max(ancestorLevels.value, descendantLevels.value);
    if (maxGen >= 4) {
        zoomLevel.value = 0.55;
    } else if (maxGen === 3) {
        zoomLevel.value = 0.70;
    } else {
        zoomLevel.value = isMobile.value ? 0.65 : 0.85;
    }
    nextTick(() => setTimeout(centerTree, 60));
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    if (isFullscreen.value) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
    setTimeout(centerTree, 100);
};

const handlePersonSelect = (id: string) => {
    if (dragMoved || isDragging.value) return;
    emit('select-person', id);
};

const handleChangeRoot = (id: string) => {
    if (dragMoved || isDragging.value) return;
    focusId.value = id;
    emit('change-root', id);
};

const fetchTreeData = async (id: string) => {
    if (!id) return;
    loading.value = true;
    try {
        const res = await fetch(`/api/gedcom/tree/${id}?ancestors=${ancestorLevels.value}&descendants=${descendantLevels.value}`);
        if (res.ok) {
            treeData.value = await res.json();
            nextTick(() => setTimeout(centerTree, 60));
        }
    } catch (e) {
        console.error('Failed to fetch heritage tree data:', e);
    } finally {
        loading.value = false;
    }
};

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
        console.error('Search error:', e);
    } finally {
        isSearching.value = false;
    }
};

const selectSearchPerson = (person: any) => {
    handleChangeRoot(person.id);
    searchInput.value = '';
    searchResults.value = [];
};

// Primary focus person and primary spouse
const primary = computed(() => treeData.value?.primary);
const primarySpouse = computed(() => {
    const spouses = primary.value?.spouses || [];
    return spouses.length > 0 ? spouses[0] : null;
});

// Focus person's parents (Level 1 ancestors)
const focusParents = computed(() => {
    return treeData.value?.ancestors?.parents || [];
});

// Spouse's parents (Level 1 ancestors on spouse side)
const spouseParents = computed(() => {
    const sAncestors = primarySpouse.value?.ancestors;
    return sAncestors?.parents || [];
});

// Descendants (Children of focus couple)
const childrenList = computed(() => {
    return treeData.value?.descendants?.children || [];
});

watch(
    [() => props.rootPersonId, ancestorLevels, descendantLevels],
    ([newId]) => {
        if (newId) {
            focusId.value = newId;
            fetchTreeData(newId);
        }
    },
    { immediate: true }
);

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    document.body.style.overflow = '';
});
</script>

<template>
    <div
        :class="[
            'transition-all duration-300 flex flex-col',
            isFullscreen
                ? 'fixed inset-0 z-50 rounded-none w-screen h-screen min-h-screen bg-slate-50 dark:bg-slate-950 border-none p-0 shadow-none'
                : 'relative bg-white/95 dark:bg-slate-900 rounded-3xl border border-slate-200/90 dark:border-slate-800 overflow-hidden shadow-2xl flex-1 min-h-[500px] sm:min-h-[750px] h-[calc(100dvh-5.5rem-env(safe-area-inset-top,0px))] sm:h-[84vh]'
        ]"
    >
        <!-- Floating Floating Controls Header -->
        <div
            :class="[
                'absolute z-20 transition-all duration-300 max-w-[calc(100vw-2rem)]',
                isFullscreen ? 'top-4 left-4 sm:top-6 sm:left-6' : 'top-3 left-3 sm:top-4 sm:left-4'
            ]"
        >
            <!-- Collapsed Controls Pill -->
            <div
                v-if="isControlsCollapsed"
                class="flex items-center gap-1.5 sm:gap-2 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl"
            >
                <button
                    @click="isControlsCollapsed = false"
                    class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-800 dark:text-slate-200 text-xs font-bold transition-all shadow-xs cursor-pointer group"
                    title="Open Heritage Chart Settings"
                >
                    <SlidersHorizontal class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform" />
                    <span>Heritage Chart</span>
                    <span class="text-[10px] text-slate-600 dark:text-slate-400 font-medium px-1 bg-slate-200/80 dark:bg-slate-700/60 rounded-md">
                        {{ Math.round(zoomLevel * 100) }}% ({{ ancestorLevels }}A / {{ descendantLevels }}D)
                    </span>
                    <ChevronRight class="w-3.5 h-3.5 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                </button>

                <button
                    @click="zoomIn"
                    class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                    title="Zoom In"
                >
                    <ZoomIn class="w-3.5 h-3.5" />
                </button>

                <button
                    @click="zoomOut"
                    class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                    title="Zoom Out"
                >
                    <ZoomOut class="w-3.5 h-3.5" />
                </button>

                <button
                    @click="toggleFullscreen"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-indigo-600 text-white text-xs font-bold transition-all shadow-xs active:scale-95 cursor-pointer"
                    :title="isFullscreen ? 'Exit Full Screen' : 'Full Screen'"
                >
                    <Shrink v-if="isFullscreen" class="w-3.5 h-3.5" />
                    <Expand v-else class="w-3.5 h-3.5" />
                </button>
            </div>

            <!-- Expanded Controls Bar -->
            <div
                v-else
                class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2.5 sm:gap-3 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-3 sm:p-2.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl animate-in fade-in zoom-in-95 duration-150 max-w-[calc(100vw-2.5rem)] overflow-x-auto"
            >
                <div class="flex items-center justify-between gap-2">
                    <button
                        @click="toggleFullscreen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 dark:bg-indigo-600 hover:bg-indigo-600 text-white text-xs font-bold transition-all shadow-md active:scale-95 cursor-pointer"
                    >
                        <Shrink v-if="isFullscreen" class="w-3.5 h-3.5" />
                        <Expand v-else class="w-3.5 h-3.5" />
                        <span>{{ isFullscreen ? 'Exit' : 'Full Screen' }}</span>
                    </button>

                    <!-- Zoom Controls -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl">
                        <button @click="zoomIn" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" title="Zoom In">
                            <ZoomIn class="w-3.5 h-3.5" />
                        </button>
                        <button @click="zoomOut" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" title="Zoom Out">
                            <ZoomOut class="w-3.5 h-3.5" />
                        </button>
                        <button @click="resetZoom" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" title="Fit Screen">
                            <Maximize2 class="w-3.5 h-3.5" />
                        </button>
                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 px-1">
                            {{ Math.round(zoomLevel * 100) }}%
                        </span>
                    </div>

                    <button
                        @click="isControlsCollapsed = true"
                        class="p-1.5 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                        title="Collapse"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                </div>

                <!-- Generation Depth Selectors (Up to 10 Generations) -->
                <div class="flex items-center gap-2.5 border-t sm:border-t-0 sm:border-l border-slate-200 dark:border-slate-800 pt-2 sm:pt-0 sm:pl-3">
                    <!-- Ancestor Depth -->
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <ArrowUp class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                        <span class="hidden sm:inline">Ancestors:</span>
                        <div class="flex items-center gap-0.5 bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg border border-slate-300 dark:border-slate-700">
                            <button
                                @click="ancestorLevels = Math.max(0, ancestorLevels - 1)"
                                :disabled="ancestorLevels <= 0"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Decrease Ancestor Generations"
                            >
                                <Minus class="w-3 h-3" />
                            </button>
                            <select
                                v-model.number="ancestorLevels"
                                class="bg-transparent text-xs font-bold text-slate-800 dark:text-slate-200 px-1.5 py-0.5 border-none focus:outline-hidden cursor-pointer"
                            >
                                <option :value="0">0 (Hidden)</option>
                                <option :value="1">1 Gen (Parents)</option>
                                <option :value="2">2 Gens (Grandparents)</option>
                                <option :value="3">3 Gens (Great-GP)</option>
                                <option :value="4">4 Gens (2nd Gt-GP)</option>
                                <option :value="5">5 Gens (3rd Gt-GP)</option>
                                <option :value="6">6 Gens (4th Gt-GP)</option>
                                <option :value="7">7 Gens (5th Gt-GP)</option>
                                <option :value="8">8 Gens (6th Gt-GP)</option>
                                <option :value="10">10 Generations</option>
                            </select>
                            <button
                                @click="ancestorLevels = Math.min(10, ancestorLevels + 1)"
                                :disabled="ancestorLevels >= 10"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Increase Ancestor Generations"
                            >
                                <Plus class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Descendant Depth -->
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <ArrowDown class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                        <span class="hidden sm:inline">Descendants:</span>
                        <div class="flex items-center gap-0.5 bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg border border-slate-300 dark:border-slate-700">
                            <button
                                @click="descendantLevels = Math.max(0, descendantLevels - 1)"
                                :disabled="descendantLevels <= 0"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Decrease Descendant Generations"
                            >
                                <Minus class="w-3 h-3" />
                            </button>
                            <select
                                v-model.number="descendantLevels"
                                class="bg-transparent text-xs font-bold text-slate-800 dark:text-slate-200 px-1.5 py-0.5 border-none focus:outline-hidden cursor-pointer"
                            >
                                <option :value="0">0 (Hidden)</option>
                                <option :value="1">1 Gen (Children)</option>
                                <option :value="2">2 Gens (Grandchildren)</option>
                                <option :value="3">3 Gens (Great-GC)</option>
                                <option :value="4">4 Gens (2nd Gt-GC)</option>
                                <option :value="5">5 Gens (3rd Gt-GC)</option>
                                <option :value="6">6 Gens (4th Gt-GC)</option>
                                <option :value="7">7 Gens (5th Gt-GC)</option>
                                <option :value="8">8 Gens (6th Gt-GC)</option>
                                <option :value="10">10 Generations</option>
                            </select>
                            <button
                                @click="descendantLevels = Math.min(10, descendantLevels + 1)"
                                :disabled="descendantLevels >= 10"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Increase Descendant Generations"
                            >
                                <Plus class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="relative min-w-[180px] border-t sm:border-t-0 sm:border-l border-slate-200 dark:border-slate-800 pt-2 sm:pt-0 sm:pl-3">
                    <div class="relative">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                        <input
                            v-model="searchInput"
                            @input="handleSearch"
                            type="text"
                            placeholder="Change focus person..."
                            class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 font-medium"
                        />
                    </div>

                    <!-- Search dropdown -->
                    <div
                        v-if="searchResults.length > 0"
                        class="absolute left-0 right-0 top-10 z-30 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl p-1.5 space-y-1 max-h-48 overflow-y-auto"
                    >
                        <button
                            v-for="p in searchResults"
                            :key="'sr-' + p.id"
                            @click="selectSearchPerson(p)"
                            class="w-full p-2 rounded-lg text-left hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-between group cursor-pointer text-xs"
                        >
                            <span class="font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 truncate">
                                {{ p.name }}
                            </span>
                            <span class="text-[10px] text-slate-400 ml-2 shrink-0">
                                b.{{ p.birth_year || '?' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas Container Area -->
        <div
            ref="canvasContainerRef"
            :class="[
                'flex-1 w-full h-full relative overflow-hidden select-none touch-none bg-slate-50/50 dark:bg-slate-950/70',
                isDragging ? 'cursor-grabbing' : 'cursor-grab'
            ]"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @touchstart="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchEnd"
            @wheel.passive="onWheel"
        >
            <!-- Loading Indicator -->
            <div
                v-if="loading"
                class="absolute inset-0 z-30 flex items-center justify-center bg-white/60 dark:bg-slate-950/60 backdrop-blur-xs"
            >
                <div class="flex items-center gap-3 px-5 py-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl">
                    <RefreshCcw class="w-5 h-5 text-indigo-600 dark:text-indigo-400 animate-spin" />
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Loading Generations...
                    </span>
                </div>
            </div>

            <!-- Scaled and Panned Canvas Content -->
            <div
                ref="treeContentRef"
                class="absolute top-0 left-0 min-w-max p-12 sm:p-24 flex flex-col items-center gap-16 shrink-0 transition-transform duration-75 ease-out"
                :style="{
                    transform: `translate3d(${panX}px, ${panY}px, 0px) scale(${zoomLevel})`,
                    transformOrigin: '0 0',
                    willChange: 'transform'
                }"
            >
                <!-- ================= ANCESTORS SECTION (RECURSIVE MULTI-GENERATION) ================= -->
                <div
                    v-if="ancestorLevels >= 1 && (focusParents.length > 0 || spouseParents.length > 0)"
                    class="flex items-start justify-center gap-16 sm:gap-28"
                >
                    <!-- Focus Person Ancestor Tree (Recursive Parents/Grandparents/Great-GP) -->
                    <div v-if="focusParents.length > 0" class="flex flex-col items-center">
                        <div class="flex items-start justify-center gap-8 sm:gap-14 relative">
                            <!-- Father Side Ancestors -->
                            <GedcomHeritageAncestorNode
                                :person="focusParents[0]"
                                :level="1"
                                :parent-index="0"
                                :spouse="focusParents[1]"
                                @select-person="handlePersonSelect"
                                @change-root="handleChangeRoot"
                            />

                            <!-- Marriage Bar between Focus Person's Parents -->
                            <div
                                v-if="focusParents.length > 1"
                                class="absolute top-[148px] left-[210px] right-[210px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                            >
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                            </div>

                            <!-- Mother Side Ancestors -->
                            <GedcomHeritageAncestorNode
                                v-if="focusParents.length > 1"
                                :person="focusParents[1]"
                                :level="1"
                                :parent-index="1"
                                :spouse="focusParents[0]"
                                @select-person="handlePersonSelect"
                                @change-root="handleChangeRoot"
                            />
                        </div>

                        <!-- Drop Line down from Parents to Focus Person -->
                        <div class="w-[3px] h-14 bg-slate-900 dark:bg-slate-300 mt-[-10px] z-0"></div>
                    </div>

                    <!-- Spouse Side Ancestor Tree (Recursive Parents/Grandparents/Great-GP) -->
                    <div v-if="spouseParents.length > 0" class="flex flex-col items-center">
                        <div class="flex items-start justify-center gap-8 sm:gap-14 relative">
                            <!-- Spouse Father Side Ancestors -->
                            <GedcomHeritageAncestorNode
                                :person="spouseParents[0]"
                                :level="1"
                                :parent-index="0"
                                :spouse="spouseParents[1]"
                                @select-person="handlePersonSelect"
                                @change-root="handleChangeRoot"
                            />

                            <!-- Marriage Bar between Spouse's Parents -->
                            <div
                                v-if="spouseParents.length > 1"
                                class="absolute top-[148px] left-[210px] right-[210px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                            >
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                            </div>

                            <!-- Spouse Mother Side Ancestors -->
                            <GedcomHeritageAncestorNode
                                v-if="spouseParents.length > 1"
                                :person="spouseParents[1]"
                                :level="1"
                                :parent-index="1"
                                :spouse="spouseParents[0]"
                                @select-person="handlePersonSelect"
                                @change-root="handleChangeRoot"
                            />
                        </div>

                        <!-- Drop Line down from Spouse Parents to Spouse -->
                        <div class="w-[3px] h-14 bg-slate-900 dark:bg-slate-300 mt-[-10px] z-0"></div>
                    </div>
                </div>

                <!-- ================= PRIMARY FOCUS INDIVIDUAL & SPOUSE ================= -->
                <div v-if="primary" class="flex flex-col items-center relative">
                    <!-- Primary Focus Row -->
                    <div class="flex items-start gap-4 sm:gap-8 relative">
                        <!-- Primary Individual -->
                        <GedcomHeritageNode
                            :person="primary"
                            :spouse="primarySpouse"
                            :is-primary="true"
                            @select-person="handlePersonSelect"
                            @change-root="handleChangeRoot"
                        />

                        <!-- Marriage Horizontal Bar with Anchor Pins -->
                        <div
                            v-if="primarySpouse"
                            class="absolute top-[148px] left-[210px] right-[210px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                        >
                            <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                            <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                        </div>

                        <!-- Spouse of Primary -->
                        <GedcomHeritageNode
                            v-if="primarySpouse"
                            :person="primarySpouse"
                            :spouse="primary"
                            @select-person="handlePersonSelect"
                            @change-root="handleChangeRoot"
                        />
                    </div>

                    <!-- Vertical Drop Line from Primary Marriage Bar down to Children -->
                    <div
                        v-if="descendantLevels >= 1 && childrenList.length > 0"
                        class="w-[3px] h-14 bg-slate-900 dark:bg-slate-300 mt-[-10px] z-0"
                    ></div>
                </div>

                <!-- ================= DESCENDANTS SECTION (RECURSIVE MULTI-GENERATION) ================= -->
                <div
                    v-if="descendantLevels >= 1 && childrenList.length > 0"
                    class="flex flex-col items-center relative mt-[-20px]"
                >
                    <!-- Horizontal Distribution Bracket across Children -->
                    <div
                        v-if="childrenList.length > 1"
                        class="h-[3px] bg-slate-900 dark:bg-slate-300 z-0 relative mb-4"
                        :style="{
                            width: `calc(100% - ${childrenList.length === 2 ? '240px' : '260px'})`
                        }"
                    ></div>

                    <!-- Row of Children -->
                    <div class="flex items-start justify-center gap-12 sm:gap-16 flex-wrap">
                        <div
                            v-for="child in childrenList"
                            :key="child.id"
                            class="flex flex-col items-center relative"
                        >
                            <!-- Vertical Drop Line into Child -->
                            <div class="w-[3px] h-6 bg-slate-900 dark:bg-slate-300 -mt-4 mb-1 z-0"></div>

                            <!-- Recursive Descendant Node for Child, Spouses, Grandchildren, etc. -->
                            <GedcomHeritageDescendantNode
                                :person="child"
                                :level="1"
                                @select-person="handlePersonSelect"
                                @change-root="handleChangeRoot"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Marcellus&display=swap');
</style>
