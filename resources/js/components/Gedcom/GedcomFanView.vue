<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
    PieChart, ZoomIn, ZoomOut, Maximize2, Shrink, Expand,
    User, SlidersHorizontal, ChevronRight, ChevronLeft, RefreshCcw, Layers
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
const isFullscreen = ref<boolean>(false);
const isControlsCollapsed = ref<boolean>(false);
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
// Slot index 1 = root, 2 = father, 3 = mother, 4 = paternal grandfather, 5 = paternal grandmother, 6 = maternal grandfather, 7 = maternal grandmother, etc.
const fanSectors = computed(() => {
    if (!treeData.value?.ancestors) return [];

    const sectors: any[] = [];
    const maxGen = generationDepth.value;

    // Helper to recursively collect ancestors into binary heap indexing
    // index 1: root
    // index 2i: father of i, 2i+1: mother of i
    const buildBinaryTree = (person: any, index: number, gen: number) => {
        if (!person || gen > maxGen) return;

        sectors.push({
            index,
            gen,
            person,
        });

        if (person.parents && person.parents.length > 0) {
            // By convention: parent index 0 = Father, 1 = Mother
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
const SVG_SIZE = 900;
const CENTER_X = SVG_SIZE / 2;
const CENTER_Y = SVG_SIZE / 2 + 100; // slightly offset down for semi-circle fan
const INNER_RADIUS = 75;
const RING_WIDTH = 75;

// Helper to compute SVG arc path
const getArcPath = (gen: number, slotInGen: number, totalSlotsInGen: number) => {
    const rIn = INNER_RADIUS + (gen - 1) * RING_WIDTH;
    const rOut = INNER_RADIUS + gen * RING_WIDTH;

    // Fan angle span from -180 deg (left) to 0 deg (right) => 180 degrees total
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

// Helper for text path or text position in sector center
const getSectorCenter = (gen: number, slotInGen: number, totalSlotsInGen: number) => {
    const rMid = INNER_RADIUS + (gen - 0.5) * RING_WIDTH;
    const angleDeg = -180 + ((slotInGen + 0.5) / totalSlotsInGen) * 180;
    const angleRad = (angleDeg * Math.PI) / 180;

    return {
        x: CENTER_X + rMid * Math.cos(angleRad),
        y: CENTER_Y + rMid * Math.sin(angleRad),
        angle: angleDeg + 90, // perpendicular angle for text alignment
    };
};

// Color assignment based on selected scheme
const getSectorFill = (sector: any) => {
    if (colorScheme.value === 'lineage') {
        if (sector.gen === 0) return 'fill-indigo-600 dark:fill-indigo-700';

        // Paternal (father's side) vs Maternal (mother's side)
        // Check highest bit after root to determine side
        const isPaternal = (sector.index >> (sector.gen - 1)) % 2 === 0;

        if (isPaternal) {
            const opacity = 1 - (sector.gen - 1) * 0.15;
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

// Helper for sector text
const getSectorLabel = (sector: any) => {
    const name = sector.person.name || '';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return name;
    // Surname or first name depending on ring depth
    if (sector.gen >= 4) {
        return parts[parts.length - 1]; // last name only for small outer slots
    }
    return `${parts[0]} ${parts[parts.length - 1]}`;
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
const zoomIn = () => { zoomLevel.value = Math.min(zoomLevel.value + 0.15, 2.0); };
const zoomOut = () => { zoomLevel.value = Math.max(zoomLevel.value - 0.15, 0.5); };
const resetZoom = () => { zoomLevel.value = 1; };
</script>

<template>
    <div
        :class="[
            'transition-all duration-300 flex flex-col',
            isFullscreen
                ? 'fixed inset-0 z-50 rounded-none w-screen h-screen min-h-screen bg-slate-100 dark:bg-slate-950 border-none p-0 shadow-none'
                : 'relative bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-2xl min-h-[550px] sm:min-h-[750px] h-[82vh]'
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
                        <button @click="zoomIn" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer">
                            <ZoomIn class="w-3.5 h-3.5" />
                        </button>
                        <button @click="zoomOut" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer">
                            <ZoomOut class="w-3.5 h-3.5" />
                        </button>
                        <button @click="resetZoom" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer">
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
                </div>
            </div>
        </div>

        <!-- Fan SVG Canvas Container -->
        <div class="flex-1 overflow-auto flex items-center justify-center p-6 relative">
            <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-slate-400">
                <div class="w-10 h-10 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-4 text-xs font-semibold text-slate-600 dark:text-slate-300">Rendering Ancestry Fan Chart...</p>
            </div>

            <div
                v-else-if="rootPerson"
                class="transition-transform duration-200 ease-out origin-center flex flex-col items-center"
                :style="{ transform: `scale(${zoomLevel})` }"
            >
                <svg
                    :width="SVG_SIZE"
                    :height="SVG_SIZE - 200"
                    :viewBox="`0 0 ${SVG_SIZE} ${SVG_SIZE - 200}`"
                    class="overflow-visible select-none"
                >
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
                            <text
                                :x="CENTER_X"
                                :y="CENTER_Y - 8"
                                text-anchor="middle"
                                class="fill-white font-extrabold text-xs tracking-tight pointer-events-none"
                            >
                                {{ rootPerson.name.split(' ')[0] }}
                            </text>
                            <text
                                :x="CENTER_X"
                                :y="CENTER_Y + 10"
                                text-anchor="middle"
                                class="fill-indigo-100 font-bold text-[11px] pointer-events-none"
                            >
                                {{ rootPerson.name.split(' ').slice(1).join(' ') }}
                            </text>
                            <text
                                :x="CENTER_X"
                                :y="CENTER_Y + 24"
                                text-anchor="middle"
                                class="fill-indigo-200 text-[10px] pointer-events-none"
                            >
                                {{ rootPerson.birth_year || '?' }} – {{ rootPerson.death_year || 'Present' }}
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

                                    <!-- Arc Label Text -->
                                    <g
                                        :transform="(() => {
                                            const c = getSectorCenter(
                                                sector.gen,
                                                sector.index - Math.pow(2, sector.gen),
                                                Math.pow(2, sector.gen)
                                            );
                                            return `translate(${c.x}, ${c.y}) rotate(${c.angle})`;
                                        })()"
                                    >
                                        <text
                                            text-anchor="middle"
                                            dominant-baseline="middle"
                                            class="fill-white font-bold text-[10px] pointer-events-none drop-shadow-xs"
                                        >
                                            {{ getSectorLabel(sector) }}
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
                    class="mt-4 bg-slate-900/90 text-white backdrop-blur-md px-4 py-2 rounded-2xl border border-slate-700 shadow-xl flex items-center gap-3 animate-in fade-in duration-150"
                >
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shrink-0">
                        <User class="w-4 h-4" />
                    </div>
                    <div class="text-xs">
                        <div class="font-extrabold text-white">{{ hoveredPerson.name }}</div>
                        <div class="text-[11px] text-indigo-300">
                            {{ hoveredPerson.birth_year || '?' }} – {{ hoveredPerson.death_year || 'Present' }}
                            <template v-if="hoveredPerson.birth_place"> | {{ hoveredPerson.birth_place }}</template>
                        </div>
                    </div>
                    <button
                        @click="emit('change-root', hoveredPerson.id)"
                        class="ml-3 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-bold transition-colors cursor-pointer"
                    >
                        Focus
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
