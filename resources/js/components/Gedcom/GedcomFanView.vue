<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    Expand,
    Layers,
    Maximize2,
    Shrink,
    SlidersHorizontal,
    User,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const loading = ref<boolean>(false);
const treeData = ref<any>(null);
const focusId = ref<string | null>(props.rootPersonId);
const generationDepth = ref<number>(4);
const colorScheme = ref<'lineage' | 'generation' | 'monochrome'>('lineage');

const zoomLevel = ref<number>(1);
const panX = ref<number>(0);
const panY = ref<number>(0);
const isDragging = ref<boolean>(false);
const startX = ref<number>(0);
const startY = ref<number>(0);

const isFullscreen = ref<boolean>(false);
const isControlsCollapsed = ref<boolean>(false);
const showAncestorList = ref<boolean>(true);
const hoveredPerson = ref<any>(null);

const fetchAncestors = async (id: string) => {
    if (!id) {
        return;
    }

    loading.value = true;

    try {
        const res = await fetch(
            `/api/gedcom/tree/${id}?ancestors=${generationDepth.value}&descendants=0`,
        );

        if (res.ok) {
            treeData.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to fetch fan chart data:', e);
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.rootPersonId,
    (newId) => {
        if (newId) {
            focusId.value = newId;
            fetchAncestors(newId);
        }
    },
);

watch(generationDepth, () => {
    if (focusId.value) {
        fetchAncestors(focusId.value);
    }
});

onMounted(() => {
    if (focusId.value) {
        fetchAncestors(focusId.value);
    }
});

const rootPerson = computed(() => {
    return treeData.value?.ancestors || null;
});

// Build binary tree array of slots for generations 1 to N
const fanSectors = computed(() => {
    if (!treeData.value?.ancestors) {
        return [];
    }

    const sectors: any[] = [];
    const maxGen = generationDepth.value;

    const buildBinaryTree = (person: any, index: number, gen: number) => {
        if (!person || gen > maxGen) {
            return;
        }

        sectors.push({
            index,
            gen,
            person,
        });

        if (person.parents && person.parents.length > 0) {
            const father = person.parents[0] || null;
            const mother = person.parents[1] || null;

            if (father) {
                buildBinaryTree(father, index * 2, gen + 1);
            }

            if (mother) {
                buildBinaryTree(mother, index * 2 + 1, gen + 1);
            }
        }
    };

    buildBinaryTree(treeData.value.ancestors, 1, 0);

    return sectors;
});

// SVG Fan geometry constants
const SVG_SIZE = 950;
const CENTER_X = SVG_SIZE / 2;
const CENTER_Y = 530;
const INNER_RADIUS = 90;
const RING_WIDTH = 95;

const getArcPath = (
    gen: number,
    slotInGen: number,
    totalSlotsInGen: number,
) => {
    const rIn = INNER_RADIUS + (gen - 1) * RING_WIDTH;
    const rOut = INNER_RADIUS + gen * RING_WIDTH;

    const startAngleDeg = -180 + (slotInGen / totalSlotsInGen) * 180;
    const endAngleDeg = -180 + ((slotInGen + 1) / totalSlotsInGen) * 180;

    const startRad = (startAngleDeg * Math.PI) / 180;
    const endRad = (endAngleDeg * Math.PI) / 180;

    const x1In = CENTER_X + rIn * Math.cos(startRad);
    const y1In = CENTER_Y + rIn * Math.sin(startRad);

    const x2In = CENTER_X + rIn * Math.cos(endRad);
    const y2In = CENTER_Y + rIn * Math.sin(endRad);

    const x1Out = CENTER_X + rOut * Math.cos(startRad);
    const y1Out = CENTER_Y + rOut * Math.sin(startRad);

    const x2Out = CENTER_X + rOut * Math.cos(endRad);
    const y2Out = CENTER_Y + rOut * Math.sin(endRad);

    const largeArc = endAngleDeg - startAngleDeg > 180 ? 1 : 0;

    return `M ${x1In} ${y1In} L ${x1Out} ${y1Out} A ${rOut} ${rOut} 0 ${largeArc} 1 ${x2Out} ${y2Out} L ${x2In} ${y2In} A ${rIn} ${rIn} 0 ${largeArc} 0 ${x1In} ${y1In} Z`;
};

const getSectorImageCenter = (
    gen: number,
    slotInGen: number,
    totalSlotsInGen: number,
) => {
    const rImg = INNER_RADIUS + (gen - 0.72) * RING_WIDTH;
    const angleDeg = -180 + ((slotInGen + 0.5) / totalSlotsInGen) * 180;
    const angleRad = (angleDeg * Math.PI) / 180;

    return {
        x: CENTER_X + rImg * Math.cos(angleRad),
        y: CENTER_Y + rImg * Math.sin(angleRad),
        angle: angleDeg + 90,
    };
};

const getSectorTextCenter = (
    gen: number,
    slotInGen: number,
    totalSlotsInGen: number,
) => {
    const rText = INNER_RADIUS + (gen - 0.28) * RING_WIDTH;
    const angleDeg = -180 + ((slotInGen + 0.5) / totalSlotsInGen) * 180;
    const angleRad = (angleDeg * Math.PI) / 180;

    return {
        x: CENTER_X + rText * Math.cos(angleRad),
        y: CENTER_Y + rText * Math.sin(angleRad),
        angle: angleDeg + 90,
    };
};

const getSectorFill = (sector: any) => {
    if (colorScheme.value === 'lineage') {
        if (sector.gen === 0) {
            return 'fill-indigo-600 dark:fill-indigo-700';
        }

        const isPaternal = (sector.index >> (sector.gen - 1)) % 2 === 0;

        if (isPaternal) {
            return sector.gen === 1
                ? 'fill-blue-600 dark:fill-blue-600'
                : sector.gen === 2
                  ? 'fill-blue-500 dark:fill-blue-700'
                  : sector.gen === 3
                    ? 'fill-sky-500 dark:fill-sky-700'
                    : 'fill-cyan-500 dark:fill-cyan-700';
        } else {
            return sector.gen === 1
                ? 'fill-pink-600 dark:fill-pink-600'
                : sector.gen === 2
                  ? 'fill-pink-500 dark:fill-pink-700'
                  : sector.gen === 3
                    ? 'fill-rose-500 dark:fill-rose-700'
                    : 'fill-fuchsia-500 dark:fill-fuchsia-700';
        }
    } else if (colorScheme.value === 'generation') {
        const genFills = [
            'fill-indigo-600',
            'fill-indigo-500',
            'fill-emerald-500',
            'fill-amber-500',
            'fill-purple-500',
            'fill-teal-500',
        ];

        return genFills[sector.gen] || 'fill-slate-600';
    } else {
        return 'fill-slate-200 dark:fill-slate-800 hover:fill-indigo-500';
    }
};

const getSectorLabel = (sector: any) => {
    const name = sector.person.name || '';
    const parts = name.trim().split(' ');

    if (parts.length === 1) {
        return name;
    }

    if (sector.gen >= 4) {
        return parts[parts.length - 1];
    }

    return `${parts[0]} ${parts[parts.length - 1]}`;
};

const getSectorDates = (person: any) => {
    const b = person.birth_year
        ? `b.${person.birth_year}`
        : person.birth_date
          ? `b.${person.birth_date}`
          : '';
    const m = person.marriage_year
        ? `m.${person.marriage_year}`
        : person.marriage_date
          ? `m.${person.marriage_date}`
          : '';
    const d = person.death_year
        ? `d.${person.death_year}`
        : person.death_date
          ? `d.${person.death_date}`
          : '';

    const parts = [b, m, d].filter(Boolean);

    return parts.length > 0 ? parts.join(' ') : '—';
};

const getRelationTitle = (sector: any) => {
    if (sector.gen === 0) {
        return 'Focus Person';
    }

    if (sector.gen === 1) {
        return sector.index % 2 === 0 ? 'Father' : 'Mother';
    }

    if (sector.gen === 2) {
        return sector.index === 4
            ? 'Paternal Grandfather'
            : sector.index === 5
              ? 'Paternal Grandmother'
              : sector.index === 6
                ? 'Maternal Grandfather'
                : 'Maternal Grandmother';
    }

    return `Generation ${sector.gen} Ancestor`;
};

const handleSectorClick = (personId: string) => {
    emit('select-person', personId);
};

const handleSectorDoubleClick = (personId: string) => {
    emit('change-root', personId);
};

const fanContainerRef = ref<HTMLElement | null>(null);

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
};
const applyZoomFromCenter = (newZoom: number) => {
    if (!fanContainerRef.value) {
        return;
    }

    const rect = fanContainerRef.value.getBoundingClientRect();
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
        6.0,
        Math.round((zoomLevel.value + 0.25) * 100) / 100,
    );
    applyZoomFromCenter(newZoom);
};

