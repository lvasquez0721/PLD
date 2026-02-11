<script setup lang="ts">
import { ref, watch } from 'vue'
import ModalForm from '@/components/ui/modals/modalForm.vue'
import Label from '@/components/ui/label/Label.vue'
import { usePage, router } from '@inertiajs/vue3'

interface Props {
  modelValue: boolean
  user: {
    id: number
    nombre: string
    apellido_p: string
    apellido_m: string | null
    email: string
    roles?: Array<{ id: number; name: string }>
  } | null
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
  (e: 'updated'): void
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

// Cargar datos del usuario cuando se abre el modal
watch(
  () => props.user,
  (user) => {
    if (user) {
      form.value = {
        nombre: user.nombre,
        apellido_p: user.apellido_p,
        apellido_m: user.apellido_m || '',
        email: user.email,
        rol_id: user.roles?.[0]?.id ? String(user.roles[0].id) : '',
      }
      errors.value = {}
      successMessage.value = ''
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

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

// Validación del formulario antes de enviar
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
  if (!validate() || !props.user) return

  loading.value = true
  errors.value = {}
  successMessage.value = ''

  // Adaptar el payload exactamente a lo que espera UsuariosController@update
  const payload = {
    nombre: form.value.nombre,
    apellido_p: form.value.apellido_p,
    apellido_m: form.value.apellido_m || null,
    email: form.value.email,
    rol_id: form.value.rol_id,
  }

  try {
    router.put(`/usuarios/${props.user.id}`, payload, {
      onSuccess: () => {
        emit('updated')
        resetForm()
        emit('update:modelValue', false)
        // Forzar recarga ya que el controlador hace un redirect/location
        // (No recargar aquí con router.reload ya que el backend ya redirige)
      },
      onError: (errorErrors) => {
        // Adaptación correcta del tipo al procesar errores
        const formattedErrors: Record<string, string[]> = {}
        Object.keys(errorErrors).forEach((key) => {
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
  <ModalForm :title="'Editar usuario'" :model-value="props.modelValue" @close="emit('close')"
    @update:model-value="emit('update:modelValue', $event)" width-class="max-w-lg">
    <div>
      <form @submit.prevent="submit" class="space-y-5">
        <!-- Nombre -->
        <div>
          <Label for="edit-nombre">Nombre <span class="text-red-500">*</span></Label>
          <input id="edit-nombre" v-model="form.nombre" :aria-invalid="!!errors.nombre" autofocus autocomplete="off"
            placeholder="Introduce el nombre" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
          <div v-if="errors.nombre" class="text-xs text-red-500 mt-1">
            {{ errors.nombre[0] }}
          </div>
        </div>

        <!-- Apellido paterno -->
        <div>
          <Label for="edit-apellido_p">Apellido paterno <span class="text-red-500">*</span></Label>
          <input id="edit-apellido_p" v-model="form.apellido_p" :aria-invalid="!!errors.apellido_p" autocomplete="off"
            placeholder="Apellido paterno" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
          <div v-if="errors.apellido_p" class="text-xs text-red-500 mt-1">
            {{ errors.apellido_p[0] }}
          </div>
        </div>

        <!-- Apellido materno (opcional) -->
        <div>
          <Label for="edit-apellido_m">Apellido materno</Label>
          <input id="edit-apellido_m" v-model="form.apellido_m" autocomplete="off" placeholder="Apellido materno"
            :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
        </div>

        <!-- Correo electrónico -->
        <div>
          <Label for="edit-email">Correo electrónico <span class="text-red-500">*</span></Label>
          <input id="edit-email" v-model="form.email" :aria-invalid="!!errors.email" autocomplete="off" type="email"
            placeholder="usuario@ejemplo.com" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400" />
          <div v-if="errors.email" class="text-xs text-red-500 mt-1">
            {{ errors.email[0] }}
          </div>
        </div>

        <!-- Rol -->
        <div>
          <Label for="edit-rol_id">Rol <span class="text-red-500">*</span></Label>
          <select id="edit-rol_id" v-model="form.rol_id" :aria-invalid="!!errors.rol_id" :disabled="loading"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60 disabled:bg-gray-100
            dark:border-gray-700 dark:bg-zinc-700 dark:text-gray-200 dark:focus:ring-blue-400 dark:focus:border-blue-400">
            <option value="">Selecciona un rol</option>
            <option v-for="role in roles" :key="role.id" :value="String(role.id)">
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
              Guardando...
            </span>
            <span v-else>Actualizar usuario</span>
          </button>
        </div>
      </form>
    </div>
  </ModalForm>
</template>
