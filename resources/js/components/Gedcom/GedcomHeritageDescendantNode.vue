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
    <div class="flex flex-col items-center relative">
        <!-- Person & Spouse(s) Row -->
        <div class="flex items-start gap-2.5 sm:gap-3.5 relative">
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
                class="absolute top-[250px] left-[254px] right-[254px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
            >
                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
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
        <div v-if="person.children && person.children.length > 0" class="flex flex-col items-center relative mt-[-6px]">
            <!-- Vertical Drop Line from Couple down to Children Bracket -->
            <div class="w-[3px] h-6 bg-slate-900 dark:bg-slate-300 z-0"></div>

            <!-- Row of Children -->
            <div class="flex items-start justify-center">
                <div
                    v-for="(child, cIdx) in person.children"
                    :key="child.id"
                    class="flex flex-col items-center relative px-2 sm:px-3 pt-6"
                >
                    <!-- Horizontal Distribution Bracket across Children -->
                    <div
                        v-if="person.children.length > 1"
                        class="absolute top-0 h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none"
                        :class="[
                            cIdx === 0
                                ? (child.spouses && child.spouses.length > 0
                                    ? 'left-[calc(50%-137px)] sm:left-[calc(50%-139px)] right-0'
                                    : 'left-1/2 right-0')
                                : '',
                            cIdx === person.children.length - 1
                                ? (child.spouses && child.spouses.length > 0
                                    ? 'left-0 right-[calc(50%-137px)] sm:right-[calc(50%-139px)]'
                                    : 'left-0 right-1/2')
                                : '',
                            cIdx > 0 && cIdx < person.children.length - 1 ? 'left-0 right-0' : ''
                        ]"
                    ></div>

                    <!-- Horizontal Bar above Single Child with Spouse/Partner -->
                    <div
                        v-if="person.children.length === 1 && child.spouses && child.spouses.length > 0"
                        class="absolute top-0 left-[calc(50%-137px)] right-[calc(50%-137px)] sm:left-[calc(50%-139px)] sm:right-[calc(50%-139px)] h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none"
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
