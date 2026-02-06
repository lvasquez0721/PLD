<?php

/*
Autor: Leonardo Vásquez
Fecha de creación: 28/06/2022
Ultima modificación: 03/11/2022
Descripción: Detección de alertas para la aplicación de pagos por ingreso en Cobranza
*/

namespace PLD\Alertas;

class AnalizaPagos
{
    // Variable para la conexión a la base de datos
    private $conexion;

    // Variables para las alertas
    private $existeAlerta;

    private $alerta_acumuladoEfectivo;

    private $alerta_fraccionado;

    private $alerta_fraccionado_descartado;

    private $alerta_montoInusual;

    private $alerta_monto;

    private $alerta_ppe;

    private $montoMinimoAlerta;

    private $montoMinimoAlertaMXN;

    private $estatusAlerta;

    // Variables para analisis del pago
    private $IDCliente;

    private $documentos;

    private $importePagado;

    private $formaPago;

    private $fechaPago;

    private $tipoCambioDolar;

    private $folioMovBanco;

    private $detallePagos;

    // Variables para el registro de la alerta
    private $poliza;

    private $numeroPagosDoc;

    private $pagosEfectivo;

    private $primaDocumentos;

    private $gastosEmision;

    private $docsAnalisis;

    private $totalAnalisis;

    private $fechaOperacion;

    private $horaOperacion;

    private $formaPagoPoliza;

    private $saldoPendientePago;

    private $detallePagosEfectivo;

    private $detallePagosFormato;

    private $OPERACION_RELEVANTE;

    public function __construct()
    {
        $this->conexion = new \procesos;
        $this->existeAlerta = false;
        $this->alerta_acumuladoEfectivo = false;
        $this->alerta_fraccionado = false;
        $this->alerta_fraccionado_descartado = false;
        $this->alerta_montoInusual = false;
        $this->alerta_monto = false;
        $this->alerta_ppe = false;
        $this->montoMinimoAlerta = \CatParametriaPLD::getMontoMinimoAlerta();
        $this->montoMinimoAlertaMXN = 0;
        $this->estatusAlerta =
        $this->IDCliente = null;
        $this->documentos = [];
        $this->importePagado = null;
        $this->formaPago = null;
        $this->fechaPago = null;
        $this->tipoCambioDolar = null;
        $this->folioMovBanco = null;
        $this->detallePagos = [];
        $this->poliza = null;
        $this->numeroPagosDoc = 0;
        $this->pagosEfectivo = 0;
        $this->primaDocumentos = null;
        $this->gastosEmision = null;
        $this->docsAnalisis = null;
        $this->totalAnalisis = 0;
        $this->fechaOperacion = date('Y-m-d');
        $this->horaOperacion = date('H:i:s');
        $this->formaPagoPoliza = null;
        $this->saldoPendientePago = null;
        $this->detallePagosEfectivo = null;
        $this->detallePagosFormato = null;
        $this->OPERACION_RELEVANTE = \CatParametriaPLD::getOperacionesRelevantes();
    }

    public function __destruct()
    {
        unset($this->conexion);
        unset($this->existeAlerta);
        unset($this->alerta_acumuladoEfectivo);
        unset($this->alerta_fraccionado);
        unset($this->alerta_fraccionado_descartado);
        unset($this->alerta_montoInusual);
        unset($this->alerta_monto);
        unset($this->alerta_ppe);
        unset($this->montoMinimoAlerta);
        unset($this->montoMinimoAlertaMXN);
        unset($this->IDCliente);
        unset($this->documentos);
        unset($this->importePagado);
        unset($this->formaPago);
        unset($this->fechaPago);
        unset($this->tipoCambioDolar);
        unset($this->folioMovBanco);
        unset($this->detallePagos);
        unset($this->poliza);
        unset($this->numeroPagosDoc);
        unset($this->pagosEfectivo);
        unset($this->primaDocumentos);
        unset($this->gastosEmision);
        unset($this->docsAnalisis);
        unset($this->totalAnalisis);
        unset($this->fechaOperacion);
        unset($this->horaOperacion);
        unset($this->formaPagoPoliza);
        unset($this->saldoPendientePago);
        unset($this->detallePagosEfectivo);
        unset($this->detallePagosFormato);
        unset($this->OPERACION_RELEVANTE);
    }

