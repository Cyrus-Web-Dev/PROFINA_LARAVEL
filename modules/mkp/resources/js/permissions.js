import { usePage } from '@inertiajs/vue3'

/** const can = useCan();  can('journal.create') -> true/false  (dari data role user yang dikirim server) */
export function useCan() {
    const page = usePage()

    return (permission) => (page.props.auth?.user?.permissions ?? []).includes(permission)
}
