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
const treeContentRef = ref<HTMLElement | null>(null);

// 2D Pan & Scale state
const panX = ref<number>(0);
const panY = ref<number>(0);

const isDragging = ref(false);
const isPointerDown = ref(false);
let startX = 0;
let startY = 0;
let startPanX = 0;
let startPanY = 0;
let dragMoved = false;

// Pinch zoom focal point state
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
        const contentW = treeContentRef.value.scrollWidth || 1000;
        const contentH = treeContentRef.value.scrollHeight || 1000;

        const scaledW = contentW * zoomLevel.value;
        const scaledH = contentH * zoomLevel.value;

        panX.value = Math.round((containerW - scaledW) / 2);
        panY.value = Math.round((containerH - scaledH) / 4);
    }
};

// Pointer Events for Mouse and Touch Drag Panning
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
};

const onPointerMove = (e: PointerEvent) => {
    if (!isPointerDown.value) return;
    if (initialPinchDistance !== null) return;

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if (!dragMoved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
        dragMoved = true;
        isDragging.value = true;
        try {
            (e.currentTarget as HTMLElement)?.setPointerCapture?.(e.pointerId);
        } catch (_) {}
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

// 2-Finger Touch Pinch-to-Zoom centered on pinch focal point
const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length === 2 && canvasContainerRef.value) {
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
    if (e.touches.length === 2 && initialPinchDistance && canvasContainerRef.value) {
        e.preventDefault();
        const rect = canvasContainerRef.value.getBoundingClientRect();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const currentDistance = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        const currentFocalX = (t1.clientX + t2.clientX) / 2 - rect.left;
        const currentFocalY = (t1.clientY + t2.clientY) / 2 - rect.top;

        const scaleRatio = currentDistance / initialPinchDistance;
        const newZoom = Math.min(2.5, Math.max(0.3, initialPinchZoom * scaleRatio));

        // Focal point in unscaled content space relative to initial pan
        const contentX = (initialPinchFocalX - initialPinchPanX) / initialPinchZoom;
        const contentY = (initialPinchFocalY - initialPinchPanY) / initialPinchZoom;

        panX.value = Math.round(currentFocalX - contentX * newZoom);
        panY.value = Math.round(currentFocalY - contentY * newZoom);
        zoomLevel.value = Math.round(newZoom * 100) / 100;
    }
};

const onTouchEnd = (e: TouchEvent) => {
    if (e.touches.length < 2) {
        initialPinchDistance = null;
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
        const newZoom = Math.min(2.5, Math.max(0.3, Math.round(zoomLevel.value * zoomFactor * 100) / 100));

        const contentX = (cursorX - panX.value) / zoomLevel.value;
        const contentY = (cursorY - panY.value) / zoomLevel.value;

        panX.value = Math.round(cursorX - contentX * newZoom);
        panY.value = Math.round(cursorY - contentY * newZoom);
        zoomLevel.value = newZoom;
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

const awaitNextTickCenter = async () => {
    await nextTick();
    setTimeout(centerTree, 60);
};

// Auto-adjust zoom scale whenever generation depth changes
watch([ancestorLevels, descendantLevels], () => {
    zoomLevel.value = calculateOptimalZoom();
    awaitNextTickCenter();
});

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

const primaryPerson = computed(() => {
    return treeData.value?.primary || treeData.value?.ancestors || treeData.value?.descendants;
});

const spouseParentsExist = computed(() => {
    const spouses = primaryPerson.value?.spouses || [];
    return spouses.some((s: any) => s.ancestors && s.ancestors.parents && s.ancestors.parents.length > 0);
});

const spouseSiblingsExist = computed(() => {
    const spouses = primaryPerson.value?.spouses || [];
    return spouses.some((s: any) => s.siblings && s.siblings.length > 0);
});

const descendantBadgeLabel = computed(() => {
    const l = descendantLevels.value;
    if (l === 0) return 'Descendants Hidden';
    if (l === 1) return 'Descendants (Children)';
    if (l === 2) return 'Descendants (Children & Grandchildren)';
    if (l === 3) return 'Descendants (Children, Grandchildren & Gt-Grandparents)';
    return `Descendants (${l} Generations)`;
});

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
    const newZoom = Math.max(0.3, Math.round((zoomLevel.value - 0.15) * 100) / 100);
    applyZoomFromCenter(newZoom);
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
    setTimeout(centerTree, 100);
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
                : 'relative bg-slate-200/70 dark:bg-slate-900 rounded-2xl sm:rounded-3xl border border-slate-300/80 dark:border-slate-800 overflow-hidden shadow-2xl flex-1 min-h-[480px] sm:min-h-[750px] h-[calc(100dvh-5.5rem-env(safe-area-inset-top,0px))] sm:h-[84vh]'
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
                            <option :value="6">6 Gen</option>
                            <option :value="7">7 Gen</option>
                            <option :value="8">8 Gen</option>
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
                            <option :value="6">6 Gen</option>
                            <option :value="7">7 Gen</option>
                            <option :value="8">8 Gen</option>
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
            @wheel="onWheel"
            :class="[
                'flex-1 overflow-hidden relative select-none touch-none',
                isDragging ? 'cursor-grabbing' : 'cursor-grab'
            ]"
        >
            <div
                v-if="loading"
                class="m-auto flex flex-col items-center justify-center py-20 text-slate-500 dark:text-slate-400 h-full"
            >
                <div class="w-10 h-10 border-3 border-indigo-600 dark:border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-4 text-xs font-semibold text-slate-700 dark:text-slate-300">Rendering visual tree graph...</p>
            </div>

            <div
                v-else-if="treeData"
                ref="treeContentRef"
                class="absolute top-0 left-0 min-w-max p-12 sm:p-20 flex flex-col items-center gap-14 shrink-0 transition-transform duration-75 ease-out"
                :style="{
                    transform: `translate3d(${panX}px, ${panY}px, 0px) scale(${zoomLevel})`,
                    transformOrigin: '0 0',
                    willChange: 'transform'
                }"
            >
                <!-- Ancestors Section (Top) -->
                <div v-if="ancestorLevels > 0 && ((treeData.ancestors && treeData.ancestors.parents && treeData.ancestors.parents.length > 0) || spouseParentsExist)" class="flex flex-col items-center gap-6">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-widest text-indigo-700 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-950/80 px-3.5 py-1 rounded-full border border-indigo-300 dark:border-indigo-800/50 shadow-md">
                            {{ ancestorBadgeLabel }}
                        </span>
                        <span v-if="spouseParentsExist" class="text-[11px] font-bold uppercase tracking-widest text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-950/80 px-3 py-1 rounded-full border border-rose-300 dark:border-rose-800/50 shadow-md">
                            + Spouse Lineage
                        </span>
                    </div>

                    <div class="flex items-start justify-center gap-8 sm:gap-12 flex-wrap">
                        <!-- Tree Focus Person Ancestors Tree -->
                        <div v-if="treeData.ancestors && treeData.ancestors.parents && treeData.ancestors.parents.length > 0" class="flex items-start gap-4 sm:gap-6">
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

                        <!-- Tree Focus Person Spouse(s) Ancestors Tree -->
                        <template v-if="treeData.primary?.spouses">
                            <template v-for="spouse in treeData.primary.spouses" :key="spouse.id">
                                <div v-if="spouse.ancestors && spouse.ancestors.parents && spouse.ancestors.parents.length > 0" class="flex items-start gap-4 sm:gap-6 pl-6 border-l-2 border-dashed border-rose-300/80 dark:border-rose-800/60">
                                    <GedcomAncestorNode
                                        v-for="(parent, idx) in spouse.ancestors.parents"
                                        :key="parent.id"
                                        :person="parent"
                                        :level="1"
                                        :parent-index="idx"
                                        :is-spouse-side="true"
                                        @select-person="handlePersonSelect($event)"
                                        @change-root="handleChangeRoot($event)"
                                    />
                                </div>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- Center Root Person & Spouse(s) Pair Node -->
                <div v-if="primaryPerson" class="flex flex-col items-center gap-2">
                    <div class="flex items-center justify-center gap-3 sm:gap-6 flex-wrap">
                        <!-- Tree Focus Person Card -->
                        <div class="relative group">
                            <div class="absolute inset-0 bg-indigo-500/20 blur-xl rounded-full"></div>
                            <div
                                @click="handlePersonSelect(primaryPerson.id)"
                                class="relative bg-white dark:bg-gradient-to-r dark:from-indigo-900 dark:via-indigo-950 dark:to-slate-900 border-2 border-indigo-500 dark:border-indigo-400 rounded-3xl p-5 w-72 shadow-2xl cursor-pointer transition-all hover:scale-105"
                            >
                                <div class="flex items-center gap-4">
                                    <img
                                        v-if="primaryPerson.primary_media"
                                        :src="primaryPerson.primary_media.url"
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
                                            {{ primaryPerson.name }}
                                        </h3>
                                        <p class="text-xs text-indigo-600 dark:text-indigo-200 font-medium mt-0.5">
                                            {{ primaryPerson.birth_year || '?' }} – {{ primaryPerson.death_year || 'Present' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Spouse Card(s) -->
                        <template v-if="primaryPerson.spouses && primaryPerson.spouses.length > 0">
                            <div
                                v-for="spouse in primaryPerson.spouses"
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

                <!-- Siblings & Spouses of Tree Focus Person & Spouse(s) -->
                <div v-if="(treeData.siblings && treeData.siblings.length > 0) || spouseSiblingsExist" class="flex flex-col items-center gap-3 pt-2">
                    <div class="flex items-center gap-2">
                        <div v-if="treeData.siblings && treeData.siblings.length > 0" class="inline-flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-purple-700 dark:text-purple-300 bg-purple-100 dark:bg-purple-950/80 px-3.5 py-1 rounded-full border border-purple-300 dark:border-purple-800/50 shadow-md">
                            <Users class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                            <span>Focus Person Siblings ({{ treeData.siblings.length }})</span>
                        </div>
                        <div v-if="spouseSiblingsExist" class="inline-flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-950/80 px-3 py-1 rounded-full border border-rose-300 dark:border-rose-800/50 shadow-md">
                            <Users class="w-3.5 h-3.5 text-rose-500" />
                            <span>+ Spouse Siblings</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-4 sm:gap-6 flex-wrap max-w-6xl">
                        <!-- Primary Focus Person Siblings -->
                        <div
                            v-for="sibling in treeData.siblings"
                            :key="sibling.id"
                            class="flex items-center gap-2.5 bg-white dark:bg-slate-900 border-2 border-purple-400/60 dark:border-purple-500/50 rounded-2xl p-3 shadow-xl hover:scale-105 transition-transform"
                        >
                            <!-- Sibling Card -->
                            <div
                                @click="handlePersonSelect(sibling.id)"
                                class="flex items-center gap-3 cursor-pointer group"
                            >
                                <img
                                    v-if="sibling.primary_media"
                                    :src="sibling.primary_media.url"
                                    class="w-11 h-11 rounded-xl object-cover border-2 border-purple-400/60 dark:border-purple-500/50 shadow-sm shrink-0"
                                />
                                <div v-else class="w-11 h-11 rounded-xl bg-purple-50 dark:bg-slate-800 border border-purple-200 dark:border-slate-700 flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0">
                                    <User class="w-5 h-5" />
                                </div>

                                <div class="min-w-0 max-w-[140px]">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-[10px] font-bold text-purple-700 dark:text-purple-300 uppercase tracking-wider">
                                            {{ sibling.sex === 'M' ? 'Brother' : (sibling.sex === 'F' ? 'Sister' : 'Sibling') }}
                                        </span>
                                        <button
                                            @click.stop="handleChangeRoot(sibling.id)"
                                            class="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline font-bold cursor-pointer ml-1"
                                            title="Set as Tree Focus"
                                        >
                                            Focus
                                        </button>
                                    </div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-purple-600 dark:group-hover:text-purple-300">
                                        {{ sibling.name }}
                                    </div>
                                    <div class="text-[10px] font-medium text-slate-500 dark:text-slate-400">
                                        {{ sibling.birth_year || '?' }} – {{ sibling.death_year || 'Present' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Sibling Spouse(s) Badge -->
                            <template v-if="sibling.spouses && sibling.spouses.length > 0">
                                <div
                                    v-for="sp in sibling.spouses"
                                    :key="sp.id"
                                    @click="handlePersonSelect(sp.id)"
                                    class="flex items-center gap-2 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-800/80 rounded-xl p-1.5 cursor-pointer hover:bg-rose-100 dark:hover:bg-rose-900/80 transition-colors"
                                    :title="`Spouse of ${sibling.name}: ${sp.name}`"
                                >
                                    <Heart class="w-3 h-3 text-rose-500 fill-rose-500/40 shrink-0" />
                                    <img
                                        v-if="sp.primary_media"
                                        :src="sp.primary_media.url"
                                        class="w-7 h-7 rounded-lg object-cover shrink-0"
                                    />
                                    <div v-else class="w-7 h-7 rounded-lg bg-rose-100 dark:bg-rose-900/80 text-rose-600 dark:text-rose-300 flex items-center justify-center text-xs shrink-0">
                                        <User class="w-3.5 h-3.5" />
                                    </div>
                                    <div class="text-[10px] font-bold text-rose-900 dark:text-rose-200 truncate max-w-[100px]">
                                        {{ sp.name }}
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Focus Person Spouse(s) Siblings -->
                        <template v-if="primaryPerson?.spouses">
                            <template v-for="spouse in primaryPerson.spouses" :key="spouse.id">
                                <template v-if="spouse.siblings && spouse.siblings.length > 0">
                                    <div
                                        v-for="spSib in spouse.siblings"
                                        :key="spSib.id"
                                        class="flex items-center gap-2.5 bg-white dark:bg-slate-900 border-2 border-rose-400/60 dark:border-rose-500/50 rounded-2xl p-3 shadow-xl hover:scale-105 transition-transform"
                                    >
                                        <!-- Spouse Sibling Card -->
                                        <div
                                            @click="handlePersonSelect(spSib.id)"
                                            class="flex items-center gap-3 cursor-pointer group"
                                        >
                                            <img
                                                v-if="spSib.primary_media"
                                                :src="spSib.primary_media.url"
                                                class="w-11 h-11 rounded-xl object-cover border-2 border-rose-400/60 dark:border-rose-500/50 shadow-sm shrink-0"
                                            />
                                            <div v-else class="w-11 h-11 rounded-xl bg-rose-50 dark:bg-slate-800 border border-rose-200 dark:border-slate-700 flex items-center justify-center text-rose-500 dark:text-rose-400 shrink-0">
                                                <User class="w-5 h-5" />
                                            </div>

                                            <div class="min-w-0 max-w-[140px]">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="text-[10px] font-bold text-rose-700 dark:text-rose-300 uppercase tracking-wider">
                                                        {{ spSib.sex === 'M' ? "Spouse's Brother" : (spSib.sex === 'F' ? "Spouse's Sister" : "Spouse Sibling") }}
                                                    </span>
                                                    <button
                                                        @click.stop="handleChangeRoot(spSib.id)"
                                                        class="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline font-bold cursor-pointer ml-1"
                                                        title="Set as Tree Focus"
                                                    >
                                                        Focus
                                                    </button>
                                                </div>
                                                <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-rose-600 dark:group-hover:text-rose-300">
                                                    {{ spSib.name }}
                                                </div>
                                                <div class="text-[10px] font-medium text-slate-500 dark:text-slate-400">
                                                    {{ spSib.birth_year || '?' }} – {{ spSib.death_year || 'Present' }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Spouse Sibling's Spouse(s) Badge -->
                                        <template v-if="spSib.spouses && spSib.spouses.length > 0">
                                            <div
                                                v-for="sp in spSib.spouses"
                                                :key="sp.id"
                                                @click="handlePersonSelect(sp.id)"
                                                class="flex items-center gap-2 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-800/80 rounded-xl p-1.5 cursor-pointer hover:bg-rose-100 dark:hover:bg-rose-900/80 transition-colors"
                                                :title="`Spouse of ${spSib.name}: ${sp.name}`"
                                            >
                                                <Heart class="w-3 h-3 text-rose-500 fill-rose-500/40 shrink-0" />
                                                <img
                                                    v-if="sp.primary_media"
                                                    :src="sp.primary_media.url"
                                                    class="w-7 h-7 rounded-lg object-cover shrink-0"
                                                />
                                                <div v-else class="w-7 h-7 rounded-lg bg-rose-100 dark:bg-rose-900/80 text-rose-600 dark:text-rose-300 flex items-center justify-center text-xs shrink-0">
                                                    <User class="w-3.5 h-3.5" />
                                                </div>
                                                <div class="text-[10px] font-bold text-rose-900 dark:text-rose-200 truncate max-w-[100px]">
                                                    {{ sp.name }}
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </template>
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
