<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import {
    ZoomIn, ZoomOut, Maximize2, User, RefreshCcw, Layers, Expand, Shrink, Target, RotateCcw,
    SlidersHorizontal, ChevronRight, ChevronLeft, Heart, MoveHand, Sparkles, PieChart
} from '@lucide/vue';
import GedcomAncestorNode from './GedcomAncestorNode.vue';
import GedcomDescendantNode from './GedcomDescendantNode.vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const loading = ref(false);
const treeData = ref<any>(null);
const zoomLevel = ref(1);
const ancestorLevels = ref(2);
const descendantLevels = ref(2);
const isFullscreen = ref(false);
const isControlsCollapsed = ref(true);
const isMobile = ref(false);
const canvasContainerRef = ref<HTMLElement | null>(null);

// Drag & Pan state
const isDragging = ref(false);
const isPointerDown = ref(false);
let startX = 0;
let startY = 0;
let startScrollLeft = 0;
let startScrollTop = 0;
let dragMoved = false;

// Pinch zoom state
let initialPinchDistance: number | null = null;
let initialPinchZoom = 1;

const checkMobile = () => {
    isMobile.value = window.innerWidth < 640;
};

const centerScroll = () => {
    if (canvasContainerRef.value) {
        const { scrollWidth, clientWidth, scrollHeight, clientHeight } = canvasContainerRef.value;
        if (scrollWidth > clientWidth) {
            canvasContainerRef.value.scrollLeft = (scrollWidth - clientWidth) / 2;
        }
        if (scrollHeight > clientHeight) {
            canvasContainerRef.value.scrollTop = (scrollHeight - clientHeight) / 2;
        }
    }
};

// Pointer Events for Mouse and Touch Drag Panning
const onPointerDown = (e: PointerEvent) => {
    if (e.button !== 0 && e.pointerType === 'mouse') return;
    const target = e.target as HTMLElement;
    if (target.closest('button, select, input, a')) return;

    isPointerDown.value = true;
    dragMoved = false;
    startX = e.clientX;
    startY = e.clientY;
    if (canvasContainerRef.value) {
        startScrollLeft = canvasContainerRef.value.scrollLeft;
        startScrollTop = canvasContainerRef.value.scrollTop;
    }
};

const onPointerMove = (e: PointerEvent) => {
    if (!isPointerDown.value || !canvasContainerRef.value) return;

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if (!dragMoved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
        dragMoved = true;
        isDragging.value = true;
    }

    if (dragMoved) {
        canvasContainerRef.value.scrollLeft = startScrollLeft - dx;
        canvasContainerRef.value.scrollTop = startScrollTop - dy;
    }
};

const onPointerUp = () => {
    isPointerDown.value = false;
    setTimeout(() => {
        isDragging.value = false;
        dragMoved = false;
    }, 50);
};

// 2-Finger Touch Pinch-to-Zoom
const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length === 2) {
        const t1 = e.touches[0];
        const t2 = e.touches[1];
        initialPinchDistance = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        initialPinchZoom = zoomLevel.value;
    }
};

const onTouchMove = (e: TouchEvent) => {
    if (e.touches.length === 2 && initialPinchDistance) {
        const t1 = e.touches[0];
        const t2 = e.touches[1];
        const currentDistance = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        const scale = currentDistance / initialPinchDistance;
        const newZoom = Math.min(1.8, Math.max(0.3, initialPinchZoom * scale));
        zoomLevel.value = Math.round(newZoom * 100) / 100;
    }
};

const onTouchEnd = (e: TouchEvent) => {
    if (e.touches.length < 2) {
        initialPinchDistance = null;
    }
};

const handlePersonSelect = (id: string) => {
    if (dragMoved || isDragging.value) return;
    emit('select-person', id);
};

const handleChangeRoot = (id: string) => {
    if (dragMoved || isDragging.value) return;
    emit('change-root', id);
};

const calculateOptimalZoom = () => {
    const maxL = Math.max(ancestorLevels.value, descendantLevels.value);
    const screenW = window.innerWidth;
    
    if (screenW < 640) {
        if (maxL <= 2) return 0.65;
        if (maxL === 3) return 0.50;
        return 0.40;
    } else if (screenW < 1024) {
        if (maxL <= 2) return 0.75;
        if (maxL === 3) return 0.65;
        return 0.50;
    } else {
        if (maxL <= 2) return 0.85;
        if (maxL === 3) return 0.70;
        if (maxL === 4) return 0.60;
        return 0.50;
    }
};

