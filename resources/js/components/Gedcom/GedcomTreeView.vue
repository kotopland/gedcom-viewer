<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    Expand,
    Heart,
    Layers,
    Maximize2,
    RotateCcw,
    Shrink,
    SlidersHorizontal,
    Target,
    User,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
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
    if (e.button !== 0 && e.pointerType === 'mouse') {
        return;
    }

    const target = e.target as HTMLElement;

    if (target.closest('button, select, input, a, [data-clickable]')) {
        return;
    }

    isPointerDown.value = true;
    dragMoved = false;
    startX = e.clientX;
    startY = e.clientY;
    startPanX = panX.value;
    startPanY = panY.value;
};

const onPointerMove = (e: PointerEvent) => {
    if (!isPointerDown.value) {
        return;
    }

    if (initialPinchDistance !== null) {
        return;
    }

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if (!dragMoved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
        dragMoved = true;
        isDragging.value = true;

        try {
            (e.currentTarget as HTMLElement)?.setPointerCapture?.(e.pointerId);
        } catch {}
    }

    if (dragMoved) {
        panX.value = startPanX + dx;
        panY.value = startPanY + dy;
    }
};

const onPointerUp = (e?: PointerEvent) => {
    if (e && e.currentTarget) {
        try {
            (e.currentTarget as HTMLElement)?.releasePointerCapture?.(
                e.pointerId,
            );
        } catch {}
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

        initialPinchDistance = Math.hypot(
            t1.clientX - t2.clientX,
            t1.clientY - t2.clientY,
        );
        initialPinchZoom = zoomLevel.value;
        initialPinchFocalX = focalX;
        initialPinchFocalY = focalY;
        initialPinchPanX = panX.value;
        initialPinchPanY = panY.value;
    }
};

const onTouchMove = (e: TouchEvent) => {
    if (
        e.touches.length === 2 &&
        initialPinchDistance &&
        canvasContainerRef.value
    ) {
        e.preventDefault();
        const rect = canvasContainerRef.value.getBoundingClientRect();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const currentDistance = Math.hypot(
            t1.clientX - t2.clientX,
            t1.clientY - t2.clientY,
        );
        const currentFocalX = (t1.clientX + t2.clientX) / 2 - rect.left;
        const currentFocalY = (t1.clientY + t2.clientY) / 2 - rect.top;

        const scaleRatio = currentDistance / initialPinchDistance;
        const newZoom = Math.min(
            2.5,
            Math.max(0.3, initialPinchZoom * scaleRatio),
        );

        // Focal point in unscaled content space relative to initial pan
        const contentX =
            (initialPinchFocalX - initialPinchPanX) / initialPinchZoom;
        const contentY =
            (initialPinchFocalY - initialPinchPanY) / initialPinchZoom;

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

        if (!canvasContainerRef.value) {
            return;
        }

        const rect = canvasContainerRef.value.getBoundingClientRect();
        const cursorX = e.clientX - rect.left;
        const cursorY = e.clientY - rect.top;

        const zoomFactor = e.deltaY < 0 ? 1.15 : 0.85;
        const newZoom = Math.min(
            2.5,
            Math.max(0.3, Math.round(zoomLevel.value * zoomFactor * 100) / 100),
        );

        const contentX = (cursorX - panX.value) / zoomLevel.value;
        const contentY = (cursorY - panY.value) / zoomLevel.value;

        panX.value = Math.round(cursorX - contentX * newZoom);
        panY.value = Math.round(cursorY - contentY * newZoom);
        zoomLevel.value = newZoom;
    }
};

const handlePersonSelect = (id: string) => {
    if (dragMoved || isDragging.value) {
        return;
    }

    emit('select-person', id);
};

const handleChangeRoot = (id: string) => {
    if (dragMoved || isDragging.value) {
        return;
    }

    emit('change-root', id);
};