    public function doAnalizaPago($datos)
    {
        // Obtiene y asigna los datos a las variables
        $datos = \Sanitizador::getArregloSanitizado($datos);
        $this->documentos = $datos['documentos'];
        $this->fechaPago = $datos['fechaPago'];
        $this->folioMovBanco = $datos['folioMovBco'];
        $this->getTipoCambioDolar();
        $this->montoMinimoAlertaMXN = round(($this->tipoCambioDolar * $this->montoMinimoAlerta), 2);
        // $strDocumentos = $datos['strDocumentos'];
        // $cadenaDocs = "'".implode("', '", $documentos)."'";

        // Valida que se trate el pago de una sola póliza, en caso contrario lo separa
        $this->separaPolizas();

        // Realiza el analisis por cada uno de los registros sepadados.
        // echo "Polizas detectadas: ".count($this->docsAnalisis)."<br>";
        foreach ($this->docsAnalisis as $key => $registro) {
            $this->documentos = $registro;
            $this->poliza = $key;
            $this->doAnalizaPatronesAlerta();
            // echo "<br><br>";
        }
    }

    private function separaPolizas()
    {
        $this->docsAnalisis = [];
        $tmpPolizas = [];
        $poliza = '';

        foreach ($this->documentos as $registro) {
            $poliza = $registro['poliza'];
            if (! in_array($poliza, $tmpPolizas)) {
                $tmpPolizas[] = $poliza;

            }
            $this->docsAnalisis[$poliza][] = ['poliza' => $registro['poliza'],
                'endoso' => $registro['endoso'],
            ];
        }
    }

    public function doAnalizaPatronesAlerta()
    {

        // Obtiene la prima total de los documentos pagados
        $this->getPrimaAseguradoDocs();
        // echo "Documentos considerados: <br>";
        // print_r($this->documentos);
        // echo "<br><br>";
        // echo "Prima calculada: ".$this->primaDocumentos;
        // echo "<br><br>";

        // Obtener el importe pagado en la operación
        $this->geMontoPagado();
        // echo "Importe pagado: ".$this->importePagado;
        // echo "<br><br>";

        // Obtener el numero de pagos total al documento
        $this->getNumeroPagos();
        // echo "Numero de pagos realizados: ".$this->numeroPagosDoc;
        // echo "<br><br>";

        // Obtener el detalle de los pagos totales realizados al documento
        $this->getDetallePagosDescarte();

        // Aqui
        if ($this->numeroPagosDoc == 2) {
            $this->analizaPagosFraccionados();
        }

        // Obtener el numero de pagos en efectivo
        $this->getNumeroPagosEfectivo();
        // echo "Numero de pagos realizados en efectivo: ".$this->pagosEfectivo;
        // echo "<br><br>";

        // Obtener la forma de pago del movimiento realizado
        $this->getFormaPagoCobranza();
        // Obtener la forma de pago a nivel Póliza
        $this->getFormaPagoEmision();
        // echo "Forma de pago Cobranza: ".$this->formaPago;
        // echo "<br><br>";

        // Obtener el tipo de cambio de la operación al día inhabil inmediato anterior a la fecha de operacion
        $this->getTipoCambioDolar();
        $equivalenteDolares = round($this->importePagado / $this->tipoCambioDolar, 2);
        // echo "Tipo de cambio Dolar: ".$this->tipoCambioDolar;
        // echo "<br><br>";

        // echo "Umbral operacion relevante: ".$this->OPERACION_RELEVANTE;
        // echo "<br><br>";

        // echo "Equivalente operacion en dolarres: ".$equivalenteDolares;
        // echo "<br><br>";

        $this->getSaldoDocumentos();
        // echo "Saldo pendiente por cobrar documentos: ".$this->saldoPendientePago;
        // echo "<br><br>";

        $this->getDetallePagosEfectivo();
        $this->getDetallePagos();

        // Realizar el analisis de las alertas

        // Verificar si existe un patron fraccionado
        // La alerta fraccionada se detona al momento de estar pagado el documento al 100% y si existe más de una operación para pagar el 100% de la prima.
        if ($this->saldoPendientePago == 0) {
            if ($this->numeroPagosDoc > 1) {
                $this->existeAlerta = true;
                $this->alerta_fraccionado = true;
            }
        }

        // Verificar si existe un patron acumulado efectivo
        if ($this->saldoPendientePago == 0) {
            if ($this->numeroPagosDoc == $this->pagosEfectivo && $this->numeroPagosDoc > 1) {
                $this->existeAlerta = true;
                $this->alerta_acumuladoEfectivo = true;
            }
        }

        // Verificar si existe un patron por monto
        // La alerta por monto se genera cuando el monto pagado a los documentos es superior o igual al umbral equivalente en dolares y con forma de pago en efectivo

        if ($equivalenteDolares >= $this->OPERACION_RELEVANTE && $this->formaPago == 1) {
            $this->existeAlerta = true;
            $this->alerta_monto = true;
        }

        // Verificar si existe un patron Persona Politicamente Expuesta (PPE)
        // La alerta ppe se emite cuando existe una operación que haya sido detectada como persona politicamente expuesta
        $this->validarPPE();

        // Verificar si existe un patron de prima inusual emitida y pagada
        // La alerta se valida en prima instancia al liberar cualquier poliza o endoso y posteriormente si es pagado totalmente el documento se crea la alerta correspondiente
        if ($this->saldoPendientePago == 0) {
            $this->validaRegistroInusualidad();
        }

        // echo "Alerta Patron Fraccionado: ".($this->alerta_fraccionado ? 'Si' : 'No')."<br>";
        // echo "Alerta Patron Acumulado Efectivo: ".($this->alerta_acumuladoEfectivo ? 'Si' : 'No')."<br>";
        // echo "Alerta Patron Monto: ".($this->alerta_monto ? 'Si' : 'No')."<br>";
        // echo "Alerta Patron PPE: ".($this->alerta_ppe ? 'Si' : 'No')."<br>";
        // echo "Alerta Patron Monto Inusual: ".($this->alerta_montoInusual ? 'Si' : 'No')."<br>";

        $this->generaRegistrosAlertas();
    }

