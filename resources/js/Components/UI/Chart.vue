<script setup>
import { computed } from "vue";
import ApexChart from "vue3-apexcharts";
import { useUiStore } from "../../Stores/ui";

const props = defineProps({
    type: { type: String, default: "bar" },
    series: { type: Array, required: true },
    height: { type: [Number, String], default: 260 },
    categories: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    horizontal: { type: Boolean, default: false },
    stacked: { type: Boolean, default: false },
    showLegend: { type: Boolean, default: false },
    showValues: { type: Boolean, default: false },
    formatter: { type: Function, default: null },
});

const ui = useUiStore();

const categorical = {
    light: ["#2a78d6", "#eb6834", "#1baf7a", "#eda100", "#e87ba4"],
    dark: ["#3987e5", "#d95926", "#199e70", "#c98500", "#d55181"],
};

const palette = computed(() =>
    ui.isDark ? categorical.dark : categorical.light,
);
const gridColor = computed(() =>
    ui.isDark ? "rgba(255,255,255,0.08)" : "rgba(15,23,42,0.08)",
);
const inkMuted = computed(() =>
    ui.isDark ? "rgba(255,255,255,0.62)" : "rgba(15,23,42,0.55)",
);
const surface = computed(() => (ui.isDark ? "#161920" : "#ffffff"));

function format(value) {
    if (value === null || value === undefined) {
        return "";
    }

    return props.formatter ? props.formatter(value) : value;
}

const options = computed(() => ({
    chart: {
        type: props.type,
        height: props.height,
        stacked: props.stacked,
        background: "transparent",
        fontFamily: "inherit",
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: true, speed: 320 },
        parentHeightOffset: 0,
    },
    theme: { mode: ui.isDark ? "dark" : "light" },
    colors: palette.value,
    dataLabels: {
        enabled: props.showValues,
        style: { fontSize: "11px", fontWeight: 500, colors: [inkMuted.value] },
        background: { enabled: false },
        formatter: (value) => format(value),
        offsetY: props.horizontal ? 0 : -18,
    },
    stroke: {
        curve: "smooth",
        width: ["line", "area"].includes(props.type) ? 2 : 2,
        colors: ["line", "area"].includes(props.type)
            ? undefined
            : [surface.value],
    },
    fill: {
        type: props.type === "area" ? "gradient" : "solid",
        gradient: {
            shadeIntensity: 0.2,
            opacityFrom: 0.3,
            opacityTo: 0.02,
            stops: [0, 100],
        },
    },
    markers: { size: props.type === "line" ? 0 : 0, hover: { size: 5 } },
    plotOptions: {
        bar: {
            horizontal: props.horizontal,
            borderRadius: 4,
            borderRadiusApplication: "end",
            columnWidth: "56%",
            barHeight: "62%",
            dataLabels: { position: props.horizontal ? "top" : "top" },
        },
        pie: {
            donut: {
                size: "70%",
                labels: {
                    show: true,
                    name: { fontSize: "12px", color: inkMuted.value },
                    value: {
                        fontSize: "20px",
                        fontWeight: 600,
                        color: inkMuted.value,
                        formatter: (value) => format(value),
                    },
                    total: {
                        show: true,
                        label: "Total",
                        color: inkMuted.value,
                        formatter: (w) =>
                            format(
                                w.globals.seriesTotals.reduce(
                                    (a, b) => a + b,
                                    0,
                                ),
                            ),
                    },
                },
            },
        },
    },
    grid: {
        borderColor: gridColor.value,
        strokeDashArray: 4,
        padding: {
            left: 6,
            right: 6,
            top: props.showValues ? 18 : 0,
            bottom: 0,
        },
        xaxis: { lines: { show: false } },
    },
    xaxis: {
        categories: props.categories,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
            style: { colors: inkMuted.value, fontSize: "11px" },
            rotate: 0,
            hideOverlappingLabels: true,
        },
        tooltip: { enabled: false },
        crosshairs: { stroke: { color: gridColor.value, dashArray: 4 } },
    },
    yaxis: {
        labels: {
            style: { colors: inkMuted.value, fontSize: "11px" },
            formatter: (value) => format(value),
        },
    },
    labels: props.labels,
    legend: {
        show: props.showLegend,
        position: "bottom",
        horizontalAlign: "center",
        fontSize: "12px",
        markers: { size: 5, offsetX: -3 },
        itemMargin: { horizontal: 8, vertical: 2 },
        labels: { colors: inkMuted.value },
    },
    tooltip: {
        theme: ui.isDark ? "dark" : "light",
        shared:
            ["line", "area", "bar"].includes(props.type) && !props.horizontal,
        intersect: false,
        y: { formatter: (value) => format(value) },
    },
    states: { hover: { filter: { type: "lighten", value: 0.06 } } },
    noData: {
        text: "Sem dados no período.",
        style: { color: inkMuted.value, fontSize: "12px" },
    },
}));
</script>

<template>
    <ApexChart
        :type="type"
        :height="height"
        :options="options"
        :series="series"
    />
</template>
