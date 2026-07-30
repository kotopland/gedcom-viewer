<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { logout } from '@/routes';
import { ShieldAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        title: 'Account Pending Approval',
        description: 'Your account has been created and is awaiting superuser verification.',
    },
});
</script>

<template>
    <Head title="Awaiting Verification" />

    <div class="flex flex-col items-center text-center space-y-4 py-2">
        <div class="rounded-full bg-amber-500/10 p-3 text-amber-500 ring-1 ring-amber-500/20">
            <ShieldAlert class="h-8 w-8" />
        </div>

        <p class="text-sm text-muted-foreground max-w-sm">
            Thank you for registering! A superuser must verify your account before access to the web application is granted.
        </p>

        <div class="pt-4 w-full">
            <Button as-child variant="outline" class="w-full">
                <Link :href="logout()" method="post" as="button">
                    Log out
                </Link>
            </Button>
        </div>
    </div>
</template>
