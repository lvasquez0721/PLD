<script setup lang="ts">
import { ref } from 'vue'
import ModalForm from '@/components/ui/modals/modalForm.vue'
import Titulo from '@/components/ui/Titulo.vue'
import Input from '@/components/ui/input/Input.vue'
import Label from '@/components/ui/label/Label.vue'
import { usePage, router } from '@inertiajs/vue3'

interface Props {
  modelValue: boolean
}

interface Role {
  id: number
  name: string
  guard_name: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'close'): void,
  (e: 'update:modelValue', payload: boolean): void,
  (e: 'created'): void
}>()

const page = usePage()
const roles = page.props.roles as Role[]

const form = ref({
  nombre: '',
  apellido_p: '',
  apellido_m: '',
  email: '',
  rol_id: '',
})

const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const successMessage = ref('')

function resetForm() {
  form.value = {
    nombre: '',
    apellido_p: '',
    apellido_m: '',
    email: '',
    rol_id: '',
  }
  errors.value = {}
  successMessage.value = ''
}

// Simple validación mínima para UX rápido
function validate() {
  errors.value = {}
  let valid = true
  if (!form.value.nombre) {
    errors.value.nombre = ['El nombre es obligatorio.']
    valid = false
  }
  if (!form.value.apellido_p) {
    errors.value.apellido_p = ['El apellido paterno es obligatorio.']
    valid = false
  }
  // apellido_m puede ser opcional
  if (!form.value.email) {
    errors.value.email = ['El correo electrónico es obligatorio.']
    valid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    errors.value.email = ['Introduce un correo electrónico válido.']
    valid = false
  }
  if (!form.value.rol_id) {
    errors.value.rol_id = ['Debe seleccionar un rol.']
    valid = false
  }
  return valid
}

async function submit() {
  if (!validate()) return
  loading.value = true
  errors.value = {}
  successMessage.value = ''

  try {
    router.post('/usuarios', form.value, {
      onSuccess: () => {
        // El toast se mostrará automáticamente desde la página Index
        emit('created')
        resetForm()
        emit('update:modelValue', false)
        // Refrescar usando Inertia para mantener el toast
        router.reload({ only: ['users'] })
      },
      onError: (errorErrors) => {
        // Convertir errores de Inertia a formato esperado
        const formattedErrors: Record<string, string[]> = {}
        Object.keys(errorErrors).forEach(key => {
          if (Array.isArray(errorErrors[key])) {
            formattedErrors[key] = errorErrors[key] as unknown as string[]
          } else {
            formattedErrors[key] = [String(errorErrors[key])]
          }
        })
        errors.value = formattedErrors
      },
      onFinish: () => {
        loading.value = false
      }
    })
  } catch (e: any) {
    errors.value = { general: [e?.message || 'Error de red'] }
    loading.value = false
  }
}
</script>

<template>
  <ModalForm :title="'Crear usuario'" :model-value="props.modelValue" @close="emit('close')"
    @update:model-value="emit('update:modelValue', $event)" width-class="max-w-lg">
    <div>
      <form @submit.prevent="submit" class="space-y-5">
        <!-- Nombre -->
        <div>
          <Label for="nombre">Nombre <span class="text-red-500">*</span></Label>
          <input id="nombre" v-model="form.nombre" :aria-invalid="!!errors.nombre" autofocus autocomplete="off"
            placeholder="Introduce el nombre" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
          <div v-if="errors.nombre" class="text-xs text-red-500 mt-1">
            {{ errors.nombre[0] }}
          </div>
        </div>
        <!-- Apellido paterno -->
        <div>
          <Label for="apellido_p">Apellido paterno <span class="text-red-500">*</span></Label>
          <input id="apellido_p" v-model="form.apellido_p" :aria-invalid="!!errors.apellido_p" autocomplete="off"
            placeholder="Apellido paterno" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
          <div v-if="errors.apellido_p" class="text-xs text-red-500 mt-1">
            {{ errors.apellido_p[0] }}
          </div>
        </div>
        <!-- Apellido materno (opcional) -->
        <div>
          <Label for="apellido_m">Apellido materno</Label>
          <input id="apellido_m" v-model="form.apellido_m" autocomplete="off" placeholder="Apellido materno"
            :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
        </div>
        <!-- Correo electrónico -->
        <div>
          <Label for="email">Correo electrónico <span class="text-red-500">*</span></Label>
          <input id="email" v-model="form.email" :aria-invalid="!!errors.email" autocomplete="off" type="email"
            placeholder="usuario@ejemplo.com" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
          <div v-if="errors.email" class="text-xs text-red-500 mt-1">
            {{ errors.email[0] }}
          </div>
        </div>
        <!-- Rol -->
        <div>
          <Label for="rol_id">Rol <span class="text-red-500">*</span></Label>
          <select id="rol_id" v-model="form.rol_id" :aria-invalid="!!errors.rol_id" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400">
            <option value="">Selecciona un rol</option>
            <option v-for="role in roles" :key="role.id" :value="role.id">
              {{ role.name }}
            </option>
          </select>
          <div v-if="errors.rol_id" class="text-xs text-red-500 mt-1">
            {{ errors.rol_id[0] }}
          </div>
        </div>
        <!-- General error -->
        <div v-if="errors.general" class="text-xs text-red-600 mt-2">
          {{ errors.general[0] }}
        </div>
        <!-- Success message -->
        <div v-if="successMessage" class="text-sm text-green-600 text-center my-1">
          {{ successMessage }}
        </div>
        <!-- Acciones -->
        <div class="flex items-center justify-end gap-2 mt-2">
          <button
            class="px-4 py-2 rounded-md text-gray-600 hover:bg-gray-100 dark:hover:bg-neutral-800 transition disabled:opacity-60"
            type="button" @click="() => { emit('update:modelValue', false); resetForm(); }" :disabled="loading">
            Cancelar
          </button>
          <button
            class="px-6 py-2 rounded-md bg-blue-600 text-white font-semibold hover:bg-blue-700 transition focus-visible:ring-2 focus-visible:ring-blue-400 disabled:opacity-60"
            type="submit" :disabled="loading">
            <span v-if="loading">
              <svg class="inline animate-spin mr-2 h-5 w-5 text-white" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              Guardando...
            </span>
            <span v-else>Guardar usuario</span>
          </button>
        </div>
      </form>
    </div>
  </ModalForm>
</template>
