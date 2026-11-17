# Prueba patrón "Monto" con múltiples pagos

Escenario: cliente con domicilio en **zona de alto riesgo** (Baja California, `IndicePaz = 3`) y una operación de **300,000 MXN** pagada con **2 movimientos en MXN**:

1. **100,000 MXN por transferencia** (`IDFormaPago = 3`) = 5,367.77 USD.
2. **150,000 MXN en efectivo** (`IDFormaPago = 1`) = 8,051.66 USD.

Aunque la operación tiene 2 pagos, el segundo pago en efectivo equivale a `8,051.66 USD >= 7,500 USD`, por lo que debe generarse la alerta **Monto**.

> El cliente queda marcado como `EsAltoRiesgo = true` por la ubicación, pero **la zona geográfica no genera una alerta de nivel cliente**. Al insertar la operación se enviará el **correo de aviso de riesgo alto** al oficial de cumplimiento porque la prima (300,000 MXN = 16,103.32 USD) supera el umbral de 7,500 USD. Ese correo es esperado y no es una alerta.
>
> La operación queda parcialmente pagada a propósito (250,000 de 300,000 MXN) para evitar que también se generen las alertas **Fraccionado** o **Acumulado Efectivo** y así aislar la prueba de **Monto**.

## Orden de ejecución

| Paso | Archivo | Endpoint |
|---|---|---|
| 1 | `01_cliente.json` | `POST /api/clientes/guardarCliente` |
| 2 | `02_operacion.json` | `POST /api/insertar/operacion` |
| 3 | `03_pagos.json` → `peticiones[0].body` | `POST /api/insertar/operacion-pago` |
| 4 | `03_pagos.json` → `peticiones[1].body` | `POST /api/insertar/operacion-pago` |

Todas las peticiones requieren el header `Authorization: Bearer {{token}}`.

## Placeholders

- `{{IDCliente}}`: valor devuelto por el paso 1.
- `{{IDOperacion}}`: valor devuelto por el paso 2.

## Resultado esperado

Después del paso 4 debe existir una alerta en `tbAlertas` con:

- `Patron = 'Monto'`
- `IDOperacion = {{IDOperacion}}`
- `Estatus = 'Por reportar'`
- `MontoOperacion = 300000`
- `Razones` con el texto: `Pago en efectivo por 150,000.00 MXN (uno de 2 pagos de la operación) (equivale a 8,051.66 USD con la FIX publicada el día hábil anterior...)`

## Nota sobre tipo de cambio

Los pagos usan `FechaPago = 2026-11-10`. Conforme a la regla del DOF, se usa la FIX publicada el día hábil anterior: **18.6297 MXN/USD** (determinada el 2026-11-06 y publicada el 2026-11-09). El sistema ignora el `TipoCambio` capturado para pagos en MXN y resuelve la FIX aplicable desde `catTipoCambio`.

Cálculo del pago en efectivo:

```text
150,000 MXN / 18.6297 = 8,051.66 USD >= 7,500 USD
```