// Auto-adjust zoom scale whenever generation depth changes
watch([ancestorLevels, descendantLevels], () => {
    zoomLevel.value = calculateOptimalZoom();
    awaitNextTickCenter();
});

const awaitNextTickCenter = async () => {
    await nextTick();
    setTimeout(centerScroll, 60);
};

const fetchTreeData = async (id: string) => {
    loading.value = true;
    try {
        const res = await fetch(`/api/gedcom/tree/${id}?ancestors=${ancestorLevels.value}&descendants=${descendantLevels.value}`);
        if (res.ok) {
            treeData.value = await res.json();
            awaitNextTickCenter();
        }
    } catch (e) {
        console.error('Failed to fetch tree data:', e);
    } finally {
        loading.value = false;
    }
};

watch(
    [() => props.rootPersonId, ancestorLevels, descendantLevels],
    ([newId]) => {
        if (newId) {
            fetchTreeData(newId);
        }
    },
    { immediate: true }
);

const ancestorBadgeLabel = computed(() => {
    const l = ancestorLevels.value;
    if (l === 0) return 'Ancestors Hidden';
    if (l === 1) return 'Ancestors (Parents)';
    if (l === 2) return 'Ancestors (Parents & Grandparents)';
    if (l === 3) return 'Ancestors (Parents, Grandparents & Gt-Grandparents)';
    return `Ancestors (${l} Generations)`;
});

const descendantBadgeLabel = computed(() => {
    const l = descendantLevels.value;
    if (l === 0) return 'Descendants Hidden';
    if (l === 1) return 'Descendants (Children)';
    if (l === 2) return 'Descendants (Children & Grandchildren)';
    if (l === 3) return 'Descendants (Children, Grandchildren & Gt-Grandchildren)';
    return `Descendants (${l} Generations)`;
});

const zoomIn = () => {
    zoomLevel.value = Math.min(1.8, Math.round((zoomLevel.value + 0.15) * 100) / 100);
};

const zoomOut = () => {
    zoomLevel.value = Math.max(0.3, Math.round((zoomLevel.value - 0.15) * 100) / 100);
};

const resetZoom = () => {
    zoomLevel.value = calculateOptimalZoom();
    awaitNextTickCenter();
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    if (isFullscreen.value) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
    setTimeout(centerScroll, 100);
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && isFullscreen.value) {
        isFullscreen.value = false;
        document.body.style.overflow = '';
    }
};

onMounted(() => {
    checkMobile();
    zoomLevel.value = calculateOptimalZoom();
    window.addEventListener('resize', checkMobile);
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    window.removeEventListener('keydown', handleKeyDown);
    document.body.style.overflow = '';
});
</script>

