<?php

return [
    'version_metodologia' => '2025',

    'escala' => [
        'muy_alto' => 4,
        'alto' => 3,
        'medio' => 2,
    ],

    'niveles_efectividad' => [
        'Informal' => 0.25,
        'Estandarizado' => 0.5,
        'Monitoreado' => 0.75,
        'Optimizado' => 1.0,
    ],

    'institucion' => [
        'pesos' => [
            'productos' => 0.20,
            'clientes' => 0.20,
            'geografia' => 0.20,
            'canales' => 0.20,
            'transacciones' => 0.20,
        ],
    ],

    'productos' => [
        'offset' => 0.4,
        'grupos' => ['Agrícola', 'Ganadero', 'Otro', 'Reaseguro Tomado'],
        'indicadores' => [
            ['codigo' => '1.1', 'nombre' => 'Producto reconocido como potencialmente de alto riesgo', 'peso' => 0.5],
            ['codigo' => '1.2', 'nombre' => 'El producto permite uso de efectivo o divisas', 'peso' => 0.3],
            ['codigo' => '1.3', 'nombre' => 'Permite que la DDC la realice un tercero', 'peso' => 0.3],
            ['codigo' => '1.4', 'nombre' => 'Permite pagos anticipados, reembolsos o cancelaciones', 'peso' => 0.1],
            ['codigo' => '1.5', 'nombre' => 'Permite alto volumen o alto valor de transacciones', 'peso' => 0.2],
        ],
        'puntajes' => [
            'Agrícola' => [1, 2, 1, 5, 5],
            'Ganadero' => [1, 2, 1, 5, 5],
            'Otro' => [1, 2, 1, 5, 5],
            'Reaseguro Tomado' => [1, 2, 1, 5, 5],
        ],
    ],

    'clientes' => [
        'pf' => [
            'offset' => 0.4,
            'referencia_metrica' => 'pm',
            'indicadores' => [
                ['clave' => 'pais', 'codigo' => '2.1.1', 'nombre' => 'País de Nacionalidad', 'peso' => 0.10, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'edad', 'codigo' => '2.1.2', 'nombre' => 'Edad del cliente', 'peso' => 0.20, 'escala' => [5, 1, 1, 5, 3]],
                ['clave' => 'duracion', 'codigo' => '2.1.3', 'nombre' => 'Duración de la relación comercial', 'peso' => 0.25, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'domicilio', 'codigo' => '2.1.4', 'nombre' => 'Lugar de residencia', 'peso' => 0.20, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'actividad', 'codigo' => '2.1.5', 'nombre' => 'Actividad Económica', 'peso' => 0.45, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'listas', 'codigo' => '2.1.6', 'nombre' => 'Clientes identificados en listas de riesgo', 'peso' => 0.20, 'escala' => [5, 3, 2, 1]],
            ],
            'etiquetas' => [
                'pais' => ['Riesgo Alto', 'Riesgo Medio', 'Riesgo Bajo', 'No definido'],
                'edad' => ['18 - 25 años', '26 - 40 años', '41 - 60 años', '61 o más años', 'No definido'],
                'duracion' => ['0 - 1 año', '1 - 3 años', 'Más de 4 años', 'No definido'],
                'domicilio' => ['Riesgo Alto', 'Riesgo Medio', 'Riesgo Bajo', 'No definido'],
                'actividad' => ['Riesgo Alto', 'Riesgo Medio', 'Riesgo Bajo', 'No definido'],
                'listas' => ['Personas bloqueadas', 'Otras listas de riesgo', 'PEP nacional', 'Sin coincidencia'],
            ],
        ],
        'pm' => [
            'offset' => 0.35,
            'referencia_metrica' => 'pm',
            'indicadores' => [
                ['clave' => 'pais', 'codigo' => '2.2.1', 'nombre' => 'País de Nacionalidad', 'peso' => 0.10, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'antiguedad', 'codigo' => '2.2.2', 'nombre' => 'Antigüedad de la empresa', 'peso' => 0.20, 'escala' => [5, 4, 2, 1, 3]],
                ['clave' => 'duracion', 'codigo' => '2.2.3', 'nombre' => 'Duración de la relación comercial', 'peso' => 0.20, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'domicilio', 'codigo' => '2.2.4', 'nombre' => 'Domicilio', 'peso' => 0.20, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'actividad', 'codigo' => '2.2.6', 'nombre' => 'Actividad Económica', 'peso' => 0.45, 'escala' => [5, 3, 1, 3]],
                ['clave' => 'listas', 'codigo' => '2.2.9', 'nombre' => 'Clientes identificados en listas de riesgo', 'peso' => 0.20, 'escala' => [5, 3, 2, 1]],
            ],
            'etiquetas' => [
                'pais' => ['Riesgo Alto', 'Riesgo Medio', 'Riesgo Bajo', 'No definido'],
                'antiguedad' => ['0 - 2 años', '3 - 5 años', '6 - 10 años', 'Más de 10 años', 'No definido'],
                'duracion' => ['0 - 1 año', '1 - 3 años', 'Más de 4 años', 'No definido'],
                'domicilio' => ['Riesgo Alto', 'Riesgo Medio', 'Riesgo Bajo', 'No definido'],
                'actividad' => ['Riesgo Alto', 'Riesgo Medio', 'Riesgo Bajo', 'No definido'],
                'listas' => ['Personas bloqueadas', 'Otras listas de riesgo', 'PEP nacional', 'Sin coincidencia'],
            ],
        ],
    ],

    'geografia' => [
        'pais' => [
            'peso' => 1.0,
            'indicador' => '3.1 Jerarquización Basilea para LD/FT',
        ],
        'estados' => [
            'indicadores' => [
                ['codigo' => '3.2', 'nombre' => 'Índice de paz', 'peso' => 0.6],
                ['codigo' => '3.3', 'nombre' => 'Estados de frontera', 'peso' => 0.4],
            ],
        ],
    ],

    'canales' => [
        'offset' => 0.2,
        'grupos' => ['Interno', 'Agente'],
        'indicadores' => [
            ['codigo' => '4.1', 'nombre' => 'El canal es no presencial', 'peso' => 0.2],
            ['codigo' => '4.2', 'nombre' => 'El canal es operado por terceros', 'peso' => 0.5],
            ['codigo' => '4.3', 'nombre' => 'El canal permite mover alto volumen de recursos', 'peso' => 0.2],
            ['codigo' => '4.4', 'nombre' => 'El canal involucra uso de efectivo', 'peso' => 0.3],
        ],
        'puntajes' => [
            'Interno' => [1, 1, 5, 1],
            'Agente' => [1, 5, 5, 1],
        ],
    ],

    'transacciones' => [
        'indicadores' => [
            ['codigo' => '5.1', 'nombre' => 'Operaciones realizadas por personas políticamente expuestas', 'puntaje' => 3, 'patron' => 'PPE', 'patrones' => ['PPE']],
            ['codigo' => '5.2', 'nombre' => 'Pago de la póliza en pagos fraccionados', 'puntaje' => 5, 'patron' => 'Fraccionado', 'patrones' => ['Fraccionado']],
            ['codigo' => '5.3', 'nombre' => 'Pago total y posterior cancelación', 'puntaje' => 3, 'patron' => 'Cancelacion', 'patrones' => ['Cancelacion', 'Cancelado']],
            ['codigo' => '5.4', 'nombre' => 'Operaciones por montos mayores o iguales a 7,500 USD en efectivo', 'puntaje' => 4, 'patron' => 'Monto', 'patrones' => ['Monto', 'Acumulado Efectivo']],
        ],
        'estatus_procedentes' => ['Reportado', 'Enviado', 'Dictaminado', 'Concluido'],
        'patrones_ignorados' => ['Personas Bloqueadas', 'Nuevo', 'Actividad vulnerable', 'Zona geografica', 'Zona geográfica', 'Monto Inusual'],
        'denominador_alertas' => 0.1,
    ],

    'mitigantes' => [
        ['id' => 1, 'nombre' => 'Pago en moneda nacional', 'scope' => 'producto_1_2', 'max' => 0.20, 'nivel' => 'Optimizado'],
        ['id' => 2, 'nombre' => 'DD Reforzada a clientes de alto riesgo', 'scope' => 'cliente_actividad', 'max' => 0.20, 'nivel' => 'Optimizado'],
        ['id' => 3, 'nombre' => 'Debida Diligencia del Cliente', 'scope' => 'cliente_elemento', 'max' => 0.10, 'nivel' => 'Optimizado'],
        ['id' => 4, 'nombre' => 'Gobierno corporativo', 'scope' => 'entidad', 'max' => 0.05, 'nivel' => 'Optimizado'],
        ['id' => 5, 'nombre' => 'Auditoría en PLD', 'scope' => 'entidad', 'max' => 0.10, 'nivel' => 'Optimizado'],
        ['id' => 6, 'nombre' => 'Estructuras internas', 'scope' => 'entidad', 'max' => 0.05, 'nivel' => 'Optimizado'],
        ['id' => 7, 'nombre' => 'Capacitación', 'scope' => 'entidad', 'max' => 0.05, 'nivel' => 'Optimizado'],
        ['id' => 8, 'nombre' => 'Control interno', 'scope' => 'entidad', 'max' => 0.05, 'nivel' => 'Optimizado'],
        ['id' => 9, 'nombre' => 'Sistemas automatizados', 'scope' => 'entidad', 'max' => 0.05, 'nivel' => 'Optimizado'],
    ],

    'categorias_pld' => [
        'personas bloqueadas' => 5,
        'otras listas de riesgo' => 3,
        'pep nacional' => 2,
        'sin coincidencia en listas' => 1,
    ],

    'csv' => [
        'clientes' => [
            'headers' => [
                'no_poliza', 'id_operacion', 'folio_endoso', 'tipo_movimiento',
                'no_cliente', 'nombre_cliente', 'tipo_persona', 'rfc', 'curp',
                'fecha_nacimiento', 'fecha_constitucion', 'fecha_primera_relacion',
                'edad', 'antiguedad_empresa_anios', 'antiguedad_relacion_anios',
                'ocupacion_giro', 'estado', 'pais_nacionalidad', 'pais_operacion',
                'categoria_pld', 'canal', 'agente', 'ramo', 'producto', 'grupo_producto',
                'prima_total', 'moneda', 'tipo_cambio', 'fecha_emision', 'num_operaciones_entrada',
            ],
            'required' => ['no_poliza', 'no_cliente', 'tipo_persona', 'prima_total', 'fecha_emision'],
            'ejemplo' => [
                'POL-2025-000001', 'OP-2025-000001', '', 'Emisión',
                '1001', 'Cliente Ejemplo', 'Persona Física', 'XAXX010101000', '',
                '1980-05-10', '', '2020-01-15', '', '', '', 'Agricultor', 'Querétaro', 'México', 'México',
                'Sin coincidencia en listas', 'Interno', '0-Agente Directo', 'Agrícola', 'Elemental', 'Agrícola',
                '15000.00', 'MXN', '1', '2025-01-10', '1',
            ],
            'ejemplos' => [
                [
                    'POL-2025-000001', 'OP-2025-000001', '', 'Emisión',
                    '1001', 'Cliente Ejemplo', 'Persona Física', 'XAXX010101000', '',
                    '1980-05-10', '', '2020-01-15', '', '', '', 'Agricultor', 'Querétaro', 'México', 'México',
                    'Sin coincidencia en listas', 'Interno', '0-Agente Directo', 'Agrícola', 'Elemental', 'Agrícola',
                    '15000.00', 'MXN', '1', '2025-01-10', '1',
                ],
                [
                    'POL-2025-000001', 'OP-2025-000001-END', 'END-2025-000001', 'Endoso',
                    '1001', 'Cliente Ejemplo', 'Persona Física', 'XAXX010101000', '',
                    '1980-05-10', '', '2020-01-15', '', '', '', 'Agricultor', 'Querétaro', 'México', 'México',
                    'Sin coincidencia en listas', 'Interno', '0-Agente Directo', 'Agrícola', 'Elemental', 'Agrícola',
                    '', 'MXN', '1', '2025-06-15', '1',
                ],
            ],
        ],
        'siniestros' => [
            'headers' => [
                'no_siniestro', 'no_poliza', 'no_cliente', 'fecha_pago', 'monto_pagado',
                'moneda', 'tipo_cambio', 'num_operaciones_salida', 'estado', 'producto',
                'grupo_producto', 'canal',
            ],
            'required' => ['no_siniestro', 'no_poliza', 'monto_pagado'],
            'ejemplo' => [
                'SIN-2025-000001', 'POL-2025-000001', '1001', '2025-06-15', '25000.00',
                'MXN', '1', '1', 'Querétaro', 'Elemental', 'Agrícola', 'Interno',
            ],
        ],
        'alertas' => [
            'headers' => [
                'patron', 'no_cliente', 'no_poliza', 'id_operacion', 'fecha_deteccion',
                'monto', 'moneda', 'tipo_cambio', 'instrumento_monetario', 'agente',
                'estatus', 'procedio', 'num_operaciones', 'descripcion', 'razones',
            ],
            'required' => ['patron', 'fecha_deteccion'],
            'ejemplo' => [
                'Fraccionado', '1001', 'POL-2025-000001', 'OP-2025-000001', '2025-06-15',
                '50000.00', 'MXN', '1', 'Transferencia', '0-Agente Directo',
                'Enviado', 'Sí', '2', 'Pago fraccionado', 'Pago en dos exhibiciones',
            ],
        ],
    ],
];