const calculateOptimalZoom = () => {
    const maxL = Math.max(ancestorLevels.value, descendantLevels.value);
    const screenW = window.innerWidth;

    if (screenW < 640) {
        if (maxL <= 2) {
            return 0.65;
        }

        if (maxL === 3) {
            return 0.5;
        }

        return 0.4;
    } else if (screenW < 1024) {
        if (maxL <= 2) {
            return 0.75;
        }

        if (maxL === 3) {
            return 0.65;
        }

        return 0.5;
    } else {
        if (maxL <= 2) {
            return 0.85;
        }

        if (maxL === 3) {
            return 0.7;
        }

        if (maxL === 4) {
            return 0.6;
        }

        return 0.5;
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
        const res = await fetch(
            `/api/gedcom/tree/${id}?ancestors=${ancestorLevels.value}&descendants=${descendantLevels.value}`,
        );

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
    { immediate: true },
);

const ancestorBadgeLabel = computed(() => {
    const l = ancestorLevels.value;

    if (l === 0) {
        return 'Ancestors Hidden';
    }

    if (l === 1) {
        return 'Ancestors (Parents)';
    }

    if (l === 2) {
        return 'Ancestors (Parents & Grandparents)';
    }

    if (l === 3) {
        return 'Ancestors (Parents, Grandparents & Gt-Grandparents)';
    }

    return `Ancestors (${l} Generations)`;
});

const primaryPerson = computed(() => {
    return (
        treeData.value?.primary ||
        treeData.value?.ancestors ||
        treeData.value?.descendants
    );
});

const spouseParentsExist = computed(() => {
    const spouses = primaryPerson.value?.spouses || [];

    return spouses.some(
        (s: any) =>
            s.ancestors &&
            s.ancestors.parents &&
            s.ancestors.parents.length > 0,
    );
});

const spouseSiblingsExist = computed(() => {
    const spouses = primaryPerson.value?.spouses || [];

    return spouses.some((s: any) => s.siblings && s.siblings.length > 0);
});

const descendantBadgeLabel = computed(() => {
    const l = descendantLevels.value;

    if (l === 0) {
        return 'Descendants Hidden';
    }

    if (l === 1) {
        return 'Descendants (Children)';
    }

    if (l === 2) {
        return 'Descendants (Children & Grandchildren)';
    }

    if (l === 3) {
        return 'Descendants (Children, Grandchildren & Gt-Grandparents)';
    }

    return `Descendants (${l} Generations)`;
});

const applyZoomFromCenter = (newZoom: number) => {
    if (!canvasContainerRef.value) {
        return;
    }

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
    const newZoom = Math.min(
        2.5,
        Math.round((zoomLevel.value + 0.15) * 100) / 100,
    );
    applyZoomFromCenter(newZoom);
};

const zoomOut = () => {
    const newZoom = Math.max(
        0.3,
        Math.round((zoomLevel.value - 0.15) * 100) / 100,
    );
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
            'flex flex-col transition-all duration-300',
            isFullscreen
                ? 'fixed inset-0 z-50 h-screen min-h-screen w-screen rounded-none border-none bg-slate-100 p-0 shadow-none dark:bg-slate-950'
                : 'relative h-[calc(100dvh-5.5rem-env(safe-area-inset-top,0px))] min-h-[480px] flex-1 overflow-hidden rounded-2xl border border-slate-300/80 bg-slate-200/70 shadow-2xl sm:h-[84vh] sm:min-h-[750px] sm:rounded-3xl dark:border-slate-800 dark:bg-slate-900',
        ]"
    >
        <!-- Floating Tree Controls Bar -->
        <div
            :class="[
                'absolute z-20 max-w-[calc(100vw-2rem)] transition-all duration-300',
                isFullscreen
                    ? 'top-4 left-4 sm:top-6 sm:left-6'
                    : 'top-3 left-3 sm:top-4 sm:left-4',
            ]"
        >
            <!-- Minimized / Collapsed Controls Pill -->
            <div
                v-if="isControlsCollapsed"
                class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white/90 p-1.5 shadow-xl backdrop-blur-md sm:gap-2 dark:border-slate-800 dark:bg-slate-900/90"
            >
                <button
                    @click="isControlsCollapsed = false"
                    class="dark:hover:bg-slate-750 group inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-bold text-slate-800 shadow-xs transition-all hover:bg-slate-200 hover:text-slate-900 sm:gap-2 sm:px-3 dark:bg-slate-800 dark:text-slate-200 dark:hover:text-white"
                    title="Expand Tree Controls & Settings"
                >
                    <SlidersHorizontal
                        class="h-3.5 w-3.5 text-indigo-600 transition-transform group-hover:scale-110 dark:text-indigo-400"
                    />
                    <span>Controls</span>
                    <span
                        class="rounded-md bg-slate-200/80 px-1 text-[10px] font-medium text-slate-600 dark:bg-slate-700/60 dark:text-slate-400"
                    >
                        {{ Math.round(zoomLevel * 100) }}%
                    </span>
                    <ChevronRight
                        class="h-3.5 w-3.5 text-slate-400 transition-transform group-hover:translate-x-0.5"
                    />
                </button>

                <button
                    @click="zoomIn"
                    class="dark:hover:bg-slate-750 cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-700 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-white"
                    title="Zoom In"
                >
                    <ZoomIn class="h-3.5 w-3.5" />
                </button>

                <button
                    @click="zoomOut"
                    class="dark:hover:bg-slate-750 cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-700 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-white"
                    title="Zoom Out"
                >
                    <ZoomOut class="h-3.5 w-3.5" />
                </button>

                <button
                    @click="toggleFullscreen"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-xl bg-indigo-600/90 px-2.5 py-1.5 text-xs font-extrabold text-white shadow-xs transition-all hover:bg-indigo-600 active:scale-95"
                    :title="
                        isFullscreen ? 'Exit Full Screen (Esc)' : 'Full Screen'
                    "
                >
                    <Shrink v-if="isFullscreen" class="h-3.5 w-3.5" />
                    <Expand v-else class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">{{
                        isFullscreen ? 'Exit' : 'Full Screen'
                    }}</span>
                </button>
            </div>

            <!-- Expanded Controls Bar -->
            <div
                v-else
                class="flex max-w-[calc(100vw-2.5rem)] animate-in flex-col items-stretch gap-2.5 overflow-x-auto rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur-md duration-150 zoom-in-95 fade-in sm:flex-row sm:flex-wrap sm:items-center sm:gap-3 sm:p-2.5 dark:border-slate-800 dark:bg-slate-900/95"
            >
                <div class="flex items-center justify-between gap-2">
                    <!-- Full Screen Toggle Button -->
                    <button
                        @click="toggleFullscreen"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-extrabold text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                        :title="
                            isFullscreen
                                ? 'Exit Full Screen (Esc)'
                                : 'Expand to Full Screen'
                        "
                    >
                        <Shrink v-if="isFullscreen" class="h-3.5 w-3.5" />
                        <Expand v-else class="h-3.5 w-3.5" />
                        <span>{{ isFullscreen ? 'Exit' : 'Full Screen' }}</span>
                    </button>

                    <!-- Zoom controls -->
                    <div
                        class="flex items-center gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/80"
                    >
                        <button
                            @click="zoomIn"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                            title="Zoom In"
                        >
                            <ZoomIn class="h-3.5 w-3.5" />
                        </button>
                        <button
                            @click="zoomOut"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                            title="Zoom Out"
                        >
                            <ZoomOut class="h-3.5 w-3.5" />
                        </button>
                        <button
                            @click="resetZoom"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                            title="Reset / Fit Zoom"
                        >
                            <Maximize2 class="h-3.5 w-3.5" />
                        </button>
                        <span
                            class="px-1 text-[11px] font-bold text-slate-700 dark:text-slate-300"
                        >
                            {{ Math.round(zoomLevel * 100) }}%
                        </span>
                    </div>

                    <!-- Minimize Button -->
                    <button
                        @click="isControlsCollapsed = true"
                        class="ml-auto cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-600 transition-colors hover:bg-slate-200 hover:text-slate-900 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white"
                        title="Minimize Controls"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                </div>

                <div
                    class="hidden h-4 w-px bg-slate-200 sm:block dark:bg-slate-800"
                ></div>

                <!-- Tree Focus Indicator Pill -->
                <div
                    v-if="treeData?.primary"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs dark:border-indigo-800/60 dark:bg-indigo-950/60"
                >
                    <Target
                        class="h-3.5 w-3.5 shrink-0 text-indigo-600 dark:text-indigo-400"
                    />
                    <span
                        class="max-w-[120px] truncate font-bold text-slate-900 sm:max-w-[180px] dark:text-white"
                        :title="treeData.primary.name"
                    >
                        {{ treeData.primary.name }}
                    </span>
                    <button
                        v-if="
                            props.rootPersonId &&
                            treeData.primary.id !== props.rootPersonId
                        "
                        @click="emit('change-root', props.rootPersonId)"
                        class="ml-1 flex cursor-pointer items-center gap-1 rounded-md bg-indigo-600 px-1.5 py-0.5 text-[10px] font-extrabold text-white transition-colors hover:bg-indigo-500"
                        title="Reset focus to my root person"
                    >
                        <RotateCcw class="h-3 w-3" />
                        <span>Reset</span>
                    </button>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-2 sm:border-t-0 sm:pt-0 dark:border-slate-800"
                >
                    <!-- Ancestor levels dropdown -->
                    <div class="flex items-center gap-1.5">
                        <label
                            class="flex items-center gap-1 text-[11px] font-semibold text-slate-600 sm:text-xs dark:text-slate-400"
                        >
                            <Layers
                                class="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400"
                            />
                            Ancestors:
                        </label>
                        <select
                            v-model.number="ancestorLevels"
                            class="cursor-pointer rounded-xl border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-900 transition-colors focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
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
                        <label
                            class="flex items-center gap-1 text-[11px] font-semibold text-slate-600 sm:text-xs dark:text-slate-400"
                        >
                            <Layers
                                class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                            />
                            Descendants:
                        </label>
                        <select
                            v-model.number="descendantLevels"
                            class="cursor-pointer rounded-xl border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-900 transition-colors focus:border-emerald-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
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
                'relative flex-1 touch-none overflow-hidden select-none',
                isDragging ? 'cursor-grabbing' : 'cursor-grab',
            ]"
        >
            <div
                v-if="loading"
                class="m-auto flex h-full flex-col items-center justify-center py-20 text-slate-500 dark:text-slate-400"
            >
                <div
                    class="h-10 w-10 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent dark:border-indigo-500"
                ></div>
                <p
                    class="mt-4 text-xs font-semibold text-slate-700 dark:text-slate-300"
                >
                    Rendering visual tree graph...
                </p>
            </div>

            <div
                v-else-if="treeData"
                ref="treeContentRef"
                class="absolute top-0 left-0 flex min-w-max shrink-0 flex-col items-center gap-14 p-12 transition-transform duration-75 ease-out sm:p-20"
                :style="{
                    transform: `translate3d(${panX}px, ${panY}px, 0px) scale(${zoomLevel})`,
                    transformOrigin: '0 0',
                    willChange: 'transform',
                }"
            >
                <!-- Ancestors Section (Top) -->
                <div
                    v-if="
                        ancestorLevels > 0 &&
                        ((treeData.ancestors &&
                            treeData.ancestors.parents &&
                            treeData.ancestors.parents.length > 0) ||
                            spouseParentsExist)
                    "
                    class="flex flex-col items-center gap-6"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full border border-indigo-300 bg-indigo-100 px-3.5 py-1 text-[11px] font-bold tracking-widest text-indigo-700 uppercase shadow-md dark:border-indigo-800/50 dark:bg-indigo-950/80 dark:text-indigo-400"
                        >
                            {{ ancestorBadgeLabel }}
                        </span>
                        <span
                            v-if="spouseParentsExist"
                            class="rounded-full border border-rose-300 bg-rose-100 px-3 py-1 text-[11px] font-bold tracking-widest text-rose-700 uppercase shadow-md dark:border-rose-800/50 dark:bg-rose-950/80 dark:text-rose-300"
                        >
                            + Spouse Lineage
                        </span>
                    </div>

                    <div
                        class="flex flex-wrap items-start justify-center gap-8 sm:gap-12"
                    >
                        <!-- Tree Focus Person Ancestors Tree -->
                        <div
                            v-if="
                                treeData.ancestors &&
                                treeData.ancestors.parents &&
                                treeData.ancestors.parents.length > 0
                            "
                            class="flex items-start gap-4 sm:gap-6"
                        >
                            <GedcomAncestorNode
                                v-for="(parent, idx) in treeData.ancestors
                                    .parents"
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
                            <template
                                v-for="spouse in treeData.primary.spouses"
                                :key="spouse.id"
                            >
                                <div
                                    v-if="
                                        spouse.ancestors &&
                                        spouse.ancestors.parents &&
                                        spouse.ancestors.parents.length > 0
                                    "
                                    class="flex items-start gap-4 border-l-2 border-dashed border-rose-300/80 pl-6 sm:gap-6 dark:border-rose-800/60"
                                >
                                    <GedcomAncestorNode
                                        v-for="(parent, idx) in spouse.ancestors
                                            .parents"
                                        :key="parent.id"
                                        :person="parent"
                                        :level="1"
                                        :parent-index="idx"
                                        :is-spouse-side="true"
                                        @select-person="
                                            handlePersonSelect($event)
                                        "
                                        @change-root="handleChangeRoot($event)"
                                    />
                                </div>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- Center Root Person & Spouse(s) Pair Node -->
                <div
                    v-if="primaryPerson"
                    class="flex flex-col items-center gap-2"
                >
                    <div
                        class="flex flex-wrap items-center justify-center gap-3 sm:gap-6"
                    >
                        <!-- Tree Focus Person Card -->
                        <div class="group relative">
                            <div
                                class="absolute inset-0 rounded-full bg-indigo-500/20 blur-xl"
                            ></div>
                            <div
                                @click="handlePersonSelect(primaryPerson.id)"
                                class="relative w-72 cursor-pointer rounded-3xl border-2 border-indigo-500 bg-white p-5 shadow-2xl transition-all hover:scale-105 dark:border-indigo-400 dark:bg-gradient-to-r dark:from-indigo-900 dark:via-indigo-950 dark:to-slate-900"
                            >
                                <div class="flex items-center gap-4">
                                    <img
                                        v-if="primaryPerson.primary_media"
                                        :src="
                                            primaryPerson.portrait_url ||
                                            primaryPerson.primary_media
                                                .portrait_url ||
                                            primaryPerson.primary_media.url
                                        "
                                        class="h-16 w-16 shrink-0 rounded-2xl border-2 border-indigo-500 object-cover shadow-md dark:border-indigo-400"
                                    />
                                    <div
                                        v-else
                                        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-indigo-200 bg-indigo-50 text-indigo-600 dark:border-slate-700 dark:bg-slate-800 dark:text-indigo-400"
                                    >
                                        <User class="h-8 w-8" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <span
                                            class="rounded-md bg-indigo-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wider text-indigo-700 uppercase dark:bg-indigo-500/20 dark:text-indigo-300"
                                        >
                                            Tree Focus
                                        </span>
                                        <h3
                                            class="mt-1 truncate text-sm font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-300"
                                        >
                                            {{ primaryPerson.name }}
                                        </h3>
                                        <p
                                            class="mt-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-200"
                                        >
                                            {{
                                                primaryPerson.birth_year || '?'
                                            }}
                                            –
                                            {{
                                                primaryPerson.death_year ||
                                                'Present'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Spouse Card(s) -->
                        <template
                            v-if="
                                primaryPerson.spouses &&
                                primaryPerson.spouses.length > 0
                            "
                        >
                            <div
                                v-for="spouse in primaryPerson.spouses"
                                :key="spouse.id"
                                class="flex items-center gap-3 sm:gap-6"
                            >
                                <!-- Marriage connector heart badge -->
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-rose-300 bg-rose-100 text-rose-500 shadow-md dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-400"
                                    title="Spouse"
                                >
                                    <Heart class="h-4 w-4 fill-rose-500/30" />
                                </div>

                                <!-- Spouse Person Card -->
                                <div class="group relative">
                                    <div
                                        class="absolute inset-0 rounded-full bg-rose-500/10 blur-xl"
                                    ></div>
                                    <div
                                        @click="handlePersonSelect(spouse.id)"
                                        class="relative w-72 cursor-pointer rounded-3xl border-2 border-rose-400/80 bg-white p-5 shadow-2xl transition-all hover:scale-105 dark:bg-gradient-to-r dark:from-slate-900 dark:via-rose-950/40 dark:to-slate-900"
                                    >
                                        <div class="flex items-center gap-4">
                                            <img
                                                v-if="spouse.primary_media"
                                                :src="
                                                    spouse.portrait_url ||
                                                    spouse.primary_media
                                                        .portrait_url ||
                                                    spouse.primary_media.url
                                                "
                                                class="h-16 w-16 shrink-0 rounded-2xl border-2 border-rose-400/60 object-cover shadow-md"
                                            />
                                            <div
                                                v-else
                                                class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-500 dark:border-slate-700 dark:bg-slate-800 dark:text-rose-400"
                                            >
                                                <User class="h-8 w-8" />
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex items-center justify-between gap-1"
                                                >
                                                    <span
                                                        class="rounded-md bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wider text-rose-700 uppercase dark:bg-rose-500/20 dark:text-rose-300"
                                                    >
                                                        Spouse
                                                    </span>
                                                    <button
                                                        @click.stop="
                                                            handleChangeRoot(
                                                                spouse.id,
                                                            )
                                                        "
                                                        class="cursor-pointer text-[10px] text-slate-500 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                                                        title="Set as Tree Focus"
                                                    >
                                                        Focus
                                                    </button>
                                                </div>
                                                <h3
                                                    class="mt-1 truncate text-sm font-extrabold text-slate-900 group-hover:text-rose-600 dark:text-white dark:group-hover:text-rose-300"
                                                >
                                                    {{ spouse.name }}
                                                </h3>
                                                <p
                                                    class="mt-0.5 text-xs font-medium text-rose-600 dark:text-rose-200/80"
                                                >
                                                    {{
                                                        spouse.birth_year || '?'
                                                    }}
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
                <div
                    v-if="
                        (treeData.siblings && treeData.siblings.length > 0) ||
                        spouseSiblingsExist
                    "
                    class="flex flex-col items-center gap-3 pt-2"
                >
                    <div class="flex items-center gap-2">
                        <div
                            v-if="
                                treeData.siblings &&
                                treeData.siblings.length > 0
                            "
                            class="inline-flex items-center gap-1.5 rounded-full border border-purple-300 bg-purple-100 px-3.5 py-1 text-[11px] font-extrabold tracking-wider text-purple-700 uppercase shadow-md dark:border-purple-800/50 dark:bg-purple-950/80 dark:text-purple-300"
                        >
                            <Users
                                class="h-3.5 w-3.5 text-purple-600 dark:text-purple-400"
                            />
                            <span
                                >Focus Person Siblings ({{
                                    treeData.siblings.length
                                }})</span
                            >
                        </div>
                        <div
                            v-if="spouseSiblingsExist"
                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-300 bg-rose-100 px-3 py-1 text-[11px] font-extrabold tracking-wider text-rose-700 uppercase shadow-md dark:border-rose-800/50 dark:bg-rose-950/80 dark:text-rose-300"
                        >
                            <Users class="h-3.5 w-3.5 text-rose-500" />
                            <span>+ Spouse Siblings</span>
                        </div>
                    </div>

                    <div
                        class="flex max-w-6xl flex-wrap items-center justify-center gap-4 sm:gap-6"
                    >
                        <!-- Primary Focus Person Siblings -->
                        <div
                            v-for="sibling in treeData.siblings"
                            :key="sibling.id"
                            class="flex items-center gap-2.5 rounded-2xl border-2 border-purple-400/60 bg-white p-3 shadow-xl transition-transform hover:scale-105 dark:border-purple-500/50 dark:bg-slate-900"
                        >
                            <!-- Sibling Card -->
                            <div
                                @click="handlePersonSelect(sibling.id)"
                                class="group flex cursor-pointer items-center gap-3"
                            >
                                <img
                                    v-if="sibling.primary_media"
                                    :src="
                                        sibling.portrait_url ||
                                        sibling.primary_media.portrait_url ||
                                        sibling.primary_media.url
                                    "
                                    class="h-11 w-11 shrink-0 rounded-xl border-2 border-purple-400/60 object-cover shadow-sm dark:border-purple-500/50"
                                />
                                <div
                                    v-else
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-purple-200 bg-purple-50 text-purple-600 dark:border-slate-700 dark:bg-slate-800 dark:text-purple-400"
                                >
                                    <User class="h-5 w-5" />
                                </div>

                                <div class="max-w-[140px] min-w-0">
                                    <div
                                        class="flex items-center justify-between gap-1"
                                    >
                                        <span
                                            class="text-[10px] font-bold tracking-wider text-purple-700 uppercase dark:text-purple-300"
                                        >
                                            {{
                                                sibling.sex === 'M'
                                                    ? 'Brother'
                                                    : sibling.sex === 'F'
                                                      ? 'Sister'
                                                      : 'Sibling'
                                            }}
                                        </span>
                                        <button
                                            @click.stop="
                                                handleChangeRoot(sibling.id)
                                            "
                                            class="ml-1 cursor-pointer text-[10px] font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                                            title="Set as Tree Focus"
                                        >
                                            Focus
                                        </button>
                                    </div>
                                    <div
                                        class="truncate text-xs font-bold text-slate-900 group-hover:text-purple-600 dark:text-white dark:group-hover:text-purple-300"
                                    >
                                        {{ sibling.name }}
                                    </div>
                                    <div
                                        class="text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{ sibling.birth_year || '?' }} –
                                        {{ sibling.death_year || 'Present' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Sibling Spouse(s) Badge -->
                            <template
                                v-if="
                                    sibling.spouses &&
                                    sibling.spouses.length > 0
                                "
                            >
                                <div
                                    v-for="sp in sibling.spouses"
                                    :key="sp.id"
                                    @click="handlePersonSelect(sp.id)"
                                    class="flex cursor-pointer items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 p-1.5 transition-colors hover:bg-rose-100 dark:border-rose-800/80 dark:bg-rose-950/70 dark:hover:bg-rose-900/80"
                                    :title="`Spouse of ${sibling.name}: ${sp.name}`"
                                >
                                    <Heart
                                        class="h-3 w-3 shrink-0 fill-rose-500/40 text-rose-500"
                                    />
                                    <img
                                        v-if="sp.primary_media"
                                        :src="
                                            sp.portrait_url ||
                                            sp.primary_media.portrait_url ||
                                            sp.primary_media.url
                                        "
                                        class="h-7 w-7 shrink-0 rounded-lg object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-xs text-rose-600 dark:bg-rose-900/80 dark:text-rose-300"
                                    >
                                        <User class="h-3.5 w-3.5" />
                                    </div>
                                    <div
                                        class="max-w-[100px] truncate text-[10px] font-bold text-rose-900 dark:text-rose-200"
                                    >
                                        {{ sp.name }}
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Focus Person Spouse(s) Siblings -->
                        <template v-if="primaryPerson?.spouses">
                            <template
                                v-for="spouse in primaryPerson.spouses"
                                :key="spouse.id"
                            >
                                <template
                                    v-if="
                                        spouse.siblings &&
                                        spouse.siblings.length > 0
                                    "
                                >
                                    <div
                                        v-for="spSib in spouse.siblings"
                                        :key="spSib.id"
                                        class="flex items-center gap-2.5 rounded-2xl border-2 border-rose-400/60 bg-white p-3 shadow-xl transition-transform hover:scale-105 dark:border-rose-500/50 dark:bg-slate-900"
                                    >
                                        <!-- Spouse Sibling Card -->
                                        <div
                                            @click="
                                                handlePersonSelect(spSib.id)
                                            "
                                            class="group flex cursor-pointer items-center gap-3"
                                        >
                                            <img
                                                v-if="spSib.primary_media"
                                                :src="
                                                    spSib.portrait_url ||
                                                    spSib.primary_media
                                                        .portrait_url ||
                                                    spSib.primary_media.url
                                                "
                                                class="h-11 w-11 shrink-0 rounded-xl border-2 border-rose-400/60 object-cover shadow-sm dark:border-rose-500/50"
                                            />
                                            <div
                                                v-else
                                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-500 dark:border-slate-700 dark:bg-slate-800 dark:text-rose-400"
                                            >
                                                <User class="h-5 w-5" />
                                            </div>

                                            <div class="max-w-[140px] min-w-0">
                                                <div
                                                    class="flex items-center justify-between gap-1"
                                                >
                                                    <span
                                                        class="text-[10px] font-bold tracking-wider text-rose-700 uppercase dark:text-rose-300"
                                                    >
                                                        {{
                                                            spSib.sex === 'M'
                                                                ? "Spouse's Brother"
                                                                : spSib.sex ===
                                                                    'F'
                                                                  ? "Spouse's Sister"
                                                                  : 'Spouse Sibling'
                                                        }}
                                                    </span>
                                                    <button
                                                        @click.stop="
                                                            handleChangeRoot(
                                                                spSib.id,
                                                            )
                                                        "
                                                        class="ml-1 cursor-pointer text-[10px] font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                                                        title="Set as Tree Focus"
                                                    >
                                                        Focus
                                                    </button>
                                                </div>
                                                <div
                                                    class="truncate text-xs font-bold text-slate-900 group-hover:text-rose-600 dark:text-white dark:group-hover:text-rose-300"
                                                >
                                                    {{ spSib.name }}
                                                </div>
                                                <div
                                                    class="text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        spSib.birth_year || '?'
                                                    }}
                                                    –
                                                    {{
                                                        spSib.death_year ||
                                                        'Present'
                                                    }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Spouse Sibling's Spouse(s) Badge -->
                                        <template
                                            v-if="
                                                spSib.spouses &&
                                                spSib.spouses.length > 0
                                            "
                                        >
                                            <div
                                                v-for="sp in spSib.spouses"
                                                :key="sp.id"
                                                @click="
                                                    handlePersonSelect(sp.id)
                                                "
                                                class="flex cursor-pointer items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 p-1.5 transition-colors hover:bg-rose-100 dark:border-rose-800/80 dark:bg-rose-950/70 dark:hover:bg-rose-900/80"
                                                :title="`Spouse of ${spSib.name}: ${sp.name}`"
                                            >
                                                <Heart
                                                    class="h-3 w-3 shrink-0 fill-rose-500/40 text-rose-500"
                                                />
                                                <img
                                                    v-if="sp.primary_media"
                                                    :src="
                                                        sp.portrait_url ||
                                                        sp.primary_media
                                                            .portrait_url ||
                                                        sp.primary_media.url
                                                    "
                                                    class="h-7 w-7 shrink-0 rounded-lg object-cover"
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-xs text-rose-600 dark:bg-rose-900/80 dark:text-rose-300"
                                                >
                                                    <User class="h-3.5 w-3.5" />
                                                </div>
                                                <div
                                                    class="max-w-[100px] truncate text-[10px] font-bold text-rose-900 dark:text-rose-200"
                                                >
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
                <div
                    v-if="
                        descendantLevels > 0 &&
                        treeData.descendants &&
                        treeData.descendants.children &&
                        treeData.descendants.children.length > 0
                    "
                    class="flex flex-col items-center gap-6"
                >
                    <span
                        class="rounded-full border border-emerald-300 bg-emerald-100 px-3.5 py-1 text-[11px] font-bold tracking-widest text-emerald-700 uppercase shadow-md dark:border-emerald-800/50 dark:bg-emerald-950/80 dark:text-emerald-400"
                    >
                        {{ descendantBadgeLabel }}
                    </span>

                    <div
                        class="flex flex-wrap items-start justify-center gap-4 sm:gap-6"
                    >
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
