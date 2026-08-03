<script setup>
import { computed } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import PhoneInput from "../../Components/UI/PhoneInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import { Plus, Trash2, X } from "lucide-vue-next";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    tenant: { type: Object, required: true },
    timezones: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
});

const page = usePage();
const { can } = usePermissions();

const user = computed(() => page.props.auth.user);

const timezoneOptions = computed(() =>
    props.timezones.map((zone) => ({ value: zone, label: zone })),
);

const cardOptions = computed(() =>
    props.cards.map((card) => ({ value: card.id, label: card.name })),
);

const exceptionTypes = [
    { value: "holiday", label: "Feriado" },
    { value: "exception", label: "Exceção" },
];

const weekdays = [
    { value: 0, label: "Domingo" },
    { value: 1, label: "Segunda" },
    { value: 2, label: "Terça" },
    { value: 3, label: "Quarta" },
    { value: 4, label: "Quinta" },
    { value: 5, label: "Sexta" },
    { value: 6, label: "Sábado" },
];

function normaliseHours(hours) {
    return Object.fromEntries(
        Object.entries(hours ?? {}).map(([day, intervals]) => [
            day,
            Array.isArray(intervals) ? intervals : [intervals],
        ]),
    );
}

const tenantForm = useForm({
    name: props.tenant.name,
    document: props.tenant.document ?? "",
    timezone: props.tenant.timezone,
    settings: {
        sign_messages: props.tenant.settings?.sign_messages ?? false,
        auto_close_hours: props.tenant.settings?.auto_close_hours ?? null,
        greeting: props.tenant.settings?.greeting ?? "",
        business_hours: normaliseHours(props.tenant.settings?.business_hours),
        business_exceptions: props.tenant.settings?.business_exceptions ?? [],
        after_hours_enabled:
            props.tenant.settings?.after_hours_enabled ?? false,
        after_hours_card_id: props.tenant.settings?.after_hours_card_id ?? null,
    },
});

const hasBusinessHours = computed(
    () => Object.keys(tenantForm.settings.business_hours ?? {}).length > 0,
);

function errorStartingWith(prefix) {
    return (
        Object.entries(tenantForm.errors).find(([key]) =>
            key.startsWith(prefix),
        )?.[1] ?? null
    );
}

const businessHoursError = computed(() =>
    errorStartingWith("settings.business_hours"),
);
const exceptionsError = computed(() =>
    errorStartingWith("settings.business_exceptions"),
);

function intervalsOf(day) {
    return tenantForm.settings.business_hours[day] ?? [];
}

function writeIntervals(day, intervals) {
    const hours = { ...tenantForm.settings.business_hours };

    if (intervals.length === 0) {
        delete hours[day];
    } else {
        hours[day] = intervals;
    }

    tenantForm.settings.business_hours = hours;

    if (Object.keys(hours).length === 0) {
        tenantForm.settings.after_hours_enabled = false;
    }
}

function toggleWeekday(day) {
    writeIntervals(
        day,
        intervalsOf(day).length > 0 ? [] : [{ start: "08:00", end: "18:00" }],
    );
}

function addInterval(day) {
    writeIntervals(day, [
        ...intervalsOf(day),
        { start: "13:00", end: "18:00" },
    ]);
}

function removeInterval(day, index) {
    writeIntervals(
        day,
        intervalsOf(day).filter((_, current) => current !== index),
    );
}

function setHour(day, index, field, value) {
    writeIntervals(
        day,
        intervalsOf(day).map((interval, current) =>
            current === index ? { ...interval, [field]: value } : interval,
        ),
    );
}

function addException() {
    const today = new Date().toISOString().slice(0, 10);

    tenantForm.settings.business_exceptions = [
        ...tenantForm.settings.business_exceptions,
        {
            type: "holiday",
            name: "",
            starts_on: today,
            ends_on: today,
            start: null,
            end: null,
            card_id: null,
        },
    ];
}

function removeException(index) {
    tenantForm.settings.business_exceptions =
        tenantForm.settings.business_exceptions.filter(
            (_, current) => current !== index,
        );
}