    // Obtener la forma de pago del movimiento realizado
    private function getFormaPagoCobranza()
    {
        $Q = 'SELECT Tipo FROM cobranza.tbEstado_de_cuenta_bancario where Folio_mov_bco = '.$this->folioMovBanco.';';
        $result = $this->conexion->SP_BD($Q);
        $this->formaPago = intval($result->fields[0]);
    }

    // Obtener la forma de pago a nivel Póliza
    private function getFormaPagoEmision()
    {
        $this->formaPagoPoliza = null;
        $Q = 'SELECT IDPoliza FROM sit.catMetPagoEquivalencia WHERE IDCobranza = '.$this->formaPago.';';
        $result = $this->conexion->SP_BD($Q);
        $this->formaPagoPoliza = intval($result->fields[0]);
    }

    private function getPrimaAseguradoDocs()
    {
        $this->primaDocumentos = 0;

        foreach ($this->documentos as $registro) {
            $Q = "SELECT Prima_asegurado, Gastos_Emision from cobranza.tbTotalPagos where Poliza = '".$registro['poliza']."' and Endoso = '".$registro['endoso']."'";
            $result = $this->conexion->SP_BD($Q);
            $this->primaDocumentos += round(floatval($result->fields[0]), 2);
            $this->gastosEmision += round(floatval($result->fields[1]), 2);
        }

    }

    private function geMontoPagado()
    {
        $this->importePagado = 0;

        foreach ($this->documentos as $registro) {
            $Q = "SELECT Importe_pago from cobranza.tbPagosClientes where Poliza = '".$registro['poliza']."' and Endoso = '".$registro['endoso']."' and Folio_mov_bco = ".$this->folioMovBanco.'';
            $result = $this->conexion->SP_BD($Q);
            $this->importePagado += round(floatval($result->fields[0]), 2);
        }
    }

    private function getNumeroPagos()
    {
        $this->numeroPagosDoc = 0;

        foreach ($this->documentos as $registro) {
            $Q = "SELECT COUNT(*) from cobranza.tbPagosClientes where Poliza = '".$registro['poliza']."' and Endoso = '".$registro['endoso']."'";
            $result = $this->conexion->SP_BD($Q);
            $this->numeroPagosDoc += round(floatval($result->fields[0]), 2);
        }
    }

