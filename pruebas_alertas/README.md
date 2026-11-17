# Pruebas de alertamiento – JSON para Postman

Archivos con el **body** (JSON) de cada petición. Solo falta que los corras tú en Postman.

## Endpoints

| Acción | Método | URL |
|---|---|---|
| Emitir póliza (operación) | `POST` | `{{base}}/api/insertar/operacion` |
| Registrar pago | `POST` | `{{base}}/api/insertar/operacion-pago` |
| Actualizar cliente (solo casos 3 y 4) | `PUT` | `{{base}}/api/clientes/{id}` |

- Base (`.env` → `APP_URL`): `https://uat-pld.tlalocseguros.com`
- Auth: todas las de operaciones piden Bearer token (`auth:sanctum`). Obténlo con `POST /api/login` o `POST /api/api-login`.

## Variables que debes reemplazar

Los pagos traen `{{IDOp_XX}}`. Sustitúyelo por el **`IDOperacion`** que devuelve la emisión previa (o define la variable en Postman):

| Placeholder | Sale de |
|---|---|
| `{{IDOp_01}}` | respuesta de `01` → `IDOperacion` |
| `{{IDOp_03}}` | respuesta de `03` |
| `{{IDOp_06}}` | respuesta de `06` |
| `{{IDOp_09}}` | respuesta de `09` |
| `{{IDOp_11}}` | respuesta de `11` |
| `{{IDOp_13}}`, `{{IDOp_15}}`, `{{IDOp_17}}` | respuestas de `13`, `15`, `17` (semillas Escobedo) |
| `{{IDOp_19}}` | respuesta de `19` |
| `{{IDOp_21}}` | respuesta de `21` |

## Orden de ejecución y resultados esperados

