<script setup lang="ts">
import GedcomHeritageNode from './GedcomHeritageNode.vue';

defineProps<{
    person: any;
    level: number;
    parentIndex?: number;
    spouse?: any;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();
</script>

<template>
    <div class="relative flex flex-col items-center">
        <!-- Render Parents Above Recursively -->
        <div
            v-if="person.parents && person.parents.length > 0"
            class="flex flex-col items-center"
        >
            <!-- Row of Parent Nodes -->
            <div class="relative flex items-start justify-center">
                <!-- Parent 1 (e.g. Father) -->
                <div class="relative flex flex-col items-center px-0.5">
                    <GedcomHeritageAncestorNode
                        :person="person.parents[0]"
                        :level="level + 1"
                        :parent-index="0"
                        :spouse="person.parents[1]"
                        @select-person="emit('select-person', $event)"
                        @change-root="emit('change-root', $event)"
                    />
                    <!-- Horizontal Bracket from Parent 1 center to seam -->
                    <div
                        v-if="person.parents.length > 1"
                        class="pointer-events-none absolute right-0 bottom-0 left-1/2 h-[3px] bg-slate-900 dark:bg-slate-300"
                    ></div>
                </div>

                <!-- Marriage Bar between Parents if both exist (only when parents have no parents above them) -->
                <div
                    v-if="
                        person.parents.length > 1 &&
                        (!person.parents[0].parents ||
                            person.parents[0].parents.length === 0) &&
                        (!person.parents[1].parents ||
                            person.parents[1].parents.length === 0)
                    "
                    class="pointer-events-none absolute top-[250px] left-1/2 z-0 flex h-[3px] w-10 -translate-x-1/2 items-center justify-between bg-slate-900 dark:bg-slate-300"
                >
                    <span
                        class="-ml-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                    ></span>
                    <span
                        class="-mr-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                    ></span>
                </div>

                <!-- Parent 2 (e.g. Mother) -->
                <div
                    v-if="person.parents.length > 1"
                    class="relative flex flex-col items-center px-0.5"
                >
                    <GedcomHeritageAncestorNode
                        :person="person.parents[1]"
                        :level="level + 1"
                        :parent-index="1"
                        :spouse="person.parents[0]"
                        @select-person="emit('select-person', $event)"
                        @change-root="emit('change-root', $event)"
                    />
                    <!-- Horizontal Bracket from seam to Parent 2 center -->
                    <div
                        class="pointer-events-none absolute right-1/2 bottom-0 left-0 h-[3px] bg-slate-900 dark:bg-slate-300"
                    ></div>
                </div>
            </div>

            <!-- Vertical Drop Line from Parents down to This Person -->
            <div
                class="z-0 h-2.5 w-[3px] bg-slate-900 sm:h-3 dark:bg-slate-300"
            ></div>
        </div>

        <!-- This Individual Node Card -->
        <GedcomHeritageNode
            :person="person"
            :spouse="spouse"
            :ancestor-level="level"
            @select-person="emit('select-person', $event)"
            @change-root="emit('change-root', $event)"
        />
    </div>
</template>
