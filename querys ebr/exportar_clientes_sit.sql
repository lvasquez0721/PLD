USE BDSIT;
GO

DECLARE @FechaCorte DATE = DATEFROMPARTS(2025, 12, 31);

WITH EntradasPorPoliza AS (
    SELECT PC.Poliza AS NoPoliza,
           COUNT(*) AS num_operaciones_entrada
    FROM cobranza.tbPagosClientes AS PC
    INNER JOIN pol.tbPolizas AS P ON P.NoPoliza = PC.Poliza
    WHERE YEAR(P.FEmisionPoliza) = YEAR(@FechaCorte)
    GROUP BY PC.Poliza
),
PrimeraRelacion AS (
    SELECT SOL.IDSolicitante, MIN(P.FEmisionPoliza) AS fecha_primera_relacion
    FROM pol.tbSolicitudes SOL
    INNER JOIN pol.tbPolizas P ON P.IDSolicitud = SOL.IDSolicitud
    GROUP BY SOL.IDSolicitante
),
DomicilioFiscal AS (
    SELECT IDSolicitante, IDEstado,
           ROW_NUMBER() OVER (
               PARTITION BY IDSolicitante
               ORDER BY CASE WHEN TipoDomicilio = 1 THEN 0 ELSE 1 END
           ) AS rn
    FROM ele.tbDomicilios
    WHERE IDEstado IS NOT NULL AND IDEstado <> ''
),
Clientes AS (
    SELECT
        S.IDSolicitante,
        S.NCliente,
        LTRIM(RTRIM(CONCAT(S.Nombre, ' ', S.APaterno, ' ', S.AMaterno))) AS nombre_cliente,
        S.RFC, S.CURP, S.FechaNC, S.TimeStampAlta,
        S.OcupGiro, S.PNacimiento, S.ckAgente, S.NAgente,
        S.ListaNegra,
        CASE
            WHEN NULLIF(LTRIM(RTRIM(S.CURP)), '') IS NOT NULL THEN 'Persona Física'
            WHEN LEN(LTRIM(RTRIM(ISNULL(S.RFC, '')))) = 12 THEN 'Persona Moral'
            WHEN LEN(LTRIM(RTRIM(ISNULL(S.RFC, '')))) = 13 THEN 'Persona Física'
            ELSE NULL
        END AS tipo_persona
    FROM ele.tbSolicitantes S
),
Operaciones AS (
    -- ===== Emisiones (una fila por póliza) =====
    SELECT
        P.NoPoliza                                AS no_poliza,
        P.NoPoliza                                AS id_operacion,
        CAST(NULL AS VARCHAR(80))                 AS folio_endoso,
        CAST('Emisión' AS VARCHAR(40))            AS tipo_movimiento,
        P.IDSolicitud,
        P.IDRamo,
        P.IDSubramo,
        P.IDProducto,
        P.IDMoneda,
        P.PrimaTotal,
        P.FEmisionPoliza                          AS fecha_emision
    FROM pol.tbPolizas P

    UNION ALL

    -- ===== Endosos (una fila por endoso) =====
    SELECT
        PE.NoPoliza                              AS no_poliza,
        PE.NoPoliza                              AS id_operacion,
        PE.Noendoso                              AS folio_endoso,
        CAST('Endoso' AS VARCHAR(40))            AS tipo_movimiento,
        PE.IDSolicitud,
        PE.IDRamo,
        PE.IDSubramo,
        (SELECT TOP 1 I.IDProducto
           FROM pol.tbIncisos I
          WHERE I.NoPoliza = PE.NoPoliza
            AND I.NoEndoso = PE.Noendoso
          ORDER BY I.IDProducto)                 AS IDProducto,
        P2.IDMoneda,
        (SELECT SUM(I.PrimaTotal)
           FROM pol.tbIncisos I
          WHERE I.NoPoliza = PE.NoPoliza
            AND I.NoEndoso = PE.Noendoso)         AS PrimaTotal,
        PE.FEmision                               AS fecha_emision
    FROM pol.tbPolizasEndoso PE
    LEFT JOIN pol.tbPolizas P2 ON P2.NoPoliza = PE.NoPoliza
)
SELECT
    Op.no_poliza,
    CAST(Op.id_operacion AS VARCHAR(80)) AS id_operacion,
    Op.folio_endoso,
    Op.tipo_movimiento,
    C.NCliente AS no_cliente,
    C.nombre_cliente,
    C.tipo_persona,
    C.RFC AS rfc,
    C.CURP AS curp,
    CASE WHEN C.tipo_persona = 'Persona Física' THEN C.FechaNC END AS fecha_nacimiento,
    CASE WHEN C.tipo_persona = 'Persona Moral'   THEN C.FechaNC END AS fecha_constitucion,
    PR.fecha_primera_relacion,
    CASE WHEN C.tipo_persona = 'Persona Física' THEN DATEDIFF(YEAR, C.FechaNC, @FechaCorte) END AS edad,
    DATEDIFF(YEAR, C.TimeStampAlta, @FechaCorte) AS antiguedad_empresa_anios,
    DATEDIFF(YEAR, PR.fecha_primera_relacion, @FechaCorte) AS antiguedad_relacion_anios,
    C.OcupGiro AS ocupacion_giro,
    CASE
        WHEN UPPER(LTRIM(RTRIM(ISNULL(E.Estado, '')))) IN ('CDMX', 'DF', 'CIUDAD DE MEXICO', 'CIUDAD DE MÉXICO') THEN 'Ciudad de México'
        WHEN UPPER(LTRIM(RTRIM(ISNULL(E.Estado, '')))) IN ('ESTADO DE MEXICO', 'ESTADO DE MÉXICO', 'EDO. DE MEXICO', 'EDO DE MEXICO', 'MEXICO', 'MÉXICO') THEN 'México'
        ELSE E.Estado
    END AS estado,
    C.PNacimiento AS pais_nacionalidad,
    CAST('México' AS VARCHAR(100)) AS pais_operacion,
    CASE
        WHEN ISNULL(C.ListaNegra, 0) IN (1, 2) THEN 'Personas bloqueadas'
        WHEN ISNULL(C.ListaNegra, 0) IN (3, 4) THEN 'PEP nacional'
        WHEN ISNULL(C.ListaNegra, 0) IN (5, 6, 7) THEN 'Otras listas de riesgo'
        ELSE 'Sin coincidencia en listas'
    END AS categoria_pld,
    CASE WHEN ISNULL(C.ckAgente, 0) = 0 THEN 'Interno' ELSE 'Agente' END AS canal,
    CASE WHEN ISNULL(C.ckAgente, 0) = 0 THEN '0-Agente Directo' ELSE CAST(C.NAgente AS VARCHAR(100)) END AS agente,
    R.Ramo AS ramo,
    PRD.Producto AS producto,
    SR.SubRamo AS grupo_producto,
    CAST(COALESCE(Op.PrimaTotal, 0) AS DECIMAL(18,2)) AS prima_total,
    M.Moneda AS moneda,
    CAST(CASE
            WHEN UPPER(LTRIM(RTRIM(ISNULL(M.Moneda, ''))) COLLATE Latin1_General_CI_AI) LIKE '%DOLAR%'
                THEN COALESCE(TCX.DOF_dolar, 1)
            ELSE 1
         END AS DECIMAL(18,6)) AS tipo_cambio,
    Op.fecha_emision,
    ISNULL(EP.num_operaciones_entrada, 0) AS num_operaciones_entrada
