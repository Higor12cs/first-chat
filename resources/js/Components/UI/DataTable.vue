<script setup>
defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: "id" },
});
</script>

<template>
    <div class="overflow-x-auto scrollbar-thin">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-border text-left">
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        class="whitespace-nowrap px-4 py-2.5 text-xs font-medium text-content-muted"
                        :class="column.align === 'right' ? 'text-right' : ''"
                    >
                        <slot :name="`${column.key}-header`" :column="column">{{ column.label }}</slot>
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row[rowKey]"
                    class="border-b border-border last:border-0 transition hover:bg-surface-muted"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-4 py-3 align-middle text-content"
                        :class="column.align === 'right' ? 'text-right' : ''"
                    >
                        <slot :name="column.key" :row="row">{{
                            row[column.key]
                        }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>

        <slot v-if="rows.length === 0" name="empty" />
    </div>
</template>
