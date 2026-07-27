<!-- Consulta Individual de Perfil Transaccional -->

<script setup lang="ts">

import { ref, watch, computed } from 'vue'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import { Search, Loader2, User } from 'lucide-vue-next'

const loading = ref(false)
const search = ref('')
const clientes = ref<any[]>([])
const clienteSeleccionado = ref<any>(null)
const perfil = ref<any>(null)
const mostrarLista = ref(false)

// ------------------------------------
// Buscar clientes
// ------------------------------------
watch(search, async (value) => {

    if (value.length < 3) {
        clientes.value = []
        return
    }

    try {
        const { data } = await axios.get('/perfil-transaccional-clientes-search', { params: { search: value }
        })
        clientes.value = data.clientes || []
        mostrarLista.value = true
    } catch (error) {
        clientes.value = []
    }

})

// ------------------------------------
// Seleccionar cliente
// ------------------------------------
const seleccionarCliente = (cliente: any) => {
    clienteSeleccionado.value = cliente
    search.value = cliente.NombreCompleto
    mostrarLista.value = false
}

// ------------------------------------
// Nivel de riesgo
// ------------------------------------
const riesgoInfo = computed(() => {

    // SE EVALÚA EL PERFIL
    const nivel = Number(perfil.value?.Perfil)

    // Sin información
    if (isNaN(nivel)) {
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
        alert('Seleccione un cliente')
        return
    }

    loading.value = true
    perfil.value = null

    try {
        const { data } = await axios.post( '/perfil-transaccional-cliente-buscar', { IDCliente: clienteSeleccionado.value.IDCliente } )
        perfil.value = data.perfil
    } catch (error: any) {
        alert( error.response?.data?.mensaje || 'Error al consultar.' )
    }
    loading.value = false
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

                <!-- Busqueda -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow p-6 transition-colors duration-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <!-- Input -->
                        <div class="relative md:col-span-2">
                            <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-zinc-200">
                                Cliente
                            </label>
                            <input v-model="search" type="text" placeholder="Buscar cliente..."
                                class="w-full border border-gray-300 dark:border-zinc-700 rounded-xl px-4 py-3 bg-white dark:bg-zinc-800 text-black dark:text-white placeholder:text-gray-400 dark:placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200" />
                            <!-- Lista -->
                            <div v-if="mostrarLista && clientes.length"
                                class="absolute z-50 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-lg mt-1 w-full max-h-72 overflow-auto transition-colors duration-200">
                                <div v-for="cliente in clientes" :key="cliente.IDCliente"
                                    @click="seleccionarCliente(cliente)"
                                    class="px-4 py-3 hover:bg-gray-100 dark:hover:bg-zinc-800 cursor-pointer border-b border-gray-200 dark:border-zinc-700 transition-colors duration-150">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ cliente.NombreCompleto }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-zinc-400">
                                        ID Cliente:
                                        {{ cliente.IDCliente }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botón -->
                        <button @click="consultarPerfil" :disabled="loading"
                            class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-4 py-3 flex items-center justify-center gap-2 transition-all duration-200 disabled:opacity-60">
                            <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                            <Search v-else class="h-4 w-4" />
                            Consultar
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
                                {{ perfil.Nombre }}
                                {{ perfil.ApellidoPaterno }}
                                {{ perfil.ApellidoMaterno }}
                            </h2>
                            <p class="text-gray-500 dark:text-zinc-400 text-sm">
                                Cliente:
                                {{ perfil.IDCliente }}
                            </p>
                        </div>
                    </div>
                    <!-- Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Perfil -->
                        <div
                            class="border border-gray-200 dark:border-zinc-700 rounded-xl p-4 bg-white dark:bg-zinc-900 transition-colors duration-200">
                            <p class="text-sm text-gray-500 dark:text-zinc-400"> Perfil </p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"> {{ perfil.Perfil }} </p>
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
                            <p class="text-lg font-semibold text-gray-900 dark:text-white"> {{ perfil.FechaEjecucción }} </p>
                        </div>

                    </div>
                </div>

            </div>

        </FadeIn>
    </AppLayout>
</template>