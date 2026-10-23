<?php

use App\Mail\ReenvioCorreo;
use App\Models\CatCorreoNotificacion;
use App\Models\LogCorreo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function crearCorreoLog(array $overrides = []): LogCorreo
{
    return LogCorreo::create(array_merge([
        'Destinatario' => 'cliente@example.com',
        'NombreDestinatario' => 'Cliente',
        'Asunto' => 'Aviso PLD',
        'Tipo' => 'OficialCumplimiento',
        'AlertasCount' => 0,
        'CuerpoHtml' => '<html><body>Contenido</body></html>',
        'Truncado' => false,
        'Estado' => 'Enviado',
    ], $overrides));
}

test('invitados no pueden reenviar correos', function () {
    $correo = crearCorreoLog();

    $this->post(route('correos.reenviar', $correo->id))
        ->assertRedirect(route('login'));
});

test('reenvía el html almacenado al oficial configurado y lo registra', function () {
    Mail::fake();

    $user = User::factory()->create();
    CatCorreoNotificacion::create([
        'Archivo' => 'OficialCumplimiento',
        'Correo' => 'oficial@example.com',
        'Nombre' => 'Oficial de Cumplimiento',
        'Activo' => 1,
    ]);
    $correo = crearCorreoLog();

    $this->actingAs($user)
        ->from(route('correos.index'))
        ->post(route('correos.reenviar', $correo->id))
        ->assertRedirect(route('correos.index'));

    Mail::assertSent(ReenvioCorreo::class, function (ReenvioCorreo $mail) {
        return $mail->hasTo('oficial@example.com')
            && $mail->asunto === 'Reenvío: Aviso PLD'
            && $mail->cuerpoHtml === '<html><body>Contenido</body></html>';
    });

    $this->assertDatabaseHas('logCorreos', [
        'Tipo' => 'Reenvio',
        'Destinatario' => 'oficial@example.com',
        'NombreDestinatario' => 'Oficial de Cumplimiento',
        'Estado' => 'Enviado',
    ]);
});

test('no reenvía si no hay oficial configurado', function () {
    Mail::fake();

    $user = User::factory()->create();
    $correo = crearCorreoLog();

    $this->actingAs($user)
        ->from(route('correos.index'))
        ->post(route('correos.reenviar', $correo->id))
        ->assertRedirect(route('correos.index'));

    Mail::assertNothingSent();
    $this->assertDatabaseHas('logCorreos', [
        'Tipo' => 'Reenvio',
        'Estado' => 'OmitidoSinConfig',
    ]);
});

test('no reenvía correos sin html almacenado', function () {
    Mail::fake();

    $user = User::factory()->create();
    CatCorreoNotificacion::create([
        'Archivo' => 'OficialCumplimiento',
        'Correo' => 'oficial@example.com',
        'Nombre' => 'Oficial de Cumplimiento',
        'Activo' => 1,
    ]);
    $correo = crearCorreoLog(['CuerpoHtml' => null]);

    $this->actingAs($user)
        ->from(route('correos.index'))
        ->post(route('correos.reenviar', $correo->id))
        ->assertRedirect(route('correos.index'));

    Mail::assertNothingSent();
    $this->assertDatabaseHas('logCorreos', [
        'Tipo' => 'Reenvio',
        'Estado' => 'Fallido',
    ]);
});