| # | Caso | IDCliente | Archivos (en orden) | Alertas esperadas |
|---|---|---|---|---|
| 1 | Samuel Alejandro García Sepúlveda (PPE) | **6939** | `01` → `02` | Emisión: **PPE**. Pago: **PPE** + **Monto** (Por reportar, 8,674.13 USD) |
| 2 | Autopartes Rozver (LPB) | **6940** | `03` → `04` | **Ninguna** por póliza/pago (las alertas LPB son de nivel cliente y ya existen: #260/#261) |
| 3 | Ricardo José Peralta (ubicación alto riesgo) | **6941** | `05` (opcional) → `06` → `07` | **Fraccionado** + **Acumulado Efectivo** (+ correo de aviso de riesgo si se corre `05`) |
| 4 | María José Loyola Anaya (actividad vulnerable) | **6942** | `08` → `09` → `10` | **Actividad vulnerable** (en `08`) + **Monto** (Por reportar) |
| 5 | Jesús Pablo Lemus Navarro (PPE) | **7010** | `11` → `12` | Emisión: **PPE**. Pago: **PPE** + **Fraccionado** |
| 6 | José Antonio Escobedo Hernández (monto inusual) | **7013** | `13`→`14`→`15`→`16`→`17`→`18` (semillas) → `19` → `20` | Solo en `20`: **Monto Inusual** |
| 7 | María Leticia Hernández Hernández (actividad vulnerable) | **7014** | `21` → `22` | **Actividad vulnerable** (ya existe, #308) + **Monto** (Por reportar) |

Estatus: `Monto` queda en **Por reportar**; `PPE`, `Fraccionado`, `Acumulado Efectivo`, `Monto Inusual` y `Actividad vulnerable` en **Generado**.

---

## Notas y decisiones tomadas (léelas antes de correr)

**Fechas / tipo de cambio.** Todas las emisiones van con `FechaEmision = 2026-11-10`. Los **pagos en efectivo** van con `FechaPago = 2026-05-20`. Conforme a la regla del DOF se usa la FIX publicada el día hábil anterior, que para esa fecha es **17.2928**, de modo que `150,000 MXN ÷ 17.2928 = 8,674.13 USD` (la cifra del caso 1). La tabla `catTipoCambio` se sincroniza diariamente con la serie SF43718 de Banxico; si falta historial local, el sistema hace backfill desde la API. Si no hay FIX disponible, **no** se evalúa el patrón Monto.

**1. Samuel (6939).** La prueba ya se corrió (operación 19551, folio `POL-0001-0001`, alertas 338/339/340). Estos JSON usan un folio nuevo (`POL-ALERTA-01`) porque la API rechaza folios duplicados.

**2. Autopartes Rozver (6940).** Coincide en listas negras, pero esa condición **no** bloquea ni alerta a nivel póliza: pago único en transferencia → ningún patrón aplica.

**3. Ricardo Peralta (6941).**
- En BD el cliente es **"RICARDO JOSE PERALTA PEREZ"** (no existe "Peralta López"); ID **6941**.
- Su domicilio está en **Michoacán (IndicePaz = 2)**, o sea **NO** está en ubicación de alto riesgo. El archivo `05` (opcional) solo cambia el estado del domicilio a **Guanajuato (IndicePaz = 3)** para que aplique el factor. Ojo: el patrón **"Zona geografica" nunca genera alerta**, solo dispara el correo de aviso al oficial de cumplimiento.
- La póliza dice "en transferencia" pero el **pago `07` son 2 exhibiciones en efectivo** (500,000 + 500,000) para que salga **Acumulado Efectivo** (y, por tener 2 pagos, también **Fraccionado**). Si prefieres un solo pago en transferencia → no se genera ninguna alerta.

**4. Loyola Anaya (6942).** **"Actividad vulnerable" es alerta de nivel cliente**: se emite al **guardar/editar el cliente** (`POST /api/clientes/guardarCliente` o `PUT /api/clientes/{id}`), **nunca** al emitir la póliza. Por eso va el `08` (PUT): ocupación 8832017 tiene `NivelRiesgo = 3`. El PUT solo reescribe domicilios y flags (`EsAltoRiesgo` pasa a `true`); verifiqué que no coincide en listas negras ni en PPE, así que no altera el resto del caso. El pago único en efectivo de 450,000 genera además la alerta **Monto**.

**5. Lemus Navarro (7010).** ID **7010** (hay un duplicado 7011). El pago va en **una sola petición con 2 detalles** (300,000 + 350,000 en transferencia) para que salga **Fraccionado** y solo **una** alerta PPE extra por pago. ⚠️ **No hagas PUT de los clientes PPE**: el servicio Q-Detect de PPE está respondiendo `success=false`, lo que pondría `EsPPEActivo = false` y rompería el caso.

**6. Escobedo Hernández (7013).** "Monto Inusual" exige **≥ 3 pólizas emitidas y pagadas** con desviación estándar distinta de 0, por eso van las 6 peticiones semilla (`13`–`18`) con primas 100,000 / 120,000 / 140,000 (todo en transferencia para no disparar otros patrones). Cálculo: μ = 120,000, σ ≈ 16,329.93 → intervalo [71,010.21 , 168,989.79]; la prima de **250,000** queda fuera → alerta.
- Se usa el ID **7013** (el que tiene `EsAltoRiesgo = true`); el 7012 es el mismo RFC.
- **No** se incluye PUT de ubicación para este cliente: el análisis de listas lo detecta en **CNSF por nombre** y crearía una alerta "Personas Bloqueadas" que ensuciaría la prueba. Además su domicilio (Michoacán) no es de alto riesgo; su marca de alto riesgo viene por ocupación vulnerable.

**7. María Leticia Hernández (7014).** ID **7014** (duplicado 7015). La alerta **Actividad vulnerable ya existe** (#308) y está deduplicada **por identidad (RFC `HEHL750505A68`)**: no se puede crear otra para este RFC, por lo que no se agrega PUT. El pago único en efectivo de 150,000 genera además la alerta **Monto** (8,674.13 USD).

## Supuestos comunes de todos los JSON

- `GastosEmision = 0` → ningún pago se descarta por coincidir con gastos de emisión (patrón Fraccionado).
- `IDMoneda = "MXN"`, `TipoCambio = 1`, agente de prueba (`HEAJ9005137N7` / `HEAJ900513HMCLRRA7`), `EsquemaDePago = "ANU01"`, `EsEndosoCancelacion = false`, `PagaTercero = false`.
- `IDFormaPago`: **1 = Efectivo**, **3 = Transferencia electrónica de fondos** (`catFormaPagos`).
- Folios nuevos `POL-ALERTA-xx` (los de las pruebas ya ejecutadas `POL-0001-0001/0002` no se pueden repetir).
