<script setup lang="ts">
import { ref, Transition, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { Bell, Search, Calendar, Download } from 'lucide-vue-next';
import axios from 'axios';

// Detect theme for custom date input styling
const isDark = ref(false);
onMounted(() => {
  // Function to check and set theme
  const checkTheme = () => {
    isDark.value = document.documentElement.classList.contains('dark');
  };
  checkTheme();
  // Listen to class mutation
  const observer = new MutationObserver(checkTheme);
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
const fechaInicio = ref('');
const fechaFin = ref('');
const alertas = ref([]);
const isLoading = ref(false);
const focusedInput = ref<string | null>(null);

const buscarAlertas = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get('/alertas/date-range', {
      params: {
        fechaInicio: fechaInicio.value,
        fechaFin: fechaFin.value,
      },
    });
    alertas.value = response.data;
  } catch (error) {
    console.error('Error al obtener alertas:', error);
  } finally {
    isLoading.value = false;
  }
};

const downloadCsv = async () => {
  try {
    const response = await axios.get('/alertas/download-csv', {
      params: {
        fechaInicio: fechaInicio.value,
        fechaFin: fechaFin.value,
      },
      responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `alertas_${fechaInicio.value}_${fechaFin.value}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error('Error al descargar el CSV:', error);
  }
};
</script>

<template>
  <AppLayout title="Módulo de Alertas">
    <div class="min-h-screen bg-gradient-to-br from-slate-50/40 via-slate-50/60 to-blue-50/25 dark:bg-gradient-to-br dark:from-black/90 dark:via-neutral-900/95 dark:to-neutral-900/90">
      <!-- Header con glassmorphism ultra sutil -->
      <div
        class="bg-[#f8fafc]/60 dark:bg-neutral-900/90 backdrop-blur-xl border-b border-slate-100/40 dark:border-neutral-800/70 shadow-[0_1px_4px_rgba(0,0,0,0.015)] mb-10 transition-all duration-700">
        <div class="max-w-[1800px] mx-auto px-8 py-7">
          <div class="flex items-center gap-4">
            <div class="relative">
              <div
                class="absolute inset-0 bg-blue-400/6 blur-2xl rounded-full scale-140 opacity-50 transition-all duration-700">
              </div>
              <div
                class="relative bg-gradient-to-br from-blue-400/80 to-blue-500/80 dark:from-neutral-800 dark:to-black p-3 rounded-[14px] shadow-[0_3px_12px_rgba(59,130,246,0.09)] dark:shadow-[0_3px_12px_rgba(0,0,0,0.30)] transition-all duration-700 hover:shadow-[0_5px_18px_rgba(59,130,246,0.13)] hover:scale-103">
                <Bell :size="22" :stroke-width="1.75"
                  class="text-white/95 transition-colors duration-500 hover:text-white" />
              </div>
            </div>
            <div>
              <h1
                class="text-[26px] font-semibold tracking-[-0.03em] text-slate-800/90 dark:text-slate-100/90 leading-tight transition-colors duration-500">
                Módulo de Alertas
              </h1>
              <p
                class="text-[13px] text-slate-500/75 dark:text-neutral-400/85 mt-1 tracking-[0.005em] leading-relaxed font-light transition-colors duration-500">
                Gestión y análisis de alertas operacionales
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Contenedor principal -->
      <div class="max-w-[1800px] mx-auto px-8">
        <!-- Panel de búsqueda refinado -->
        <div
          class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 p-8 mb-10 transition-all duration-700 hover:shadow-[0_7px_28px_rgba(15,23,42,0.05)] hover:bg-[#f8fafc]/80 dark:hover:bg-black">
          <div class="flex items-end gap-6">
            <!-- Fecha inicio -->
            <div class="flex-1 relative group">
              <label for="fechaInicio"
                class="block text-[11px] font-medium text-slate-600/85 dark:text-neutral-300/85 mb-3 tracking-[0.05em] uppercase flex items-center gap-2 transition-colors duration-500">
                <Calendar :size="13" class="opacity-55 transition-opacity duration-500 dark:text-neutral-300/80" />
                <span class="leading-none">Fecha inicio</span>
              </label>
              <div class="relative">
                <input
                  type="date"
                  id="fechaInicio"
                  v-model="fechaInicio"
                  @focus="focusedInput = 'inicio'"
                  @blur="focusedInput = null"
                  :class="[
                    'w-full px-4 py-3.5 border rounded-[14px] text-[14px] font-light tracking-[0.003em] transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1)',
                    isDark
                      ? (focusedInput === 'inicio'
                        ? 'custom-dark-date-focused'
                        : 'custom-dark-date')
                      : (focusedInput === 'inicio'
                        ? 'custom-light-date-focused'
                        : 'custom-light-date')
                  ]"
                />
              </div>
            </div>

            <!-- Fecha fin -->
            <div class="flex-1 relative group">
              <label for="fechaFin"
                class="block text-[11px] font-medium text-slate-600/85 dark:text-neutral-300/85 mb-3 tracking-[0.05em] uppercase flex items-center gap-2 transition-colors duration-500">
                <Calendar :size="13" class="opacity-55 transition-opacity duration-500 dark:text-neutral-300/80" />
                <span class="leading-none">Fecha fin</span>
              </label>
              <div class="relative">
                <input
                  type="date"
                  id="fechaFin"
                  v-model="fechaFin"
                  @focus="focusedInput = 'fin'"
                  @blur="focusedInput = null"
                  :class="[
                    'w-full px-4 py-3.5 border rounded-[14px] text-[14px] font-light tracking-[0.003em] transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1)',
                    isDark
                      ? (focusedInput === 'fin'
                        ? 'custom-dark-date-focused'
                        : 'custom-dark-date')
                      : (focusedInput === 'fin'
                        ? 'custom-light-date-focused'
                        : 'custom-light-date')
                  ]"
                />
              </div>
            </div>

            <!-- Botón de búsqueda -->
            <button @click="buscarAlertas" :disabled="isLoading"
              class="px-7 py-3.5 bg-gradient-to-br from-blue-400/90 to-blue-500/90 text-white/95 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
                shadow-[0_3px_12px_rgba(59,130,246,0.13)] hover:shadow-[0_5px_18px_rgba(59,130,246,0.18)]
                hover:from-blue-500/90 hover:to-blue-600/90
                disabled:from-slate-300/80 disabled:to-slate-400/80 dark:disabled:from-neutral-800/80 dark:disabled:to-neutral-800/70 disabled:shadow-none disabled:cursor-not-allowed
                transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-[0.5px] active:translate-y-0 active:scale-100
                focus:outline-none focus:ring-2 focus:ring-blue-400/25 dark:focus:ring-neutral-600/50 focus:ring-offset-2 focus:ring-offset-[#f8fafc]/50 dark:focus:ring-offset-black/30">
              <div class="flex items-center gap-2.5">
                <template v-if="isLoading">
                  <div class="w-4 h-4 border-[2px] border-white/25 border-t-white/95 rounded-full animate-spin"></div>
                  <span>Buscando...</span>
                </template>
                <template v-else>
                  <Search :size="15" :stroke-width="2" transition-all duration-500 />
                  <span>Buscar</span>
                </template>
              </div>
            </button>

            <!-- Botón exportar -->
            <button v-if="alertas.length > 0" @click="downloadCsv"
              class="px-6 py-3.5 bg-[#f8fafc]/90 dark:bg-neutral-900/90 border border-slate-100/60 dark:border-neutral-800/70 text-slate-700/85 dark:text-neutral-200/90 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
                shadow-[0_2px_7px_rgba(15,23,42,0.025)] dark:shadow-[0_2px_7px_rgba(0,0,0,0.20)] hover:shadow-[0_3px_12px_rgba(15,23,42,0.05)] hover:border-slate-200/60 dark:hover:border-neutral-600/60 hover:bg-[#f8fafc] dark:hover:bg-black
                transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-[0.5px]
                focus:outline-none focus:ring-2 focus:ring-slate-200/35 dark:focus:ring-neutral-700/50 focus:ring-offset-2 focus:ring-offset-[#f8fafc]/50 dark:focus:ring-offset-black/30">
              <Download :size="15" :stroke-width="2" transition-all duration-500 />
            </button>
          </div>
        </div>

        <!-- Tabla con diseño ultra refinado -->
        <div v-if="isLoading || alertas.length > 0"
          class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr
                  class="bg-gradient-to-r from-slate-50/50 via-slate-50/40 to-slate-50/50 dark:from-neutral-800/80 dark:via-black dark:to-neutral-900/95 border-b border-slate-100/50 dark:border-neutral-800/70">
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap transition-colors duration-500">
                    ID Registro</th>
                  <!-- Resto de th elementos con el mismo ajuste de text-slate-600/85 y tracking-[0.07em] -->
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Folio</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Patrón</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    ID Cliente</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Cliente</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Póliza</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Fecha Detección</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Hora Detección</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Fecha Operación</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Hora Operación</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Monto</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Instrumento</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    RFC Agente</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Agente</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Estatus</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Descripción</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Razones</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    Evidencias</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    ID Reporte</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    ID Pago</th>
                  <th
                    class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                    ID Operación</th>
                </tr>
              </thead>
              <Transition name="fade" mode="out-in">
                <tbody v-if="isLoading && alertas.length === 0" class="divide-y divide-slate-100/60 dark:divide-neutral-800/70">
                  <!-- Loading state -->
                  <tr>
                    <td colspan="21" class="px-6 py-20 text-center">
                      <div class="flex flex-col items-center gap-5">
                        <div
                          class="w-11 h-11 border-[2.5px] border-blue-200/40 dark:border-neutral-800 border-t-blue-400/80 dark:border-t-neutral-300 rounded-full animate-spin">
                        </div>
                        <p
                          class="text-slate-500/75 dark:text-neutral-400/85 text-[14px] font-light tracking-[0.008em] transition-colors duration-500">
                          Cargando alertas...</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else-if="alertas.length > 0" class="divide-y divide-slate-100/60 dark:divide-neutral-800/70">
                  <!-- Data rows -->
                  <tr v-for="alerta in alertas" :key="alerta.IDRegistroAlerta"
                    class="hover:bg-blue-50/15 dark:hover:bg-neutral-800/70 transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1) group transform hover:scale-[1.002]">
                    <td
                      class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-100/90 font-medium tracking-[0.003em] transition-colors duration-500">
                      {{ alerta.IDRegistroAlerta }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                      {{ alerta.Folio }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                      {{ alerta.Patron }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                      {{ alerta.IDCliente }}</td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-100/90 font-medium tracking-[0.003em]">
                      {{ alerta.Cliente }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                      {{ alerta.Poliza }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                      {{ alerta.FechaDeteccion }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-light tracking-[0.003em]">
                      {{ alerta.HoraDeteccion }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                      {{ alerta.FechaOperacion }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-light tracking-[0.003em]">
                      {{ alerta.HoraOperacion }}</td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-200/90 font-semibold tracking-[0.003em]">
                      {{ alerta.MontoOperacion }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                      {{ alerta.InstrumentoMonetario }}</td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-mono font-light tracking-[0.003em]">
                      {{ alerta.RFCAgente }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-100/90 font-light tracking-[0.003em]">
                      {{ alerta.Agente }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span
                        class="px-3 py-1.5 text-[11.5px] font-medium tracking-[0.02em] rounded-full bg-amber-50/70 dark:bg-neutral-800/70 text-amber-700/90 dark:text-amber-100/90 border border-amber-200/40 dark:border-neutral-600/20 shadow-[0_1px_3px_rgba(217,119,6,0.04)] dark:shadow-[0_1px_3px_rgba(0,0,0,0.12)]">
                        {{ alerta.Estatus }}
                      </span>
                    </td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 max-w-xs truncate font-light tracking-[0.003em]">
                      {{ alerta.Descripcion }}</td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 max-w-xs truncate font-light tracking-[0.003em]">
                      {{ alerta.Razones }}</td>
                    <td
                      class="px-6 py-4 whitespace-nowrap text-[13px] text-blue-600/85 dark:text-blue-300/75 hover:text-blue-700/90 dark:hover:text-blue-200/90 cursor-pointer font-medium tracking-[0.003em] transition-colors duration-300">
                      {{ alerta.Evidencias }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-light tracking-[0.003em]">
                      {{ alerta.IDReporteOP }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-light tracking-[0.003em]">
                      {{ alerta.IDPago }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-light tracking-[0.003em]">
                      {{ alerta.IDOperacionPago }}</td>
                  </tr>
                </tbody>
              </Transition>
            </table>
          </div>
        </div>

        <!-- Empty state (sin tabla) -->
        <Transition name="fade" mode="out-in">
          <div v-if="!isLoading && alertas.length === 0"
            class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 p-20 text-center">
            <div class="flex flex-col items-center gap-4">
              <div
                class="w-16 h-16 bg-slate-100/50 dark:bg-neutral-800/95 rounded-full flex items-center justify-center shadow-[0_2px_7px_rgba(15,23,42,0.025)] dark:shadow-[0_2px_7px_rgba(0,0,0,0.17)] transition-all duration-500 hover:bg-slate-100/60 dark:hover:bg-neutral-700/80">
                <Bell :size="30" :stroke-width="1.5" class="text-slate-400/65 dark:text-neutral-200/65 transition-colors duration-500" />
              </div>
              <p class="text-slate-500/75 dark:text-neutral-400/85 text-[14px] font-medium tracking-[0.008em] transition-colors duration-500">No
                hay
                alertas para mostrar</p>
              <p class="text-slate-400/65 dark:text-neutral-500/80 text-[13px] font-light tracking-[0.008em] transition-colors duration-500">
                Selecciona
                un rango de fechas y
                presiona buscar</p>
            </div>
          </div>
        </Transition>

        <!-- Footer refinado -->
        <div v-if="alertas.length > 0"
          class="mt-8 flex justify-between items-center text-[13px] text-slate-500/70 dark:text-neutral-400/70 px-2">
          <p class="font-light tracking-[0.008em] transition-colors duration-500">
            Mostrando <span class="font-medium text-slate-600/80 dark:text-neutral-200/90">{{ alertas.length }}</span>
            {{ alertas.length === 1 ? 'alerta' : 'alertas' }}
          </p>
          <p class="text-[12px] text-slate-400/65 dark:text-neutral-500/70 font-light tracking-[0.008em] transition-colors duration-500">
            Última actualización: {{ new Date().toLocaleTimeString('es-MX') }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Transiciones suaves y naturales para todos los elementos interactivos */
* {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}

/* Suavizar animación del spinner */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

/* Mejorar el foco en inputs para accesibilidad con teclado */
input[type="date"]:focus-visible {
  outline: 2px solid rgb(59 130 246 / 0.3);
  outline-offset: 2px;
}

/* Custom Date Styles for light and dark mode (ensures native pickers match theme) */
.custom-light-date {
  background-color: rgba(255,255,255,0.95) !important;
  color: #1e293b !important;
  border: 1px solid rgba(226,232,240,0.7); /* slate-100/70 */
}
.custom-light-date:hover {
  border-color: rgba(226,232,240,0.85);
  background-color: #fff;
}
.custom-light-date-focused {
  background-color: #fff !important;
  color: #0f172a !important;
  border: 1px solid rgba(147,197,253,0.5); /* blue-300/50 */
  box-shadow: 0 3px 12px rgba(59,130,246,0.07);
  transform: scale(1.005);
}

.custom-dark-date {
  background-color: rgba(31,31,31,0.60) !important;
  color: #fafbfc !important;
  border: 1px solid rgba(38,38,38,0.65); /* neutral-700/60 approx */
}
.custom-dark-date:hover {
  border-color: rgba(163,163,163,0.32); /* neutral-500/40 */
  background-color: rgba(31,31,31,0.85);
}
.custom-dark-date-focused {
  background-color: rgba(23,23,23,0.95) !important;
  color: #fafbfc !important;
  border: 1px solid rgba(147,197,253,0.5);
  box-shadow: 0 3px 12px rgba(0,0,0,0.15);
  transform: scale(1.005);
}

/* Hide date spinner controls for more seamless look (optional) */
input[type="date"]::-webkit-inner-spin-button,
input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(0);
}
.custom-dark-date::-webkit-calendar-picker-indicator,
.custom-dark-date-focused::-webkit-calendar-picker-indicator {
  filter: invert(1);
}
/* support firefox */
.custom-dark-date::-moz-calendar-picker-indicator,
.custom-dark-date-focused::-moz-calendar-picker-indicator {
  filter: invert(1);
}

/* Transición suave para hover en filas */
tbody tr {
  will-change: background-color;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.7s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