    private function getDetallePagosDescarte()
    {
        $this->detallePagos = [];

        foreach ($this->documentos as $registro) {
            $Q = "SELECT Importe_pago from cobranza.tbPagosClientes where Poliza = '".$registro['poliza']."' and Endoso = '".$registro['endoso']."'";
            $result = $this->conexion->SP_BD($Q);
            while (! $result->EOF) {
                $this->detallePagos[] = round(floatval($result->fields[0]), 2);
                $result->MoveNext();
            }
        }
    }

    private function analizaPagosFraccionados()
    {
        $pago1 = $this->detallePagos[0];
        $pago2 = $this->detallePagos[1];
        $gastos = $this->gastosEmision;
        $prima = $this->primaDocumentos;
        $gastosCoincide = false;
        $primaCoincide = false;
        $resultado = null;
        $tolerancia = (\CatParametriaPLD::getToleranciaPagosFraccionados() / 100);

        // Buscar la tolerancia para los gastos de emision
        $toleranciaGastos = abs((($pago1 - $gastos) / $gastos));
        $gastosCoincide = ($toleranciaGastos <= $tolerancia);
        $toleranciaGastos = abs((($pago2 - $gastos) / $gastos));
        $gastosCoincide = ($gastosCoincide === true ? true : $toleranciaGastos <= $tolerancia);

        // Buscar la tolerancia para la prima
        $toleranciaPrima = abs((($pago1 - $prima) / $prima));
        $primaCoincide = ($toleranciaPrima <= $tolerancia);
        $toleranciaPrima = abs((($pago2 - $prima) / $prima));
        $primaCoincide = ($primaCoincide === true ? true : $toleranciaPrima <= $tolerancia);

        $this->alerta_fraccionado_descartado = $gastosCoincide && $primaCoincide;

    }

    private function getSaldoDocumentos()
    {
        $this->saldoPendientePago = 0;

        foreach ($this->documentos as $registro) {
            $Q = "SELECT Prima_asegurado-Total_pagos_cte FROM cobranza.tbTotalPagos where Poliza = '".$registro['poliza']."' and Endoso = '".$registro['endoso']."'";
            $result = $this->conexion->SP_BD($Q);
            $this->saldoPendientePago += round(floatval($result->fields[0]), 2);
        }
    }

    private function getNumeroPagosEfectivo()
    {
        $this->pagosEfectivo = 0;

        foreach ($this->documentos as $registro) {
            $Q = "SELECT
					count(*)
				from cobranza.tbPagosClientes PC
				JOIN cobranza.tbEstado_de_cuenta_bancario E ON PC.Folio_mov_bco = E.Folio_mov_bco
				where
					Poliza = '".$registro['poliza']."'
					AND Endoso = '".$registro['endoso']."'
					AND Tipo = 1";
            $result = $this->conexion->SP_BD($Q);
            $this->pagosEfectivo += round(floatval($result->fields[0]), 2);
        }
    }

    private function getDetallePagosEfectivo()
    {
        $this->detallePagosEfectivo = [];

        foreach ($this->documentos as $registro) {
            $Q = "SELECT
					PC.Importe_pago
				from cobranza.tbPagosClientes PC
				JOIN cobranza.tbEstado_de_cuenta_bancario E ON PC.Folio_mov_bco = E.Folio_mov_bco
				where
					Poliza = '".$registro['poliza']."'
					AND Endoso = '".$registro['endoso']."'
					AND Tipo = 1";
            // echo $Q."<br>";
            $result = $this->conexion->SP_BD($Q);
            while (! $result->EOF) {
                $this->detallePagosEfectivo[] = '$'.number_format($result->fields[0], 2);
                $result->MoveNext();
            }

        }
    }

    private function getDetallePagos()
    {
        $this->detallePagosFormato = [];

        foreach ($this->documentos as $registro) {
            $Q = "SELECT
					PC.Importe_pago
				from cobranza.tbPagosClientes PC
				JOIN cobranza.tbEstado_de_cuenta_bancario E ON PC.Folio_mov_bco = E.Folio_mov_bco
				where
					Poliza = '".$registro['poliza']."'
					AND Endoso = '".$registro['endoso']."'";
            // echo $Q."<br>";
            $result = $this->conexion->SP_BD($Q);
            while (! $result->EOF) {
                $this->detallePagosFormato[] = '$'.number_format($result->fields[0], 2);
                $result->MoveNext();
            }

        }
    }

