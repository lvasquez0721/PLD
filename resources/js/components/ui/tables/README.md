# Datatable Component

Un componente de tabla avanzado con funcionalidades de búsqueda, filtrado, paginación y botones personalizados.

## Características

- ✅ Búsqueda global con debounce
- ✅ Filtros avanzados por columna
- ✅ Ordenamiento por columnas
- ✅ Paginación con navegación completa
- ✅ Botones personalizados en el header
- ✅ Acciones por fila
- ✅ Pantalla completa
- ✅ Animaciones suaves
- ✅ Modo oscuro
- ✅ Responsive design

## Uso Básico

```vue
<template>
  <Datatable
    :columns="columns"
    :rows="rows"
    search-placeholder="Buscar..."
    @button-click="handleButtonClick"
    @row-action="handleRowAction"
  />
</template>

<script setup>
import Datatable from '@/components/ui/tables/Datatable.vue';

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'name', label: 'Nombre', sortable: true, filterType: 'text' },
  { key: 'email', label: 'Email', sortable: true, filterType: 'text' }
];

const rows = [
  { id: 1, name: 'Juan Pérez', email: 'juan@ejemplo.com' },
  { id: 2, name: 'María García', email: 'maria@ejemplo.com' }
];
</script>
```

## Botones Personalizados

### Botones del Header

```vue
<template>
  <Datatable
    :columns="columns"
    :rows="rows"
    :custom-buttons="customButtons"
    @button-click="handleButtonClick"
  />
</template>

<script setup>
const customButtons = [
  {
    id: 'add',
    label: 'Agregar',
    icon: 'add',
    variant: 'primary'
  },
  {
    id: 'export',
    label: 'Exportar',
    icon: 'download',
    variant: 'secondary'
  }
];

function handleButtonClick(buttonId) {
  console.log('Botón clickeado:', buttonId);
}
</script>
```

### Acciones por Fila

```vue
<template>
  <Datatable
    :columns="columns"
    :rows="rows"
    :row-actions="rowActions"
    @row-action="handleRowAction"
  />
</template>

<script setup>
const rowActions = [
  {
    id: 'edit',
    label: 'Editar',
    icon: 'edit',
    variant: 'primary'
  },
  {
    id: 'delete',
    label: 'Eliminar',
    icon: 'delete',
    variant: 'danger',
    disabled: (row) => row.role === 'Admin'
  }
];

function handleRowAction(actionId, rowData) {
  console.log('Acción:', actionId, rowData);
}
</script>
```

## Props

### Columnas

| Prop | Tipo | Descripción |
|------|------|-------------|
| `key` | `string` | Clave única de la columna |
| `label` | `string` | Etiqueta visible |
| `sortable` | `boolean` | Si la columna es ordenable |
| `filterType` | `'text' \| 'select'` | Tipo de filtro |
| `options` | `any[]` | Opciones para filtros select |
| `width` | `string` | Ancho de la columna |
| `align` | `'left' \| 'center' \| 'right'` | Alineación del contenido |
| `format` | `'text' \| 'number' \| 'date' \| 'currency'` | Formato de los valores |

### Botones Personalizados

| Prop | Tipo | Descripción |
|------|------|-------------|
| `id` | `string` | Identificador único |
| `label` | `string` | Texto del botón |
| `icon` | `string` | Icono (add, edit, delete, download, refresh, view) |
| `variant` | `'primary' \| 'secondary' \| 'success' \| 'warning' \| 'danger'` | Estilo del botón |
| `disabled` | `boolean` | Si está deshabilitado |
| `loading` | `boolean` | Si está en estado de carga |

### Acciones de Fila

| Prop | Tipo | Descripción |
|------|------|-------------|
| `id` | `string` | Identificador único |
| `label` | `string` | Texto de la acción |
| `icon` | `string` | Icono de la acción |
| `variant` | `string` | Estilo de la acción |
| `disabled` | `boolean \| function` | Si está deshabilitado (puede ser función) |
| `hidden` | `boolean \| function` | Si está oculto (puede ser función) |

## Eventos

| Evento | Parámetros | Descripción |
|--------|------------|-------------|
| `button-click` | `(buttonId: string, rowData?: any)` | Click en botón del header |
| `row-action` | `(actionId: string, rowData: any)` | Click en acción de fila |
| `sort` | `(key: string, direction: 'asc' \| 'desc')` | Ordenamiento |
| `filter` | `(filters: Record<string, string>)` | Filtros aplicados |
| `search` | `(query: string)` | Búsqueda |
| `page-change` | `(page: number)` | Cambio de página |

## Iconos Disponibles

- `add` - Agregar
- `edit` - Editar
- `delete` - Eliminar
- `download` - Descargar
- `refresh` - Actualizar
- `view` - Ver

## Variantes de Botones

- `primary` - Azul (por defecto)
- `secondary` - Gris
- `success` - Verde
- `warning` - Amarillo
- `danger` - Rojo

## Ejemplo Completo

```vue
<template>
  <Datatable
    :columns="columns"
    :rows="users"
    :custom-buttons="headerButtons"
    :row-actions="rowActions"
    search-placeholder="Buscar usuarios..."
    empty-message="No se encontraron usuarios"
    @button-click="handleHeaderButton"
    @row-action="handleRowAction"
  />
</template>

<script setup>
import { ref } from 'vue';
import Datatable from '@/components/ui/tables/Datatable.vue';

const columns = [
  { key: 'id', label: 'ID', sortable: true, width: '80px' },
  { key: 'name', label: 'Nombre', sortable: true, filterType: 'text' },
  { key: 'email', label: 'Email', sortable: true, filterType: 'text' },
  { key: 'role', label: 'Rol', sortable: true, filterType: 'select', options: ['Admin', 'Usuario'] },
  { key: 'status', label: 'Estado', sortable: true, filterType: 'select', options: ['Activo', 'Inactivo'] }
];

const users = ref([
  { id: 1, name: 'Juan Pérez', email: 'juan@ejemplo.com', role: 'Admin', status: 'Activo' },
  { id: 2, name: 'María García', email: 'maria@ejemplo.com', role: 'Usuario', status: 'Activo' }
]);

const headerButtons = [
  { id: 'add', label: 'Agregar Usuario', icon: 'add', variant: 'primary' },
  { id: 'export', label: 'Exportar', icon: 'download', variant: 'secondary' }
];

const rowActions = [
  { id: 'edit', label: 'Editar', icon: 'edit', variant: 'primary' },
  { id: 'delete', label: 'Eliminar', icon: 'delete', variant: 'danger' }
];

function handleHeaderButton(buttonId) {
  if (buttonId === 'add') {
    // Lógica para agregar usuario
  } else if (buttonId === 'export') {
    // Lógica para exportar
  }
}

function handleRowAction(actionId, rowData) {
  if (actionId === 'edit') {
    // Lógica para editar
  } else if (actionId === 'delete') {
    // Lógica para eliminar
  }
}
</script>
```
