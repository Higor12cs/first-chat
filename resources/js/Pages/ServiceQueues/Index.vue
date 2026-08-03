<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import Avatar from "../../Components/UI/Avatar.vue";
import { ListChecks, Pencil, Plus, Trash2 } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    queues: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    objectives: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const weekdays = [
    { value: 0, label: "Domingo" },
    { value: 1, label: "Segunda" },
    { value: 2, label: "Terça" },
    { value: 3, label: "Quarta" },
    { value: 4, label: "Quinta" },
    { value: 5, label: "Sexta" },
    { value: 6, label: "Sábado" },
];

const colors = ["primary", "success", "warning", "danger", "info", "muted"];

const strategies = [
    { value: "manual", label: "Manual" },
    { value: "round_robin", label: "Rodízio" },
    { value: "least_busy", label: "Menos Ocupado" },
];

const objectiveOptions = computed(() =>
    props.objectives.map((item) => ({ value: item.id, label: item.name })),
);

const form = useForm({
    name: "",
    description: "",
    color: "primary",
    icon: "queue",
    priority: 0,
    assignment_strategy: "manual",
    business_hours: {},
    outside_hours_message: "",
    ai_objective_id: null,
    is_default: false,
    is_active: true,
    users: [],
});

function openForm(queue = null) {
    editing.value = queue;

    form.defaults({
        name: queue?.name ?? "",
        description: queue?.description ?? "",
        color: queue?.color ?? "primary",
        icon: queue?.icon ?? "queue",
        priority: queue?.priority ?? 0,
        assignment_strategy: queue?.assignment_strategy ?? "manual",
        business_hours: queue?.business_hours ?? {},
        outside_hours_message: queue?.outside_hours_message ?? "",
        ai_objective_id: queue?.ai_objective_id ?? null,
        is_default: queue?.is_default ?? false,
        is_active: queue?.is_active ?? true,
        users: queue?.users?.map((user) => user.id) ?? [],
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function toggleAgent(id) {
    form.users = form.users.includes(id)
        ? form.users.filter((item) => item !== id)
        : [...form.users, id];
}

function toggleWeekday(day) {
    const hours = { ...form.business_hours };

    if (hours[day]) {
        delete hours[day];
    } else {
        hours[day] = { start: "08:00", end: "18:00" };
    }

    form.business_hours = hours;
}

function setHour(day, field, value) {
    form.business_hours = {
        ...form.business_hours,
        [day]: { ...form.business_hours[day], [field]: value },
    };
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    };

    if (editing.value) {
        form.put(`/filas/${editing.value.id}`, options);
    } else {
        form.post("/filas", options);
    }
}

function destroy() {
    router.delete(`/filas/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Setores de Atendimento" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Setores de Atendimento"
            subtitle="Organize a distribuição das conversas entre times e horários."
        >
            <template #actions>
                <Button
                    v-if="can('queues.create')"
                    :icon="Plus"
                    @click="openForm()"
                    >Novo Setor</Button
                >
            </template>
        </PageHeader>

        <div
            v-if="queues.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Card v-for="queue in queues" :key="queue.id">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <span
                            class="rounded-control bg-primary-soft p-2 text-primary"
                        >
                            <ListChecks :size="18" />
                        </span>

                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-content">
                                {{ queue.name }}
                            </p>
                            <p class="text-xs text-content-muted">
                                {{ queue.description ?? "Sem descrição." }}
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-1">
                        <button
                            v-if="can('queues.update')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(queue)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            v-if="can('queues.delete') && !queue.is_default"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            @click="deleting = queue"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1">
                    <Badge
                        :color="queue.is_open ? 'success' : 'muted'"
                        size="sm"
                    >
                        {{ queue.is_open ? "Aberta Agora" : "Fora do Horário" }}
                    </Badge>
                    <Badge v-if="queue.is_default" color="info" size="sm"
                        >Padrão</Badge
                    >
                    <Badge v-if="!queue.is_active" color="danger" size="sm"
                        >Inativa</Badge
                    >
                    <Badge color="muted" size="sm"
                        >{{
                            queue.conversations_count ?? 0
                        }}
                        Atendimentos</Badge
                    >
                    <Badge color="primary" size="sm"
                        >Prioridade {{ queue.priority }}</Badge
                    >
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <span class="text-xs text-content-subtle">Equipe</span>
                    <div class="flex -space-x-1.5">
                        <Avatar
                            v-for="user in queue.users"
                            :key="user.id"
                            :name="user.name"
                            :src="user.avatar_url"
                            :size="24"
                            class="ring-2 ring-surface"
                        />
                    </div>
                    <span
                        v-if="!queue.users?.length"
                        class="text-xs text-content-subtle"
                        >Sem atendentes.</span
                    >
                </div>
            </Card>
        </div>

        <Card v-else>
            <EmptyState
                :icon="ListChecks"
                title="Nenhum Setor"
                description="Crie setores para separar comercial, suporte e financeiro."
            />
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Setor' : 'Novo Setor'"
        description="Distribuição, horários e automação do setor."
        size="lg"
        @close="showForm = false"
    >
        <form id="queue-form" class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Nome" :error="form.errors.name" required>
                    <TextInput
                        v-model="form.name"
                        :invalid="Boolean(form.errors.name)"
                    />
                </FormField>

                <FormField
                    label="Prioridade"
                    :error="form.errors.priority"
                    hint="Setores com prioridade maior aparecem primeiro."
                >
                    <TextInput v-model="form.priority" type="number" />
                </FormField>
            </div>

            <FormField label="Descrição" :error="form.errors.description">
                <TextArea v-model="form.description" rows="2" />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Distribuição"
                    :error="form.errors.assignment_strategy"
                >
                    <SelectInput
                        v-model="form.assignment_strategy"
                        :options="strategies"
                    />
                </FormField>

                <FormField
                    label="Objetivo de IA"
                    :error="form.errors.ai_objective_id"
                    hint="A IA assume a conversa ao entrar no setor."
                >
                    <SelectInput
                        v-model="form.ai_objective_id"
                        :options="objectiveOptions"
                        placeholder="Sem IA"
                    />
                </FormField>
            </div>

            <FormField group label="Cor">
                <div class="flex gap-1.5">
                    <button
                        v-for="color in colors"
                        :key="color"
                        type="button"
                        class="rounded-full px-2.5 py-1 text-xs capitalize transition"
                        :class="[
                            form.color === color ? 'ring-2 ring-primary' : '',
                            {
                                'bg-primary-soft text-primary':
                                    color === 'primary',
                                'bg-success-soft text-success':
                                    color === 'success',
                                'bg-warning-soft text-warning':
                                    color === 'warning',
                                'bg-danger-soft text-danger':
                                    color === 'danger',
                                'bg-info-soft text-info': color === 'info',
                                'bg-surface-muted text-content-muted':
                                    color === 'muted',
                            },
                        ]"
                        @click="form.color = color"
                    >
                        {{ color }}
                    </button>
                </div>
            </FormField>

            <FormField group label="Atendentes">
                <div class="grid gap-1.5 sm:grid-cols-2">
                    <button
                        v-for="agent in agents"
                        :key="agent.id"
                        type="button"
                        class="flex items-center gap-2 rounded-control border px-2.5 py-1.5 text-left transition"
                        :class="
                            form.users.includes(agent.id)
                                ? 'border-primary bg-primary-soft'
                                : 'border-border hover:bg-surface-hover'
                        "
                        @click="toggleAgent(agent.id)"
                    >
                        <Avatar
                            :name="agent.name"
                            :src="agent.avatar_url"
                            :size="24"
                        />
                        <span class="truncate text-xs text-content">{{
                            agent.name
                        }}</span>
                    </button>
                </div>
            </FormField>

            <FormField
                group
                label="Horário de atendimento"
                hint="Sem dias marcados o setor atende em qualquer horário."
            >
                <div class="space-y-1.5">
                    <div
                        v-for="day in weekdays"
                        :key="day.value"
                        class="flex items-center gap-2"
                    >
                        <button
                            type="button"
                            class="w-24 rounded-control border px-2 py-1 text-left text-xs transition"
                            :class="
                                form.business_hours[day.value]
                                    ? 'border-primary bg-primary-soft text-primary'
                                    : 'border-border text-content-muted hover:bg-surface-hover'
                            "
                            @click="toggleWeekday(day.value)"
                        >
                            {{ day.label }}
                        </button>

                        <template v-if="form.business_hours[day.value]">
                            <input
                                type="time"
                                :value="form.business_hours[day.value].start"
                                class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                @input="
                                    setHour(
                                        day.value,
                                        'start',
                                        $event.target.value,
                                    )
                                "
                            />
                            <span class="text-xs text-content-subtle">até</span>
                            <input
                                type="time"
                                :value="form.business_hours[day.value].end"
                                class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                @input="
                                    setHour(
                                        day.value,
                                        'end',
                                        $event.target.value,
                                    )
                                "
                            />
                        </template>
                    </div>
                </div>
            </FormField>

            <FormField
                label="Mensagem fora do horário"
                :error="form.errors.outside_hours_message"
            >
                <TextArea v-model="form.outside_hours_message" rows="2" />
            </FormField>

            <div class="space-y-3">
                <Toggle
                    v-model="form.is_default"
                    label="Setor padrão"
                    description="Recebe as conversas sem destino definido e é usado quando alguém assume um atendimento sem setor. Só um setor pode ser o padrão."
                />
                <Toggle v-model="form.is_active" label="Setor ativo" />
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button type="submit" form="queue-form" :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Setor"
        :message="`O setor ${deleting?.name} será removido e os atendimentos ficarão sem setor.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
