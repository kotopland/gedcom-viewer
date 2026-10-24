<script setup lang="ts">
import GedcomHeritageNode from './GedcomHeritageNode.vue';

defineProps<{
    person: any;
    level: number;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();
</script>

<template>
    <div class="relative flex flex-col items-center">
        <!-- Person & Spouse(s) Row -->
        <div class="relative flex items-start gap-2 sm:gap-2.5">
            <!-- Main Person Node -->
            <GedcomHeritageNode
                :person="person"
                :spouse="person.spouses?.[0]"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />

            <!-- Marriage Horizontal Bar with Anchor Pins -->
            <div
                v-if="person.spouses && person.spouses.length > 0"
                class="pointer-events-none absolute top-[250px] left-1/2 z-0 flex h-[3px] w-10 -translate-x-1/2 items-center justify-between bg-slate-900 dark:bg-slate-300"
            >
                <span
                    class="-ml-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                ></span>
                <span
                    class="-mr-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                ></span>
            </div>

            <!-- Spouse Node -->
            <GedcomHeritageNode
                v-if="person.spouses && person.spouses.length > 0"
                :person="person.spouses[0]"
                :spouse="person"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />
        </div>

        <!-- Children Section Below (Recursive) -->
        <div
            v-if="person.children && person.children.length > 0"
            class="relative mt-[-6px] flex flex-col items-center"
        >
            <!-- Vertical Drop Line from Couple down to Children Bracket -->
            <div
                class="z-0 h-4 w-[3px] bg-slate-900 sm:h-5 dark:bg-slate-300"
            ></div>

            <!-- Row of Children -->
            <div class="flex items-start justify-center">
                <div
                    v-for="(child, cIdx) in person.children"
                    :key="child.id"
                    class="relative flex flex-col items-center px-1 pt-4 sm:px-1.5 sm:pt-5"
                >
                    <!-- Horizontal Distribution Bracket across Children -->
                    <div
                        v-if="person.children.length > 1"
                        class="pointer-events-none absolute top-0 h-[3px] bg-slate-900 dark:bg-slate-300"
                        :class="[
                            cIdx === 0
                                ? child.spouses && child.spouses.length > 0
                                    ? 'right-0 left-[calc(50%-134px)] sm:left-[calc(50%-135px)]'
                                    : 'right-0 left-1/2'
                                : '',
                            cIdx === person.children.length - 1
                                ? child.spouses && child.spouses.length > 0
                                    ? 'right-[calc(50%-134px)] left-0 sm:right-[calc(50%-135px)]'
                                    : 'right-1/2 left-0'
                                : '',
                            cIdx > 0 && cIdx < person.children.length - 1
                                ? 'right-0 left-0'
                                : '',
                        ]"
                    ></div>

                    <!-- Horizontal Bar above Single Child with Spouse/Partner -->
                    <div
                        v-if="
                            person.children.length === 1 &&
                            child.spouses &&
                            child.spouses.length > 0
                        "
                        class="pointer-events-none absolute top-0 right-[calc(50%-134px)] left-[calc(50%-134px)] h-[3px] bg-slate-900 sm:right-[calc(50%-135px)] sm:left-[calc(50%-135px)] dark:bg-slate-300"
                    ></div>

                    <!-- Recursive Child Descendant Node -->
                    <GedcomHeritageDescendantNode
                        :person="child"
                        :level="level + 1"
                        @select-person="emit('select-person', $event)"
                        @change-root="emit('change-root', $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
