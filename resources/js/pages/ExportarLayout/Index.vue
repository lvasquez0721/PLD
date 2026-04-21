<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import { type BreadcrumbItem } from '@/types'
import { Download, FileText, User, Building2, Users, CheckCircle2, ChevronDown } from 'lucide-vue-next'

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exportar Layout', href: '' },
]

const tipoPersona = ref('')
const exportando = ref(false)
const exportado = ref(false)
const mensajeError = ref('')

const opciones = [
    {
        label: 'Física',
        value: '1',
        icon: User,
        descripcion: 'ID, Nombre, Apellidos, CURP y RFC',
        ext: '.txt',
        color: 'blue',
    },
    {
        label: 'Moral',
        value: '2',
        icon: Building2,
        descripcion: 'ID, Razón Social y RFC',
        ext: '.txt',
        color: 'violet',
    },
    {
        label: 'Física / Moral',
        value: '0',
        icon: Users,
        descripcion: 'FULL_NAME, TAX_ID y NATIONAL_ID',
        ext: '.csv',
        color: 'emerald',
    },
]

const opcionSeleccionada = computed(() =>
    opciones.find(o => o.value === tipoPersona.value) ?? null
)

const colorMap: Record<string, { card: string; icon: string; badge: string; ring: string }> = {
    blue: {
        card: 'border-blue-300/50 dark:border-blue-600/40 bg-blue-50/40 dark:bg-blue-950/20',
        icon: 'text-blue-600 dark:text-blue-400 bg-blue-100/70 dark:bg-blue-900/40',
        badge: 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300',
        ring: 'ring-blue-400/60 dark:ring-blue-500/50',
    },
    violet: {
        card: 'border-violet-300/50 dark:border-violet-600/40 bg-violet-50/40 dark:bg-violet-950/20',
        icon: 'text-violet-600 dark:text-violet-400 bg-violet-100/70 dark:bg-violet-900/40',
        badge: 'bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300',
        ring: 'ring-violet-400/60 dark:ring-violet-500/50',
    },
    emerald: {
        card: 'border-emerald-300/50 dark:border-emerald-600/40 bg-emerald-50/40 dark:bg-emerald-950/20',
        icon: 'text-emerald-600 dark:text-emerald-400 bg-emerald-100/70 dark:bg-emerald-900/40',
        badge: 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300',
        ring: 'ring-emerald-400/60 dark:ring-emerald-500/50',
    },
}

function seleccionar(value: string) {
    tipoPersona.value = value
    mensajeError.value = ''
    exportado.value = false
}

function exportar() {
    if (!tipoPersona.value) {
        mensajeError.value = 'Selecciona un tipo de persona para continuar.'
        return
    }

    mensajeError.value = ''
    exportando.value = true
    exportado.value = false

    const params = new URLSearchParams({ tipo: tipoPersona.value })
    window.location.href = `/exportar-layout/exportar?${params.toString()}`

    setTimeout(() => {
        exportando.value = false
        exportado.value = true
    }, 2000)

    setTimeout(() => {
        exportado.value = false
    }, 6000)
}
</script>

