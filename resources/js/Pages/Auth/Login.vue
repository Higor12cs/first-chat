<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Button from "../../Components/UI/Button.vue";

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

function submit() {
    form.post("/entrar", { onFinish: () => form.reset("password") });
}
</script>

<template>
    <Head title="Entrar" />

    <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-1">
            <h2 class="text-base font-semibold text-content">
                Acessar a Plataforma
            </h2>
            <p class="text-sm text-content-muted">
                Use suas credenciais de acesso.
            </p>
        </div>

        <FormField label="Email" :error="form.errors.email" required>
            <TextInput
                v-model="form.email"
                type="email"
                placeholder="Voce@empresa.com"
                :invalid="Boolean(form.errors.email)"
            />
        </FormField>

        <FormField label="Senha" :error="form.errors.password" required>
            <TextInput
                v-model="form.password"
                type="password"
                placeholder="••••••••"
                :invalid="Boolean(form.errors.password)"
            />
        </FormField>

        <Toggle v-model="form.remember" label="Manter conectado" />

        <Button
            type="submit"
            class="w-full justify-center"
            :loading="form.processing"
            >Entrar</Button
        >
    </form>
</template>