<template>
    <div
        :class="[
            'transition-all duration-300 flex flex-col',
            isFullscreen
                ? 'fixed inset-0 z-50 rounded-none w-screen h-screen min-h-screen bg-slate-100 dark:bg-slate-950 border-none p-0 shadow-none'
                : 'relative bg-slate-200/70 dark:bg-slate-900 rounded-3xl border border-slate-300/80 dark:border-slate-800 overflow-hidden shadow-2xl min-h-[550px] sm:min-h-[750px] h-[82vh]'
        ]"
    >
        <!-- Floating Tree Controls Bar -->
        <div
            :class="[
                'absolute z-20 transition-all duration-300 max-w-[calc(100vw-2rem)]',
                isFullscreen ? 'top-4 left-4 sm:top-6 sm:left-6' : 'top-3 left-3 sm:top-4 sm:left-4'
            ]"
        >
            <!-- Minimized / Collapsed Controls Pill -->
            <div
                v-if="isControlsCollapsed"
                class="flex items-center gap-1.5 sm:gap-2 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl"
            >
                <button
                    @click="isControlsCollapsed = false"
                    class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-800 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white text-xs font-bold transition-all shadow-xs cursor-pointer group"
                    title="Expand Tree Controls & Settings"
                >
                    <SlidersHorizontal class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform" />
                    <span>Controls</span>
                    <span class="text-[10px] text-slate-600 dark:text-slate-400 font-medium px-1 bg-slate-200/80 dark:bg-slate-700/60 rounded-md">
                        {{ Math.round(zoomLevel * 100) }}%
                    </span>
                    <ChevronRight class="w-3.5 h-3.5 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                </button>

                <button
                    @click="zoomIn"
                    class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                    title="Zoom In"
                >
                    <ZoomIn class="w-3.5 h-3.5" />
                </button>

                <button
                    @click="zoomOut"
                    class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                    title="Zoom Out"
                >
                    <ZoomOut class="w-3.5 h-3.5" />
                </button>

                <button
                    @click="toggleFullscreen"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-indigo-600/90 hover:bg-indigo-600 text-white text-xs font-extrabold transition-all shadow-xs active:scale-95 cursor-pointer"
                    :title="isFullscreen ? 'Exit Full Screen (Esc)' : 'Full Screen'"
                >
                    <Shrink v-if="isFullscreen" class="w-3.5 h-3.5" />
                    <Expand v-else class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">{{ isFullscreen ? 'Exit' : 'Full Screen' }}</span>
                </button>
            </div>

            <!-- Expanded Controls Bar -->
            <div
                v-else
                class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2.5 sm:gap-3 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-3 sm:p-2.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl animate-in fade-in zoom-in-95 duration-150 max-w-[calc(100vw-2.5rem)] overflow-x-auto"
            >
                <div class="flex items-center justify-between gap-2">
                    <!-- Full Screen Toggle Button -->
                    <button
                        @click="toggleFullscreen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-extrabold transition-all shadow-md active:scale-95 cursor-pointer"
                        :title="isFullscreen ? 'Exit Full Screen (Esc)' : 'Expand to Full Screen'"
                    >
                        <Shrink v-if="isFullscreen" class="w-3.5 h-3.5" />
                        <Expand v-else class="w-3.5 h-3.5" />
                        <span>{{ isFullscreen ? 'Exit' : 'Full Screen' }}</span>
                    </button>

                    <!-- Zoom controls -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl">
                        <button
                            @click="zoomIn"
                            class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                            title="Zoom In"
                        >
                            <ZoomIn class="w-3.5 h-3.5" />
                        </button>
                        <button
                            @click="zoomOut"
                            class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                            title="Zoom Out"
                        >
                            <ZoomOut class="w-3.5 h-3.5" />
                        </button>
                        <button
                            @click="resetZoom"
                            class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                            title="Reset / Fit Zoom"
                        >
                            <Maximize2 class="w-3.5 h-3.5" />
                        </button>
                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 px-1">
                            {{ Math.round(zoomLevel * 100) }}%
                        </span>
                    </div>

                    <!-- Minimize Button -->
                    <button
                        @click="isControlsCollapsed = true"
                        class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer ml-auto"
                        title="Minimize Controls"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                </div>

                <div class="hidden sm:block h-4 w-px bg-slate-200 dark:bg-slate-800"></div>

                <!-- Tree Focus Indicator Pill -->
                <div v-if="treeData?.primary" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/60 text-xs">
                    <Target class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400 shrink-0" />
                    <span class="font-bold text-slate-900 dark:text-white truncate max-w-[120px] sm:max-w-[180px]" :title="treeData.primary.name">
                        {{ treeData.primary.name }}
                    </span>
                    <button
                        v-if="props.rootPersonId && treeData.primary.id !== props.rootPersonId"
                        @click="emit('change-root', props.rootPersonId)"
                        class="ml-1 px-1.5 py-0.5 rounded-md bg-indigo-600 text-white text-[10px] font-extrabold hover:bg-indigo-500 transition-colors flex items-center gap-1 cursor-pointer"
                        title="Reset focus to my root person"
                    >
                        <RotateCcw class="w-3 h-3" />
                        <span>Reset</span>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-200 dark:border-slate-800">
                    <!-- Ancestor levels dropdown -->
                    <div class="flex items-center gap-1.5">
                        <label class="text-[11px] sm:text-xs font-semibold text-slate-600 dark:text-slate-400 flex items-center gap-1">
                            <Layers class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                            Ancestors:
                        </label>
                        <select
                            v-model.number="ancestorLevels"
                            class="bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs font-semibold rounded-xl px-2 py-1 focus:outline-hidden focus:border-indigo-500 transition-colors cursor-pointer"
                        >
                            <option :value="0">0 (Hide)</option>
                            <option :value="1">1 (Parents)</option>
                            <option :value="2">2 (Grandparents)</option>
                            <option :value="3">3 (Gt-Grandparents)</option>
                            <option :value="4">4 Gen</option>
                            <option :value="5">5 Gen</option>
                        </select>
                    </div>

                    <!-- Descendant levels dropdown -->
                    <div class="flex items-center gap-1.5">
                        <label class="text-[11px] sm:text-xs font-semibold text-slate-600 dark:text-slate-400 flex items-center gap-1">
                            <Layers class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                            Descendants:
                        </label>
                        <select
                            v-model.number="descendantLevels"
                            class="bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs font-semibold rounded-xl px-2 py-1 focus:outline-hidden focus:border-emerald-500 transition-colors cursor-pointer"
                        >
                            <option :value="0">0 (Hide)</option>
                            <option :value="1">1 (Children)</option>
                            <option :value="2">2 (Grandchildren)</option>
                            <option :value="3">3 (Gt-Grandchildren)</option>
                            <option :value="4">4 Gen</option>
                            <option :value="5">5 Gen</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tree Canvas -->
        <div
            ref="canvasContainerRef"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @touchstart="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
            :class="[
                'flex-1 overflow-auto flex select-none touch-none',
                isDragging ? 'cursor-grabbing' : 'cursor-grab'
            ]"
        >
            <div
                v-if="loading"
                class="m-auto flex flex-col items-center justify-center py-20 text-slate-500 dark:text-slate-400"
            >
                <div class="w-10 h-10 border-3 border-indigo-600 dark:border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-4 text-xs font-semibold text-slate-700 dark:text-slate-300">Rendering visual tree graph...</p>
            </div>

            <div
                v-else-if="treeData"
                class="m-auto min-w-max p-16 sm:p-24 transition-transform duration-200 ease-out origin-top flex flex-col items-center gap-14"
                :style="{ transform: `scale(${zoomLevel})` }"
            >
                <!-- Ancestors Section (Top) -->
                <div v-if="ancestorLevels > 0 && treeData.ancestors && treeData.ancestors.parents && treeData.ancestors.parents.length > 0" class="flex flex-col items-center gap-6">
                    <span class="text-[11px] font-bold uppercase tracking-widest text-indigo-700 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-950/80 px-3.5 py-1 rounded-full border border-indigo-300 dark:border-indigo-800/50 shadow-md">
                        {{ ancestorBadgeLabel }}
                    </span>

                    <div class="flex items-start gap-4 sm:gap-6">
                        <GedcomAncestorNode
                            v-for="(parent, idx) in treeData.ancestors.parents"
                            :key="parent.id"
                            :person="parent"
                            :level="1"
                            :parent-index="idx"
                            @select-person="handlePersonSelect($event)"
                            @change-root="handleChangeRoot($event)"
                        />
                    </div>
                </div>

                <!-- Center Root Person & Spouse(s) Pair Node -->
                <div v-if="treeData.ancestors || treeData.descendants" class="flex flex-col items-center gap-2">
                    <div class="flex items-center justify-center gap-3 sm:gap-6 flex-wrap">
                        <!-- Tree Focus Person Card -->
                        <div class="relative group">
                            <div class="absolute inset-0 bg-indigo-500/20 blur-xl rounded-full"></div>
                            <div
                                @click="handlePersonSelect((treeData.ancestors || treeData.descendants).id)"
                                class="relative bg-white dark:bg-gradient-to-r dark:from-indigo-900 dark:via-indigo-950 dark:to-slate-900 border-2 border-indigo-500 dark:border-indigo-400 rounded-3xl p-5 w-72 shadow-2xl cursor-pointer transition-all hover:scale-105"
                            >
                                <div class="flex items-center gap-4">
                                    <img
                                        v-if="(treeData.ancestors || treeData.descendants).primary_media"
                                        :src="(treeData.ancestors || treeData.descendants).primary_media.url"
                                        class="w-16 h-16 rounded-2xl object-cover shrink-0 border-2 border-indigo-500 dark:border-indigo-400 shadow-md"
                                    />
                                    <div v-else class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-slate-800 border border-indigo-200 dark:border-slate-700 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                                        <User class="w-8 h-8" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <span class="px-2 py-0.5 rounded-md bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold uppercase tracking-wider">
                                            Tree Focus
                                        </span>
                                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white truncate mt-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-300">
                                            {{ (treeData.ancestors || treeData.descendants).name }}
                                        </h3>
                                        <p class="text-xs text-indigo-600 dark:text-indigo-200 font-medium mt-0.5">
                                            {{ (treeData.ancestors || treeData.descendants).birth_year || '?' }} – {{ (treeData.ancestors || treeData.descendants).death_year || 'Present' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Spouse Card(s) -->
                        <template v-if="(treeData.ancestors || treeData.descendants).spouses && (treeData.ancestors || treeData.descendants).spouses.length > 0">
                            <div
                                v-for="spouse in (treeData.ancestors || treeData.descendants).spouses"
                                :key="spouse.id"
                                class="flex items-center gap-3 sm:gap-6"
                            >
                                <!-- Marriage connector heart badge -->
                                <div class="w-8 h-8 rounded-full bg-rose-100 dark:bg-rose-500/20 border border-rose-300 dark:border-rose-500/40 flex items-center justify-center text-rose-500 dark:text-rose-400 shadow-md" title="Spouse">
                                    <Heart class="w-4 h-4 fill-rose-500/30" />
                                </div>

                                <!-- Spouse Person Card -->
                                <div class="relative group">
                                    <div class="absolute inset-0 bg-rose-500/10 blur-xl rounded-full"></div>
                                    <div
                                        @click="handlePersonSelect(spouse.id)"
                                        class="relative bg-white dark:bg-gradient-to-r dark:from-slate-900 dark:via-rose-950/40 dark:to-slate-900 border-2 border-rose-400/80 rounded-3xl p-5 w-72 shadow-2xl cursor-pointer transition-all hover:scale-105"
                                    >
                                        <div class="flex items-center gap-4">
                                            <img
                                                v-if="spouse.primary_media"
                                                :src="spouse.primary_media.url"
                                                class="w-16 h-16 rounded-2xl object-cover shrink-0 border-2 border-rose-400/60 shadow-md"
                                            />
                                            <div v-else class="w-16 h-16 rounded-2xl bg-rose-50 dark:bg-slate-800 border border-rose-200 dark:border-slate-700 flex items-center justify-center text-rose-500 dark:text-rose-400 shrink-0">
                                                <User class="w-8 h-8" />
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="px-2 py-0.5 rounded-md bg-rose-500/10 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 text-[10px] font-bold uppercase tracking-wider">
                                                        Spouse
                                                    </span>
                                                    <button
                                                        @click.stop="handleChangeRoot(spouse.id)"
                                                        class="text-[10px] text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white underline cursor-pointer"
                                                        title="Set as Tree Focus"
                                                    >
                                                        Focus
                                                    </button>
                                                </div>
                                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white truncate mt-1 group-hover:text-rose-600 dark:group-hover:text-rose-300">
                                                    {{ spouse.name }}
                                                </h3>
                                                <p class="text-xs text-rose-600 dark:text-rose-200/80 font-medium mt-0.5">
                                                    {{ spouse.birth_year || '?' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Descendants Section (Bottom) -->
                <div v-if="descendantLevels > 0 && treeData.descendants && treeData.descendants.children && treeData.descendants.children.length > 0" class="flex flex-col items-center gap-6">
                    <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/80 px-3.5 py-1 rounded-full border border-emerald-300 dark:border-emerald-800/50 shadow-md">
                        {{ descendantBadgeLabel }}
                    </span>

                    <div class="flex flex-wrap justify-center items-start gap-4 sm:gap-6">
                        <GedcomDescendantNode
                            v-for="child in treeData.descendants.children"
                            :key="child.id"
                            :person="child"
                            :level="1"
                            @select-person="handlePersonSelect($event)"
                            @change-root="handleChangeRoot($event)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
