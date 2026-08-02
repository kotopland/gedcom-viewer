<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
    PieChart, ZoomIn, ZoomOut, Maximize2, Shrink, Expand,
    User, SlidersHorizontal, ChevronRight, ChevronLeft, RefreshCcw, Layers, Heart, Search, Move
} from '@lucide/vue';

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
    if (!id) return;
    loading.value = true;
    try {
        const res = await fetch(`/api/gedcom/tree/${id}?ancestors=${generationDepth.value}&descendants=0`);
        if (res.ok) {
            treeData.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to fetch fan chart data:', e);
    } finally {
        loading.value = false;
    }
};

watch(() => props.rootPersonId, (newId) => {
    if (newId) {
        focusId.value = newId;
        fetchAncestors(newId);
    }
});

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
    if (!treeData.value?.ancestors) return [];

    const sectors: any[] = [];
    const maxGen = generationDepth.value;

    const buildBinaryTree = (person: any, index: number, gen: number) => {
        if (!person || gen > maxGen) return;

        sectors.push({
            index,
            gen,
            person,
        });

        if (person.parents && person.parents.length > 0) {
            const father = person.parents[0] || null;
            const mother = person.parents[1] || null;

            if (father) buildBinaryTree(father, index * 2, gen + 1);
            if (mother) buildBinaryTree(mother, index * 2 + 1, gen + 1);
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

const getArcPath = (gen: number, slotInGen: number, totalSlotsInGen: number) => {
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

const getSectorImageCenter = (gen: number, slotInGen: number, totalSlotsInGen: number) => {
    const rImg = INNER_RADIUS + (gen - 0.72) * RING_WIDTH;
    const angleDeg = -180 + ((slotInGen + 0.5) / totalSlotsInGen) * 180;
    const angleRad = (angleDeg * Math.PI) / 180;

    return {
        x: CENTER_X + rImg * Math.cos(angleRad),
        y: CENTER_Y + rImg * Math.sin(angleRad),
        angle: angleDeg + 90,
    };
};

const getSectorTextCenter = (gen: number, slotInGen: number, totalSlotsInGen: number) => {
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
        if (sector.gen === 0) return 'fill-indigo-600 dark:fill-indigo-700';

        const isPaternal = (sector.index >> (sector.gen - 1)) % 2 === 0;

        if (isPaternal) {
            return sector.gen === 1 ? 'fill-blue-600 dark:fill-blue-600' :
                   sector.gen === 2 ? 'fill-blue-500 dark:fill-blue-700' :
                   sector.gen === 3 ? 'fill-sky-500 dark:fill-sky-700' :
                   'fill-cyan-500 dark:fill-cyan-700';
        } else {
            return sector.gen === 1 ? 'fill-pink-600 dark:fill-pink-600' :
                   sector.gen === 2 ? 'fill-pink-500 dark:fill-pink-700' :
                   sector.gen === 3 ? 'fill-rose-500 dark:fill-rose-700' :
                   'fill-fuchsia-500 dark:fill-fuchsia-700';
        }
    } else if (colorScheme.value === 'generation') {
        const genFills = [
            'fill-indigo-600',
            'fill-indigo-500',
            'fill-emerald-500',
            'fill-amber-500',
            'fill-purple-500',
            'fill-teal-500'
        ];
        return genFills[sector.gen] || 'fill-slate-600';
    } else {
        return 'fill-slate-200 dark:fill-slate-800 hover:fill-indigo-500';
    }
};

const getSectorLabel = (sector: any) => {
    const name = sector.person.name || '';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return name;
    if (sector.gen >= 4) {
        return parts[parts.length - 1];
    }
    return `${parts[0]} ${parts[parts.length - 1]}`;
};

const getSectorDates = (person: any) => {
    const b = person.birth_year ? `b.${person.birth_year}` : (person.birth_date ? `b.${person.birth_date}` : '');
    const m = person.marriage_year ? `m.${person.marriage_year}` : (person.marriage_date ? `m.${person.marriage_date}` : '');
    const d = person.death_year ? `d.${person.death_year}` : (person.death_date ? `d.${person.death_date}` : '');

    const parts = [b, m, d].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : '—';
};

const getRelationTitle = (sector: any) => {
    if (sector.gen === 0) return 'Focus Person';
    if (sector.gen === 1) return sector.index % 2 === 0 ? 'Father' : 'Mother';
    if (sector.gen === 2) {
        return sector.index === 4 ? 'Paternal Grandfather' :
               sector.index === 5 ? 'Paternal Grandmother' :
               sector.index === 6 ? 'Maternal Grandfather' : 'Maternal Grandmother';
    }
    return `Generation ${sector.gen} Ancestor`;
};

const handleSectorClick = (personId: string) => {
    emit('select-person', personId);
};

const handleSectorDoubleClick = (personId: string) => {
    emit('change-root', personId);
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
};
const zoomIn = () => { zoomLevel.value = Math.min(zoomLevel.value + 0.15, 2.5); };
const zoomOut = () => { zoomLevel.value = Math.max(zoomLevel.value - 0.15, 0.4); };
const resetZoom = () => {
    zoomLevel.value = 1;
    panX.value = 0;
    panY.value = 0;
};

// Mouse Panning Handlers
const onMouseDown = (e: MouseEvent) => {
    if ((e.target as HTMLElement).tagName === 'button' || (e.target as HTMLElement).closest('button')) return;
    isDragging.value = true;
    startX.value = e.clientX - panX.value;
    startY.value = e.clientY - panY.value;
};

const onMouseMove = (e: MouseEvent) => {
    if (!isDragging.value) return;
    panX.value = e.clientX - startX.value;
    panY.value = e.clientY - startY.value;
};

const onMouseUp = () => {
    isDragging.value = false;
};

// Touch Panning Handlers for Mobile Devices
const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length === 1) {
        isDragging.value = true;
        startX.value = e.touches[0].clientX - panX.value;
        startY.value = e.touches[0].clientY - panY.value;
    }
};

