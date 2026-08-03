import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.auth?.permissions ?? []);
    const isSuperAdmin = computed(() =>
        Boolean(page.props.auth?.is_super_admin),
    );

    function can(permission) {
        return isSuperAdmin.value || permissions.value.includes(permission);
    }

    function canAny(...keys) {
        return keys.some((key) => can(key));
    }

    return { can, canAny, permissions, isSuperAdmin };
}
