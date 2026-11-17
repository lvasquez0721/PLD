DELIMITER $$
CREATE PROCEDURE `SP_PerfilTransIndividual`(IN `param_IDSolicitante` INT, IN `param_IDTipoEjecuccion` INT, IN `param_NoPoliza` VARCHAR(50))
BEGIN
    DECLARE PorNacimiento DECIMAL(18,6);
    DECLARE PorResidencia DECIMAL(18,6);
    DECLARE PorPredio DECIMAL(18,6);
    DECLARE PorNacionalidad DECIMAL(18,6);
    DECLARE PorAmbitoLaboral DECIMAL(18,6);
    DECLARE PorUbicacion DECIMAL(18,6);
    DECLARE PorOrigenRecursos DECIMAL(18,6);
    DECLARE PorIngresosEstimados DECIMAL(18,6);
    DECLARE PorPromedioUR DECIMAL(18,6);
    DECLARE PorDatosEconomicos DECIMAL(18,6);
    DECLARE PorDatosLaborales DECIMAL(18,6);
    DECLARE Desviacion DECIMAL(18,6);
    DECLARE Nivel DECIMAL(18,6);
    DECLARE totalHa DECIMAL(18,6);
    DECLARE TienePoliza INT;
    DECLARE NacimientoCorrecto INT;
    DECLARE TieneSubsidios DECIMAL(18,6);
    
    DECLARE Nombre VARCHAR(1000);
    DECLARE IDEstadoSolicitante INT;
    DECLARE TipoSolicitante INT;
    DECLARE OcupacionGiro VARCHAR(1000);
    DECLARE EdoNacimiento VARCHAR(2);
    DECLARE NivelRiesgoNac DECIMAL(18,6);
    DECLARE CalculoNac DECIMAL(18,6);
    DECLARE IDEstadoDom INT;
    DECLARE NivelRiesgoDom DECIMAL(18,6);
    DECLARE CalculoResidencia DECIMAL(18,6);
    DECLARE IDEstadoLabora INT;
    DECLARE NivelRiesgoLab DECIMAL(18,6);
    DECLARE CalculoLabora DECIMAL(18,6);
    DECLARE TotalUbicacionGeografica DECIMAL(18,6);
    DECLARE OrigenRecursos INT;
    DECLARE CadenaOrigenRecursos VARCHAR(100);
    DECLARE CalculoOrigenRecursos DECIMAL(18,6);
    DECLARE AVGPrimaTotal DECIMAL(22,10);
    DECLARE STDEVPrimaTotal DECIMAL(18,10);
    DECLARE IngresoEstimado DECIMAL(18,6);
    DECLARE ValorIngresoEstimado INT;
    DECLARE CalculoIngresoEstimado DECIMAL(18,6);
    DECLARE AVGHaTotal DECIMAL(18,10);
    DECLARE STDEVHaTotal DECIMAL(18,10);
    DECLARE HaEstimada DECIMAL(18,6);
    DECLARE ValorHaEstimado INT;
    DECLARE CalculoHaEstimado DECIMAL(18,6);
    DECLARE TotalEconomicos DECIMAL(18,6);
    DECLARE NivelRiesgoOcupacion DECIMAL(18,6);
    DECLARE TotalLaboral DECIMAL(18,6);
    DECLARE Perfil DECIMAL(18,6);
    DECLARE Nacionalidad VARCHAR(100);
    
    DECLARE PolizaReferencia VARCHAR(40);
    DECLARE EndosoReferencia VARCHAR(40);
    DECLARE TotalDocsReferencia INT;

    DECLARE FechaPagoPolizaReferencia DATE;
    DECLARE ActivacionReglaDolares BOOLEAN DEFAULT FALSE;

    
    DECLARE MontoMinimoAlertaPesos DECIMAL(18,6);
    DECLARE TipoCambioDolar DECIMAL(18,6);
    DECLARE MontoMinimoAlertaDolares DECIMAL(18,6);
    DECLARE FechaEvaluacion DATE;
    
    -- Establecer collation consistente para toda la sesión
    DECLARE CONTINUE HANDLER FOR 1267
    BEGIN
        -- Si hay error de collation, establecer una consistente
        SET @collation_error = 1;
    END;
    
    DROP TEMPORARY TABLE IF EXISTS T1;
    CREATE TEMPORARY TABLE T1 (
        ID INT AUTO_INCREMENT PRIMARY KEY,
        VALOR VARCHAR(255)
    ) ENGINE=Memory;
    
    DROP TEMPORARY TABLE IF EXISTS T2;
    CREATE TEMPORARY TABLE T2 (
        Nopoliza VARCHAR(50),
        NoEndoso VARCHAR(50),
        PrimaTotal DECIMAL(18,6),
        FEmision DATE,
        StatusPol VARCHAR(50),
        NoRPP VARCHAR(50),
        SuperAsegurada DECIMAL(18,6)
    ) ENGINE=Memory;
    
    DROP TEMPORARY TABLE IF EXISTS T3;
    CREATE TEMPORARY TABLE T3 (
        Nopoliza VARCHAR(50),
        PrimaTotal DECIMAL(18,6),
        SuperAsegurada DECIMAL(18,6)
    ) ENGINE=Memory;

    SELECT 
        PorcentajeNacimiento / 100.00,
        PorcentajeResidencia / 100.00,
        PorcentajePredio / 100.00,
        PorcentajeNacionalidad / 100.00,
        PorcentajeAmbitoLaboral / 100.00,
        PorcentajeUbicacion / 100.00,
        PorcentajeOrigenRecursos / 100.00,
        PorcentajeIngresosEstimados / 100.00,
        PorcentajePromedioUR / 100.00,
        PorcentajeDatosEconomicos / 100.00,
        PorcentajeDatosLaborales / 100.00
    INTO 
        PorNacimiento,
        PorResidencia,
        PorPredio,
        PorNacionalidad,
        PorAmbitoLaboral,
        PorUbicacion,
        PorOrigenRecursos,
        PorIngresosEstimados,
        PorPromedioUR,
        PorDatosEconomicos,
        PorDatosLaborales
    FROM catParametrosPerfilTrans 
    ORDER BY FechaActualizacion DESC
    LIMIT 1;

    SELECT 
        c.IDCliente, 
        CONCAT(c.Nombre, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno),
        SUBSTRING(c.CURP, 12, 2),
        cd.IDEstado, 
        og.OcupacionGiro, 
        c.IDTipoPersona
    INTO 
        param_IDSolicitante,
        Nombre,
        EdoNacimiento,
        IDEstadoSolicitante,
        OcupacionGiro,
        TipoSolicitante
    FROM tbClientes c 
    LEFT JOIN catOcupacionesGiros og ON og.IDOcupacionGiro = c.IDOcupacionGiro
    LEFT JOIN tbClientesDomicilio cd ON cd.IDCliente = c.IDCliente
    WHERE c.IDCliente = param_IDSolicitante
    LIMIT 1;

    IF TipoSolicitante = 0 THEN
    	/* PERSONA MORAL */
        SET NivelRiesgoNac = 0;
        SET CalculoNac = 0;

		SELECT COUNT(*) INTO NacimientoCorrecto FROM catEstados WHERE IDEstado = IDEstadoSolicitante ;
       
        IF NacimientoCorrecto = 0 THEN
            SET NivelRiesgoNac = 3;
        ELSE
            SELECT CAST(IndicePaz AS DECIMAL(18,6)) INTO NivelRiesgoNac FROM catEstados WHERE IDEstado = IDEstadoSolicitante ;
        END IF;

		SET CalculoNac = CAST(NivelRiesgoNac AS DECIMAL(18,6)) * PorNacionalidad * PorUbicacion;
		
        SELECT IDEstado INTO IDEstadoDom FROM tbClientesDomicilio WHERE IDCliente = param_IDSolicitante LIMIT 1;
        
        SELECT CAST(IndicePaz AS DECIMAL(18,6)) INTO NivelRiesgoDom FROM catEstados WHERE IdEstado = IDEstadoDom LIMIT 1;

        SET CalculoResidencia = CAST(NivelRiesgoDom AS DECIMAL(18,6)) * PorAmbitoLaboral * PorUbicacion;
        SET IDEstadoLabora = IDEstadoDom;

        SELECT CAST(IndicePaz AS DECIMAL(18,6)) INTO NivelRiesgoLab FROM catEstados WHERE IdEstado = IDEstadoLabora LIMIT 1;

        SET CalculoLabora = CAST(NivelRiesgoLab AS DECIMAL(18,6)) * PorAmbitoLaboral * PorUbicacion;

    ELSE    
    	/* PERSONA FISICA */
        SELECT COUNT(*) INTO NacimientoCorrecto FROM catEstados WHERE CveEntidad COLLATE utf8mb4_unicode_ci = EdoNacimiento COLLATE utf8mb4_unicode_ci;
       
        IF NacimientoCorrecto = 0 THEN
            SET NivelRiesgoNac = 3;
        ELSE
            SELECT CAST(IndicePaz AS DECIMAL(18,6)) INTO NivelRiesgoNac FROM catEstados WHERE CveEntidad COLLATE utf8mb4_unicode_ci = EdoNacimiento COLLATE utf8mb4_unicode_ci LIMIT 1;
        END IF;
        
        SET CalculoNac = CAST(NivelRiesgoNac AS DECIMAL(18,6)) * PorNacimiento * PorUbicacion;
        
        SELECT IDEstado INTO IDEstadoDom FROM tbClientesDomicilio WHERE IDCliente = param_IDSolicitante LIMIT 1;

        SELECT CAST(IndicePaz AS DECIMAL(18,6)) INTO NivelRiesgoDom FROM catEstados WHERE IdEstado = IDEstadoDom LIMIT 1;
        
        SET CalculoResidencia = CAST(NivelRiesgoDom AS DECIMAL(18,6)) * PorResidencia * PorUbicacion;

        SELECT COUNT(*) INTO TienePoliza FROM tbOperaciones WHERE IDCliente = param_IDSolicitante;

        IF TienePoliza = 0 THEN
            SET IDEstadoLabora = IDEstadoDom;
        ELSE
            SELECT 
                E.IDEstado
            INTO IDEstadoLabora
            FROM tbOperaciones O
            LEFT JOIN tbClientes C ON C.IDCliente = O.IDCliente
            LEFT JOIN tbClientesDomicilio CD ON CD.IDCliente = C.IDCliente
            LEFT JOIN catEstados E ON CD.IDEstado = E.IDEstado
            WHERE O.IDCliente = param_IDSolicitante
            GROUP BY E.IDEstado
            ORDER BY COUNT(*) DESC
            LIMIT 1;
        END IF;
    
        SELECT CAST(IndicePaz AS DECIMAL(18,6)) INTO NivelRiesgoLab FROM catEstados WHERE IdEstado = IDEstadoLabora LIMIT 1;
        
        SET CalculoLabora = CAST(NivelRiesgoLab AS DECIMAL(18,6)) /** PorPredio*/ * PorUbicacion;
    END IF;
    
    SET TotalUbicacionGeografica = /*CalculoLabora +*/ CalculoResidencia + CalculoNac;
    
    SELECT Preguntas INTO CadenaOrigenRecursos FROM tbClientes WHERE IDCliente = param_IDSolicitante LIMIT 1;
    
    IF CadenaOrigenRecursos IS NOT NULL THEN
        INSERT INTO T1 (VALOR)
        SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(CadenaOrigenRecursos, ':', n), ':', -1)) AS valor
        FROM (
            SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL 
            SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
        ) AS numbers
        WHERE n <= LENGTH(CadenaOrigenRecursos) - LENGTH(REPLACE(CadenaOrigenRecursos, ':', '')) + 1;
    END IF;
    
    SELECT 
        CASE 
            WHEN VALOR = 'Si' THEN 1 
            ELSE 3 
        END INTO OrigenRecursos
    FROM T1 
    WHERE ID = 2
    LIMIT 1;
    
    SELECT COALESCE(SUM(PrimaTotal), 0) INTO TieneSubsidios FROM tbOperaciones WHERE IDCliente = param_IDSolicitante;
           
    IF TieneSubsidios > 0 AND OrigenRecursos = 3 THEN
        SET OrigenRecursos = 1;
    END IF;
    
    SET CalculoOrigenRecursos = CAST(COALESCE(OrigenRecursos, 3) AS DECIMAL(18,2)) * PorOrigenRecursos * PorDatosEconomicos;
    
    IF param_IDTipoEjecuccion = 1 THEN
        INSERT INTO T2 (Nopoliza, NoEndoso, PrimaTotal, FEmision, SuperAsegurada)
        SELECT  
            FolioPoliza AS Nopoliza, 
            COALESCE(FolioEndoso, '') AS NoEndoso, 
            SUM(PrimaTotal) AS PrimaTotal, 
            MAX(FechaEmision) AS FEmision, 
            2 AS SuperAsegurada
        FROM tbOperaciones 
        WHERE IDCliente = param_IDSolicitante 
            AND BINARY tipoDocumento NOT IN ('Rehabilitación', 'Modificación') 
        GROUP BY FolioPoliza, FolioEndoso;
        
        SELECT 
            Nopoliza,
            FEmision 
        INTO 
            PolizaReferencia,
            FechaPagoPolizaReferencia
        FROM T2
        ORDER BY FEmision DESC
        LIMIT 1;

        SELECT 
            COALESCE(SUM(PrimaTotal), 0),
            COALESCE(SUM(SuperAsegurada), 0)
        INTO 
            IngresoEstimado,
            HaEstimada
        FROM T2 
        WHERE Nopoliza = PolizaReferencia;
        
        IF PolizaReferencia IS NOT NULL THEN
            DELETE FROM T2 WHERE Nopoliza = PolizaReferencia;
        END IF;
        
        INSERT INTO T3 (Nopoliza, PrimaTotal, SuperAsegurada)
        SELECT 
            Nopoliza,
            SUM(PrimaTotal) AS PrimaTotal,
            SUM(SuperAsegurada) AS HaTotal
        FROM T2
        GROUP BY Nopoliza;
        
        SELECT COUNT(*) INTO TotalDocsReferencia FROM T3;
        
        SELECT 
            COALESCE(AVG(PrimaTotal), 0),
            COALESCE(STDDEV_SAMP(PrimaTotal), 0),
            COALESCE(AVG(SuperAsegurada), 0),
            COALESCE(STDDEV_SAMP(SuperAsegurada), 0)
        INTO 
            AVGPrimaTotal,
            STDEVPrimaTotal,
            AVGHaTotal,
            STDEVHaTotal
        FROM T3;
    ELSE
        INSERT INTO T2 (Nopoliza, NoEndoso, PrimaTotal, FEmision, SuperAsegurada)
        SELECT
            O.FolioPoliza AS Nopoliza,
            O.FolioEndoso AS NoEndoso,
            SUM(O.PrimaTotal) AS PrimaTotal,
            MAX(O.FechaEmision) AS FEmision,
            2 AS SuperAsegurada
        FROM tbOperaciones O
        WHERE O.IDCliente = param_IDSolicitante
            AND BINARY O.tipoDocumento <> 'Modificación'
        GROUP BY O.FolioPoliza, O.FolioEndoso;
            
        SELECT COUNT(*) INTO TotalDocsReferencia FROM T2;
        
        SELECT 
            COALESCE(AVG(PrimaTotal), 0),
            COALESCE(STDDEV_SAMP(PrimaTotal), 0),
            COALESCE(AVG(SuperAsegurada), 0),
            COALESCE(STDDEV_SAMP(SuperAsegurada), 0)
        INTO 
            AVGPrimaTotal,
            STDEVPrimaTotal,
            AVGHaTotal,
            STDEVHaTotal
        FROM (
            SELECT 
                Nopoliza,
                SUM(PrimaTotal) AS PrimaTotal,
                SUM(SuperAsegurada) AS SuperAsegurada
            FROM T2 
            GROUP BY Nopoliza
        ) AS DatosAgrupados;
    END IF;
    
    SET ValorIngresoEstimado = CASE 
        WHEN IngresoEstimado <= AVGPrimaTotal + (1 * STDEVPrimaTotal) THEN 1
        WHEN IngresoEstimado > AVGPrimaTotal + (1 * STDEVPrimaTotal) 
            AND IngresoEstimado <= AVGPrimaTotal + (2 * STDEVPrimaTotal) THEN 2
        WHEN IngresoEstimado > AVGPrimaTotal + (2 * STDEVPrimaTotal) THEN 3
        ELSE 1
    END;
    
    IF ValorIngresoEstimado <> 1 THEN
        IF param_IDTipoEjecuccion = 1 THEN
            SET FechaEvaluacion = FechaPagoPolizaReferencia;
        ELSEIF param_IDTipoEjecuccion = 2 THEN
            SET FechaEvaluacion = CURDATE();
        END IF;
        
        SELECT valor INTO MontoMinimoAlertaPesos FROM catParametriaPLD WHERE BINARY Parametro = 'Monto minimo alerta' LIMIT 1;
                
        SELECT DOFDolar INTO TipoCambioDolar FROM catTipoCambio WHERE fecha = FechaEvaluacion LIMIT 1;
        
        IF TipoCambioDolar IS NULL THEN
            SELECT DOFDolar INTO TipoCambioDolar FROM catTipoCambio ORDER BY fecha DESC LIMIT 1;
        END IF;
    
        SET MontoMinimoAlertaDolares = MontoMinimoAlertaPesos * TipoCambioDolar;
        
        IF (AVGPrimaTotal - (3 * STDEVPrimaTotal)) < MontoMinimoAlertaDolares THEN
            SET ValorIngresoEstimado = 1;
            SET ActivacionReglaDolares = TRUE;
        END IF;
    END IF;
    
    IF TotalDocsReferencia < 3 AND STDEVPrimaTotal = 0 THEN
        SET ValorIngresoEstimado = 1;
    END IF;
    
    SET CalculoIngresoEstimado = CAST(COALESCE(ValorIngresoEstimado, 1) AS DECIMAL(18,2)) * PorIngresosEstimados * PorDatosEconomicos;
    
    SELECT COUNT(*) INTO totalHa FROM tbOperaciones WHERE IDCliente = param_IDSolicitante;
    
    IF totalHa > 0 THEN
        SET ValorHaEstimado = CASE 
            WHEN HaEstimada <= AVGHaTotal + (1 * STDEVHaTotal) THEN 1
            WHEN HaEstimada > AVGHaTotal + (1 * STDEVHaTotal) 
                AND HaEstimada <= AVGHaTotal + (2 * STDEVHaTotal) THEN 2
            WHEN HaEstimada > AVGHaTotal + (2 * STDEVHaTotal) THEN 3
            ELSE 1
        END;
    ELSE
        SET ValorHaEstimado = 1;
    END IF;
    
    IF TotalDocsReferencia < 3 AND STDEVHaTotal = 0 THEN
        SET ValorHaEstimado = 1;
    END IF;
    
    SET CalculoHaEstimado = CAST(COALESCE(ValorHaEstimado, 1) AS DECIMAL(18,2)) * PorPromedioUR * PorDatosEconomicos;
    
    SET TotalEconomicos = COALESCE(CalculoOrigenRecursos, 0) + COALESCE(CalculoIngresoEstimado, 0) /*+ COALESCE(CalculoHaEstimado, 0)*/;
    
    SELECT NivelRiesgo INTO NivelRiesgoOcupacion 
    FROM catOcupacionesGiros cog 
    WHERE cog.OcupacionGiro LIKE CONCAT('%', OcupacionGiro, '%') 
    LIMIT 1;

    IF NivelRiesgoOcupacion IS NULL THEN
        SET NivelRiesgoOcupacion = 2;
    END IF;
    
    SET TotalLaboral = CAST(COALESCE(NivelRiesgoOcupacion, 2) AS DECIMAL(18,2)) * PorDatosLaborales;
    
    SET Perfil = COALESCE(TotalUbicacionGeografica, 0) + COALESCE(TotalEconomicos, 0) + COALESCE(TotalLaboral, 0);
    
    INSERT INTO tbPerfilTransaccional (
        IDCliente, TipoSolicitante, IDEstadoNacimiento, NivelRiesgoNac, CalculoNacimiento,
        IDEstadoDomicilio, NivelRiesgoDoc, CalculoResidencia, IDEstadoLabora,
        NivelRiesgoResidencia, CalculoLaboral, TotalUbicacion, Origen,
        ORecursos, IngresosMensuales, PromedioHA, TotalEconomico, OcupGiro, NivelRiesgo,
        CalculoOcupacion, Perfil, FechaEjecucción, IDTipoEjecuccion, AVGPrimaTotal,
        AVGHaTotal, STDEVPrimaTotal, STDEVHaTotal, origenRecursos,
        ValorIngresoEstimado, ValorHaEstimado, TimeStamp, created_at, updated_at
    ) VALUES (
        param_IDSolicitante, 
		TipoSolicitante,
        COALESCE(EdoNacimiento, ''), 
        COALESCE(NivelRiesgoNac, 0), 
        COALESCE(CalculoNac, 0),
        COALESCE(IDEstadoDom, 0), 
        COALESCE(NivelRiesgoDom, 0), 
        COALESCE(CalculoResidencia, 0), 
        COALESCE(IDEstadoLabora, 0),
        COALESCE(NivelRiesgoDom, 0), 
        COALESCE(CalculoLabora, 0), 
        COALESCE(TotalUbicacionGeografica, 0), 
        COALESCE(OrigenRecursos, 0),
        COALESCE(CalculoOrigenRecursos, 0), 
        COALESCE(CalculoIngresoEstimado, 0), 
        COALESCE(CalculoHaEstimado, 0), 
        COALESCE(TotalEconomicos, 0), 
        COALESCE(OcupacionGiro, ''), 
        COALESCE(NivelRiesgoOcupacion, 2),
        COALESCE(TotalLaboral, 0), 
        COALESCE(Perfil, 0), 
        CURDATE(), 
        param_IDTipoEjecuccion, 
        COALESCE(AVGPrimaTotal, 0), 
        COALESCE(AVGHaTotal, 0),
        COALESCE(STDEVPrimaTotal, 0), 
        COALESCE(STDEVHaTotal, 0), 
        COALESCE(OrigenRecursos, 0),
        COALESCE(IngresoEstimado, 0), 
        COALESCE(ValorHaEstimado, 0),  
        NOW(), 
        NOW(), 
        NOW()
    );
    
    DROP TEMPORARY TABLE IF EXISTS T1;
    DROP TEMPORARY TABLE IF EXISTS T2;
    DROP TEMPORARY TABLE IF EXISTS T3;

END$$
DELIMITER ;