const zoomOut = () => {
    const newZoom = Math.max(
        0.3,
        Math.round((zoomLevel.value - 0.25) * 100) / 100,
    );
    applyZoomFromCenter(newZoom);
};

const resetZoom = () => {
    zoomLevel.value = 1;
    panX.value = 0;
    panY.value = 0;
};

// Mouse Panning Handlers
const onMouseDown = (e: MouseEvent) => {
    if (
        (e.target as HTMLElement).tagName === 'button' ||
        (e.target as HTMLElement).closest('button')
    ) {
        return;
    }

    isDragging.value = true;
    startX.value = e.clientX - panX.value;
    startY.value = e.clientY - panY.value;
};

const onMouseMove = (e: MouseEvent) => {
    if (!isDragging.value) {
        return;
    }

    panX.value = e.clientX - startX.value;
    panY.value = e.clientY - startY.value;
};

const onMouseUp = () => {
    isDragging.value = false;
};

let initialPinchDistance: number | null = null;
let initialPinchZoom: number = 1;
let initialPinchFocalX = 0;
let initialPinchFocalY = 0;
let initialPinchPanX = 0;
let initialPinchPanY = 0;

// Touch Panning & 2-Finger Pinch-to-Zoom Handlers for Mobile Devices
const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length === 1) {
        isDragging.value = true;
        startX.value = e.touches[0].clientX - panX.value;
        startY.value = e.touches[0].clientY - panY.value;
    } else if (e.touches.length === 2 && fanContainerRef.value) {
        isDragging.value = false;
        const rect = fanContainerRef.value.getBoundingClientRect();
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
    if (e.touches.length === 1 && isDragging.value) {
        panX.value = e.touches[0].clientX - startX.value;
        panY.value = e.touches[0].clientY - startY.value;
    } else if (
        e.touches.length === 2 &&
        initialPinchDistance &&
        fanContainerRef.value
    ) {
        const rect = fanContainerRef.value.getBoundingClientRect();
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
            6.0,
            Math.max(0.3, initialPinchZoom * scaleRatio),
        );

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

    if (e.touches.length === 0) {
        isDragging.value = false;
    }
};

