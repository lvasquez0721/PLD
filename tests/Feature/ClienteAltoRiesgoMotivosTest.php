<?php

use App\Http\Controllers\ClientesController;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbPerfilTransaccional;

/**
 * Valida que el detalle del cliente incluya motivos de alto riesgo tanto de
 * las alertas PLD como del perfil transaccional.
 */
beforeEach(function () {
    $this->controller = new ClientesController;
    $this->motivos = new ReflectionMethod($this->controller, 'motivosAltoRiesgo');
    $this->motivosPerfil = new ReflectionMethod($this->controller, 'motivosPerfilTransaccional');
});

test('usa las alertas guardadas cuando no hay factores actuales', function () {
    $cliente = new TbClientes(['EsAltoRiesgo' => true]);

    $alertas = collect([
        new TbAlertas([
            'Patron' => 'Actividad vulnerable',
            'Razones' => 'Ocupación/giro con NivelRiesgo = 3.',
        ]),
        new TbAlertas([
            'Patron' => 'Zona geografica',
            'Descripcion' => 'Domicilio en estado de alto riesgo.',
        ]),
    ]);

    $factores = $this->motivos->invoke($this->controller, $cliente, $alertas, [], []);

    expect($factores)->toHaveCount(2)
        ->and($factores[0])->toContain('Actividad vulnerable')
        ->and($factores[0])->toContain('NivelRiesgo = 3')
        ->and($factores[1])->toContain('Zona geografica')
        ->and($factores[1])->toContain('Domicilio en estado de alto riesgo');
});

test('agrega los motivos del perfil transaccional cuando Perfil es 2 o mayor', function () {
    $perfil = new TbPerfilTransaccional([
        'Perfil' => '2.000000',
        'NivelRiesgoNac' => 2,
        'CalculoNacimiento' => '0.300000',
        'NivelRiesgoResidencia' => 2,
        'CalculoResidencia' => '0.300000',
        'NivelRiesgo' => 2,
        'CalculoOcupacion' => '0.300000',
        'OcupGiro' => 'Servicios',
        'TotalUbicacion' => '0.600000',
        'TotalEconomico' => '1.100000',
    ]);

    $motivosPerfil = $this->motivosPerfil->invoke($this->controller, $perfil);
    $factores = $this->motivos->invoke(
        $this->controller,
        new TbClientes(['EsAltoRiesgo' => false]),
        collect(),
        [],
        $motivosPerfil
    );

    expect($motivosPerfil)->toContain('Nacionalidad: nivel Medio; aporte 0.30.')
        ->and($motivosPerfil)->toContain('Residencia: nivel Medio; aporte 0.30.')
        ->and($motivosPerfil)->toContain('Ocupación/giro: "Servicios"; nivel Medio; aporte 0.30.')
        ->and($motivosPerfil)->toContain('Ubicación total: 0.60.')
        ->and($motivosPerfil)->toContain('Económico total: 1.10.')
        ->and($factores)->toContain('Perfil transaccional: Perfil total calculado: 2.00 (alto riesgo a partir de 2).');
});

test('no agrega motivos del perfil cuando Perfil es menor a 2', function () {
    $perfil = new TbPerfilTransaccional([
        'Perfil' => '1.000000',
        'NivelRiesgoNac' => 1,
        'CalculoNacimiento' => '0.100000',
    ]);

    expect($this->motivosPerfil->invoke($this->controller, $perfil))->toBeEmpty();
});
