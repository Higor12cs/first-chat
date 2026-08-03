<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { Search, Smile } from "lucide-vue-next";

const emit = defineEmits(["pick"]);

const open = ref(false);
const term = ref("");
const root = ref(null);
const searchInput = ref(null);

const groups = [
    {
        label: "Frequentes",
        emojis: [
            ["👍", "joia positivo ok"],
            ["🙏", "obrigado por favor"],
            ["😀", "sorriso feliz"],
            ["😊", "sorriso simpatico"],
            ["😉", "piscada"],
            ["👏", "palmas parabens"],
            ["🎉", "festa parabens comemoracao"],
            ["❤️", "coracao amor"],
            ["🔥", "fogo top"],
            ["✅", "check certo confirmado"],
        ],
    },
    {
        label: "Rostos",
        emojis: [
            ["😃", "sorriso alegre"],
            ["😁", "sorriso dentes"],
            ["😄", "riso"],
            ["😅", "riso alivio"],
            ["😂", "chorando de rir"],
            ["🥰", "apaixonado carinho"],
            ["😍", "encantado"],
            ["😎", "oculos estiloso"],
            ["🤔", "pensando duvida"],
            ["😴", "sono dormindo"],
            ["😕", "confuso"],
            ["😢", "triste choro"],
            ["😭", "chorando muito"],
            ["😡", "raiva bravo"],
            ["😱", "susto medo"],
            ["🤝", "aperto de mao acordo"],
        ],
    },
    {
        label: "Trabalho",
        emojis: [
            ["📝", "anotacao escrever nota"],
            ["📄", "documento arquivo"],
            ["📎", "anexo clipe"],
            ["📅", "agenda calendario data"],
            ["⏰", "hora alarme prazo"],
            ["📞", "telefone ligacao"],
            ["📧", "email"],
            ["💬", "mensagem conversa"],
            ["💡", "ideia dica"],
            ["⚠️", "atencao aviso"],
            ["❌", "erro nao cancelado"],
            ["📌", "importante fixado"],
            ["🔎", "buscar procurar"],
            ["📈", "grafico crescimento"],
        ],
    },
    {
        label: "Negócio",
        emojis: [
            ["💰", "dinheiro pagamento"],
            ["💳", "cartao pagamento"],
            ["🧾", "nota fiscal recibo"],
            ["🛒", "carrinho compra pedido"],
            ["📦", "pacote entrega pedido"],
            ["🚚", "entrega frete transporte"],
            ["🏠", "casa endereco"],
            ["📍", "local endereco"],
            ["⭐", "estrela avaliacao"],
            ["🤑", "desconto promocao"],
        ],
    },
];

function fold(value) {
    return value.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
}

const results = computed(() => {
    const search = fold(term.value.trim());

    if (search === "") {
        return groups;
    }

    return groups
        .map((group) => ({
            ...group,
            emojis: group.emojis.filter(([, keywords]) =>
                fold(keywords).includes(search),
            ),
        }))
        .filter((group) => group.emojis.length > 0);
});

function toggle() {
    open.value = !open.value;
}

function pick(emoji) {
    emit("pick", emoji);
    open.value = false;
}

function onOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === "Escape" && open.value) {
        event.preventDefault();
        event.stopPropagation();
        open.value = false;
    }
}

watch(open, (value) => {
    term.value = "";

    if (value) {
        nextTick(() => searchInput.value?.focus());
    }
});

onMounted(() => {
    document.addEventListener("click", onOutside);
    document.addEventListener("keydown", onKeydown, true);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", onOutside);
    document.removeEventListener("keydown", onKeydown, true);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="rounded-control p-1 transition"
            :class="
                open
                    ? 'bg-surface-hover text-content'
                    : 'text-content-subtle hover:text-content'
            "
            title="Emoji"
            aria-label="Inserir emoji"
            @click="toggle"
        >
            <Smile :size="18" />
        </button>

        <Transition
            enter-active-class="transition duration-100"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-75"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute bottom-full right-0 z-40 mb-2 w-72 origin-bottom-right rounded-card border border-border-strong bg-surface"
            >
                <div
                    class="flex items-center gap-2 border-b border-border px-2.5"
                >
                    <Search :size="14" class="shrink-0 text-content-subtle" />

                    <input
                        ref="searchInput"
                        v-model="term"
                        type="text"
                        placeholder="Buscar emoji"
                        class="h-9 flex-1 bg-transparent text-sm text-content placeholder:text-content-subtle focus:outline-none"
                    />
                </div>

                <div class="max-h-56 overflow-y-auto scrollbar-thin p-1.5">
                    <div
                        v-for="group in results"
                        :key="group.label"
                        class="mb-1"
                    >
                        <p
                            class="px-1 pb-1 text-[10px] font-semibold uppercase tracking-wider text-content-subtle"
                        >
                            {{ group.label }}
                        </p>

                        <div class="grid grid-cols-8 gap-0.5">
                            <button
                                v-for="[emoji] in group.emojis"
                                :key="emoji"
                                type="button"
                                class="rounded-control py-1 text-lg leading-none transition hover:bg-surface-hover"
                                @click="pick(emoji)"
                            >
                                {{ emoji }}
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="!results.length"
                        class="px-1 py-4 text-center text-xs text-content-subtle"
                    >
                        Nenhum emoji para esta busca.
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>
