# Plan: Mostrar sistemas en Detalles del Cliente

## Archivos a modificar (3)

### 1. `app/Models/Clientes/CatIDClientesSistema.php`
Agregar relación `sistema()`:
```php
public function sistema()
{
    return $this->belongsTo(CatSistemas::class, 'IDSistema', 'IDSistema');
}
```

### 2. `app/Http/Controllers/ClientesController.php`
- Agregar import: `use App\Models\Clientes\CatIDClientesSistema;`
- En `verDetallesCliente`, antes del `return inertia(...)`, consultar:
```php
$sistemasDelCliente = CatIDClientesSistema::where('IDCliente', $cliente->IDCliente)
    ->with('sistema')
    ->get();
```
- Agregar al return de inertia: `'sistemasDelCliente' => $sistemasDelCliente,`

### 3. `resources/js/pages/Clientes/Detalles.vue`
- Agregar prop: `sistemasDelCliente: any[]`
- Insertar después de la línea del RFC (l304, antes de `</div>` de `mt-4 space-y-1` ~l305):

```html
<div class="flex items-start gap-2 text-gray-600 dark:text-neutral-300">
    <span class="w-15 font-semibold shrink-0">Sistemas</span>
    <div class="flex flex-wrap gap-1">
        <span v-if="sistemasDelCliente.length === 0"
            class="inline-flex items-center rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 font-semibold dark:bg-indigo-900/40 dark:text-indigo-200">
            PLD (Sistema Local)
        </span>
        <span v-for="sis in sistemasDelCliente" :key="sis.IDOrigenSistema"
            class="inline-flex items-center rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 font-semibold dark:bg-indigo-900/40 dark:text-indigo-200">
            {{ sis.sistema?.Sistema ?? 'ID ' + sis.IDSistema }}
        </span>
    </div>
</div>
```

## Comportamiento
- **Con sistemas externos**: muestra badges con el nombre de cada sistema (SIT, Xpertys, etc.)
- **Sin sistemas externos**: muestra badge "PLD (Sistema Local)" indicando que solo está en el sistema local
- Mismo estilo visual que los badges existentes (PPE, Listas, Extranjero)
