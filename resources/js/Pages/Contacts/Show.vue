<script setup>
import { ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Avatar from "../../Components/UI/Avatar.vue";
import Badge from "../../Components/UI/Badge.vue";
import EmptyState from "../../Components/UI/EmptyState.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import PhoneInput from "../../Components/UI/PhoneInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import {
    formatDateTime,
    formatPhone,
    formatRelative,
} from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    contact: { type: Object, required: true },
    conversations: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
});

const { can } = usePermissions();
const editing = ref(false);

const form = useForm({
    name: props.contact.legal_name ?? props.contact.name,
    nickname: props.contact.nickname ?? "",
    phone: props.contact.phone ?? "",
    email: props.contact.email ?? "",
    document: props.contact.document ?? "",
    notes: props.contact.notes ?? "",
    is_blocked: props.contact.is_blocked,
    tags: props.contact.tags?.map((tag) => tag.id) ?? [],
});

function toggleTag(id) {
    form.tags = form.tags.includes(id)
        ? form.tags.filter((item) => item !== id)
        : [...form.tags, id];
}

function submit() {
    form.put(`/contatos/${props.contact.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}

function destroy() {
    router.delete(`/contatos/${props.contact.id}`);
}
</script>

<template>
    <Head :title="contact.name" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            :title="contact.name"
            subtitle="Ficha completa do contato e histórico de atendimentos."
        >
            <template #actions>
                <Button variant="secondary" :icon="ArrowLeft" href="/contatos"
                    >Voltar</Button
                >
                <Button
                    v-if="can('contacts.update')"
                    :icon="Pencil"
                    @click="editing = !editing"
                >
                    {{ editing ? "Cancelar Edição" : "Editar" }}
                </Button>
                <Button
                    v-if="can('contacts.delete')"
                    variant="danger"
                    :icon="Trash2"
                    @click="destroy"
                    >Excluir</Button
                >
            </template>
        </PageHeader>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-1">
                <Card>
                    <div class="flex flex-col items-center gap-2 text-center">
                        <Avatar
                            :name="contact.name"
                            :src="contact.avatar_url"
                            :size="72"
                        />
                        <p class="text-base font-semibold text-content">
                            {{ contact.name }}
                        </p>
                        <p
                            v-if="contact.nickname"
                            class="text-xs text-content-subtle"
                        >
                            WhatsApp: {{ contact.legal_name }}
                        </p>
                        <div class="flex flex-wrap justify-center gap-1">
                            <Badge
                                v-for="tag in contact.tags"
                                :key="tag.id"
                                :color="tag.color"
                                size="sm"
                            >
                                {{ tag.name }}
                            </Badge>
                            <Badge
                                v-if="contact.is_blocked"
                                color="danger"
                                size="sm"
                                >Bloqueado</Badge
                            >
                        </div>
                    </div>

                    <dl v-if="!editing" class="mt-5 space-y-3 text-sm">
                        <div class="flex items-center gap-2">
                            <Phone :size="15" class="text-content-subtle" />
                            <dd class="text-content">
                                {{ formatPhone(contact.phone) || "—" }}
                            </dd>
                        </div>
                        <div class="flex items-center gap-2">
                            <Mail :size="15" class="text-content-subtle" />
                            <dd class="text-content">
                                {{ contact.email ?? "—" }}
                            </dd>
                        </div>
                        <div class="flex items-center gap-2">
                            <ShieldCheck
                                :size="15"
                                class="text-content-subtle"
                            />
                            <dd class="text-content">
                                {{ contact.document ?? "—" }}
                            </dd>
                        </div>
                        <div class="flex items-center gap-2">
                            <Clock :size="15" class="text-content-subtle" />
                            <dd class="text-content">
                                Última interação
                                {{
                                    formatRelative(contact.last_interaction_at)
                                }}
                            </dd>
                        </div>
                        <p
                            v-if="contact.notes"
                            class="whitespace-pre-wrap rounded-control bg-surface-muted p-2.5 text-xs text-content-muted"
                        >
                            {{ contact.notes }}
                        </p>
                    </dl>

                    <form
                        v-else
                        class="mt-5 space-y-4"
                        @submit.prevent="submit"
                    >
                        <FormField
                            label="Nome"
                            :error="form.errors.name"
                            required
                        >
                            <TextInput
                                v-model="form.name"
                                :invalid="Boolean(form.errors.name)"
                            />
                        </FormField>

                        <FormField
                            label="Apelido"
                            :error="form.errors.nickname"
                            hint="Substitui o nome vindo do WhatsApp."
                        >
                            <TextInput v-model="form.nickname" />
                        </FormField>

                        <FormField label="Telefone" :error="form.errors.phone">
                            <PhoneInput
                                v-model="form.phone"
                                with-country
                                placeholder="+55 (11) 98888-7777"
                                :invalid="Boolean(form.errors.phone)"
                            />
                        </FormField>

                        <FormField label="Email" :error="form.errors.email">
                            <TextInput v-model="form.email" type="email" />
                        </FormField>

                        <FormField
                            label="Documento"
                            :error="form.errors.document"
                        >
                            <TextInput v-model="form.document" />
                        </FormField>

                        <FormField
                            label="Observações"
                            :error="form.errors.notes"
                        >
                            <TextArea v-model="form.notes" rows="3" />
                        </FormField>

                        <FormField group label="Tags">
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="tag in tags"
                                    :key="tag.id"
                                    type="button"
                                    class="rounded-full border px-2 py-0.5 text-xs transition"
                                    :class="
                                        form.tags.includes(tag.id)
                                            ? 'border-primary bg-primary-soft text-primary'
                                            : 'border-border text-content-muted hover:bg-surface-hover'
                                    "
                                    @click="toggleTag(tag.id)"
                                >
                                    {{ tag.name }}
                                </button>
                            </div>
                        </FormField>

                        <Toggle
                            v-model="form.is_blocked"
                            label="Bloquear contato"
                        />

                        <Button
                            type="submit"
                            :loading="form.processing"
                            class="w-full justify-center"
                            >Salvar</Button
                        >
                    </form>
                </Card>

                <Card
                    title="Canais"
                    description="Onde este contato pode ser alcançado."
                >
                    <div v-if="contact.channels?.length" class="space-y-2">
                        <div
                            v-for="channel in contact.channels"
                            :key="channel.id"
                            class="flex items-center justify-between gap-2 rounded-control bg-surface-muted px-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm text-content">
                                    {{ formatPhone(channel.identifier) }}
                                </p>
                                <p class="truncate text-xs text-content-subtle">
                                    {{ channel.connection?.name }}
                                </p>
                            </div>
                            <Badge size="sm">{{ channel.channel_label }}</Badge>
                        </div>
                    </div>

                    <EmptyState
                        v-else
                        :icon="Plug"
                        title="Sem Canais"
                        description="Nenhum identificador vinculado ainda."
                    />
                </Card>
            </div>

            <Card
                title="Histórico de Atendimentos"
                :padded="false"
                class="lg:col-span-2"
            >
                <div v-if="conversations.length" class="divide-y divide-border">
                    <Link
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        :href="`/atendimentos/${conversation.id}`"
                        class="flex items-center gap-3 px-4 py-3 transition hover:bg-surface-muted"
                    >
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <Badge
                                    :color="conversation.status_color"
                                    size="sm"
                                    >{{ conversation.status_label }}</Badge
                                >
                                <Badge
                                    :color="conversation.channel_color"
                                    size="sm"
                                    >{{ conversation.channel_label }}</Badge
                                >
                                <Badge
                                    v-if="conversation.service_queue"
                                    color="muted"
                                    size="sm"
                                >
                                    {{ conversation.service_queue.name }}
                                </Badge>
                            </div>
                            <p class="truncate text-xs text-content-muted">
                                {{
                                    conversation.last_message?.body ??
                                    "Sem mensagens."
                                }}
                            </p>
                        </div>

                        <div
                            class="shrink-0 text-right text-xs text-content-subtle"
                        >
                            <p>
                                {{
                                    conversation.assigned_user?.name ??
                                    "Sem Responsável"
                                }}
                            </p>
                            <p>
                                {{
                                    formatDateTime(
                                        conversation.last_message_at ??
                                            conversation.created_at,
                                    )
                                }}
                            </p>
                        </div>
                    </Link>
                </div>

                <EmptyState
                    v-else
                    :icon="MessagesSquare"
                    title="Nenhum Atendimento"
                    description="Este contato ainda não iniciou conversas."
                />
            </Card>
        </div>
    </div>
</template>