const onWheel = (e: WheelEvent) => {
    if (!fanContainerRef.value) {
        return;
    }

    const rect = fanContainerRef.value.getBoundingClientRect();
    const cursorX = e.clientX - rect.left;
    const cursorY = e.clientY - rect.top;

    const zoomFactor = e.deltaY < 0 ? 1.15 : 0.85;
    const newZoom = Math.min(
        6.0,
        Math.max(0.3, Math.round(zoomLevel.value * zoomFactor * 100) / 100),
    );

    const contentX = (cursorX - panX.value) / zoomLevel.value;
    const contentY = (cursorY - panY.value) / zoomLevel.value;

    panX.value = Math.round(cursorX - contentX * newZoom);
    panY.value = Math.round(cursorY - contentY * newZoom);
    zoomLevel.value = newZoom;
};
</script>

<template>
    <div
        :class="[
            'flex flex-col transition-all duration-300',
            isFullscreen
                ? 'fixed inset-0 z-50 h-screen min-h-screen w-screen rounded-none border-none bg-slate-100 p-0 shadow-none dark:bg-slate-950'
                : 'relative h-[85vh] min-h-[550px] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl sm:min-h-[750px] dark:border-slate-800 dark:bg-slate-900',
        ]"
    >
        <!-- Top Floating Controls -->
        <div
            :class="[
                'absolute z-20 max-w-[calc(100vw-2rem)] transition-all duration-300',
                isFullscreen
                    ? 'top-4 left-4 sm:top-6 sm:left-6'
                    : 'top-3 left-3 sm:top-4 sm:left-4',
            ]"
        >
            <!-- Collapsed Pill -->
            <div
                v-if="isControlsCollapsed"
                class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white/90 p-1.5 shadow-xl backdrop-blur-md sm:gap-2 dark:border-slate-800 dark:bg-slate-900/90"
            >
                <button
                    @click="isControlsCollapsed = false"
                    class="group inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-bold text-slate-800 transition-all sm:gap-2 sm:px-3 dark:bg-slate-800 dark:text-slate-200"
                >
                    <SlidersHorizontal
                        class="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400"
                    />
                    <span>Fan Controls</span>
                    <ChevronRight class="h-3.5 w-3.5 text-slate-400" />
                </button>
            </div>

            <!-- Expanded Controls Bar -->
            <div
                v-else
                class="flex animate-in flex-col items-stretch gap-2.5 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur-md duration-150 zoom-in-95 fade-in sm:flex-row sm:flex-wrap sm:items-center sm:gap-3 sm:p-2.5 dark:border-slate-800 dark:bg-slate-900/95"
            >
                <div class="flex items-center justify-between gap-2">
                    <button
                        @click="toggleFullscreen"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-extrabold text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                    >
                        <Shrink v-if="isFullscreen" class="h-3.5 w-3.5" />
                        <Expand v-else class="h-3.5 w-3.5" />
                        <span>{{ isFullscreen ? 'Exit' : 'Full Screen' }}</span>
                    </button>

                    <!-- Zoom Controls -->
                    <div
                        class="flex items-center gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/80"
                    >
                        <button
                            @click="zoomIn"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                            title="Zoom In"
                        >
                            <ZoomIn class="h-3.5 w-3.5" />
                        </button>
                        <button
                            @click="zoomOut"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                            title="Zoom Out"
                        >
                            <ZoomOut class="h-3.5 w-3.5" />
                        </button>
                        <button
                            @click="resetZoom"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                            title="Reset Fit View"
                        >
                            <Maximize2 class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <button
                        @click="isControlsCollapsed = true"
                        class="ml-auto cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-500 hover:text-slate-900 dark:bg-slate-800 dark:hover:text-white"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                </div>

                <div
                    class="hidden h-4 w-px bg-slate-200 sm:block dark:bg-slate-800"
                ></div>

                <!-- Fan Settings Dropdowns -->
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1.5">
                        <label
                            class="text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                            >Generations:</label
                        >
                        <select
                            v-model.number="generationDepth"
                            class="cursor-pointer rounded-xl border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option :value="2">2 Gen</option>
                            <option :value="3">3 Gen</option>
                            <option :value="4">4 Gen</option>
                            <option :value="5">5 Gen</option>
                            <option :value="6">6 Gen</option>
                            <option :value="7">7 Gen</option>
                            <option :value="8">8 Gen</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <label
                            class="text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                            >Colors:</label
                        >
                        <select
                            v-model="colorScheme"
                            class="cursor-pointer rounded-xl border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="lineage">
                                Lineage (Paternal / Maternal)
                            </option>
                            <option value="generation">
                                By Generation Level
                            </option>
                            <option value="monochrome">
                                Monochrome / Clean
                            </option>
                        </select>
                    </div>

                    <button
                        @click="showAncestorList = !showAncestorList"
                        class="flex cursor-pointer items-center gap-1 rounded-xl border px-2.5 py-1 text-xs font-bold transition-all"
                        :class="
                            showAncestorList
                                ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                                : 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'
                        "
                    >
                        <Layers class="h-3.5 w-3.5" />
                        <span>Gallery List</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Fan Area (Responsive Pan & Zoom Canvas + Ancestors Gallery List) -->
        <div class="relative flex flex-1 flex-col overflow-hidden lg:flex-row">
            <!-- Touch & Drag Pan SVG Canvas Container -->
            <div
                ref="fanContainerRef"
                class="relative flex flex-1 cursor-grab touch-none flex-col items-center justify-center overflow-hidden p-2 select-none active:cursor-grabbing sm:p-6"
                @mousedown="onMouseDown"
                @mousemove="onMouseMove"
                @mouseup="onMouseUp"
                @mouseleave="onMouseUp"
                @touchstart="onTouchStart"
                @touchmove="onTouchMove"
                @touchend="onTouchEnd"
                @wheel.prevent="onWheel"
            >
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
                        Rendering Ancestry Fan Chart...
                    </p>
                </div>

                <div
                    v-else-if="rootPerson"
                    class="flex w-full max-w-[950px] flex-col items-center transition-transform duration-75 ease-out"
                    :style="{
                        transform: `translate3d(${panX}px, ${panY}px, 0px) scale(${zoomLevel})`,
                        transformOrigin: '0 0',
                        willChange: 'transform',
                    }"
                >
                    <!-- Responsive SVG Chart (Fits 100% Mobile Viewport Width by default) -->
                    <svg
                        viewBox="0 0 950 550"
                        class="h-auto w-full max-w-full overflow-visible select-none"
                    >
                        <defs>
                            <!-- Center avatar clip path -->
                            <clipPath id="clip-root-avatar">
                                <circle
                                    :cx="CENTER_X"
                                    :cy="CENTER_Y - 24"
                                    r="24"
                                />
                            </clipPath>

                            <!-- Dynamic sector avatar clip paths -->
                            <clipPath
                                v-for="sector in fanSectors"
                                :key="'clip-def-' + sector.index"
                                :id="'clip-fan-' + sector.index"
                            >
                                <circle
                                    :cx="
                                        getSectorImageCenter(
                                            sector.gen,
                                            sector.index -
                                                Math.pow(2, sector.gen),
                                            Math.pow(2, sector.gen),
                                        ).x
                                    "
                                    :cy="
                                        getSectorImageCenter(
                                            sector.gen,
                                            sector.index -
                                                Math.pow(2, sector.gen),
                                            Math.pow(2, sector.gen),
                                        ).y
                                    "
                                    :r="sector.gen <= 2 ? 14 : 10"
                                />
                            </clipPath>
                        </defs>

                        <g>
                            <!-- Center Focus Circle (Generation 0 - Root Individual) -->
                            <g
                                @click="handleSectorClick(rootPerson.id)"
                                @dblclick="
                                    handleSectorDoubleClick(rootPerson.id)
                                "
                                @mouseenter="hoveredPerson = rootPerson"
                                @mouseleave="hoveredPerson = null"
                                class="group cursor-pointer"
                            >
                                <circle
                                    :cx="CENTER_X"
                                    :cy="CENTER_Y"
                                    :r="INNER_RADIUS"
                                    class="fill-indigo-600 stroke-white stroke-2 shadow-xl transition-colors group-hover:fill-indigo-500 dark:fill-indigo-700 dark:stroke-slate-900"
                                />

                                <!-- Center Avatar Image or Placeholder -->
                                <image
                                    v-if="rootPerson.primary_media?.url"
                                    :href="
                                        rootPerson.portrait_url ||
                                        rootPerson.primary_media
                                            ?.portrait_url ||
                                        rootPerson.primary_media.url
                                    "
                                    :x="CENTER_X - 24"
                                    :y="CENTER_Y - 48"
                                    width="48"
                                    height="48"
                                    clip-path="url(#clip-root-avatar)"
                                    preserveAspectRatio="xMidYMid slice"
                                />
                                <circle
                                    v-else
                                    :cx="CENTER_X"
                                    :cy="CENTER_Y - 24"
                                    r="24"
                                    class="fill-indigo-500/50 stroke-white/40 stroke-1"
                                />

                                <text
                                    :x="CENTER_X"
                                    :y="CENTER_Y + 10"
                                    text-anchor="middle"
                                    class="pointer-events-none fill-white text-xs font-extrabold tracking-tight drop-shadow-xs"
                                >
                                    {{ rootPerson.name.split(' ')[0] }}
                                </text>
                                <text
                                    :x="CENTER_X"
                                    :y="CENTER_Y + 24"
                                    text-anchor="middle"
                                    class="pointer-events-none fill-indigo-100 text-[11px] font-bold"
                                >
                                    {{
                                        rootPerson.name
                                            .split(' ')
                                            .slice(1)
                                            .join(' ')
                                    }}
                                </text>

                                <!-- Dates under center name -->
                                <text
                                    :x="CENTER_X"
                                    :y="CENTER_Y + 40"
                                    text-anchor="middle"
                                    class="pointer-events-none fill-indigo-200 text-[10px] font-semibold"
                                >
                                    {{ getSectorDates(rootPerson) }}
                                </text>
                            </g>

                            <!-- Concentric Ancestor Fan Sectors (Generations 1 to N) -->
                            <g
                                v-for="sector in fanSectors"
                                :key="`${sector.gen}-${sector.index}`"
                            >
                                <template v-if="sector.gen > 0">
                                    <g
                                        @click="
                                            handleSectorClick(sector.person.id)
                                        "
                                        @dblclick="
                                            handleSectorDoubleClick(
                                                sector.person.id,
                                            )
                                        "
                                        @mouseenter="
                                            hoveredPerson = sector.person
                                        "
                                        @mouseleave="hoveredPerson = null"
                                        class="group cursor-pointer"
                                    >
                                        <!-- Fan Arc Path -->
                                        <path
                                            :d="
                                                getArcPath(
                                                    sector.gen,
                                                    sector.index -
                                                        Math.pow(2, sector.gen),
                                                    Math.pow(2, sector.gen),
                                                )
                                            "
                                            :class="[
                                                getSectorFill(sector),
                                                'stroke-1.5 stroke-white shadow-md transition-colors group-hover:brightness-110 dark:stroke-slate-900',
                                            ]"
                                        />

                                        <!-- Sector Avatar Photo Image (Radially Inner Band) -->
                                        <template
                                            v-if="
                                                sector.person.primary_media?.url
                                            "
                                        >
                                            <image
                                                :href="
                                                    sector.person
                                                        .portrait_url ||
                                                    sector.person.primary_media
                                                        ?.portrait_url ||
                                                    sector.person.primary_media
                                                        .url
                                                "
                                                :x="
                                                    getSectorImageCenter(
                                                        sector.gen,
                                                        sector.index -
                                                            Math.pow(
                                                                2,
                                                                sector.gen,
                                                            ),
                                                        Math.pow(2, sector.gen),
                                                    ).x -
                                                    (sector.gen <= 2 ? 14 : 10)
                                                "
                                                :y="
                                                    getSectorImageCenter(
                                                        sector.gen,
                                                        sector.index -
                                                            Math.pow(
                                                                2,
                                                                sector.gen,
                                                            ),
                                                        Math.pow(2, sector.gen),
                                                    ).y -
                                                    (sector.gen <= 2 ? 14 : 10)
                                                "
                                                :width="
                                                    sector.gen <= 2 ? 28 : 20
                                                "
                                                :height="
                                                    sector.gen <= 2 ? 28 : 20
                                                "
                                                :clip-path="`url(#clip-fan-${sector.index})`"
                                                preserveAspectRatio="xMidYMid slice"
                                                class="pointer-events-none"
                                            />
                                        </template>

                                        <!-- Arc Label Text & Dates (Radially Outer Band) -->
                                        <g
                                            :transform="
                                                (() => {
                                                    const c =
                                                        getSectorTextCenter(
                                                            sector.gen,
                                                            sector.index -
                                                                Math.pow(
                                                                    2,
                                                                    sector.gen,
                                                                ),
                                                            Math.pow(
                                                                2,
                                                                sector.gen,
                                                            ),
                                                        );
                                                    return `translate(${c.x}, ${c.y}) rotate(${c.angle})`;
                                                })()
                                            "
                                        >
                                            <!-- Person Name -->
                                            <text
                                                text-anchor="middle"
                                                y="-2"
                                                class="pointer-events-none fill-white text-[10px] font-extrabold drop-shadow-xs"
                                            >
                                                {{ getSectorLabel(sector) }}
                                            </text>
                                            <!-- Birth, Married, Death Dates -->
                                            <text
                                                text-anchor="middle"
                                                y="10"
                                                class="pointer-events-none fill-white/95 text-[8.5px] font-semibold drop-shadow-xs"
                                            >
                                                {{
                                                    getSectorDates(
                                                        sector.person,
                                                    )
                                                }}
                                            </text>
                                        </g>
                                    </g>
                                </template>
                            </g>
                        </g>
                    </svg>

                    <!-- Hovered Person Summary Tooltip Bar -->
                    <div
                        v-if="hoveredPerson"
                        class="z-20 -mt-4 flex animate-in items-center gap-3 rounded-2xl border border-slate-700 bg-slate-900/95 px-5 py-2.5 text-white shadow-2xl backdrop-blur-md duration-150 fade-in sm:-mt-6"
                    >
                        <!-- Hovered Person Photo Thumbnail -->
                        <img
                            v-if="hoveredPerson.primary_media?.url"
                            :src="
                                hoveredPerson.portrait_url ||
                                hoveredPerson.primary_media?.portrait_url ||
                                hoveredPerson.primary_media.url
                            "
                            class="h-10 w-10 shrink-0 rounded-xl border border-white/20 object-cover shadow-md"
                        />
                        <div
                            v-else
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-xs font-bold text-white"
                        >
                            <User class="h-5 w-5" />
                        </div>

                        <div class="text-xs">
                            <div class="text-sm font-extrabold text-white">
                                {{ hoveredPerson.name }}
                            </div>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] font-medium text-indigo-200"
                            >
                                <span
                                    v-if="
                                        hoveredPerson.birth_date ||
                                        hoveredPerson.birth_year
                                    "
                                    ><span
                                        class="font-bold text-indigo-300 opacity-85"
                                        >b.</span
                                    >
                                    {{
                                        hoveredPerson.birth_date ||
                                        hoveredPerson.birth_year
                                    }}</span
                                >
                                <span
                                    v-if="
                                        hoveredPerson.marriage_date ||
                                        hoveredPerson.marriage_year
                                    "
                                    class="text-rose-300"
                                    ><span class="font-bold opacity-85"
                                        >m.</span
                                    >
                                    {{
                                        hoveredPerson.marriage_date ||
                                        hoveredPerson.marriage_year
                                    }}</span
                                >
                                <span
                                    v-if="
                                        hoveredPerson.death_date ||
                                        hoveredPerson.death_year
                                    "
                                    ><span
                                        class="font-bold text-indigo-300 opacity-85"
                                        >d.</span
                                    >
                                    {{
                                        hoveredPerson.death_date ||
                                        hoveredPerson.death_year
                                    }}</span
                                >
                                <span
                                    v-if="hoveredPerson.birth_place"
                                    class="text-slate-400"
                                    >| {{ hoveredPerson.birth_place }}</span
                                >
                            </div>
                        </div>

                        <button
                            @click="emit('change-root', hoveredPerson.id)"
                            class="ml-3 cursor-pointer rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-md transition-all hover:bg-indigo-500"
                        >
                            Focus
                        </button>
                    </div>
                </div>
            </div>

            <!-- Side Ancestors Gallery List (Photo + Name + Birth/Married/Death Year) -->
            <div
                v-if="showAncestorList && fanSectors.length > 0"
                class="flex max-h-80 w-full shrink-0 flex-col gap-3 overflow-y-auto border-t border-slate-200 bg-slate-50/90 p-4 lg:max-h-none lg:w-80 lg:border-t-0 lg:border-l dark:border-slate-800 dark:bg-slate-900/90"
            >
                <div class="flex items-center justify-between">
                    <h3
                        class="flex items-center gap-1.5 text-xs font-black tracking-wider text-slate-700 uppercase dark:text-slate-300"
                    >
                        <User
                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                        />
                        Fan Chart Ancestors ({{ fanSectors.length }})
                    </h3>
                    <button
                        @click="showAncestorList = false"
                        class="cursor-pointer text-[11px] font-bold text-slate-400 hover:text-slate-700 dark:hover:text-white"
                    >
                        Hide
                    </button>
                </div>

                <div class="space-y-2 overflow-y-auto pr-1">
                    <div
                        v-for="sec in fanSectors"
                        :key="'list-' + sec.index"
                        @click="emit('select-person', sec.person.id)"
                        @dblclick="emit('change-root', sec.person.id)"
                        class="group flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-2xs transition-all hover:border-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-500"
                    >
                        <!-- Person Picture Photo Thumbnail -->
                        <img
                            v-if="sec.person.primary_media?.url"
                            :src="
                                sec.person.portrait_url ||
                                sec.person.primary_media?.portrait_url ||
                                sec.person.primary_media.url
                            "
                            class="h-10 w-10 shrink-0 rounded-xl border border-slate-200 object-cover shadow-xs dark:border-slate-700"
                        />
                        <div
                            v-else
                            class="dark:bg-slate-750 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:text-indigo-400"
                        >
                            <User class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <span
                                    class="truncate text-xs font-bold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                                >
                                    {{ sec.person.name }}
                                </span>
                            </div>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                            >
                                <span
                                    v-if="
                                        sec.person.birth_year ||
                                        sec.person.birth_date
                                    "
                                    class="text-slate-700 dark:text-slate-300"
                                >
                                    b.{{
                                        sec.person.birth_year ||
                                        sec.person.birth_date
                                    }}
                                </span>
                                <span
                                    v-if="
                                        sec.person.marriage_year ||
                                        sec.person.marriage_date
                                    "
                                    class="text-rose-600 dark:text-rose-400"
                                >
                                    m.{{
                                        sec.person.marriage_year ||
                                        sec.person.marriage_date
                                    }}
                                </span>
                                <span
                                    v-if="
                                        sec.person.death_year ||
                                        sec.person.death_date
                                    "
                                    class="text-slate-700 dark:text-slate-300"
                                >
                                    d.{{
                                        sec.person.death_year ||
                                        sec.person.death_date
                                    }}
                                </span>
                            </div>
                            <div
                                class="mt-0.5 text-[9px] font-bold tracking-tight text-indigo-600 uppercase dark:text-indigo-400"
                            >
                                {{ getRelationTitle(sec) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
