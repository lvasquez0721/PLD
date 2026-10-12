import { PLD_CATEGORY_OPTIONS } from '@/components/Clientes/categoryMeta';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export interface ClientesFilters {
    search?: string;
    tipo?: string;
    estatus?: string;
    per_page?: string | number;
    category?: string | string[];
}

const ITEMS_PER_PAGE_OPTIONS = [5, 10, 20, 50, 100] as const;

function normalizeInitialCategories(
    category: ClientesFilters['category'],
): string[] {
    if (category === 'todos') return PLD_CATEGORY_OPTIONS.map((o) => o.value);
    if (Array.isArray(category)) return [...category];
    if (typeof category === 'string' && category !== '') return [category];
    // Sin parámetro: equivale a "todas" para no vaciar la tabla.
    return PLD_CATEGORY_OPTIONS.map((o) => o.value);
}

/**
 * Estado y sincronización de filtros/paginación con la URL.
 * Conserva el contrato backend: search, tipo, estatus, per_page,
 * category ('todos' | string[] | ausente) y page del paginador.
 */
export function useClientesFilters(filters?: ClientesFilters) {
    const busqueda = ref(filters?.search ?? '');
    const filtroTipoPersona = ref(filters?.tipo ?? 'todos');
    const filtroEstatus = ref(filters?.estatus ?? 'todos');
    const filtroCategoriaPLD = ref<string[]>(
        normalizeInitialCategories(filters?.category),
    );
    const itemsPerPage = ref(Number(filters?.per_page) || 10);
    const isSearching = ref(false);

    // Si la primera carga vino sin `category`, reflejar "todas".
    if (!filters?.category) {
        filtroCategoriaPLD.value = PLD_CATEGORY_OPTIONS.map((o) => o.value);
    }

    function getCategoriesToSend(): string[] | string | undefined {
        if (filtroCategoriaPLD.value.length === PLD_CATEGORY_OPTIONS.length)
            return 'todos';
        if (filtroCategoriaPLD.value.length > 0)
            return [...filtroCategoriaPLD.value];
        return undefined;
    }

    function buildParams(page?: number): Record<string, any> {
        const params: Record<string, any> = {
            search: busqueda.value.trim(),
            tipo: filtroTipoPersona.value,
            estatus: filtroEstatus.value,
            per_page: itemsPerPage.value,
            category: getCategoriesToSend(),
        };
        if (typeof page === 'number') params.page = page;
        if (!params.search) delete params.search;
        if (params.tipo === 'todos') delete params.tipo;
        if (params.estatus === 'todos') delete params.estatus;
        if (params.category === undefined) delete params.category;
        return params;
    }

    function fetchClientes(page?: number, replace = true) {
        isSearching.value = true;
        router.get('/clientes', buildParams(page), {
            preserveState: true,
            preserveScroll: true,
            replace,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    }

    let searchTimeout: ReturnType<typeof setTimeout> | null = null;
    function cancelSearchDebounce() {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = null;
    }

    watch(busqueda, () => {
        cancelSearchDebounce();
        isSearching.value = true;
        searchTimeout = setTimeout(() => fetchClientes(1), 400);
    });

    watch(
        [filtroTipoPersona, filtroEstatus, itemsPerPage, filtroCategoriaPLD],
        () => fetchClientes(1),
        {
            deep: true,
        },
    );

    const hasActiveFilters = computed(
        () =>
            busqueda.value.trim() !== '' ||
            filtroTipoPersona.value !== 'todos' ||
            filtroEstatus.value !== 'todos' ||
            (filtroCategoriaPLD.value.length !== PLD_CATEGORY_OPTIONS.length &&
                filtroCategoriaPLD.value.length !== 0),
    );

    const selectedCategoryCount = computed(
        () => filtroCategoriaPLD.value.length,
    );
    const allCategoriesSelected = computed(
        () => filtroCategoriaPLD.value.length === PLD_CATEGORY_OPTIONS.length,
    );

    function clearAllFilters() {
        cancelSearchDebounce();
        busqueda.value = '';
        filtroTipoPersona.value = 'todos';
        filtroEstatus.value = 'todos';
        filtroCategoriaPLD.value = PLD_CATEGORY_OPTIONS.map((o) => o.value);
        itemsPerPage.value = 10;
    }

    function removeCategory(cat: string) {
        filtroCategoriaPLD.value = filtroCategoriaPLD.value.filter(
            (c) => c !== cat,
        );
    }

    function descargarCSV() {
        const params = new URLSearchParams();
        const built = buildParams();
        if (built.search) params.append('search', built.search);
        if (built.tipo) params.append('tipo', built.tipo);
        if (built.estatus) params.append('estatus', built.estatus);
        const categoriesToSend = getCategoriesToSend();
        if (categoriesToSend) {
            if (Array.isArray(categoriesToSend)) {
                categoriesToSend.forEach((cat) =>
                    params.append('category[]', cat),
                );
            } else {
                params.append('category', categoriesToSend);
            }
        }
        window.location.href = `/clientes/exportar?${params.toString()}`;
    }

    return {
        busqueda,
        filtroTipoPersona,
        filtroEstatus,
        filtroCategoriaPLD,
        itemsPerPage,
        itemsPerPageOptions: [...ITEMS_PER_PAGE_OPTIONS],
        isSearching,
        hasActiveFilters,
        selectedCategoryCount,
        allCategoriesSelected,
        getCategoriesToSend,
        buildParams,
        fetchClientes,
        clearAllFilters,
        removeCategory,
        descargarCSV,
        cancelSearchDebounce,
    };
}

export type UseClientesFilters = ReturnType<typeof useClientesFilters>;
