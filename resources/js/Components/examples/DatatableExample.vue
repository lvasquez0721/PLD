<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Ejemplo de Datatable con Botones Personalizados</h1>

    <Datatable :columns="columns" :rows="rows" :custom-buttons="customButtons" :row-actions="rowActions"
      search-placeholder="Buscar usuarios..." empty-message="No se encontraron usuarios"
      @button-click="handleButtonClick" @row-action="handleRowAction" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import Datatable from '../ui/tables/Datatable.vue';

// Definir las columnas de la tabla
const columns = [
  { key: 'id', label: 'ID', sortable: true, width: '80px' },
  { key: 'name', label: 'Nombre', sortable: true, filterType: 'text' },
  { key: 'email', label: 'Email', sortable: true, filterType: 'text' },
  { key: 'role', label: 'Rol', sortable: true, filterType: 'select', options: ['Admin', 'Usuario', 'Editor'] },
  { key: 'status', label: 'Estado', sortable: true, filterType: 'select', options: ['Activo', 'Inactivo', 'Pendiente'] },
  { key: 'created_at', label: 'Fecha de Creación', sortable: true, format: 'date' }
];

// Datos de ejemplo
const rows = ref([
  {
    id: 1,
    name: 'Juan Pérez',
    email: 'juan@ejemplo.com',
    role: 'Admin',
    status: 'Activo',
    created_at: '2024-01-15'
  },
  {
    id: 2,
    name: 'María García',
    email: 'maria@ejemplo.com',
    role: 'Usuario',
    status: 'Activo',
    created_at: '2024-01-20'
  },
  {
    id: 3,
    name: 'Carlos López',
    email: 'carlos@ejemplo.com',
    role: 'Editor',
    status: 'Inactivo',
    created_at: '2024-01-25'
  }
]);

// Botones personalizados del header
const customButtons = [
  {
    id: 'add-user',
    label: 'Agregar Usuario',
    icon: 'add',
    variant: 'primary'
  },
  {
    id: 'export-data',
    label: 'Exportar',
    icon: 'download',
    variant: 'secondary'
  },
  {
    id: 'refresh-data',
    label: 'Actualizar',
    icon: 'refresh',
    variant: 'success'
  }
];

// Acciones por fila
const rowActions = [
  {
    id: 'edit',
    label: 'Editar',
    icon: 'edit',
    variant: 'primary'
  },
  {
    id: 'view',
    label: 'Ver',
    icon: 'view',
    variant: 'secondary'
  },
  {
    id: 'delete',
    label: 'Eliminar',
    icon: 'delete',
    variant: 'danger',
    disabled: (row: any) => row.role === 'Admin' // No permitir eliminar admins
  }
];

// Manejar clicks en botones del header
function handleButtonClick(buttonId: string) {
  console.log('Botón clickeado:', buttonId);

  switch (buttonId) {
    case 'add-user':
      alert('Agregar nuevo usuario');
      break;
    case 'export-data':
      alert('Exportar datos');
      break;
    case 'refresh-data':
      alert('Actualizar datos');
      break;
  }
}

// Manejar acciones de fila
function handleRowAction(actionId: string, rowData: any) {
  console.log('Acción de fila:', actionId, rowData);

  switch (actionId) {
    case 'edit':
      alert(`Editar usuario: ${rowData.name}`);
      break;
    case 'view':
      alert(`Ver detalles de: ${rowData.name}`);
      break;
    case 'delete':
      if (confirm(`¿Estás seguro de eliminar a ${rowData.name}?`)) {
        // Aquí iría la lógica para eliminar
        alert(`Usuario ${rowData.name} eliminado`);
      }
      break;
  }
}
</script>