const onTouchMove = (e: TouchEvent) => {
    if (isDragging.value && e.touches.length === 1) {
        panX.value = e.touches[0].clientX - startX.value;
        panY.value = e.touches[0].clientY - startY.value;
    }
};

const onTouchEnd = () => {
    isDragging.value = false;
};
</script>

<template>
    <div
        :class="[
            'transition-all duration-300 flex flex-col',
            isFullscreen
                ? 'fixed inset-0 z-50 rounded-none w-screen h-screen min-h-screen bg-slate-100 dark:bg-slate-950 border-none p-0 shadow-none'
                : 'relative bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-2xl min-h-[550px] sm:min-h-[750px] h-[85vh]'
        ]"
    >
        <!-- Top Floating Controls -->
        <div
            :class="[
                'absolute z-20 transition-all duration-300 max-w-[calc(100vw-2rem)]',
                isFullscreen ? 'top-4 left-4 sm:top-6 sm:left-6' : 'top-3 left-3 sm:top-4 sm:left-4'
            ]"
        >
            <!-- Collapsed Pill -->
            <div
                v-if="isControlsCollapsed"
                class="flex items-center gap-1.5 sm:gap-2 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl"
            >
                <button
                    @click="isControlsCollapsed = false"
                    class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs font-bold transition-all cursor-pointer group"
                >
                    <SlidersHorizontal class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                    <span>Fan Controls</span>
                    <ChevronRight class="w-3.5 h-3.5 text-slate-400" />
                </button>
            </div>

            <!-- Expanded Controls Bar -->
            <div
                v-else
                class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2.5 sm:gap-3 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-3 sm:p-2.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl animate-in fade-in zoom-in-95 duration-150"
            >
                <div class="flex items-center justify-between gap-2">
                    <button
                        @click="toggleFullscreen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-extrabold transition-all shadow-md active:scale-95 cursor-pointer"
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
                        <button @click="resetZoom" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" title="Reset Fit View">
                            <Maximize2 class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <button
                        @click="isControlsCollapsed = true"
                        class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white cursor-pointer ml-auto"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                </div>

                <div class="hidden sm:block h-4 w-px bg-slate-200 dark:bg-slate-800"></div>

                <!-- Fan Settings Dropdowns -->
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1.5">
                        <label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Generations:</label>
                        <select
                            v-model.number="generationDepth"
                            class="bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs font-semibold rounded-xl px-2 py-1 cursor-pointer"
                        >
                            <option :value="2">2 Gen</option>
                            <option :value="3">3 Gen</option>
                            <option :value="4">4 Gen</option>
                            <option :value="5">5 Gen</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Colors:</label>
                        <select
                            v-model="colorScheme"
                            class="bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs font-semibold rounded-xl px-2 py-1 cursor-pointer"
                        >
                            <option value="lineage">Lineage (Paternal / Maternal)</option>
                            <option value="generation">By Generation Level</option>
                            <option value="monochrome">Monochrome / Clean</option>
                        </select>
                    </div>

                    <button
                        @click="showAncestorList = !showAncestorList"
                        class="px-2.5 py-1 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1 border"
                        :class="showAncestorList ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-700'"
                    >
                        <Layers class="w-3.5 h-3.5" />
                        <span>Gallery List</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Fan Area (Responsive Pan & Zoom Canvas + Ancestors Gallery List) -->
        <div class="flex-1 overflow-hidden flex flex-col lg:flex-row relative">
            <!-- Touch & Drag Pan SVG Canvas Container -->
            <div
                class="flex-1 overflow-hidden flex flex-col items-center justify-center p-2 sm:p-6 relative cursor-grab active:cursor-grabbing touch-none select-none"
                @mousedown="onMouseDown"
                @mousemove="onMouseMove"
                @mouseup="onMouseUp"
                @mouseleave="onMouseUp"
                @touchstart="onTouchStart"
                @touchmove="onTouchMove"
                @touchend="onTouchEnd"
            >
                <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-slate-400">
                    <div class="w-10 h-10 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="mt-4 text-xs font-semibold text-slate-600 dark:text-slate-300">Rendering Ancestry Fan Chart...</p>
                </div>

                <div
                    v-else-if="rootPerson"
                    class="w-full max-w-[950px] transition-transform duration-75 ease-out flex flex-col items-center"
                    :style="{ transform: `translate(${panX}px, ${panY}px) scale(${zoomLevel})` }"
                >
                    <!-- Responsive SVG Chart (Fits 100% Mobile Viewport Width by default) -->
                    <svg
                        viewBox="0 0 950 550"
                        class="w-full h-auto max-w-full overflow-visible select-none"
                    >
                        <defs>
                            <!-- Center avatar clip path -->
                            <clipPath id="clip-root-avatar">
                                <circle :cx="CENTER_X" :cy="CENTER_Y - 24" r="24" />
                            </clipPath>

                            <!-- Dynamic sector avatar clip paths -->
                            <clipPath
                                v-for="sector in fanSectors"
                                :key="'clip-def-' + sector.index"
                                :id="'clip-fan-' + sector.index"
                            >
                                <circle
                                    :cx="getSectorImageCenter(sector.gen, sector.index - Math.pow(2, sector.gen), Math.pow(2, sector.gen)).x"
                                    :cy="getSectorImageCenter(sector.gen, sector.index - Math.pow(2, sector.gen), Math.pow(2, sector.gen)).y"
                                    :r="sector.gen <= 2 ? 14 : 10"
                                />
                            </clipPath>
                        </defs>

                        <g>
                            <!-- Center Focus Circle (Generation 0 - Root Individual) -->
                            <g
                                @click="handleSectorClick(rootPerson.id)"
                                @dblclick="handleSectorDoubleClick(rootPerson.id)"
                                @mouseenter="hoveredPerson = rootPerson"
                                @mouseleave="hoveredPerson = null"
                                class="cursor-pointer group"
                            >
                                <circle
                                    :cx="CENTER_X"
                                    :cy="CENTER_Y"
                                    :r="INNER_RADIUS"
                                    class="fill-indigo-600 dark:fill-indigo-700 stroke-2 stroke-white dark:stroke-slate-900 group-hover:fill-indigo-500 transition-colors shadow-xl"
                                />

                                <!-- Center Avatar Image or Placeholder -->
                                <image
                                    v-if="rootPerson.primary_media?.url"
                                    :href="rootPerson.primary_media.url"
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
                                    class="fill-indigo-500/50 stroke-1 stroke-white/40"
                                />

                                <text
                                    :x="CENTER_X"
                                    :y="CENTER_Y + 10"
                                    text-anchor="middle"
                                    class="fill-white font-extrabold text-xs tracking-tight pointer-events-none drop-shadow-xs"
                                >
                                    {{ rootPerson.name.split(' ')[0] }}
                                </text>
                                <text
                                    :x="CENTER_X"
                                    :y="CENTER_Y + 24"
                                    text-anchor="middle"
                                    class="fill-indigo-100 font-bold text-[11px] pointer-events-none"
                                >
                                    {{ rootPerson.name.split(' ').slice(1).join(' ') }}
                                </text>

                                <!-- Dates under center name -->
                                <text
                                    :x="CENTER_X"
                                    :y="CENTER_Y + 40"
                                    text-anchor="middle"
                                    class="fill-indigo-200 text-[10px] font-semibold pointer-events-none"
                                >
                                    {{ getSectorDates(rootPerson) }}
                                </text>
                            </g>

                            <!-- Concentric Ancestor Fan Sectors (Generations 1 to N) -->
                            <g v-for="sector in fanSectors" :key="`${sector.gen}-${sector.index}`">
                                <template v-if="sector.gen > 0">
                                    <g
                                        @click="handleSectorClick(sector.person.id)"
                                        @dblclick="handleSectorDoubleClick(sector.person.id)"
                                        @mouseenter="hoveredPerson = sector.person"
                                        @mouseleave="hoveredPerson = null"
                                        class="cursor-pointer group"
                                    >
                                        <!-- Fan Arc Path -->
                                        <path
                                            :d="getArcPath(
                                                sector.gen,
                                                sector.index - Math.pow(2, sector.gen),
                                                Math.pow(2, sector.gen)
                                            )"
                                            :class="[
                                                getSectorFill(sector),
                                                'stroke-white dark:stroke-slate-900 stroke-1.5 transition-colors group-hover:brightness-110 shadow-md'
                                            ]"
                                        />

                                        <!-- Sector Avatar Photo Image (Radially Inner Band) -->
                                        <template v-if="sector.person.primary_media?.url">
                                            <image
                                                :href="sector.person.primary_media.url"
                                                :x="getSectorImageCenter(sector.gen, sector.index - Math.pow(2, sector.gen), Math.pow(2, sector.gen)).x - (sector.gen <= 2 ? 14 : 10)"
                                                :y="getSectorImageCenter(sector.gen, sector.index - Math.pow(2, sector.gen), Math.pow(2, sector.gen)).y - (sector.gen <= 2 ? 14 : 10)"
                                                :width="sector.gen <= 2 ? 28 : 20"
                                                :height="sector.gen <= 2 ? 28 : 20"
                                                :clip-path="`url(#clip-fan-${sector.index})`"
                                                preserveAspectRatio="xMidYMid slice"
                                                class="pointer-events-none"
                                            />
                                        </template>

                                        <!-- Arc Label Text & Dates (Radially Outer Band) -->
                                        <g
                                            :transform="(() => {
                                                const c = getSectorTextCenter(
                                                    sector.gen,
                                                    sector.index - Math.pow(2, sector.gen),
                                                    Math.pow(2, sector.gen)
                                                );
                                                return `translate(${c.x}, ${c.y}) rotate(${c.angle})`;
                                            })()"
                                        >
                                            <!-- Person Name -->
                                            <text
                                                text-anchor="middle"
                                                y="-2"
                                                class="fill-white font-extrabold text-[10px] pointer-events-none drop-shadow-xs"
                                            >
                                                {{ getSectorLabel(sector) }}
                                            </text>
                                            <!-- Birth, Married, Death Dates -->
                                            <text
                                                text-anchor="middle"
                                                y="10"
                                                class="fill-white/95 font-semibold text-[8.5px] pointer-events-none drop-shadow-xs"
                                            >
                                                {{ getSectorDates(sector.person) }}
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
                        class="-mt-4 sm:-mt-6 bg-slate-900/95 text-white backdrop-blur-md px-5 py-2.5 rounded-2xl border border-slate-700 shadow-2xl flex items-center gap-3 animate-in fade-in duration-150 z-20"
                    >
                        <!-- Hovered Person Photo Thumbnail -->
                        <img
                            v-if="hoveredPerson.primary_media?.url"
                            :src="hoveredPerson.primary_media.url"
                            class="w-10 h-10 rounded-xl object-cover border border-white/20 shrink-0 shadow-md"
                        />
                        <div v-else class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shrink-0">
                            <User class="w-5 h-5" />
                        </div>

                        <div class="text-xs">
                            <div class="font-extrabold text-white text-sm">{{ hoveredPerson.name }}</div>
                            <div class="text-[11px] text-indigo-200 flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5 font-medium">
                                <span v-if="hoveredPerson.birth_date || hoveredPerson.birth_year"><span class="font-bold opacity-85 text-indigo-300">b.</span> {{ hoveredPerson.birth_date || hoveredPerson.birth_year }}</span>
                                <span v-if="hoveredPerson.marriage_date || hoveredPerson.marriage_year" class="text-rose-300"><span class="font-bold opacity-85">m.</span> {{ hoveredPerson.marriage_date || hoveredPerson.marriage_year }}</span>
                                <span v-if="hoveredPerson.death_date || hoveredPerson.death_year"><span class="font-bold opacity-85 text-indigo-300">d.</span> {{ hoveredPerson.death_date || hoveredPerson.death_year }}</span>
                                <span v-if="hoveredPerson.birth_place" class="text-slate-400">| {{ hoveredPerson.birth_place }}</span>
                            </div>
                        </div>

                        <button
                            @click="emit('change-root', hoveredPerson.id)"
                            class="ml-3 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md cursor-pointer"
                        >
                            Focus
                        </button>
                    </div>
                </div>
            </div>

            <!-- Side Ancestors Gallery List (Photo + Name + Birth/Married/Death Year) -->
            <div
                v-if="showAncestorList && fanSectors.length > 0"
                class="w-full lg:w-80 bg-slate-50/90 dark:bg-slate-850/90 border-t lg:border-t-0 lg:border-l border-slate-200 dark:border-slate-800 p-4 overflow-y-auto flex flex-col gap-3 max-h-80 lg:max-h-none shrink-0"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <User class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        Fan Chart Ancestors ({{ fanSectors.length }})
                    </h3>
                    <button
                        @click="showAncestorList = false"
                        class="text-[11px] font-bold text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer"
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
                        class="p-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-500 transition-all cursor-pointer shadow-2xs flex items-center gap-3 group"
                    >
                        <!-- Person Picture Photo Thumbnail -->
                        <img
                            v-if="sec.person.primary_media?.url"
                            :src="sec.person.primary_media.url"
                            class="w-10 h-10 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0 shadow-xs"
                        />
                        <div v-else class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-slate-750 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <User class="w-5 h-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-900 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                    {{ sec.person.name }}
                                </span>
                            </div>
                            <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-1.5">
                                <span v-if="sec.person.birth_year || sec.person.birth_date" class="text-slate-700 dark:text-slate-300">
                                    b.{{ sec.person.birth_year || sec.person.birth_date }}
                                </span>
                                <span v-if="sec.person.marriage_year || sec.person.marriage_date" class="text-rose-600 dark:text-rose-400">
                                    m.{{ sec.person.marriage_year || sec.person.marriage_date }}
                                </span>
                                <span v-if="sec.person.death_year || sec.person.death_date" class="text-slate-700 dark:text-slate-300">
                                    d.{{ sec.person.death_year || sec.person.death_date }}
                                </span>
                            </div>
                            <div class="text-[9px] text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-tight mt-0.5">
                                {{ getRelationTitle(sec) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