    private function getTipoCambioDolar()
    {
        $offset = -1;
        $existeTipoCambio = false;

        // Verificar que el día habil anterior que exista tipo de cambio
        do {
            $Q = 'SELECT MAX(DOF_dolar) as PrimaDolar FROM cat.Cat_TipoCambio WHERE fecha = DATEADD(d,'.$offset.", CAST('".$this->fechaPago."' as DATE))";
            $result = $this->conexion->SP_BD($Q);
            if ($result->fields[0] === null) {
                $offset--;
            } else {
                $existeTipoCambio = true;
            }
        } while ($existeTipoCambio !== true);

        // Consultar el tipo de cambio del día hábil anterior
        $Q = 'SELECT MAX(DOF_dolar) as PrimaDolar FROM cat.Cat_TipoCambio WHERE fecha = DATEADD(d,'.$offset.", CAST('".$this->fechaPago."' as DATE))";
        $result = $this->conexion->SP_BD($Q);
        $this->tipoCambioDolar = $result->fields[0];
    }

    private function validaRegistroInusualidad()
    {
        $this->registrosInusuales = [];
        foreach ($this->documentos as $registro) {
            $Q = "SELECT LimiteInferior, LimiteSuperior, PrimaEmitida from pld.logCalculoInusualidadPrimaEmitida where PolizaEmitida = '".$registro['poliza']."' and EndosoEmitido = '".$registro['endoso']."' and Detectado = 1";
            $result = $this->conexion->SP_BD($Q);
            if ($result->_numOfRows > 0) {
                $this->registrosInusuales[] = ['poliza' => $registro['poliza'],
                    'endoso' => $registro['endoso'],
                    'limiteInferior' => round(floatval($result->fields[0]), 2),
                    'limiteSuperior' => round(floatval($result->fields[1]), 2),
                    'primaEmitida' => round(floatval($result->fields[2]), 2),
                ];
                $this->alerta_montoInusual = true;
            }
        }

    }

    private function validarPPE()
    {
        $Q = "SELECT
					DPPE.IDSolicitante
				FROM pld.catDeteccionPPE DPPE
				JOIN pol.tbSolicitudes S ON S.IDSolicitante = DPPE.IDSolicitante
				JOIN pol.tbPolizas P ON P.IDSolicitud = S.IDSolicitud
				WHERE NoPoliza = '".$this->poliza."'";
        $result = $this->conexion->SP_BD($Q);
        if ($result->_numOfRows > 0) {
            $this->existeAlerta = true;
            $this->alerta_ppe = true;
        }

    }

    private function generaCadenaDocs()
    {
        $documentos = [];
        $cadenaDocs = '';

        foreach ($this->documentos as $registro) {
            $aux = ($registro['endoso'] == '' ? $registro['poliza'] : $registro['poliza'].' / '.$registro['endoso']);
            $documentos[] = $aux;
        }

        $cadenaDocs = implode(', ', $documentos);

        return $cadenaDocs;
    }