<template>
    <Head title="Exportar Layout" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div class="flex flex-col gap-8 p-6 max-w-2xl">

                <!-- Selector de tipo de persona -->
                <div class="space-y-3">
                    <p class="text-xs font-medium uppercase tracking-widest text-muted-foreground/70 pl-0.5">
                        Tipo de persona
                    </p>

                    <div class="grid gap-3">
                        <button v-for="op in opciones" :key="op.value" type="button" @click="seleccionar(op.value)"
                            :class="[
                                'group relative flex items-center gap-4 rounded-xl border px-4 py-3.5 text-left transition-all duration-200',
                                'focus:outline-none',
                                tipoPersona === op.value
                                    ? `${colorMap[op.color].card} ring-2 ${colorMap[op.color].ring} shadow-sm`
                                    : 'border-border bg-card hover:border-border/80 hover:bg-accent/40 hover:shadow-sm'
                            ]">
                            <!-- Icono -->
                            <span :class="[
                                'flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg transition-colors duration-200',
                                tipoPersona === op.value
                                    ? colorMap[op.color].icon
                                    : 'bg-muted/60 text-muted-foreground group-hover:bg-muted group-hover:text-foreground'
                            ]">
                                <component :is="op.icon" class="w-4 h-4" />
                            </span>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-foreground">{{ op.label }}</span>
                                    <span :class="[
                                        'inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide transition-colors duration-200',
                                        tipoPersona === op.value
                                            ? colorMap[op.color].badge
                                            : 'bg-muted text-muted-foreground'
                                    ]">{{ op.ext }}</span>
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ op.descripcion }}</p>
                            </div>

                            <!-- Check -->
                            <Transition enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="opacity-0 scale-75" enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
                                <CheckCircle2 v-if="tipoPersona === op.value"
                                    :class="['w-4 h-4 flex-shrink-0 transition-colors', `text-${op.color}-500 dark:text-${op.color}-400`]" />
                            </Transition>
                        </button>
                    </div>

                    <!-- Error inline -->
                    <Transition enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0">
                        <p v-if="mensajeError"
                            class="flex items-center gap-1.5 text-xs text-destructive pl-0.5 mt-1">
                            <span class="w-1 h-1 rounded-full bg-destructive inline-block" />
                            {{ mensajeError }}
                        </p>
                    </Transition>
                </div>

                <!-- Preview del archivo seleccionado -->
                <Transition enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-1">
                    <div v-if="opcionSeleccionada"
                        class="rounded-xl border border-border/60 bg-muted/30 px-4 py-3 flex items-center gap-3">
                        <div
                            class="flex-shrink-0 w-8 h-8 rounded-lg bg-muted flex items-center justify-center text-muted-foreground">
                            <FileText class="w-3.5 h-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-foreground">
                                layout_{{ opcionSeleccionada.label.toLowerCase().replace(' / ', '_') }}{{ opcionSeleccionada.ext }}
                            </p>
                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ opcionSeleccionada.descripcion }}</p>
                        </div>
                        <span
                            :class="['text-[10px] font-semibold uppercase px-2 py-1 rounded-md', colorMap[opcionSeleccionada.color].badge]">
                            {{ opcionSeleccionada.ext }}
                        </span>
                    </div>
                </Transition>

                <!-- Botón de exportar -->
                <div class="flex items-center gap-3">
                    <button type="button" :disabled="exportando || !tipoPersona" @click="exportar" :class="[
                        'relative inline-flex items-center gap-2.5 rounded-xl px-6 py-3 text-sm font-semibold text-white',
                        'transition-all duration-200 ease-out',
                        'focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400/70 focus-visible:ring-offset-2',
                        'disabled:pointer-events-none disabled:opacity-40',
                        exportado
                            ? 'bg-gradient-to-r from-emerald-500 to-teal-500 shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/40'
                            : 'bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-500 shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 hover:brightness-110 active:scale-[0.97] active:shadow-md'
                    ]">
                        <!-- Brillo interno -->
                        <span v-if="!exportado"
                            class="pointer-events-none absolute inset-0 rounded-xl bg-gradient-to-b from-white/15 to-transparent" />

                        <Transition mode="out-in" enter-active-class="transition-all duration-150 ease-out"
                            enter-from-class="opacity-0 scale-75" enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition-all duration-100 ease-in"
                            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
                            <CheckCircle2 v-if="exportado" class="w-4 h-4 drop-shadow-sm" key="check" />
                            <Download v-else-if="!exportando" class="w-4 h-4 drop-shadow-sm" key="down" />
                            <svg v-else class="w-4 h-4 animate-spin" key="spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                        </Transition>
                        <span class="relative tracking-wide">
                            {{ exportado ? 'Descarga iniciada' : exportando ? 'Generando…' : 'Exportar archivo' }}
                        </span>
                    </button>
                </div>

            </div>
        </FadeIn>
    </AppLayout>
</template>
