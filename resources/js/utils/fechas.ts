/**
 * Utilidades de fecha para pintar valores DATE/DATETIME del backend.
 *
 * Problema que resuelve: el backend envía fechas DATE como string
 * 'YYYY-MM-DD'. `new Date('2025-12-15')` se interpreta como medianoche
 * UTC y al formatear en America/Mexico_City (UTC-6) muestra el día
 * anterior ("14 dic"). Parseando como fecha local se muestra el día
 * correcto.
 */

/** /^YYYY-MM-DD$/ (solo fecha, sin hora). */
const SOLO_FECHA = /^(\d{4})-(\d{2})-(\d{2})$/;

/**
 * Parsea un string de fecha evitando el corrimiento UTC de `new Date()`.
 * - 'YYYY-MM-DD' -> medianoche LOCAL (sin desfase de un día).
 * - Cualquier otro formato (con hora/timezone) -> `new Date(d)` normal.
 * Retorna `null` si no se puede parsear.
 */
export function parseFechaLocal(d: string | null | undefined): Date | null {
    if (!d || typeof d !== 'string') return null;
    const m = SOLO_FECHA.exec(d.trim());
    if (m) {
        const dt = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        return isNaN(dt.getTime()) ? null : dt;
    }
    try {
        const dt = new Date(d);
        return isNaN(dt.getTime()) ? null : dt;
    } catch {
        return null;
    }
}

/** '2025-12-15' -> '15 dic 2025'. Retorna el string original si es inválida. */
export function formatFechaCorta(d: string | null | undefined): string {
    if (!d) return '—';
    const dt = typeof d === 'string' ? parseFechaLocal(d) : null;
    if (!dt) return typeof d === 'string' ? d : '—';
    return dt.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
}

/** '2025-12-15' -> '15 de diciembre de 2025'. */
export function formatFechaLarga(d: string | null | undefined): string {
    if (!d) return '—';
    const dt = typeof d === 'string' ? parseFechaLocal(d) : null;
    if (!dt) return typeof d === 'string' ? d : '—';
    return dt.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Comparador de rango para filtros por fecha (strings 'YYYY-MM-DD').
 * Todo se evalúa en hora local: evita excluir los días borde.
 */
export function fechaEnRango(
    fecha: string,
    inicio: string | null | undefined,
    fin: string | null | undefined,
): boolean {
    if (!fecha) return false;
    if (!inicio && !fin) return true;
    const base = parseFechaLocal(fecha);
    if (!base) return false;
    const t = new Date(base.getFullYear(), base.getMonth(), base.getDate()).getTime();
    if (inicio) {
        const s = parseFechaLocal(inicio);
        if (s && t < new Date(s.getFullYear(), s.getMonth(), s.getDate()).getTime()) return false;
    }
    if (fin) {
        const e = parseFechaLocal(fin);
        if (e && t > new Date(e.getFullYear(), e.getMonth(), e.getDate()).getTime()) return false;
    }
    return true;
}
