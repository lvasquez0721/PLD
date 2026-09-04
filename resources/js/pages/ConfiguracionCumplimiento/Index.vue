<script setup lang="ts">
import Input from '@/components/forms/Input.vue';
import Toast from '@/components/ui/alert/Toast.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Settings } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración de Cumplimiento',
        href: '',
    },
];

interface Props {
    config: {
        correo: string;
        nombre: string;
        activo: boolean;
    };
}

const props = defineProps<Props>();

const form = useForm({
    correo: props.config.correo,
    nombre: props.config.nombre,
    activo: props.config.activo,
});

const page = usePage();

const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref<'success' | 'warning' | 'error'>('success');

function parseUrlToastParams() {
    const query = window.location.search.substring(1);
    if (!query) return {};
    const params: Record<string, string> = {};
    query.split('&').forEach((pair) => {
        const parts = pair.split('=');
        const key = parts[0];
        if (key) {
            let value = parts[1] || '';
            value = value.replace(/\+/g, ' ');
            params[decodeURIComponent(key)] = decodeURIComponent(value);
        }
    });
    return params;
}

onMounted(() => {
    let foundToast = false;

    const propToast = page.props.toast as
        | { message: string; type: 'success' | 'warning' | 'error' }
        | undefined;
    if (propToast && propToast.message && propToast.type) {
        toastMessage.value = propToast.message;
        toastType.value = propToast.type;
        showToast.value = true;
        foundToast = true;
    }

    if (!foundToast) {
        const urlParams = parseUrlToastParams();
        if (urlParams.toast_message) {
            toastMessage.value = urlParams.toast_message;
            if (
                urlParams.toast_type === 'success' ||
                urlParams.toast_type === 'warning' ||
                urlParams.toast_type === 'error'
            ) {
                toastType.value = urlParams.toast_type;
            } else {
                toastType.value = 'success';
            }
            showToast.value = true;

            try {
                const url = new URL(window.location.href);
                url.searchParams.delete('toast_message');
                url.searchParams.delete('toast_type');
                window.history.replaceState(
                    {},
                    document.title,
                    url.pathname + url.search,
                );
            } catch {}
        }
    }
});

const guardar = () => {
    form.post('/configuracion-cumplimiento/actualizar', {
        preserveScroll: true,
        onSuccess: () => {
            toastMessage.value =
                'Correo del oficial de cumplimiento actualizado correctamente.';
            toastType.value = 'success';
            showToast.value = true;

            router.reload({ only: ['config'] });
        },
        onError: () => {
            toastMessage.value =
                'Error al guardar. Verifica el correo ingresado.';
            toastType.value = 'error';
            showToast.value = true;
        },
    });
};
</script>

<template>
    <Head title="Configuración de Cumplimiento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-0">
                <form @submit.prevent="guardar" class="space-y-6">
                    <div
                        class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white p-6 shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)]"
                    >
                        <section class="space-y-4">
                            <h3
                                class="mb-4 flex items-center gap-2 text-2xl font-semibold text-slate-800 dark:text-neutral-200"
                            >
                                <Settings class="h-6 w-6 text-blue-600" />
                                Correo del Oficial de Cumplimiento
                            </h3>
                            <div
                                class="grid grid-cols-1 gap-x-4 md:grid-cols-2"
                            >
                                <Input
                                    label="Correo electrónico"
                                    v-model="form.correo"
                                    type="email"
                                    placeholder="oficial.cumplimiento@dominio.com"
                                    required
                                    icon="mail"
                                />
                                <Input
                                    label="Nombre del oficial"
                                    v-model="form.nombre"
                                    type="text"
                                    placeholder="Nombre del oficial de cumplimiento"
                                    icon="user"
                                />
                            </div>
                            <div class="flex items-center gap-2 pt-2">
                                <input
                                    id="cumplimiento-activo"
                                    v-model="form.activo"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-blue-500 accent-blue-500 focus:ring-blue-500"
                                />
                                <label
                                    for="cumplimiento-activo"
                                    class="cursor-pointer text-sm font-medium text-slate-700 dark:text-neutral-300"
                                >
                                    Enviar avisos al oficial de cumplimiento
                                </label>
                            </div>
                        </section>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Guardando...'
                                    : 'Guardar configuración'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </FadeIn>

        <Toast
            v-model="showToast"
            :message="toastMessage"
            :type="toastType"
            :duration="5000"
        />
    </AppLayout>
</template>
