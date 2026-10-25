<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El archivo subido no se puede interpretar como una malla 3D (STL/OBJ) válida.
 */
class InvalidMeshException extends RuntimeException
{
}
