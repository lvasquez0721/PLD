<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Datatable from '@/components/ui/tables/Datatable.vue'
import Titulo from '@/components/ui/Titulo.vue'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import Toast from '@/components/ui/alert/Toast.vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types'
import { Users, Trash2 } from 'lucide-vue-next'

import FormCrear from '@/components/pages/usuarios/formCrear.vue'
import FormEditar from '@/components/pages/usuarios/formEditar.vue'
import ModalConfirm from '@/components/ui/modals/modalConfirm.vue'

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Usuarios',
    href: '',
  },
]

const columns = [
  { key: 'usuario', label: 'Usuario' },
  { key: 'nombre', label: 'Nombre' },
  { key: 'email', label: 'Email' },
]

interface User {
  id: number
  usuario: string
  nombre: string
  apellido_p: string
  apellido_m: string
  primer_login: boolean | number | null
  email: string
  roles?: Array<{ id: number; name: string }>
}

const users = usePage().props.users as User[]

const rows = users.map((user) => ({
  id: user.id,
  usuario: user.usuario,
  nombre: `${user.nombre} ${user.apellido_p ?? ''} ${user.apellido_m ?? ''}`.trim(),
  email: user.email,
}))

const rowActions = [
  {
    id: 'edit',
    label: 'Editar',
    icon: 'edit',
    variant: 'secondary' as const,
    disabled: false,
  },
  {
    id: 'delete',
    label: 'Eliminar',
    icon: 'trash',
    variant: 'danger' as const,
    disabled: false,
  },
]

const customButtons = [
  {
    id: 'create-user',
    label: 'Crear usuario',
    icon: 'add',
    variant: 'primary' as const,
    disabled: false,
    loading: false,
  },
]

const showCreateUserModal = ref(false)
const showEditUserModal = ref(false)
const selectedUser = ref<User | null>(null)
// Para la confirmación de eliminación
const showDeleteConfirm = ref(false)
const deletingUser = ref<User | null>(null)
const deleting = ref(false)

const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref<'success' | 'warning' | 'error'>('success')

// Obtener datos de la página para el toast
const page = usePage()

// Función para parsear y decodificar los parámetros de la URL de un string tipo "?toast_message=...&toast_type=..."
function parseUrlToastParams() {
  const query = window.location.search.substring(1)
  if (!query) return {}
  const params: Record<string, string> = {}
  // Adaptar para que reemplace "+" por " " y luego decodifique correctamente
  query.split('&').forEach((pair) => {
    let [key, value] = pair.split('=')
    if (key) {
      // Remplazar + por espacio y luego decodificar
      value = (value || '').replace(/\+/g, ' ')
      params[decodeURIComponent(key)] = decodeURIComponent(value)
    }
  })
  return params
}

// Inicialmente, revisa props o query string (en caso de redirección Inertia::location)
onMounted(() => {
  let foundToast = false

  // 1. Verifica si props trae toast (ej: después del create)
  const propToast = page.props.toast as { message: string; type: 'success' | 'warning' | 'error' } | undefined
  if (propToast && propToast.message && propToast.type) {
    toastMessage.value = propToast.message
    toastType.value = propToast.type
    showToast.value = true
    foundToast = true
  }

  // 2. Si no hay en props, busca en la URL (por redirección Inertia::location, después de update)
  if (!foundToast) {
    const urlParams = parseUrlToastParams()
    if (urlParams.toast_message) {
      toastMessage.value = urlParams.toast_message
      if (
        urlParams.toast_type === 'success' ||
        urlParams.toast_type === 'warning' ||
        urlParams.toast_type === 'error'
      ) {
        toastType.value = urlParams.toast_type
      } else {
        toastType.value = 'success'
      }
      showToast.value = true

      // Opcional: Limpia la query string para mejorar la experiencia y evitar toasts repetidos al recargar
      try {
        const url = new URL(window.location.href)
        url.searchParams.delete('toast_message')
        url.searchParams.delete('toast_type')
        window.history.replaceState({}, document.title, url.pathname + url.search)
      } catch (e) { }
    }
  }
})

const handleButtonClick = (buttonId: string) => {
  if (buttonId === 'create-user') {
    showCreateUserModal.value = true
  }
}

const handleRowAction = (actionId: string, rowData: any) => {
  if (actionId === 'edit') {
    const user = users.find(u => u.id === rowData.id)
    if (user) {
      selectedUser.value = user
      showEditUserModal.value = true
    }
  } else if (actionId === 'delete') {
    const user = users.find(u => u.id === rowData.id)
    if (user) {
      deletingUser.value = user
      showDeleteConfirm.value = true
    }
  }
}

const refreshUsers = () => {
  router.reload({ only: ['users'] })
}

// Función para eliminar el usuario seleccionado
const deleteUser = async () => {
  if (!deletingUser.value) return
  deleting.value = true
  router.delete(
    // Usando routeName como string en vez de helper (que no está disponible aquí)
    // 'usuarios.destroy' corresponde al nombre de la ruta backend
    // El segundo argumento es el parámetro :id
    // router.delete espera la url, pero podemos usar la versión shortcut como string name+params:
    // https://inertiajs.com/manual-visits#deleting-resources
    // Aquí, este hack funcionará, pero si no va, usar `/usuarios/${deletingUser.value.id}`
    // La siguiente línea asume que tienes las rutas nombradas
    // Si no tienes el helper route(), reemplaza con string hardcodeado:
    // `/usuarios/${deletingUser.value.id}`
    `/usuarios/${deletingUser.value.id}`,
    {
      onSuccess: () => {
        showDeleteConfirm.value = false
        deletingUser.value = null
        refreshUsers()
      },
      onError: (errors) => {
        showDeleteConfirm.value = false
        deletingUser.value = null
      },
      onFinish: () => {
        deleting.value = false
      },
      preserveScroll: true,
    }
  )
}
</script>

<template>

  <Head title="Usuarios" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <FadeIn>
      <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between">
          <Titulo :icon="Users" title="Gestión de Usuarios" size="md" weight="bold" class="mb-2" />
        </div>
        <Datatable :columns="columns" :rows="rows" :custom-buttons="customButtons" :row-actions="rowActions"
          @button-click="handleButtonClick" @row-action="handleRowAction" />
      </div>
    </FadeIn>
    <FormCrear v-model="showCreateUserModal" @created="refreshUsers" @close="showCreateUserModal = false" />
    <FormEditar v-model="showEditUserModal" :user="selectedUser" @updated="refreshUsers"
      @close="showEditUserModal = false" />

    <!-- Modal de confirmación para eliminar usuario -->
    <ModalConfirm v-model="showDeleteConfirm" :loading="deleting" @confirm="deleteUser" @cancel="deletingUser = null">
      <template #icon>
        <Trash2 class="text-red-600 mb-2" :size="36" />
      </template>
      <template #title>
        ¿Eliminar usuario?
      </template>
      <template #description>
        ¿Seguro que deseas eliminar al usuario<br />
        <span class="font-bold">{{ deletingUser?.nombre }} {{ deletingUser?.apellido_p }} {{ deletingUser?.apellido_m
        }}</span>?
      </template>
      <template #cancel>
        Cancelar
      </template>
      <template #confirm>
        {{ deleting ? 'Eliminando...' : 'Eliminar' }}
      </template>
    </ModalConfirm>

    <!-- Toast notification -->
    <Toast v-model="showToast" :message="toastMessage" :type="toastType" :duration="5000" />
  </AppLayout>
</template>
