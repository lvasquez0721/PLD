Genera una pantalla de detalle de alerta enfocada en análisis y revisión por un analista de cumplimiento (PLD/AML).

La interfaz debe priorizar claridad, jerarquía visual y rapidez de análisis.
Organiza la información en secciones visuales claras utilizando tarjetas (cards).

Objetivo de la pantalla

Permitir que un analista pueda entender rápidamente:

Qué alerta se generó

Qué cliente está involucrado

Qué operación la generó

Qué evidencias justifican la alerta

Qué pagos o movimientos la provocaron

Si existen reportes relacionados

La interfaz debe permitir detectar el problema en menos de 10 segundos.

Estructura de la pantalla
1. Header de la alerta

Mostrar en la parte superior:

Tipo de alerta (Patrón detectado)

Estatus

Fecha y hora de detección

Monto total involucrado

Póliza relacionada

El patrón debe mostrarse como badge o tag visual.

2. Resumen rápido (cards horizontales)

Mostrar indicadores clave:

Cliente

RFC

Póliza

Monto total de operación

Número de pagos detectados

Estado de la operación

Estas cards deben ser compactas y fáciles de escanear.

3. Información del cliente

Sección tipo tarjeta con:

Datos personales

Nombre completo

RFC

Tipo de persona

Nacionalidad

Fecha de nacimiento

Perfil financiero

Ingresos estimados

Ocupación / giro

Indicadores de riesgo

Coincidencia en listas negras

PPE activo

4. Información de la operación

Mostrar:

Datos de la póliza

Número de póliza

Endoso

Fecha de emisión

Vigencia

Datos financieros

Prima total

Gastos de emisión

Moneda

Agente involucrado

Nombre del agente

RFC del agente

5. Pagos de la operación

Mostrar una tabla clara con:

Fecha de pago

Forma de pago

Monto

Moneda

Si hay más de un pago, destacar visualmente que la operación fue fraccionada.

6. Evidencias del sistema

Mostrar un bloque de análisis automático con:

Resumen:

Total de pagos

Total pagado

Saldo pendiente

Si la operación fue liquidada

Después mostrar tabla de análisis de fraccionamiento:

Monto del pago

Monto esperado

Diferencia

Si está dentro de tolerancia

Resaltar en rojo los pagos fuera de tolerancia.

7. Descripción de la alerta

Mostrar:

Descripción generada por el sistema.

Después mostrar:

Razones del sistema que justifican la alerta.

Este bloque debe ser legible y separado del resto de datos.

8. Reportes relacionados

Mostrar tabla de reportes generados con:

Tipo de reporte

Periodo

Monto

Estatus

Fecha de operación

Si el reporte ya fue enviado, marcarlo visualmente.

Reglas UX

Usar cards para cada sección

Usar tablas para pagos y análisis

Usar badges para estatus

Evitar bloques grandes de texto

Priorizar escaneabilidad

Mostrar primero lo crítico

Resultado esperado

Generar la estructura visual de la pantalla lista para implementar en un dashboard administrativo moderno.

La salida debe ser organizada jerárquicamente para frontend, priorizando experiencia de usuario.
