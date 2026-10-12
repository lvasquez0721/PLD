export interface PldCategoryOption {
    value: string;
    label: string;
    short: string;
    description: string;
    dot: string;
}

export const PLD_CATEGORY_OPTIONS: PldCategoryOption[] = [
    {
        value: 'sin-coincidencia',
        label: 'Sin coincidencia en listas',
        short: 'Sin coincidencia',
        description: 'Sin registros en listas de observación.',
        dot: 'bg-border',
    },
    {
        value: 'coincidencia-revision',
        label: 'Coincidencia, necesita revisión',
        short: 'Coincidencia',
        description: 'Aparece en listas bloqueadas, requiere revisión.',
        dot: 'bg-orange-500',
    },
    {
        value: 'ppe-revision',
        label: 'PPE, necesita revisión',
        short: 'PPE',
        description: 'Persona Políticamente Expuesta; atención especial.',
        dot: 'bg-indigo-400',
    },
    {
        value: 'autorizada-listas',
        label: 'Autorizada que aparece en listas',
        short: 'Autorizada en listas',
        description: 'Cliente autorizado que figura en listas de control.',
        dot: 'bg-yellow-400',
    },
    {
        value: 'fuera-categoria',
        label: 'Fuera de categoría Tláloc',
        short: 'Fuera de categoría',
        description: 'Detectado fuera de categoría Tláloc, necesita revisión.',
        dot: 'bg-purple-500',
    },
    {
        value: 'listas-internas',
        label: 'Listas internas (oficios CNSF)',
        short: 'Listas internas',
        description: 'Identificado en comunicados internos de la CNSF.',
        dot: 'bg-rose-400',
    },
];

export const CATEGORY_DISPLAY_NAMES: Record<string, string> =
    Object.fromEntries(PLD_CATEGORY_OPTIONS.map((o) => [o.value, o.label]));

export const CATEGORY_SHORT_NAMES: Record<string, string> = Object.fromEntries(
    PLD_CATEGORY_OPTIONS.map((o) => [o.value, o.short]),
);

export interface CategoryTag {
    dot: string;
    tooltip: string;
    kind: 'dot' | 'text';
}

/**
 * Replica la lógica de negocio original de Index.vue:getCategoryTags
 * pero devuelve un modelo neutro (dot + tooltip) para que cada
 * vista decida su representación. No altera reglas de negocio.
 */
export function getCategoryTags(cliente: any): CategoryTag[] {
    const tags: CategoryTag[] = [];

    if (cliente?.coincidencias) {
        tags.push({
            dot: 'bg-orange-500',
            tooltip: 'Coincidencia, necesita revisión',
            kind: 'dot',
        });
    }
    if (cliente?.esPPE) {
        tags.push({
            dot: 'bg-indigo-400',
            tooltip: 'PPE, necesita revisión',
            kind: 'dot',
        });
    }
    if (cliente?.autorizadoApareceEnListas) {
        tags.push({
            dot: 'bg-yellow-400',
            tooltip: 'Autorizada que aparece en listas',
            kind: 'dot',
        });
    }
    if (cliente?.fueraDeCategoria) {
        tags.push({
            dot: 'bg-purple-500',
            tooltip: 'Fuera de categoría Tláloc',
            kind: 'dot',
        });
    }
    if (cliente?.CNSF) {
        tags.push({
            dot: 'bg-rose-400',
            tooltip: 'Listas internas (oficios CNSF)',
            kind: 'dot',
        });
    }
    if (tags.length === 0) {
        tags.push({
            dot: '',
            tooltip: 'Sin coincidencia en listas',
            kind: 'text',
        });
    }
    return tags;
}

export function displayNameForCliente(cliente: any): string {
    const parts = [
        cliente?.Nombre,
        cliente?.ApellidoPaterno,
        cliente?.ApellidoMaterno,
    ]
        .filter((p) => typeof p === 'string' && p.trim() !== '')
        .join(' ')
        .trim();
    return parts || 'Sin nombre registrado';
}