FROM Operaciones Op
INNER JOIN pol.tbSolicitudes SOL ON SOL.IDSolicitud = Op.IDSolicitud
INNER JOIN Clientes C ON C.IDSolicitante = SOL.IDSolicitante
LEFT JOIN EntradasPorPoliza EP ON EP.NoPoliza = Op.no_poliza
LEFT JOIN PrimeraRelacion PR ON PR.IDSolicitante = C.IDSolicitante
LEFT JOIN DomicilioFiscal DF ON DF.IDSolicitante = C.IDSolicitante AND DF.rn = 1
LEFT JOIN ele.cat_Estados E ON E.IdEstado = DF.IDEstado
LEFT JOIN ele.CatRamos R ON R.IDRamo = Op.IDRamo
LEFT JOIN ele.CatSubRamos SR ON SR.PKSubRamo = Op.IDSubramo
LEFT JOIN ele.CatProductos PRD ON PRD.IDProducto = Op.IDProducto
LEFT JOIN ele.CatMoneda M ON M.IDMoneda = Op.IDMoneda
OUTER APPLY (
    SELECT TOP 1 TC.DOF_dolar
    FROM Cat.Cat_TipoCambio AS TC
    WHERE TC.fecha <= Op.fecha_emision
    ORDER BY TC.fecha DESC
) AS TCX
ORDER BY C.NCliente, Op.no_poliza, Op.tipo_movimiento;