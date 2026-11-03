export const scopeLabels: Record<string, string> = {
    producto_1_2: 'Productos · Indicador 1.2 (efectivo/divisas)',
    cliente_actividad: 'Clientes · Actividad Económica',
    cliente_elemento: 'Clientes · Debida Diligencia',
    entidad: 'Entidad · residual institucional',
};

export function scopeLabel(scope: string): string {
    return scopeLabels[scope] ?? scope;
}

export function riesgoClase(nivel?: string): string {
    const n = (nivel ?? '').toLowerCase();

    if (n.includes('muy alto')) {
        return 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300';
    }

    if (n.includes('alto')) {
        return 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300';
    }

    if (n.includes('medio')) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
    }

    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
}
