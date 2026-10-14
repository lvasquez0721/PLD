<!-- Consulta Individual de Perfil Transaccional -->

<script setup lang="ts">

import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import { Search, Loader2, User } from 'lucide-vue-next'

const loading = ref(false)
const cargandoClientes = ref(false)
const search = ref('')
const listaClientes = ref<any[]>([]) // Lista completa cargada una sola vez
const clientes = ref<any[]>([]) // Resultados filtrados
const clienteSeleccionado = ref<any>(null)
const perfil = ref<any>(null)
const mostrarLista = ref(false)


const alerta = ref<{tipo: 'success' | 'error'
    mensaje: string
} | null>(null)

let alertaTimeout: ReturnType<typeof setTimeout> | null = null

const mostrarAlerta = ( tipo: 'success' | 'error', mensaje: string) => {
    alerta.value = { tipo, mensaje }
    if (alertaTimeout) {
        clearTimeout(alertaTimeout)
    }
    alertaTimeout = setTimeout(() => {
        alerta.value = null
    }, 5000)
}
// Normalizar búsqueda: minúsculas, sin acentos y espacios uniformes
const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase()
        .replace(/\s+/g, ' ')
        .trim()


const obtenerNombreCliente = (cliente: any): string => {
    const nombreCompleto = [
        cliente.Nombre,
        cliente.ApellidoPaterno,
        cliente.ApellidoMaterno
    ]
        .filter(valor => valor != null && String(valor).trim() !== '')
        .map(valor => String(valor).trim())
        .join(' ')

    if (nombreCompleto) { return nombreCompleto }
    const razonSocial = String(cliente.RazonSocial ?? '').trim()
    if (razonSocial) { return razonSocial }

    return 'Sin dato'
}

// ------------------------------------
// Tipo de persona
// ------------------------------------
const obtenerTipoPersona = (cliente: any): string => {
    const tipo = cliente?.IDTipoPersona

    if (tipo === null || tipo === undefined || String(tipo).trim() === '') {return ''}
    if (Number(tipo) === 1) return 'Física'
    if (Number(tipo) === 2) return 'Moral'

    return ''
}

// ------------------------------------
// Cargar todos los clientes una sola vez
// ------------------------------------
const cargarClientes = async () => {
    try {
        const { data } = await axios.get('/perfil-transaccional-clientes-list')
        listaClientes.value = data.success ? data.clientes || [] : []
    } catch (error) {
        listaClientes.value = []
        console.error('Error al cargar los clientes', error)
    } finally {
        cargandoClientes.value = false
    }
}

onMounted(() => {cargarClientes()})

// ------------------------------------
// Buscar clientes en memoria
// ------------------------------------
watch(search, (value) => {
    // Evitar abrir la lista después de seleccionar un cliente
    if (clienteSeleccionado.value && value ===  obtenerNombreCliente(clienteSeleccionado.value)) {
        mostrarLista.value = false
        return
    }

    // Si modifica el texto, debe seleccionar nuevamente
    clienteSeleccionado.value = null

    const busqueda = normalizar(value)
    const esNumero = /^\d+$/.test(busqueda)
    const minimoCaracteres = esNumero ? 1 : 3

    if (busqueda.length < minimoCaracteres) {
        clientes.value = []
        mostrarLista.value = false
        return
    }

    clientes.value = listaClientes.value.filter((cliente) => {
            // Construir el nombre completo
            const nombreMostrar = normalizar( obtenerNombreCliente(cliente))

            const rfc = normalizar(cliente.RFC || '')
            const id = normalizar(String(cliente.IDCliente ?? ''))

            return (
                nombreMostrar.includes(busqueda) ||
                rfc.includes(busqueda) ||
                id.includes(busqueda)
            )
        })
        .slice(0, 20)

    mostrarLista.value = true
})

// ------------------------------------
// Seleccionar cliente
// ------------------------------------
const seleccionarCliente = (cliente: any) => {
    clienteSeleccionado.value = cliente
    search.value = obtenerNombreCliente(cliente)
    mostrarLista.value = false
    clientes.value = []
}

