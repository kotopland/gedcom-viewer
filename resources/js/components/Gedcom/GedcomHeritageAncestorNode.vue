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
    <div class="flex flex-col items-center relative">
        <!-- Render Parents Above Recursively -->
        <div v-if="person.parents && person.parents.length > 0" class="flex flex-col items-center">
            <!-- Row of Parent Nodes -->
            <div class="flex items-start justify-center relative">
                <!-- Parent 1 (e.g. Father) -->
                <div class="flex flex-col items-center relative px-1 sm:px-1.5">
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
                        class="absolute bottom-0 h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none left-1/2 right-0"
                    ></div>
                </div>

                <!-- Marriage Bar between Parents if both exist (only when parents have no parents above them) -->
                <div
                    v-if="person.parents.length > 1 && (!person.parents[0].parents || person.parents[0].parents.length === 0) && (!person.parents[1].parents || person.parents[1].parents.length === 0)"
                    class="absolute top-[250px] left-[254px] right-[254px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                >
                    <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                    <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                </div>

                <!-- Parent 2 (e.g. Mother) -->
                <div
                    v-if="person.parents.length > 1"
                    class="flex flex-col items-center relative px-1 sm:px-1.5"
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
                        class="absolute bottom-0 h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none left-0 right-1/2"
                    ></div>
                </div>
            </div>

            <!-- Vertical Drop Line from Parents down to This Person -->
            <div class="w-[3px] h-4 sm:h-5 bg-slate-900 dark:bg-slate-300 z-0"></div>
        </div>

        <!-- This Individual Node Card -->
        <GedcomHeritageNode
            :person="person"
            :spouse="spouse"
            @select-person="emit('select-person', $event)"
            @change-root="emit('change-root', $event)"
        />
    </div>
</template>
