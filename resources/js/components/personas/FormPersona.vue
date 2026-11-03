<script setup lang="ts">
import Label from '@/components/ui/label/Label.vue';
import ModalForm from '@/components/ui/modals/modalForm.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Modulo {
    basePath: string;
    titulo: string;
    singular: string;
    plural: string;
    tipoPersona: string;
}

interface Catalogos {
    tiposPersona: Array<{ IDTipoPersona: number; TipoPersona: string }>;
    nacionalidades: Array<{ IDNacionalidad: string; Nacionalidad: string }>;
    ocupaciones: Array<{
        IDOcupacionGiro: number;
        OcupacionGiro: string;
        NivelRiesgo?: string;
    }>;
    estados: Array<{ IDEstado: string | number; Estado: string }>;
}

const props = defineProps<{
    modelValue: boolean;
    modulo: Modulo;
    catalogos: Catalogos;
    persona?: any | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const esEdicion = computed(() => !!props.persona?.IDCliente);
const esFisica = computed(() => String(form.IDTipoPersona) === '1');

function fechaInput(value: any): string {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function ocupacionColaboradorPorDefecto(): string {
    if (props.modulo.tipoPersona !== 'Colaborador') return '';

    const ocupacion = props.catalogos.ocupaciones.find((item) =>
        String(item.OcupacionGiro)
            .toLowerCase()
            .startsWith('empleado del sector privado'),
    );

    return ocupacion ? String(ocupacion.IDOcupacionGiro) : '';
}

function initialData() {
    const persona = props.persona ?? {};
    const domicilio = persona.domicilios?.[0] ?? {};

    return {
        IDTipoPersona: String(persona.IDTipoPersona ?? 1),
        RFC: persona.RFC ?? '',
        Nombre: persona.Nombre ?? '',
        ApellidoPaterno: persona.ApellidoPaterno ?? '',
        ApellidoMaterno: persona.ApellidoMaterno ?? '',
        RazonSocial: persona.RazonSocial ?? '',
        CURP: persona.CURP ?? '',
        IDNacionalidad: persona.IDNacionalidad
            ? String(persona.IDNacionalidad)
            : '',
        IDOcupacionGiro: persona.IDOcupacionGiro
            ? String(persona.IDOcupacionGiro)
            : ocupacionColaboradorPorDefecto(),
        FechaNacimiento: fechaInput(persona.FechaNacimiento),
        FechaConstitucion: fechaInput(persona.FechaConstitucion),
        FolioMercantil: persona.FolioMercantil ?? '',
        IDEstadoNacimiento: persona.IDEstadoNacimiento
            ? String(persona.IDEstadoNacimiento)
            : '',
        IngresosEstimados: persona.IngresosEstimados ?? '',
        Preguntas: persona.Preguntas ?? '',
        domicilio: {
            Calle: domicilio.Calle ?? '',
            NoExterior: domicilio.NoExterior ?? '',
            NoInterior: domicilio.NoInterior ?? '',
            Colonia: domicilio.Colonia ?? '',
            CP: domicilio.CP ?? '',
            IDEstado: domicilio.IDEstado ? String(domicilio.IDEstado) : '',
            Municipio: domicilio.Municipio ?? '',
            Localidad: domicilio.Localidad ?? '',
            Telefono: domicilio.Telefono ?? '',
        },
    };
}

const form = useForm<any>(initialData());

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        const data = initialData();
        Object.keys(data).forEach((key) => {
            form[key] = data[key];
        });
        form.clearErrors();
    },
);

function close() {
    emit('update:modelValue', false);
    emit('close');
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (esEdicion.value) {
        form.put(
            `${props.modulo.basePath}/${props.persona.IDCliente}`,
            options,
        );
    } else {
        form.post(props.modulo.basePath, options);
    }
}

const error = (key: string): string | undefined => form.errors[key];
</script>

