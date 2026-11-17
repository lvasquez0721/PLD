DELIMITER $$
CREATE PROCEDURE `SP_PerfilTransaccional`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_IDCliente INT;

    -- Cursor con tu nueva consulta
    DECLARE cur CURSOR FOR
        SELECT C.IDCliente
        FROM tbClientes C
        INNER JOIN tbClientesDomicilio D 
            ON D.IDCliente = C.IDCliente
        ORDER BY C.IDCliente ASC;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_IDCliente;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Llamada al SP individual (versión MySQL)
        CALL SP_PerfilTransIndividual(v_IDCliente, 1, '');
    END LOOP;

    CLOSE cur;
END$$
DELIMITER ;