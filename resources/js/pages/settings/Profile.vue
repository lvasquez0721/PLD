<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { edit } from '@/routes/profile';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configuración de perfil',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user as {
    usuario?: string;
    nombre?: string;
    apellido_p?: string;
    apellido_m?: string;
    email: string;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configuración de perfil" />

        <SettingsLayout>
            <div class="flex flex-col space-y-8 max-w-xl mx-auto">
                <HeadingSmall
                    title="Información del perfil"
                    description="Tu información personal de usuario"
                />

                <div class="space-y-4 bg-white rounded-xl shadow-sm px-8 py-8 border border-gray-100">
                    <dl class="divide-y divide-gray-200">
                        <!-- Mejor alineación a la izquierda, mejor contraste y jerarquía para mejor UX -->
                        <div class="grid grid-cols-1 sm:grid-cols-5 items-center py-4 first:pt-0 last:pb-0">
                            <dt class="col-span-2 text-gray-600 text-base font-medium sm:text-left sm:pr-6 pb-2 sm:pb-0">Usuario</dt>
                            <dd class="col-span-3 text-gray-900 text-lg font-semibold sm:text-left text-left break-words">{{ user.usuario || '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-5 items-center py-4 first:pt-0 last:pb-0">
                            <dt class="col-span-2 text-gray-600 text-base font-medium sm:text-left sm:pr-6 pb-2 sm:pb-0">Nombre</dt>
                            <dd class="col-span-3 text-gray-900 text-lg font-semibold sm:text-left text-left break-words">{{ user.nombre || '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-5 items-center py-4 first:pt-0 last:pb-0">
                            <dt class="col-span-2 text-gray-600 text-base font-medium sm:text-left sm:pr-6 pb-2 sm:pb-0">Apellido paterno</dt>
                            <dd class="col-span-3 text-gray-900 text-lg font-semibold sm:text-left text-left break-words">{{ user.apellido_p || '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-5 items-center py-4 first:pt-0 last:pb-0">
                            <dt class="col-span-2 text-gray-600 text-base font-medium sm:text-left sm:pr-6 pb-2 sm:pb-0">Apellido materno</dt>
                            <dd class="col-span-3 text-gray-900 text-lg font-semibold sm:text-left text-left break-words">{{ user.apellido_m || '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-5 items-center py-4 first:pt-0 last:pb-0">
                            <dt class="col-span-2 text-gray-600 text-base font-medium sm:text-left sm:pr-6 pb-2 sm:pb-0">Correo electrónico</dt>
                            <dd class="col-span-3 text-gray-900 text-lg font-semibold sm:text-left text-left break-all">{{ user.email }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="mt-10 max-w-xl mx-auto">
                <DeleteUser />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