// ------------------------------------
// Nivel de riesgo
// ------------------------------------
const riesgoInfo = computed(() => {

    const valorPerfil = perfil.value?.Perfil
    const nivel = Number(valorPerfil)

    // Sin información
    if (valorPerfil === null || valorPerfil === undefined || valorPerfil === '' || !Number.isFinite(nivel)) {
        return {
            texto: 'SIN RIESGO',
            numero: 0,
            clases: 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-zinc-300'
        }
    }

    // MENOR A 2 = BAJO
    if (nivel < 2) {
        return {
            texto: 'BAJO',
            numero: 1,
            clases: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border border-green-300 dark:border-green-800'
        }
    }

    // MAYOR O IGUAL A 2 = ALTO
    return {
        texto: 'ALTO',
        numero: 2,
        clases: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border border-red-300 dark:border-red-800'
    }
})

// ------------------------------------
// Consultar perfil
// ------------------------------------
const consultarPerfil = async () => {
    if (!clienteSeleccionado.value) {
         mostrarAlerta(
            'error',
            'Seleccione un cliente antes de continuar.'
        )
        return
    }

    // Limpiar únicamente cuando se realiza una nueva consulta
    alerta.value = null
    loading.value = true
    perfil.value = null

    try {
        const { data } = await axios.post( '/perfil-transaccional-cliente-buscar', { IDCliente: clienteSeleccionado.value.IDCliente } )
        perfil.value = data.perfil
    } catch (error: any) {
        alert( error.response?.data?.mensaje || 'Error al consultar el perfil transaccional.' )
    } finally {
        loading.value = false
    }
}

</script>