function setException(index, field, value) {
    tenantForm.settings.business_exceptions =
        tenantForm.settings.business_exceptions.map((exception, current) => {
            if (current !== index) {
                return exception;
            }

            const next = { ...exception, [field]: value };

            if (field === "type" && value === "holiday") {
                return { ...next, start: null, end: null };
            }

            if (field === "type" && value === "exception") {
                return {
                    ...next,
                    start: next.start ?? "12:00",
                    end: next.end ?? "13:00",
                };
            }

            if (field === "starts_on" && next.ends_on < value) {
                return { ...next, ends_on: value };
            }

            return next;
        });
}

const profileForm = useForm({
    name: user.value.name,
    email: user.value.email,
    phone: user.value.phone ?? "",
    auto_lock_minutes: user.value.auto_lock_minutes,
    current_password: "",
    password: "",
    password_confirmation: "",
});

function saveTenant() {
    tenantForm.put("/configuracoes", { preserveScroll: true });
}

function saveProfile() {
    profileForm.put("/perfil", {
        preserveScroll: true,
        onSuccess: () =>
            profileForm.reset(
                "current_password",
                "password",
                "password_confirmation",
            ),
    });
}
</script>

<template>
    <Head title="Configurações" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Configurações"
            subtitle="Dados da empresa, preferências de atendimento e seu perfil."
        />

        <div class="grid gap-5 lg:grid-cols-2">
            <Card
                title="Minha Conta"
                description="Seus dados de acesso à plataforma."
            >
                <form class="space-y-4" @submit.prevent="saveProfile">
                    <FormField
                        label="Nome"
                        :error="profileForm.errors.name"
                        required
                    >
                        <TextInput
                            v-model="profileForm.name"
                            :invalid="Boolean(profileForm.errors.name)"
                        />
                    </FormField>

                    <FormField
                        label="Email"
                        :error="profileForm.errors.email"
                        required
                    >
                        <TextInput
                            v-model="profileForm.email"
                            type="email"
                            :invalid="Boolean(profileForm.errors.email)"
                        />
                    </FormField>

                    <FormField
                        label="Telefone"
                        :error="profileForm.errors.phone"
                    >
                        <PhoneInput
                            v-model="profileForm.phone"
                            placeholder="(11) 98888-7777"
                            :invalid="Boolean(profileForm.errors.phone)"
                        />
                    </FormField>

                    <FormField
                        label="Bloqueio automático (min)"
                        :error="profileForm.errors.auto_lock_minutes"
                        hint="A tela é bloqueada após esse tempo de inatividade."
                    >
                        <TextInput
                            v-model="profileForm.auto_lock_minutes"
                            type="number"
                            placeholder="Desativado"
                        />
                    </FormField>

                    <div class="space-y-4 border-t border-border pt-4">
                        <FormField
                            label="Senha atual"
                            :error="profileForm.errors.current_password"
                        >
                            <TextInput
                                v-model="profileForm.current_password"
                                type="password"
                            />
                        </FormField>

                        <FormField
                            label="Nova senha"
                            :error="profileForm.errors.password"
                        >
                            <TextInput
                                v-model="profileForm.password"
                                type="password"
                            />
                        </FormField>

                        <FormField label="Confirmar nova senha">
                            <TextInput
                                v-model="profileForm.password_confirmation"
                                type="password"
                            />
                        </FormField>
                    </div>

                    <Button type="submit" :loading="profileForm.processing"
                        >Salvar Perfil</Button
                    >
                </form>
            </Card>

            <Card
                title="Empresa"
                description="Informações usadas em todos os atendimentos."
            >
                <template #actions>
                    <Badge v-if="tenant.max_connections" color="primary">
                        {{ tenant.max_connections }} conexões
                    </Badge>
                </template>

                <form class="space-y-4" @submit.prevent="saveTenant">
                    <FormField
                        label="Nome"
                        :error="tenantForm.errors.name"
                        required
                    >
                        <TextInput
                            v-model="tenantForm.name"
                            :disabled="!can('settings.manage')"
                        />
                    </FormField>

                    <FormField
                        label="Documento"
                        :error="tenantForm.errors.document"
                    >
                        <TextInput
                            v-model="tenantForm.document"
                            :disabled="!can('settings.manage')"
                        />
                    </FormField>

                    <FormField
                        label="Fuso horário"
                        :error="tenantForm.errors.timezone"
                        required
                    >
                        <SelectInput
                            v-model="tenantForm.timezone"
                            :options="timezoneOptions"
                            :disabled="!can('settings.manage')"
                        />
                    </FormField>

                    <Toggle
                        v-model="tenantForm.settings.sign_messages"
                        label="Assinar mensagens"
                        description="O nome do atendente vai no início de cada mensagem enviada pela equipe."
                        :disabled="!can('settings.manage')"
                    />

                    <FormField
                        label="Mensagem de boas-vindas"
                        :error="tenantForm.errors['settings.greeting']"
                    >
                        <TextArea
                            v-model="tenantForm.settings.greeting"
                            rows="3"
                        />
                    </FormField>

                    <FormField
                        label="Encerrar automaticamente após (horas)"
                        :error="tenantForm.errors['settings.auto_close_hours']"
                        hint="Atendimentos sem interação são finalizados."
                    >
                        <TextInput
                            v-model="tenantForm.settings.auto_close_hours"
                            type="number"
                            placeholder="Desativado"
                        />
                    </FormField>

                    <FormField
                        group
                        label="Horário de atendimento"
                        :error="businessHoursError"
                        hint="Sem dias marcados a empresa atende em qualquer horário."
                    >
                        <div class="space-y-1.5">
                            <div
                                v-for="day in weekdays"
                                :key="day.value"
                                class="flex items-start gap-2"
                            >
                                <button
                                    type="button"
                                    class="mt-0.5 h-8 w-24 shrink-0 rounded-control border px-2 text-left text-xs transition"
                                    :class="
                                        intervalsOf(day.value).length
                                            ? 'border-primary bg-primary-soft text-primary'
                                            : 'border-border text-content-muted hover:bg-surface-hover'
                                    "
                                    :disabled="!can('settings.manage')"
                                    @click="toggleWeekday(day.value)"
                                >
                                    {{ day.label }}
                                </button>

                                <div
                                    v-if="intervalsOf(day.value).length"
                                    class="flex flex-1 flex-col gap-1.5"
                                >
                                    <div
                                        v-for="(interval, index) in intervalsOf(
                                            day.value,
                                        )"
                                        :key="index"
                                        class="flex items-center gap-2"
                                    >
                                        <input
                                            type="time"
                                            :value="interval.start"
                                            class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                            :disabled="!can('settings.manage')"
                                            @input="
                                                setHour(
                                                    day.value,
                                                    index,
                                                    'start',
                                                    $event.target.value,
                                                )
                                            "
                                        />
                                        <span
                                            class="text-xs text-content-subtle"
                                            >até</span
                                        >
                                        <input
                                            type="time"
                                            :value="interval.end"
                                            class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                            :disabled="!can('settings.manage')"
                                            @input="
                                                setHour(
                                                    day.value,
                                                    index,
                                                    'end',
                                                    $event.target.value,
                                                )
                                            "
                                        />
                                        <button
                                            v-if="
                                                can('settings.manage') &&
                                                intervalsOf(day.value).length >
                                                    1
                                            "
                                            type="button"
                                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                                            @click="
                                                removeInterval(day.value, index)
                                            "
                                        >
                                            <X :size="14" />
                                        </button>
                                        <button
                                            v-if="
                                                can('settings.manage') &&
                                                index ===
                                                    intervalsOf(day.value)
                                                        .length -
                                                        1
                                            "
                                            type="button"
                                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                                            title="Adicionar Intervalo"
                                            @click="addInterval(day.value)"
                                        >
                                            <Plus :size="14" />
                                        </button>
                                    </div>
                                </div>

                                <span
                                    v-else
                                    class="mt-2 text-xs text-content-subtle"
                                    >Fechado</span
                                >
                            </div>
                        </div>
                    </FormField>

                    <FormField
                        group
                        label="Feriados e exceções"
                        :error="exceptionsError"
                        hint="O feriado bloqueia o dia inteiro, a exceção bloqueia apenas o intervalo escolhido."
                    >
                        <div class="space-y-2">
                            <div
                                v-for="(exception, index) in tenantForm.settings
                                    .business_exceptions"
                                :key="index"
                                class="space-y-2 rounded-control border border-border p-2.5"
                            >
                                <div class="flex items-center gap-2">
                                    <SelectInput
                                        :model-value="exception.type"
                                        :options="exceptionTypes"
                                        class="w-32"
                                        :disabled="!can('settings.manage')"
                                        @update:model-value="
                                            setException(index, 'type', $event)
                                        "
                                    />
                                    <TextInput
                                        :model-value="exception.name"
                                        placeholder="Natal"
                                        class="flex-1"
                                        :disabled="!can('settings.manage')"
                                        @update:model-value="
                                            setException(index, 'name', $event)
                                        "
                                    />
                                    <button
                                        v-if="can('settings.manage')"
                                        type="button"
                                        class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                                        @click="removeException(index)"
                                    >
                                        <Trash2 :size="14" />
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <input
                                        type="date"
                                        :value="exception.starts_on"
                                        class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                        :disabled="!can('settings.manage')"
                                        @input="
                                            setException(
                                                index,
                                                'starts_on',
                                                $event.target.value,
                                            )
                                        "
                                    />
                                    <span class="text-xs text-content-subtle"
                                        >até</span
                                    >
                                    <input
                                        type="date"
                                        :value="exception.ends_on"
                                        class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                        :disabled="!can('settings.manage')"
                                        @input="
                                            setException(
                                                index,
                                                'ends_on',
                                                $event.target.value,
                                            )
                                        "
                                    />

                                    <template
                                        v-if="exception.type === 'exception'"
                                    >
                                        <input
                                            type="time"
                                            :value="exception.start"
                                            class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                            :disabled="!can('settings.manage')"
                                            @input="
                                                setException(
                                                    index,
                                                    'start',
                                                    $event.target.value,
                                                )
                                            "
                                        />
                                        <span
                                            class="text-xs text-content-subtle"
                                            >às</span
                                        >
                                        <input
                                            type="time"
                                            :value="exception.end"
                                            class="h-8 rounded-control border border-border bg-surface px-2 text-xs text-content focus:border-primary focus:outline-none"
                                            :disabled="!can('settings.manage')"
                                            @input="
                                                setException(
                                                    index,
                                                    'end',
                                                    $event.target.value,
                                                )
                                            "
                                        />
                                    </template>
                                </div>

                                <SelectInput
                                    :model-value="exception.card_id"
                                    :options="cardOptions"
                                    placeholder="Usar o cartão padrão"
                                    :disabled="!can('settings.manage')"
                                    @update:model-value="
                                        setException(index, 'card_id', $event)
                                    "
                                />
                            </div>

                            <Button
                                v-if="can('settings.manage')"
                                size="sm"
                                variant="secondary"
                                :icon="Plus"
                                class="w-full justify-center"
                                @click="addException"
                            >
                                Adicionar Feriado ou Exceção
                            </Button>
                        </div>
                    </FormField>

                    <Toggle
                        v-model="tenantForm.settings.after_hours_enabled"
                        label="Separar atendimentos fora de hora"
                        description="Mensagens recebidas fora do horário ficam na seção Fora de Hora, sem chatbot e sem setor, até a equipe voltar."
                        :disabled="!can('settings.manage') || !hasBusinessHours"
                    />

                    <FormField
                        label="Cartão de fora de hora"
                        :error="
                            tenantForm.errors['settings.after_hours_card_id']
                        "
                        hint="Enviado uma vez ao contato quando o atendimento fica fora de hora."
                    >
                        <SelectInput
                            v-model="tenantForm.settings.after_hours_card_id"
                            :options="cardOptions"
                            placeholder="Não responder"
                            :disabled="
                                !can('settings.manage') ||
                                !tenantForm.settings.after_hours_enabled
                            "
                        />
                    </FormField>

                    <Button
                        v-if="can('settings.manage')"
                        type="submit"
                        :loading="tenantForm.processing"
                    >
                        Salvar Configurações
                    </Button>
                </form>
            </Card>
        </div>
    </div>
</template>