    private function generaRegistrosAlertas()
    {
        // Ingresar el registro en el reporte regulatorio en caso de ser  un patron de monto
        if ($this->existeAlerta) {

            $strDocumentos = $this->generaCadenaDocs();

            // Obtencion de datos para las alertas
            $Q = "SELECT
					S.IDSolicitante,
					Nombre+SPACE(1)+APaterno+SPACE(1)+AMaterno AS Nom,
					P.IDAgente
				FROM pol.tbPolizas P
				INNER JOIN pol.tbSolicitudes So ON So.IDSolicitud = P.IDSolicitud
				INNER JOIN ele.tbSolicitantes S ON S.IDSolicitante = So.IDSolicitante
				WHERE
					NoPoliza = '".$this->poliza."';";
            $result = $this->conexion->SP_BD($Q);

            // variables auxiliares
            $idCliente = $result->fields[0];
            $cliente = $result->fields[1];
            $agente = $result->fields[2];

            if ($this->alerta_fraccionado === true && $this->alerta_fraccionado_descartado === false) {
                // Validar el estatus para generar la alerta
                $estatusFraccionado = ($this->primaDocumentos < $this->montoMinimoAlertaMXN ? 'Cerrado' : 'Generado');
                $leyendaAutomaticaCierre = ($this->primaDocumentos < $this->montoMinimoAlertaMXN ? '. La alerta ha sido cerrado automáticamente debido a que no es igual o superior al monto minimo de '.number_format($this->montoMinimoAlertaMXN, 2) : '');
                $leyendaAutomaticaCierre = utf8_decode($leyendaAutomaticaCierre);

                // Insertar la alerta del patron fraccionado
                $I_PF = "INSERT INTO intranet.tbAlertas (Patron,NCliente,Nombre,NoPoliza,FechaDeteccion,Hora,FechaOperacion,HoraOperacion,Monto,InstrumentoMonetario,Agente, Descripcion, Razones, Estatus) VALUES ('Fraccionado','".$idCliente."','".$cliente."','".$this->poliza."','".$this->fechaOperacion."', '".$this->horaOperacion."','".$this->fechaPago."', '".$this->horaOperacion."','".$this->primaDocumentos."', '".$this->formaPagoPoliza."', '".$agente."', 'Operaciones fraccionadas', 'Operaciones fraccionadas del pago total de los documentos ".$strDocumentos.'. Pagos realizados por '.implode(', ', $this->detallePagosFormato).$leyendaAutomaticaCierre."', '".$estatusFraccionado."');";
                $this->conexion->SP_BD($I_PF);
            }

            if ($this->alerta_monto === true) {
                // Insertar la alerta del patron monto
                $I_PM = "INSERT INTO intranet.tbAlertas (Patron,NCliente,Nombre,NoPoliza,FechaDeteccion,Hora,FechaOperacion,HoraOperacion,Monto,InstrumentoMonetario,Agente, Descripcion, Razones, Estatus) VALUES ('Monto','".$idCliente."','".$cliente."','".$this->poliza."','".$this->fechaOperacion."', '".$this->horaOperacion."','".$this->fechaPago."', '".$this->horaOperacion."','".$this->importePagado."', '".$this->formaPagoPoliza."', '".$agente."', 'Operacion relevante en Efectivo', 'Operacion mayor o igual a ".number_format($this->OPERACION_RELEVANTE, 2).' USD en Efectivo. Pago de los documentos '.$strDocumentos.', tomado tipo de cambio de '.round($this->tipoCambioDolar, 4)."','Reportado');";
                $this->conexion->SP_BD($I_PM);

                // Crear el registro para el reporte regulatorio
                $Q = "SELECT IDAlertas FROM intranet.tbAlertas WHERE Patron = 'Monto' and NoPoliza = '".$this->poliza."' and FechaDeteccion = '".$this->fechaOperacion."' and Hora = '".$this->horaOperacion."'";
                $result = $this->conexion->SP_BD($Q);
                $IDAlerta = $result->fields[0];

                $obj = new \PLD\Alertas\GeneraReporteRegulatorio;
                $resultado = $obj->creaRegistroRR($IDAlerta);

                // echo "Creacion del registro regulatorio: ".($resultado ? 'Si' : 'No')."<br>";
            }

            if ($this->alerta_acumuladoEfectivo === true) {
                // Validar el estatus para generar la alerta
                $estatusAcumuladoEfectivo = ($this->primaDocumentos < $this->montoMinimoAlertaMXN ? 'Cerrado' : 'Generado');
                $leyendaAutomaticaCierre = ($this->primaDocumentos < $this->montoMinimoAlertaMXN ? '. La alerta ha sido cerrado automáticamente debido a que no es igual o superior al monto minimo de '.number_format($this->montoMinimoAlertaMXN, 2) : '');
                $leyendaAutomaticaCierre = utf8_decode($leyendaAutomaticaCierre);

                // Insertar la alerta del patron monto
                $I_PM = "INSERT INTO intranet.tbAlertas (Patron,NCliente,Nombre,NoPoliza,FechaDeteccion,Hora,FechaOperacion,HoraOperacion,Monto,InstrumentoMonetario,Agente, Descripcion, Razones, Estatus) VALUES ('Acumulado Efectivo','".$idCliente."','".$cliente."','".$this->poliza."','".$this->fechaOperacion."', '".$this->horaOperacion."','".$this->fechaPago."', '".$this->horaOperacion."','".$this->primaDocumentos."', '".$this->formaPagoPoliza."', '".$agente."', 'Operaciones por montos acumulados en efectivo', 'Pago de los documentos ".$strDocumentos.' en mas de una operacion en Efectivo. Pagos realizados por '.implode(', ', $this->detallePagosEfectivo).$leyendaAutomaticaCierre."','".$estatusAcumuladoEfectivo."');";
                $this->conexion->SP_BD($I_PM);
            }

            if ($this->alerta_ppe === true) {
                // Validar el estatus para generar la alerta
                $estatusPPE = ($this->importePagado < $this->montoMinimoAlertaMXN ? 'Cerrado' : 'Generado');
                $leyendaAutomaticaCierre = ($this->primaDocumentos < $this->montoMinimoAlertaMXN ? '. La alerta ha sido cerrado automáticamente debido a que no es igual o superior al monto minimo de '.number_format($this->montoMinimoAlertaMXN, 2) : '');
                $leyendaAutomaticaCierre = utf8_decode($leyendaAutomaticaCierre);

                // Insertar la alerta del patron monto
                $I_PPE = "INSERT INTO intranet.tbAlertas (Patron,NCliente,Nombre,NoPoliza,FechaDeteccion,Hora,FechaOperacion,HoraOperacion,Monto,InstrumentoMonetario,Agente, Descripcion, Razones, Estatus) VALUES ('PPE','".$idCliente."','".$cliente."','".$this->poliza."','".$this->fechaOperacion."', '".$this->horaOperacion."','".$this->fechaPago."', '".$this->horaOperacion."','".$this->importePagado."', '".$this->formaPagoPoliza."', '".$agente."', 'Operacion por Persona Politicamente Expuesta".$leyendaAutomaticaCierre."', 'Pago de los documentos ".$strDocumentos.' por $'.number_format($this->importePagado)."','".$estatusPPE."');";
                $this->conexion->SP_BD($I_PPE);
            }

            $this->generaAlertasMontoInusual($idCliente, $cliente, $agente);

        }
    }

