<script setup lang="ts">
import {
    CATEGORY_SHORT_NAMES,
    PLD_CATEGORY_OPTIONS,
} from '@/components/Clientes/categoryMeta';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { ref } from 'vue';

const search = defineModel<string>('search', { default: '' });
const tipo = defineModel<string>('tipo', { default: 'todos' });
const estatus = defineModel<string>('estatus', { default: 'todos' });
const categories = defineModel<string[]>('categories', { default: [] });

const props = defineProps<{
    isSearching: boolean;
    hasActiveFilters: boolean;
}>();

const emit = defineEmits<{
    clearSearch: [];
    clearAll: [];
    removeCategory: [cat: string];
}>();

const searchInputRef = ref<HTMLInputElement | null>(null);

function focusSearch() {
    searchInputRef.value?.focus();
}

function toggleCategory(value: string) {
    const next = [...categories.value];
    const i = next.indexOf(value);
    if (i >= 0) next.splice(i, 1);
    else next.push(value);
    categories.value = next;
}

function selectAll() {
    categories.value = PLD_CATEGORY_OPTIONS.map((o) => o.value);
}

function clearCategories() {
    categories.value = [];
}

defineExpose({ focusSearch });
</script>

<template>
    <section
        aria-label="Búsqueda y filtros"
        class="rounded-xl border border-border bg-card"
    >
        <div
            class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 lg:grid-cols-[minmax(0,1.618fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]"
        >
            <!-- Búsqueda -->
            <div class="relative md:col-span-2 lg:col-span-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    ref="searchInputRef"
                    v-model="search"
                    type="search"
                    role="searchbox"
                    aria-label="Buscar clientes"
                    placeholder="Buscar por nombre, RFC, CURP, ID, póliza…"
                    @keydown.escape="emit('clearSearch')"
                    class="h-10 w-full rounded-lg border border-input bg-background pr-16 pl-9 text-sm text-foreground transition-[border-color,box-shadow] duration-150 outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 motion-reduce:transition-none [&::-webkit-search-cancel-button]:hidden"
                />
                <span
                    class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 items-center gap-1 lg:inline-flex"
                    aria-hidden="true"
                >
                    <span
                        v-if="props.isSearching"
                        class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-muted-foreground/40 border-t-foreground"
                    />
                    <kbd
                        v-else-if="!search"
                        class="rounded border border-border bg-muted px-1.5 py-0.5 font-sans text-[10px] font-medium text-muted-foreground"
                        >Ctrl K</kbd
                    >
                    <button
                        v-else
                        type="button"
                        tabindex="-1"
                        aria-hidden="true"
                        class="pointer-events-auto rounded p-0.5 text-muted-foreground transition-colors duration-150 hover:text-foreground"
                        @click="emit('clearSearch')"
                    >
                        <X class="size-4" />
                    </button>
                </span>
                <button
                    v-if="search"
                    type="button"
                    aria-label="Limpiar búsqueda"
                    class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-1 text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:hidden"
                    @click="emit('clearSearch')"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>

            <!-- Tipo persona -->
            <div class="flex flex-col gap-1.5">
                <label
                    for="filtro-tipo-persona"
                    class="text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                >
                    Tipo persona
                </label>
                <select
                    id="filtro-tipo-persona"
                    v-model="tipo"
                    class="h-10 w-full cursor-pointer appearance-none rounded-lg border border-input bg-background px-3 pr-8 text-sm text-foreground transition-[border-color,box-shadow] duration-150 outline-none hover:border-muted-foreground/40 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 motion-reduce:transition-none"
                >
                    <option value="todos">Todas las personas</option>
                    <option value="fisica">Personas físicas</option>
                    <option value="moral">Personas morales</option>
                </select>
            </div>

            <!-- Estatus -->
            <div class="flex flex-col gap-1.5">
                <label
                    for="filtro-estatus-cliente"
                    class="text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                >
                    Estatus
                </label>
                <select
                    id="filtro-estatus-cliente"
                    v-model="estatus"
                    class="h-10 w-full cursor-pointer appearance-none rounded-lg border border-input bg-background px-3 pr-8 text-sm text-foreground transition-[border-color,box-shadow] duration-150 outline-none hover:border-muted-foreground/40 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 motion-reduce:transition-none"
                >
                    <option value="todos">Todos los estatus</option>
                    <option value="activo">Activos</option>
                    <option value="inactivo">Inactivos</option>
                </select>
            </div>

            <!-- Categoría PLD -->
            <div class="flex flex-col gap-1.5">
                <span
                    id="pld-category-label"
                    class="text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                >
                    Categoría PLD
                </span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            aria-labelledby="pld-category-label"
                            class="h-10 w-full justify-between font-normal transition-colors duration-150 motion-reduce:transition-none"
                        >
                            <span class="truncate text-sm">
                                <template
                                    v-if="
                                        categories.length ===
                                        PLD_CATEGORY_OPTIONS.length
                                    "
                                    >Todas</template
                                >
                                <template v-else-if="categories.length === 0"
                                    >Ninguna (sin filtro)</template
                                >
                                <template v-else-if="categories.length === 1">{{
                                    CATEGORY_SHORT_NAMES[categories[0]] ??
                                    categories[0]
                                }}</template>
                                <template v-else
                                    >{{
                                        categories.length
                                    }}
                                    seleccionadas</template
                                >
                            </span>
                            <ChevronDown
                                class="size-4 shrink-0 opacity-50"
                                aria-hidden="true"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-72">
                        <DropdownMenuLabel
                            class="flex items-center justify-between text-xs font-medium"
                        >
                            <span>Categorías PLD</span>
                            <span class="text-muted-foreground tabular-nums"
                                >{{ categories.length }}/{{
                                    PLD_CATEGORY_OPTIONS.length
                                }}</span
                            >
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div
                            class="max-h-64 overflow-y-auto p-1"
                            role="group"
                            aria-label="Categorías PLD"
                        >
                            <button
                                v-for="opt in PLD_CATEGORY_OPTIONS"
                                :key="opt.value"
                                type="button"
                                role="checkbox"
                                :aria-checked="categories.includes(opt.value)"
                                @click="toggleCategory(opt.value)"
                                class="flex w-full items-center gap-2.5 rounded-sm px-2 py-2 text-left text-sm transition-colors duration-150 outline-none hover:bg-accent focus-visible:bg-accent motion-reduce:transition-none"
                            >
                                <span
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors duration-150"
                                    :class="
                                        categories.includes(opt.value)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input bg-background'
                                    "
                                    aria-hidden="true"
                                >
                                    <Check
                                        v-if="categories.includes(opt.value)"
                                        class="size-3"
                                    />
                                </span>
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full ring-1 ring-black/10 ring-inset dark:ring-white/15"
                                    :class="opt.dot"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 flex-1 truncate">{{
                                    opt.label
                                }}</span>
                            </button>
                        </div>
                        <DropdownMenuSeparator />
                        <div class="flex items-center justify-between p-2">
                            <button
                                type="button"
                                class="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                @click="clearCategories"
                            >
                                Limpiar
                            </button>
                            <button
                                type="button"
                                class="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                @click="selectAll"
                            >
                                Todas
                            </button>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Chips de filtros activos -->
        <div
            v-if="props.hasActiveFilters"
            class="flex flex-wrap items-center gap-1.5 border-t border-border px-4 py-2.5"
        >
            <span class="mr-1 text-xs text-muted-foreground">Filtros:</span>
            <span
                v-if="search.trim()"
                class="inline-flex max-w-full items-center gap-1 rounded-full border border-border bg-muted/60 py-0.5 pr-1 pl-2.5 text-xs text-foreground"
            >
                <span class="truncate">“{{ search.trim() }}”</span>
                <button
                    type="button"
                    aria-label="Quitar búsqueda"
                    class="rounded-full p-0.5 transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="emit('clearSearch')"
                >
                    <X class="size-3" aria-hidden="true" />
                </button>
            </span>
            <span
                v-if="tipo !== 'todos'"
                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 py-0.5 pr-1 pl-2.5 text-xs text-foreground"
            >
                {{
                    tipo === 'fisica' ? 'Personas físicas' : 'Personas morales'
                }}
                <button
                    type="button"
                    aria-label="Quitar filtro de tipo"
                    class="rounded-full p-0.5 transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="tipo = 'todos'"
                >
                    <X class="size-3" aria-hidden="true" />
                </button>
            </span>
            <span
                v-if="estatus !== 'todos'"
                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 py-0.5 pr-1 pl-2.5 text-xs text-foreground"
            >
                {{ estatus === 'activo' ? 'Activos' : 'Inactivos' }}
                <button
                    type="button"
                    aria-label="Quitar filtro de estatus"
                    class="rounded-full p-0.5 transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="estatus = 'todos'"
                >
                    <X class="size-3" aria-hidden="true" />
                </button>
            </span>
            <template
                v-if="
                    categories.length !== PLD_CATEGORY_OPTIONS.length &&
                    categories.length > 0
                "
            >
                <span
                    v-for="cat in categories"
                    :key="cat"
                    class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 py-0.5 pr-1 pl-2.5 text-xs text-foreground"
                >
                    {{ CATEGORY_SHORT_NAMES[cat] ?? cat }}
                    <button
                        type="button"
                        :aria-label="`Quitar categoría ${CATEGORY_SHORT_NAMES[cat] ?? cat}`"
                        class="rounded-full p-0.5 transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="emit('removeCategory', cat)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>
            </template>
            <button
                type="button"
                class="ml-1 rounded px-1.5 py-0.5 text-xs font-medium text-muted-foreground underline-offset-2 transition-colors duration-150 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="emit('clearAll')"
            >
                Limpiar todo
            </button>
        </div>
    </section>
</template>
