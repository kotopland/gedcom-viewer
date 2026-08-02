<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Sparkles, Mail } from '@lucide/vue';

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your name and email below to register. No password required.',
    },
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <!-- Informational Banner -->
            <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-600 dark:text-indigo-400 text-xs font-semibold flex items-start gap-2.5">
                <Sparkles class="w-4 h-4 shrink-0 mt-0.5 text-indigo-500" />
                <span class="leading-relaxed">
                    Passwordless registration! You will log in using Magic Links sent to your email.
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="name">Full Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full Name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full font-bold cursor-pointer"
                tabindex="3"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                <Mail class="w-4 h-4 mr-2" />
                Create Passwordless Account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground border-t border-slate-200 dark:border-slate-800 pt-4">
            Already registered?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="4"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
