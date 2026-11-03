<script setup lang="ts">
import Label from '@/components/ui/label/Label.vue';
import ModalForm from '@/components/ui/modals/modalForm.vue';
import { router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Download, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ModuloImportacion {
    basePath: string;
    titulo: string;
    singular: string;
    plural: string;
    tipoPersona: string;
}

interface ImportacionResumen {
    id: number;
    tipo_persona: string;
    estado: 'prevalidado' | 'importado' | 'error';
    total_filas: number;
    filas_validas: number;
    filas_error: number;
    resumen?: Record<string, any> | null;
    nombre_archivo: string;
}

const props = defineProps<{
    modelValue: boolean;
    modulo: ModuloImportacion;
    importacionReciente?: ImportacionResumen | null;
    erroresImportacion?: Array<{
        linea?: number | null;
        campo?: string | null;
        valor?: string | number | null;
        mensaje?: string | null;
    }>;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const form = useForm<{ archivo: File | null }>({ archivo: null });
const confirmando = ref(false);

const errores = computed(() => props.erroresImportacion ?? []);
const importacion = computed(() => props.importacionReciente ?? null);
const puedeConfirmar = computed(
    () => importacion.value?.estado === 'prevalidado' && importacion.value.filas_validas > 0,
);

function cerrar() {
    emit('update:modelValue', false);
}

function seleccionarArchivo(event: Event) {
    const input = event.target as HTMLInputElement;
    form.archivo = input.files?.[0] ?? null;
}

function prevalidar() {
    form.post(`${props.modulo.basePath}/importar`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('archivo'),
    });
}

function confirmar() {
    if (!importacion.value || confirmando.value) return;
    confirmando.value = true;

    router.post(
        `${props.modulo.basePath}/importaciones/${importacion.value.id}/confirmar`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                confirmando.value = false;
            },
        },
    );
}
</script>

<template>
    <ModalForm
        :model-value="props.modelValue"
        :title="`Importar ${props.modulo.plural}`"
        subtitle="Carga masiva desde CSV con validación previa antes de confirmar."
        width-class="max-w-3xl"
        :persistent="form.processing"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <div class="space-y-5">
            <div
                class="rounded-xl border border-blue-200 bg-blue-50/80 p-4 dark:border-blue-500/30 dark:bg-blue-500/10"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                            Descarga la plantilla
                        </h3>
                        <p class="mt-1 text-xs text-blue-700 dark:text-blue-200">
                            Captura los datos y vuelve a cargar el archivo en formato CSV.
                            La plantilla incluye las columnas obligatorias y ejemplos.
                        </p>
                        <p class="mt-2 text-[11px] text-blue-700/80 dark:text-blue-200/80">
                            Obligatorias: tipo_persona, rfc, nacionalidad, calle, colonia,
                            cp, estado y municipio. Para persona física: nombre y
                            apellido_paterno. Para persona moral: razon_social.
                        </p>
                    </div>
                    <a
                        :href="`${props.modulo.basePath}/plantilla`"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        <Download class="h-4 w-4" />
                        Descargar plantilla CSV
                    </a>
                </div>
            </div>

            <div>
                <Label for="personas-importar-archivo">Archivo CSV</Label>
                <input
                    id="personas-importar-archivo"
                    type="file"
                    accept=".csv,text/csv,.txt"
                    :disabled="form.processing"
                    class="mt-1 block w-full rounded-lg border border-gray-300 bg-white p-2 text-xs shadow-sm dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200"
                    @change="seleccionarArchivo"
                />
                <p v-if="form.errors.archivo" class="mt-1 text-xs text-red-600">
                    {{ form.errors.archivo }}
                </p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-neutral-400">
                    Máximo 100 registros y 10 MB. Se aceptan archivos CSV o TXT.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                :disabled="!form.archivo || form.processing"
                @click="prevalidar"
            >
                <Upload class="h-4 w-4" />
                {{ form.processing ? 'Validando…' : 'Validar archivo' }}
            </button>

            <div
                v-if="importacion"
                class="rounded-xl border p-4 text-sm"
                :class="
                    importacion.estado === 'error'
                        ? 'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10'
                        : importacion.estado === 'importado'
                          ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                          : 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'
                "
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 font-semibold">
                            <CheckCircle2
                                v-if="importacion.estado === 'importado'"
                                class="h-4 w-4 text-emerald-600"
                            />
                            <AlertTriangle
                                v-else
                                class="h-4 w-4"
                                :class="
                                    importacion.estado === 'error'
                                        ? 'text-red-600'
                                        : 'text-amber-600'
                                "
                            />
                            {{ importacion.nombre_archivo }}
                        </div>
                        <p class="mt-1 text-xs">
                            {{ importacion.filas_validas }} fila(s) válida(s),
                            {{ importacion.filas_error }} error(es). Estado:
                            <span class="font-semibold">{{ importacion.estado }}</span>
                        </p>
                        <p
                            v-if="importacion.estado === 'prevalidado'"
                            class="mt-1 text-xs text-amber-700 dark:text-amber-200"
                        >
                            Revisa el resumen y confirma para crear los registros.
                        </p>
                        <p
                            v-if="importacion.estado === 'importado'"
                            class="mt-1 text-xs text-emerald-700 dark:text-emerald-200"
                        >
                            Importación completada. Ya puedes cerrar esta ventana.
                        </p>
                        <p
                            v-if="importacion.estado === 'importado' && importacion.resumen?.alertas_generadas !== undefined"
                            class="mt-1 text-[11px] text-emerald-700/80 dark:text-emerald-200/80"
                        >
                            Alertas PLD generadas: {{ importacion.resumen.alertas_generadas }} ·
                            Marcadas como alto riesgo: {{ importacion.resumen.marcadas_alto_riesgo ?? 0 }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="puedeConfirmar"
                            type="button"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                            @click="confirmar"
                        >
                            Confirmar importación
                        </button>
                        <a
                            v-if="errores.length"
                            :href="`${props.modulo.basePath}/importaciones/${importacion.id}/errores`"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        >
                            Descargar errores CSV
                        </a>
                    </div>
                </div>

                <div
                    v-if="errores.length"
                    class="mt-3 max-h-56 overflow-auto rounded-lg bg-white/80 p-3 text-xs dark:bg-neutral-950/60"
                >
                    <div
                        v-for="(error, index) in errores.slice(0, 100)"
                        :key="`error-${index}`"
                        class="border-b border-slate-100 py-1 last:border-0 dark:border-neutral-800"
                    >
                        <span v-if="error.linea">Línea {{ error.linea }} · </span>
                        <span class="font-semibold">{{ error.campo || 'general' }}</span>:
                        {{ error.mensaje }}
                        <span v-if="error.valor">({{ error.valor }})</span>
                    </div>
                    <p
                        v-if="errores.length > 100"
                        class="mt-2 text-[11px] text-gray-500 dark:text-neutral-400"
                    >
                        Mostrando los primeros 100 errores. Descarga el CSV para ver todos.
                    </p>
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-neutral-700">
                <button
                    type="button"
                    class="rounded-md px-4 py-2 text-gray-600 transition hover:bg-gray-100 disabled:opacity-60 dark:hover:bg-neutral-800"
                    :disabled="form.processing"
                    @click="cerrar"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </ModalForm>
</template>