    private function generaAlertasMontoInusual($idCliente, $cliente, $agente)
    {
        $strDocumentos = $this->generaCadenaDocs();

        $totalMontosInusuales = count($this->registrosInusuales);

        if ($totalMontosInusuales > 0) {
            foreach ($this->registrosInusuales as $registro) {
                // Validar el estatus para generar la alerta
                $estatusMontoInusual = ($this->primaDocumentos < $this->montoMinimoAlertaMXN ? 'Cerrado' : 'Generado');
                $leyendaAutomaticaCierre = ($this->primaDocumentos < $this->montoMinimoAlertaMXN ? '. La alerta ha sido cerrado automáticamente debido a que no es igual o superior al monto minimo de '.number_format($this->montoMinimoAlertaMXN, 2) : '');
                $leyendaAutomaticaCierre = utf8_decode($leyendaAutomaticaCierre);

                $documento = ($registro['endoso'] == '' ? $registro['poliza'] : $registro['poliza'].' / '.$registro['endoso']);
                $razones = 'Se ha realizado el pago del '.$documento.' que ha superado el rango usual de primas emitidas del cliente, rango ['.number_format($registro['limiteInferior']).', '.number_format($registro['limiteSuperior']).']';

                $I_MI = "INSERT INTO intranet.tbAlertas (Patron,NCliente,Nombre,NoPoliza,FechaDeteccion,Hora,FechaOperacion,HoraOperacion,Monto,InstrumentoMonetario,Agente, Descripcion, Razones, Estatus) VALUES ('Monto inusual','".$idCliente."','".$cliente."','".$this->poliza."','".$this->fechaOperacion."', '".$this->horaOperacion."','".$this->fechaPago."', '".$this->horaOperacion."','".$this->primaDocumentos."', '".$this->formaPagoPoliza."', '".$agente."', 'Operacion por Monto de Prima Inusual Cliente".$leyendaAutomaticaCierre."', '".$razones."','".$estatusMontoInusual."');";

                $this->conexion->SP_BD($I_MI);
            }
        }
    }
}
