USE BDSIT;
GO

DECLARE @Anio INT = 2025;

SELECT
    A.Patron                                                          AS patron,
    A.NCliente                                                        AS no_cliente,
    A.NoPoliza                                                        AS no_poliza,
    A.NoOperacion                                                     AS id_operacion,
    FORMAT(TRY_CAST(A.FechaDeteccion AS date), 'dd/MM/yyyy')          AS fecha_deteccion,
    FORMAT(TRY_CAST(A.Monto AS decimal(18,2)), 'N2', 'es-MX')         AS monto,
    CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(M.Moneda, ''))) COLLATE Latin1_General_CI_AI) LIKE '%DOLAR%' THEN 'Dolares americanos' ELSE 'Pesos Mexicanos' END AS moneda,
    CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(M.Moneda, ''))) COLLATE Latin1_General_CI_AI) LIKE '%DOLAR%' THEN COALESCE(TC.DOF_dolar, 1.0) ELSE 1.0 END AS tipo_cambio,
    CASE TRY_CAST(A.InstrumentoMonetario AS int)
         WHEN 0 THEN 'Efectivo'
         WHEN 1 THEN 'Cheque'
         WHEN 2 THEN 'Transferencia'
         WHEN 3 THEN 'Deposito'
         ELSE ''
    END                                                               AS instrumento_monetario,
    CASE
         WHEN TRY_CAST(A.Agente AS int) IS NULL
           OR TRY_CAST(A.Agente AS int) = 0 THEN 'Venta directa'
         ELSE S.Nombre + SPACE(1) + S.APaterno + SPACE(1) + S.AMaterno
    END                                                               AS agente,
    A.Estatus                                                         AS estatus,
    CASE WHEN A.Estatus IN ('Reportado','Enviado','Dictaminado','Concluido') THEN 'Sí' ELSE 'No' END AS procedio,
    (SELECT COUNT(*)
       FROM cobranza.tbPagosClientes PC
      WHERE PC.Poliza = A.NoPoliza)                                   AS num_operaciones,
    A.Descripcion                                                     AS descripcion,
    A.Razones                                                         AS razones
FROM intranet.tbAlertas A
LEFT JOIN pol.tbPolizas P
       ON P.NoPoliza = A.NoPoliza
LEFT JOIN ele.tbSolicitantes S
       ON S.IDSolicitante = TRY_CAST(A.Agente AS int)
LEFT JOIN ele.CatMoneda M
       ON M.IDMoneda = P.IDMoneda
LEFT JOIN cat.Cat_TipoCambio TC
       ON TC.fecha = CAST(A.FechaDeteccion AS DATE)
WHERE A.FechaDeteccion >= DATEFROMPARTS(@Anio, 1, 1)
  AND A.FechaDeteccion <  DATEFROMPARTS(@Anio + 1, 1, 1)
ORDER BY A.IDAlertas ASC;