<template>
    <ModalForm
        :model-value="props.modelValue"
        :title="
            esEdicion
                ? `Editar ${props.modulo.singular}`
                : `Nuevo ${props.modulo.singular}`
        "
        width-class="max-w-3xl"
        :persistent="form.processing"
        @update:model-value="emit('update:modelValue', $event)"
        @close="close"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <Label for="persona-tipo"
                        >Tipo de persona
                        <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="persona-tipo"
                        v-model="form.IDTipoPersona"
                        :disabled="form.processing"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    >
                        <option
                            v-for="tipo in props.catalogos.tiposPersona"
                            :key="tipo.IDTipoPersona"
                            :value="String(tipo.IDTipoPersona)"
                        >
                            {{ tipo.TipoPersona }}
                        </option>
                    </select>
                    <p
                        v-if="error('IDTipoPersona')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('IDTipoPersona') }}
                    </p>
                </div>

                <div>
                    <Label for="persona-rfc"
                        >RFC <span class="text-red-500">*</span></Label
                    >
                    <input
                        id="persona-rfc"
                        v-model="form.RFC"
                        :disabled="form.processing"
                        maxlength="20"
                        autocomplete="off"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm uppercase shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    />
                    <p v-if="error('RFC')" class="mt-1 text-xs text-red-500">
                        {{ error('RFC') }}
                    </p>
                </div>

                <template v-if="esFisica">
                    <div>
                        <Label for="persona-nombre"
                            >Nombre <span class="text-red-500">*</span></Label
                        >
                        <input
                            id="persona-nombre"
                            v-model="form.Nombre"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('Nombre')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('Nombre') }}
                        </p>
                    </div>
                    <div>
                        <Label for="persona-apellido-paterno"
                            >Apellido paterno
                            <span class="text-red-500">*</span></Label
                        >
                        <input
                            id="persona-apellido-paterno"
                            v-model="form.ApellidoPaterno"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('ApellidoPaterno')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('ApellidoPaterno') }}
                        </p>
                    </div>
                    <div>
                        <Label for="persona-apellido-materno"
                            >Apellido materno</Label
                        >
                        <input
                            id="persona-apellido-materno"
                            v-model="form.ApellidoMaterno"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('ApellidoMaterno')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('ApellidoMaterno') }}
                        </p>
                    </div>
                    <div>
                        <Label for="persona-curp">CURP</Label>
                        <input
                            id="persona-curp"
                            v-model="form.CURP"
                            :disabled="form.processing"
                            maxlength="18"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm uppercase shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('CURP')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('CURP') }}
                        </p>
                    </div>
                </template>

                <template v-else>
                    <div class="sm:col-span-2">
                        <Label for="persona-razon-social"
                            >Razón social
                            <span class="text-red-500">*</span></Label
                        >
                        <input
                            id="persona-razon-social"
                            v-model="form.RazonSocial"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('RazonSocial')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('RazonSocial') }}
                        </p>
                    </div>
                </template>

                <div>
                    <Label for="persona-nacionalidad"
                        >Nacionalidad <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="persona-nacionalidad"
                        v-model="form.IDNacionalidad"
                        :disabled="form.processing"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    >
                        <option value="">Selecciona una nacionalidad</option>
                        <option
                            v-for="nac in props.catalogos.nacionalidades"
                            :key="nac.IDNacionalidad"
                            :value="String(nac.IDNacionalidad)"
                        >
                            {{ nac.Nacionalidad }}
                        </option>
                    </select>
                    <p
                        v-if="error('IDNacionalidad')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('IDNacionalidad') }}
                    </p>
                </div>

                <div>
                    <Label for="persona-ocupacion">Ocupación / giro</Label>
                    <select
                        id="persona-ocupacion"
                        v-model="form.IDOcupacionGiro"
                        :disabled="form.processing"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    >
                        <option value="">Sin especificar</option>
                        <option
                            v-for="oc in props.catalogos.ocupaciones"
                            :key="oc.IDOcupacionGiro"
                            :value="String(oc.IDOcupacionGiro)"
                        >
                            {{ oc.OcupacionGiro }}
                        </option>
                    </select>
                    <p
                        v-if="error('IDOcupacionGiro')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('IDOcupacionGiro') }}
                    </p>
                </div>

                <div>
                    <Label for="persona-estado-nacimiento"
                        >Estado de nacimiento</Label
                    >
                    <select
                        id="persona-estado-nacimiento"
                        v-model="form.IDEstadoNacimiento"
                        :disabled="form.processing"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    >
                        <option value="">Sin especificar</option>
                        <option
                            v-for="estado in props.catalogos.estados"
                            :key="estado.IDEstado"
                            :value="String(estado.IDEstado)"
                        >
                            {{ estado.Estado }}
                        </option>
                    </select>
                    <p
                        v-if="error('IDEstadoNacimiento')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('IDEstadoNacimiento') }}
                    </p>
                </div>

                <div v-if="esFisica">
                    <Label for="persona-fecha-nacimiento"
                        >Fecha de nacimiento</Label
                    >
                    <input
                        id="persona-fecha-nacimiento"
                        v-model="form.FechaNacimiento"
                        type="date"
                        :disabled="form.processing"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    />
                    <p
                        v-if="error('FechaNacimiento')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('FechaNacimiento') }}
                    </p>
                </div>
                <div v-else>
                    <Label for="persona-fecha-constitucion"
                        >Fecha de constitución</Label
                    >
                    <input
                        id="persona-fecha-constitucion"
                        v-model="form.FechaConstitucion"
                        type="date"
                        :disabled="form.processing"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    />
                    <p
                        v-if="error('FechaConstitucion')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('FechaConstitucion') }}
                    </p>
                </div>

                <div v-if="!esFisica">
                    <Label for="persona-folio-mercantil">Folio mercantil</Label>
                    <input
                        id="persona-folio-mercantil"
                        v-model="form.FolioMercantil"
                        :disabled="form.processing"
                        autocomplete="off"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    />
                    <p
                        v-if="error('FolioMercantil')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('FolioMercantil') }}
                    </p>
                </div>

                <div>
                    <Label for="persona-ingresos">Ingresos estimados</Label>
                    <input
                        id="persona-ingresos"
                        v-model="form.IngresosEstimados"
                        type="number"
                        min="0"
                        step="0.01"
                        :disabled="form.processing"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                    />
                    <p
                        v-if="error('IngresosEstimados')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ error('IngresosEstimados') }}
                    </p>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4 dark:border-neutral-700">
                <h4
                    class="mb-3 text-sm font-semibold text-gray-800 dark:text-neutral-200"
                >
                    Domicilio
                </h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <Label for="domicilio-calle"
                            >Calle <span class="text-red-500">*</span></Label
                        >
                        <input
                            id="domicilio-calle"
                            v-model="form.domicilio.Calle"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('domicilio.Calle')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('domicilio.Calle') }}
                        </p>
                    </div>
                    <div>
                        <Label for="domicilio-no-exterior">No. exterior</Label>
                        <input
                            id="domicilio-no-exterior"
                            v-model="form.domicilio.NoExterior"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                    </div>
                    <div>
                        <Label for="domicilio-no-interior">No. interior</Label>
                        <input
                            id="domicilio-no-interior"
                            v-model="form.domicilio.NoInterior"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                    </div>
                    <div>
                        <Label for="domicilio-colonia"
                            >Colonia <span class="text-red-500">*</span></Label
                        >
                        <input
                            id="domicilio-colonia"
                            v-model="form.domicilio.Colonia"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('domicilio.Colonia')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('domicilio.Colonia') }}
                        </p>
                    </div>
                    <div>
                        <Label for="domicilio-cp"
                            >Código postal
                            <span class="text-red-500">*</span></Label
                        >
                        <input
                            id="domicilio-cp"
                            v-model="form.domicilio.CP"
                            :disabled="form.processing"
                            maxlength="10"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('domicilio.CP')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('domicilio.CP') }}
                        </p>
                    </div>
                    <div>
                        <Label for="domicilio-estado"
                            >Estado <span class="text-red-500">*</span></Label
                        >
                        <select
                            id="domicilio-estado"
                            v-model="form.domicilio.IDEstado"
                            :disabled="form.processing"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        >
                            <option value="">Selecciona un estado</option>
                            <option
                                v-for="estado in props.catalogos.estados"
                                :key="estado.IDEstado"
                                :value="String(estado.IDEstado)"
                            >
                                {{ estado.Estado }}
                            </option>
                        </select>
                        <p
                            v-if="error('domicilio.IDEstado')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('domicilio.IDEstado') }}
                        </p>
                    </div>
                    <div>
                        <Label for="domicilio-municipio"
                            >Municipio
                            <span class="text-red-500">*</span></Label
                        >
                        <input
                            id="domicilio-municipio"
                            v-model="form.domicilio.Municipio"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('domicilio.Municipio')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('domicilio.Municipio') }}
                        </p>
                    </div>
                    <div>
                        <Label for="domicilio-localidad">Localidad</Label>
                        <input
                            id="domicilio-localidad"
                            v-model="form.domicilio.Localidad"
                            :disabled="form.processing"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                    </div>
                    <div>
                        <Label for="domicilio-telefono">Teléfono</Label>
                        <input
                            id="domicilio-telefono"
                            v-model="form.domicilio.Telefono"
                            :disabled="form.processing"
                            maxlength="20"
                            autocomplete="off"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200"
                        />
                        <p
                            v-if="error('domicilio.Telefono')"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ error('domicilio.Telefono') }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="form.errors.general" class="text-xs text-red-600">
                {{ form.errors.general }}
            </div>

            <div
                class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4 dark:border-neutral-700"
            >
                <button
                    type="button"
                    :disabled="form.processing"
                    @click="close"
                    class="rounded-md px-4 py-2 text-gray-600 transition hover:bg-gray-100 disabled:opacity-60 dark:hover:bg-neutral-800"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-blue-600 px-6 py-2 font-semibold text-white transition hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-400 disabled:opacity-60"
                >
                    <span v-if="form.processing">Guardando...</span>
                    <span v-else>{{
                        esEdicion ? 'Guardar cambios' : 'Crear'
                    }}</span>
                </button>
            </div>
        </form>
    </ModalForm>
</template>
