<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { logout } from '@/routes';
import { ShieldAlert, CheckCircle2, Clock, RefreshCw, UserCheck, GitBranch, LogOut } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        title: 'Account Waiting Room',
        description: 'Your account is in the waiting room pending superuser approval & lineage assignment.',
    },
});

const props = defineProps<{
    isVerified: boolean;
    hasStartPerson: boolean;
    startPersonId?: string | null;
}>();

const checkStatus = () => {
    router.reload();
};
</script>

<template>
    <Head title="Waiting Room" />

    <div class="space-y-6 text-slate-900 dark:text-slate-100">
        <!-- Top Status Banner -->
        <div class="flex flex-col items-center text-center space-y-3 p-4 rounded-3xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400">
            <div class="rounded-2xl bg-amber-500/20 p-3 ring-1 ring-amber-500/30">
                <ShieldAlert class="h-8 w-8" />
            </div>
            <div>
                <h2 class="text-base font-extrabold tracking-tight">
                    Account Waiting Room
                </h2>
                <p class="text-xs text-slate-600 dark:text-slate-300 max-w-sm mt-1 leading-relaxed">
                    Welcome to the Topland Family Archive! Before full access to the family tree viewer is granted, an administrator must complete your account setup.
                </p>
            </div>
        </div>

        <!-- Verification Checklist Cards -->
        <div class="space-y-3">
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                Setup Requirements Status
            </div>

            <!-- Card 1: Superuser Verification -->
            <div
                class="p-4 rounded-2xl border transition-all flex items-center justify-between gap-3"
                :class="isVerified ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800' : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700'"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                        :class="isVerified ? 'bg-emerald-600 text-white' : 'bg-amber-500/20 text-amber-600'"
                    >
                        <CheckCircle2 v-if="isVerified" class="w-4 h-4" />
                        <UserCheck v-else class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="font-extrabold text-xs">
                            Administrator Verification
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ isVerified ? 'Verified by Administrator' : 'Awaiting Administrator Approval' }}
                        </div>
                    </div>
                </div>

                <span
                    class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold uppercase shrink-0"
                    :class="isVerified ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                >
                    {{ isVerified ? 'Verified' : 'Pending' }}
                </span>
            </div>

            <!-- Card 2: Lineage Assignment (start_person_id) -->
            <div
                class="p-4 rounded-2xl border transition-all flex items-center justify-between gap-3"
                :class="hasStartPerson ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800' : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700'"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                        :class="hasStartPerson ? 'bg-emerald-600 text-white' : 'bg-amber-500/20 text-amber-600'"
                    >
                        <CheckCircle2 v-if="hasStartPerson" class="w-4 h-4" />
                        <GitBranch v-else class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="font-extrabold text-xs">
                            Lineage Person Assignment
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ hasStartPerson ? 'Lineage Root Person Assigned' : 'Awaiting Start Person Assignment (start_person_id)' }}
                        </div>
                    </div>
                </div>

                <span
                    class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold uppercase shrink-0"
                    :class="hasStartPerson ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                >
                    {{ hasStartPerson ? 'Assigned' : 'Pending' }}
                </span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-2 gap-3 pt-2">
            <Button
                @click="checkStatus"
                variant="default"
                class="w-full font-bold cursor-pointer"
            >
                <RefreshCw class="w-4 h-4 mr-2" />
                Refresh Status
            </Button>

            <Button
                as-child
                variant="outline"
                class="w-full font-bold cursor-pointer"
            >
                <Link :href="logout()" method="post" as="button">
                    <LogOut class="w-4 h-4 mr-2" />
                    Log out
                </Link>
            </Button>
        </div>
    </div>
</template>
