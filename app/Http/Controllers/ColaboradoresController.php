<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;

class ColaboradoresController extends ClientesController
{
    protected string $tipoPersona = TbClientes::TIPO_COLABORADOR;

    protected string $rutaBase = '/colaboradores';

    protected string $tituloModulo = 'Colaboradores';

    protected string $sustantivoSingular = 'colaborador';

    protected string $sustantivoPlural = 'colaboradores';
}