<template>
    <AppLayout>
        <FadeIn>

            <div class="p-6 transition-colors duration-200">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Consulta Perfil Transaccional Cliente
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">
                        Consulta individual de perfil transaccional por cliente
                    </p>
                </div>

                <!-- Mensaje de alerta -->
                <transition name="fade-slide">
                    <div
                        v-if="alerta" class="mb-6 rounded-xl border px-4 py-3.5 shadow-lg"
                        :class="alerta.tipo === 'error'
                            ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-100'
                            : 'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950/40 dark:text-green-100'"
                        role="alert"
                    >
                        <p class="font-semibold">{{ alerta.tipo === 'error' ? 'Error' : 'Éxito' }}</p>
                        <p class="mt-1 text-sm leading-relaxed">{{ alerta.mensaje }}</p>
                    </div>
                </transition>

                <!-- Busqueda -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow p-6 transition-colors duration-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <!-- Input -->
                        <div class="relative md:col-span-2">
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-zinc-200">
                                Cliente
                            </label>
                            <input v-model="search" type="text" placeholder="Buscar por nombre, razón social, RFC o ID..." @focus="mostrarLista = search.trim().length >= 3 && !clienteSeleccionado" @keydown.esc="mostrarLista = false" class="w-full border border-gray-300 dark:border-zinc-700 rounded-xl px-4 py-3 bg-white dark:bg-zinc-800 text-black dark:text-white placeholder:text-gray-400 dark:placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200" />
                            
                             <!-- Cargando catálogo -->
                            <div v-if="cargandoClientes" class="mt-2 text-sm text-gray-500 dark:text-zinc-400 flex items-center gap-2">
                                <Loader2 class="h-4 w-4 animate-spin" /> Cargando clientes...
                            </div>

                            <!-- Lista -->
                            <div v-if="mostrarLista && clientes.length"
                                class="absolute z-50 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-lg mt-1 w-full max-h-72 overflow-auto transition-colors duration-200">
                                <div v-for="cliente in clientes" :key="cliente.IDCliente"
                                    @click="seleccionarCliente(cliente)"
                                    class="px-4 py-3 hover:bg-gray-100 dark:hover:bg-zinc-800 cursor-pointer border-b border-gray-200 dark:border-zinc-700 transition-colors duration-150">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ obtenerNombreCliente(cliente) }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-zinc-400">
                                        ID Cliente: {{ cliente.IDCliente }}
                                        <span v-if="String(cliente.RFC ?? '').trim() !== ''">
                                            | RFC: {{ cliente.RFC }}
                                        </span>

                                        <span v-if="obtenerTipoPersona(cliente) !== ''">
                                            | Persona: {{ obtenerTipoPersona(cliente) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sin coincidencias -->
                            <div v-if="mostrarLista && search.trim().length >= 3 && !clientes.length && !cargandoClientes"
                                class="absolute z-50 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-lg mt-1 w-full p-4 text-sm text-gray-500 dark:text-zinc-400">
                                No se encontraron clientes.
                            </div>
                            <p v-if="!cargandoClientes && search.trim().length < 3 && !clienteSeleccionado" class="text-xs text-gray-500 dark:text-zinc-400 mt-2" >
                                Escribe al menos 3 caracteres para buscar por nombre.
                            </p>
                        </div>

                        <!-- Botón -->
                        <button @click="consultarPerfil" :disabled="loading || cargandoClientes" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-4 py-3 mt-[30px] self-start flex items-center justify-center gap-2 transition-all duration-200 disabled:opacity-60">
                            <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                            <Search v-else class="h-4 w-4" />
                            {{ loading ? 'Consultando...' : 'Consultar' }}
                        </button>
                    </div>
                </div>

                <!-- Resultado -->
                <div v-if="perfil" class="mt-6 bg-white dark:bg-zinc-900 rounded-2xl shadow p-6 transition-colors duration-200">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="bg-blue-100 dark:bg-blue-900/30 p-3 rounded-full">
                            <User class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{
                                    [
                                        perfil.Nombre,
                                        perfil.ApellidoPaterno,
                                        perfil.ApellidoMaterno
                                    ]
                                        .filter(valor => valor && String(valor).trim() !== '')
                                        .join(' ')
                                    || perfil.RazonSocial
                                    || obtenerNombreCliente(clienteSeleccionado || {})
                                }}
                            </h2>
                            <p class="text-gray-500 dark:text-zinc-400 text-sm">
                                Cliente: {{ perfil.IDCliente }}
                                <span v-if="perfil.RFC || clienteSeleccionado?.RFC">
                                    | RFC: {{ perfil.RFC || clienteSeleccionado?.RFC }}
                                </span>
                                <span v-if="perfil.IDTipoPersona || clienteSeleccionado?.IDTipoPersona">
                                    | Persona: {{ obtenerTipoPersona(perfil) || obtenerTipoPersona(clienteSeleccionado) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <!-- Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Perfil -->
                        <div
                            class="border border-gray-200 dark:border-zinc-700 rounded-xl p-4 bg-white dark:bg-zinc-900 transition-colors duration-200">
                            <p class="text-sm text-gray-500 dark:text-zinc-400"> Perfil </p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"> {{ perfil.Perfil ?? 'Sin dato' }} </p>
                        </div>

                        <!-- Nivel Riesgo -->
                        <div class="rounded-xl p-4 transition-all duration-200" :class="riesgoInfo.clases">
                            <p class="text-sm opacity-80"> Nivel Riesgo </p>
                            <p class="text-2xl font-bold"> {{ riesgoInfo.texto }} ({{ riesgoInfo.numero }}) </p>
                        </div>

                        <!-- Fecha -->
                        <div
                            class="border border-gray-200 dark:border-zinc-700 rounded-xl p-4 bg-white dark:bg-zinc-900 transition-colors duration-200">
                            <p class="text-sm text-gray-500 dark:text-zinc-400"> Fecha Ejecución </p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white"> {{ perfil.FechaEjecucción ?? 'Sin dato' }} </p>
                        </div>

                    </div>
                </div>

            </div>

        </FadeIn>
    </AppLayout>
</template>