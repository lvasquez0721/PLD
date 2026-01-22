<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CatCategoriaPersonasBloqueadas extends Model
{

    // Comentarios estáticos para cada categoría de PLD
    const COMENTARIOS_PLD = [
        1 => 'Tláloc seguros no podrá ofrecer ningún producto con la persona o empresa por ser una persona que aparece en listas bloqueadas',
        2 => 'Se necesita revisión de la empresa / persona registrada debido a que se encuentra en listas bloqueadas y su nombre no coincide al 100%. Se necesita realizar esta verificación antes de emitir un producto con esta persona',
        3 => 'Tláloc seguros podrá trabajar con la persona políticamente expuesta',
        4 => 'Se necesita revisión de la persona registrada debido a que se encuentra en listas políticamente expuestas y su nombre no coincide al 100%. Se necesita realizar esta verificación antes de emitir un producto con esta persona',
        5 => 'Persona / empresa revisada y autorizada para trabajar con Tláloc Seguros',
        6 => 'Persona detectada en listas y no fue encontrada en las categorías de Tláloc Seguros. Se necesita realizar esta verificación antes de emitir un producto con esta persona',
        7 => 'Persona / empresa detectada en Listas internas con base oficios CNSF. Se necesita realizar esta verificación antes de emitir un producto con esta persona.',
    ];

    // Nombre de la tabla
    protected $table = 'cat_categoria_personas_bloqueadas';

    // Nombre de la clave primaria
    protected $primaryKey = 'ID';

    // Laravel no espera que el PK sea string ni tenga increment
    public $incrementing = true;
    protected $keyType = 'int';

    // No timestamps en la migración original
    public $timestamps = false;

    // Atributos asignables masivamente
    protected $fillable = [
        'IDCategoria',
        'Categoria',
        'Color',
    ];

    // Obtiene la categoría de PLD por su ID
    public static function getCategoriaPLD($IDCategoria) {
        if (empty($IDCategoria)) {
            return null;
        }
        return self::where('ID', $IDCategoria)->value('Categoria');
    }

    //Obtiene los comentarios de detección según la categoría de PLD
    public static function getComentariosDeteccion($IDCategoria) {
        if (empty($IDCategoria)) {
            return 'Categoría no especificada.';
        }

        return self::COMENTARIOS_PLD[$IDCategoria] ?? 'Categoría no encontrada.';
    }

    // Obtiene el código del mensaje, ajuste de SIT a PLD
    public static function getCodigoMensaje($IDCategoria) {
        return 'PLD' . str_pad($IDCategoria, 3, '0', STR_PAD_LEFT);
    }

    //Obtiene todo los datos de una categoría de PLD
    public static function getDatosCompletos($IDCategoria) {
        $categoria = self::find($IDCategoria);

        if (!$categoria) {
            return null;
        }

        return [
            'id' => $categoria->ID,
            'idCategoria' => $categoria->IDCategoria,
            'categoria' => $categoria->Categoria,
            'color' => $categoria->Color,
            'codigo' => self::getCodigoMensaje($categoria->IDCategoria),
            'comentarios' => self::getComentariosDeteccion($categoria->IDCategoria),
        ];
    }
}
