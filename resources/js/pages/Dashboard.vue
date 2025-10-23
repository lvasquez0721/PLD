<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>

    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <!-- Sección de tarjetas principales -->
                <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div
                        class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern />
                    </div>
                    <div
                        class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern />
                    </div>
                    <div
                        class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern />
                    </div>
                </div>

                <!-- Contenido principal con scroll -->
                <div class="space-y-6">
                    <!-- Sección de estadísticas -->
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="mb-4 text-2xl font-bold">Estadísticas Generales</h2>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                                <h3 class="text-lg font-semibold text-blue-700 dark:text-blue-300">Usuarios Activos</h3>
                                <p class="text-3xl font-bold text-blue-900 dark:text-blue-100">1,234</p>
                            </div>
                            <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                                <h3 class="text-lg font-semibold text-green-700 dark:text-green-300">Ventas Hoy</h3>
                                <p class="text-3xl font-bold text-green-900 dark:text-green-100">$5,678</p>
                            </div>
                            <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                                <h3 class="text-lg font-semibold text-purple-700 dark:text-purple-300">Órdenes</h3>
                                <p class="text-3xl font-bold text-purple-900 dark:text-purple-100">89</p>
                            </div>
                            <div class="rounded-lg bg-orange-50 p-4 dark:bg-orange-900/20">
                                <h3 class="text-lg font-semibold text-orange-700 dark:text-orange-300">Conversión</h3>
                                <p class="text-3xl font-bold text-orange-900 dark:text-orange-100">12.5%</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de gráficos -->
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="mb-4 text-2xl font-bold">Análisis de Rendimiento</h2>
                        <div class="h-64 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <p class="text-gray-500 dark:text-gray-400">Gráfico de rendimiento aquí</p>
                        </div>
                    </div>

                    <!-- Tabla de datos recientes -->
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="mb-4 text-2xl font-bold">Actividad Reciente</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-sidebar-border/70">
                                        <th class="p-3 text-left font-semibold">Usuario</th>
                                        <th class="p-3 text-left font-semibold">Acción</th>
                                        <th class="p-3 text-left font-semibold">Fecha</th>
                                        <th class="p-3 text-left font-semibold">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="i in 20" :key="i" class="border-b border-sidebar-border/30">
                                        <td class="p-3">Usuario {{ i }}</td>
                                        <td class="p-3">Acción {{ i }}</td>
                                        <td class="p-3">{{ new Date().toLocaleDateString() }}</td>
                                        <td class="p-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-medium"
                                                :class="i % 3 === 0 ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300' :
                                                    i % 3 === 1 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300' :
                                                        'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300'">
                                                {{ i % 3 === 0 ? 'Completado' : i % 3 === 1 ? 'En Proceso' : 'Pendiente'
                                                }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sección de notificaciones -->
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="mb-4 text-2xl font-bold">Notificaciones</h2>
                        <div class="space-y-3">
                            <div v-for="i in 15" :key="i"
                                class="flex items-center space-x-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                                <div class="flex-1">
                                    <p class="font-medium">Notificación {{ i }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Descripción de la notificación
                                        {{ i }}</p>
                                </div>
                                <span class="text-xs text-gray-400">{{ i }}m</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de configuración rápida -->
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="mb-4 text-2xl font-bold">Configuración Rápida</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-3">
                                <h3 class="font-semibold">Preferencias</h3>
                                <div class="space-y-2">
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded" checked>
                                        <span>Notificaciones por email</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded">
                                        <span>Modo oscuro</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded" checked>
                                        <span>Actualizaciones automáticas</span>
                                    </label>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <h3 class="font-semibold">Configuración del Sistema</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Límite de usuarios</span>
                                        <span class="font-medium">1,000</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Almacenamiento usado</span>
                                        <span class="font-medium">2.5 GB</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Última actualización</span>
                                        <span class="font-medium">Hace 2 horas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido adicional para asegurar scroll -->
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="mb-4 text-2xl font-bold">Contenido Adicional</h2>
                        <div class="space-y-4">
                            <div v-for="i in 10" :key="i"
                                class="rounded-lg bg-gradient-to-r from-blue-50 to-purple-50 p-4 dark:from-blue-900/20 dark:to-purple-900/20">
                                <h3 class="font-semibold text-lg">Sección {{ i }}</h3>
                                <p class="text-gray-600 dark:text-gray-300">
                                    Este es el contenido de la sección {{ i }}. Aquí puedes agregar cualquier
                                    información relevante
                                    para tu aplicación. El contenido se extiende lo suficiente para probar el scroll
                                    vertical
                                    mientras el sidebar permanece fijo.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer del contenido -->
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <div class="text-center text-gray-500 dark:text-gray-400">
                            <p>Fin del contenido - Scroll funcionando correctamente</p>
                            <p class="text-sm mt-2">El sidebar debe permanecer fijo mientras este contenido hace scroll
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </FadeIn>
    </AppLayout>
</template>
