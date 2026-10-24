<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    GitBranch,
    LogOut,
    RefreshCw,
    ShieldAlert,
    UserCheck,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

defineOptions({
    layout: {
        title: 'Account Waiting Room',
        description:
            'Your account is in the waiting room pending superuser approval & lineage assignment.',
    },
});

defineProps<{
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
        <div
            class="flex flex-col items-center space-y-3 rounded-3xl border border-amber-500/20 bg-amber-500/10 p-4 text-center text-amber-600 dark:text-amber-400"
        >
            <div
                class="rounded-2xl bg-amber-500/20 p-3 ring-1 ring-amber-500/30"
            >
                <ShieldAlert class="h-8 w-8" />
            </div>
            <div>
                <h2 class="text-base font-extrabold tracking-tight">
                    Account Waiting Room
                </h2>
                <p
                    class="mt-1 max-w-sm text-xs leading-relaxed text-slate-600 dark:text-slate-300"
                >
                    Welcome to the Topland Family Archive! Before full access to
                    the family tree viewer is granted, an administrator must
                    complete your account setup.
                </p>
            </div>
        </div>

        <!-- Verification Checklist Cards -->
        <div class="space-y-3">
            <div
                class="text-[11px] font-extrabold tracking-wider text-slate-400 uppercase"
            >
                Setup Requirements Status
            </div>

            <!-- Card 1: Superuser Verification -->
            <div
                class="flex items-center justify-between gap-3 rounded-2xl border p-4 transition-all"
                :class="
                    isVerified
                        ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/40'
                        : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60'
                "
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl"
                        :class="
                            isVerified
                                ? 'bg-emerald-600 text-white'
                                : 'bg-amber-500/20 text-amber-600'
                        "
                    >
                        <CheckCircle2 v-if="isVerified" class="h-4 w-4" />
                        <UserCheck v-else class="h-4 w-4" />
                    </div>
                    <div>
                        <div class="text-xs font-extrabold">
                            Administrator Verification
                        </div>
                        <div
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            {{
                                isVerified
                                    ? 'Verified by Administrator'
                                    : 'Awaiting Administrator Approval'
                            }}
                        </div>
                    </div>
                </div>

                <span
                    class="shrink-0 rounded-xl px-2.5 py-1 text-[10px] font-extrabold uppercase"
                    :class="
                        isVerified
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                    "
                >
                    {{ isVerified ? 'Verified' : 'Pending' }}
                </span>
            </div>

            <!-- Card 2: Lineage Assignment (start_person_id) -->
            <div
                class="flex items-center justify-between gap-3 rounded-2xl border p-4 transition-all"
                :class="
                    hasStartPerson
                        ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/40'
                        : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60'
                "
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl"
                        :class="
                            hasStartPerson
                                ? 'bg-emerald-600 text-white'
                                : 'bg-amber-500/20 text-amber-600'
                        "
                    >
                        <CheckCircle2 v-if="hasStartPerson" class="h-4 w-4" />
                        <GitBranch v-else class="h-4 w-4" />
                    </div>
                    <div>
                        <div class="text-xs font-extrabold">
                            Lineage Person Assignment
                        </div>
                        <div
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            {{
                                hasStartPerson
                                    ? 'Lineage Root Person Assigned'
                                    : 'Awaiting Start Person Assignment (start_person_id)'
                            }}
                        </div>
                    </div>
                </div>

                <span
                    class="shrink-0 rounded-xl px-2.5 py-1 text-[10px] font-extrabold uppercase"
                    :class="
                        hasStartPerson
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                    "
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
                class="w-full cursor-pointer font-bold"
            >
                <RefreshCw class="mr-2 h-4 w-4" />
                Refresh Status
            </Button>

            <Button
                as-child
                variant="outline"
                class="w-full cursor-pointer font-bold"
            >
                <Link :href="logout()" method="post" as="button">
                    <LogOut class="mr-2 h-4 w-4" />
                    Log out
                </Link>
            </Button>
        </div>
    </div>
</template>
