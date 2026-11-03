<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;

class AgentesController extends ClientesController
{
    protected string $tipoPersona = TbClientes::TIPO_AGENTE;

    protected string $rutaBase = '/agentes';

    protected string $tituloModulo = 'Agentes';

    protected string $sustantivoSingular = 'agente';

    protected string $sustantivoPlural = 'agentes';
}
