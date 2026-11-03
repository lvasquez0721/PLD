SELECT
    D.FolioDictamen AS no_siniestro,
    D.Poliza AS no_poliza,
    C.NCliente AS no_cliente,
    D.FechaSolicitudPago AS fecha_pago,
    (D.Indemnizacion - D.Descuentos) AS monto_pagado,

    CASE
        WHEN UPPER(LTRIM(RTRIM(ISNULL(M.Moneda, 'Pesos Mexicanos')))) LIKE '%DOLAR%'
            THEN 'Dolares americanos'
        ELSE 'Pesos Mexicanos'
    END AS moneda,

    CASE
        WHEN UPPER(LTRIM(RTRIM(ISNULL(M.Moneda, 'Pesos Mexicanos')))) LIKE '%DOLAR%'
            THEN COALESCE(TC.DOF_dolar, 1.0)
        ELSE 1.0
    END AS tipo_cambio,

    1 AS num_operaciones_salida,
    E.Estado AS estado,
    PR.Producto AS producto,
    R.Ramo AS grupo_producto,

    CASE
        WHEN P.IDAgente IS NULL OR P.IDAgente = 0
            THEN 'Venta directa'
        ELSE 'Agente'
    END AS canal

FROM (
    SELECT
        D.FolioDictamen,
        A.Poliza,
        D.FechaSolicitudPago,
        SUM(AD.Indemnizacion) AS Indemnizacion,
        COALESCE((
            SELECT SUM(DD.Descuento)
            FROM siniestros.tbDescuentosDictamen DD
            WHERE DD.FolioDictamen = D.FolioDictamen
        ), 0) AS Descuentos
    FROM siniestros.tbDictamen D
    INNER JOIN siniestros.tbAjusteDetalleAgricola AD
        ON AD.FolioAjuste = D.FolioAjuste
    INNER JOIN siniestros.tbAvisos A
        ON A.FolioAviso = AD.FolioAviso
    WHERE SUBSTRING(D.FolioDictamen, 13, 1) IN ('0', '6')
    GROUP BY
        D.FolioDictamen,
        A.Poliza,
        D.FechaSolicitudPago

    UNION ALL

    SELECT
        D.FolioDictamen,
        A.Poliza,
        D.FechaSolicitudPago,
        SUM(AD.Indemnizacion) AS Indemnizacion,
        COALESCE((
            SELECT SUM(DD.Descuento)
            FROM siniestros.tbDescuentosDictamen DD
            WHERE DD.FolioDictamen = D.FolioDictamen
        ), 0) AS Descuentos
    FROM siniestros.tbDictamen D
    INNER JOIN siniestros.tbAjusteDetalleGanadero AD
        ON AD.FolioAjuste = D.FolioAjuste
    INNER JOIN siniestros.tbAvisos A
        ON A.FolioAviso = AD.FolioAviso
    WHERE SUBSTRING(D.FolioDictamen, 13, 1) = '1'
    GROUP BY
        D.FolioDictamen,
        A.Poliza,
        D.FechaSolicitudPago

    UNION ALL

    SELECT
        D.FolioDictamen,
        A.Poliza,
        D.FechaSolicitudPago,
        SUM(AD.Indemnizacion) AS Indemnizacion,
        COALESCE((
            SELECT SUM(DD.Descuento)
            FROM siniestros.tbDescuentosDictamen DD
            WHERE DD.FolioDictamen = D.FolioDictamen
        ), 0) AS Descuentos
    FROM siniestros.tbDictamen D
    INNER JOIN siniestros.tbAjusteDetalleAgricolaCat AD
        ON AD.FolioAjuste = D.FolioAjuste
    INNER JOIN siniestros.tbAvisos A
        ON A.FolioAviso = AD.FolioAviso
    WHERE SUBSTRING(D.FolioDictamen, 13, 1) = '3'
    GROUP BY
        D.FolioDictamen,
        A.Poliza,
        D.FechaSolicitudPago
) AS D

INNER JOIN pol.tbPolizas P
    ON P.NoPoliza = D.Poliza COLLATE SQL_Latin1_General_CP1_CI_AS

INNER JOIN pol.tbSolicitudes S
    ON S.IDSolicitud = P.IDSolicitud

INNER JOIN ele.tbSolicitantes C
    ON C.IDSolicitante = S.IDSolicitante

INNER JOIN ele.CatProductos PR
    ON PR.IDProducto = CASE
        WHEN P.IDProducto = 21 THEN 0
        ELSE P.IDProducto
    END

INNER JOIN ele.CatRamos R
    ON R.IDRamo = P.IDRamo

INNER JOIN ele.cat_Estados E
    ON E.IdEstado = P.IDEstado

LEFT JOIN ele.CatMoneda M
    ON M.IDMoneda = P.IDMoneda

LEFT JOIN cat.Cat_TipoCambio TC
    ON TC.fecha = CAST(D.FechaSolicitudPago AS DATE)

WHERE D.FechaSolicitudPago >= '20250101'
  AND D.FechaSolicitudPago < '20260101'

ORDER BY D.FolioDictamen DESC;