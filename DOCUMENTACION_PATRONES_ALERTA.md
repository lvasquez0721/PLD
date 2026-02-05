# Documentación de Patrones de Alerta PLD

Este documento detalla los criterios, variables y lógica utilizada para la generación de alertas en el módulo de Prevención de Lavado de Dinero (PLD), específicamente dentro del servicio `AnalisisPagosService`.

## 1. Patrón Fraccionado (`PATRON_FRACCIONADO`)

Detecta operaciones que han sido liquidadas mediante múltiples pagos, donde los pagos individuales no cubren el monto total esperado, sugiriendo una posible estructuración para evadir umbrales de reporte.

### Variables Involucradas
*   **Operación**: `PrimaTotal`, `GastosEmision`.
*   **Pagos**: Lista de pagos asociados (`Monto`).
*   **Configuración**: `ToleranciaPorcentaje` (Obtenido de `CatParametriaPLD`, IDParametro 15).
*   **Estado**: `operacionTotalmentePagada` (Calculado: Suma de pagos >= Prima Total).

### Condiciones de Activación
Se genera la alerta si se cumplen **todas** las siguientes condiciones:
1.  **Múltiples Pagos**: La operación tiene más de 1 pago asociado (`count(pagos) > 1`).
2.  **Liquidación Total**: La operación ha sido pagada en su totalidad (`saldoPendiente <= 0`).
3.  **Fallo de Tolerancia**: Al menos un pago individual **no** coincide con el `MontoEsperado` (PrimaTotal + GastosEmision) dentro del margen de tolerancia permitido.
    *   *Lógica*: Se calcula un rango `[MontoEsperado - Tolerancia, MontoEsperado + Tolerancia]`. Si los pagos fraccionados son montos menores a este rango (lo cual es esperado en pagos parciales), se activa la alerta.

### Descripción Técnica
El sistema verifica si una operación totalmente pagada fue liquidada en "abonos" o partes. Si los pagos individuales no corresponden al total (dentro de un margen de error/tolerancia), se considera una operación fraccionada sospechosa.

---

## 2. Patrón Acumulado en Efectivo (`PATRON_ACUMULADO_EFECTIVO`)

Identifica operaciones que han sido liquidadas totalmente utilizando exclusivamente efectivo en múltiples exhibiciones.

### Variables Involucradas
*   **Pagos**: `IDFormaPago`.
*   **Estado**: `operacionTotalmentePagada`.

### Condiciones de Activación
Se genera la alerta si se cumplen **todas** las siguientes condiciones:
1.  **Liquidación Total**: La operación ha sido pagada en su totalidad.
2.  **Múltiples Pagos**: La operación tiene más de 1 pago asociado.
3.  **Exclusividad de Efectivo**: **Todos** los pagos registrados tienen `IDFormaPago == 1` (Efectivo).

### Descripción Técnica
Este patrón busca detectar el uso reiterado de efectivo para liquidar una misma operación, lo cual puede ser indicativo de intentos de colocación de efectivo en el sistema financiero.

---

## 3. Patrón Monto Relevante (`PATRON_MONTO_RELEVANTE`)

Detecta pagos individuales en efectivo que superan el umbral regulatorio establecido (generalmente 10,000 USD).

### Variables Involucradas
*   **Pagos**: `Monto`, `IDMoneda`, `IDFormaPago`.
*   **Conversión**: Tasa de cambio para convertir a USD.

### Condiciones de Activación
Se genera la alerta si se cumple la siguiente condición en **cualquiera** de los pagos:
1.  **Forma de Pago**: El pago es en efectivo (`IDFormaPago == 1`).
2.  **Umbral Superado**: El monto del pago, convertido a Dólares Americanos (USD), es mayor o igual a **$10,000.00 USD**.

### Detalles Adicionales
*   **Conversión de Moneda**:
    *   Si la moneda es USD (`IDMoneda == 2`), se usa el monto directo.
    *   Si la moneda es MXN (`IDMoneda == 1`), se intenta obtener el tipo de cambio del día hábil anterior desde Banxico. Si falla, se usa un valor fallback (ej. 20.0).
*   **Reporte**: Esta alerta marca automáticamente la bandera `genera_reporte = true`, indicando que debe ser considerada para reportes regulatorios.

---

## 4. Patrón Persona Políticamente Expuesta (`PATRON_PPE`)

Identifica operaciones realizadas por clientes marcados como Personas Políticamente Expuestas (PPE).

### Variables Involucradas
*   **Cliente**: `EsPPEActivo`.

### Condiciones de Activación
Se genera la alerta si:
1.  **Cliente PPE**: El cliente asociado a la operación tiene la bandera `EsPPEActivo` en `true`.

### Descripción Técnica
Es una alerta basada en listas de coincidencia (Watchlist). Cualquier actividad transaccional de un cliente identificado como PPE debe ser monitoreada.

---

## 5. Patrón Monto Inusual (`PATRON_MONTO_INUSUAL`)

Detecta operaciones cuya prima total se sale de los rangos permitidos establecidos en la parametría del sistema.

### Variables Involucradas
*   **Operación**: `PrimaTotal`, `IDMoneda`.
*   **Cliente**: `IDTipoPersona` (Física o Moral).
*   **Estado**: `operacionTotalmentePagada`.
*   **Parametría**: 
    *   `limiteInferior`: Obtenido de `CatParametriaPLD` (ID 14: Monto mínimo alerta).
    *   `limiteSuperior`: Obtenido de `CatParametriaPLD` (ID 16 para PF, ID 17 para PM).

### Condiciones de Activación
Se genera la alerta si se cumplen **todas** las siguientes condiciones:
1.  **Liquidación Total**: La operación ha sido pagada en su totalidad.
2.  **Fuera de Rango**: La `PrimaTotal` (convertida a MXN) es menor al `limiteInferior` O mayor al `limiteSuperior`.

### Descripción Técnica
Busca identificar operaciones con montos fuera de los umbrales definidos por la normativa interna (Parametría PLD). Se realiza la conversión de moneda a Pesos Mexicanos (MXN) antes de la comparación.
