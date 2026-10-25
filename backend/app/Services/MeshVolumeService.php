<?php

namespace App\Services;

use App\Exceptions\InvalidMeshException;

/**
 * Análisis geométrico de mallas STL (binario / ASCII) y OBJ.
 *
 * Devuelve volumen (|Σ volumen con signo de los tetraedros origen-triángulo|),
 * área total, área de triángulos que miran "hacia abajo" (aprox. área de soportes)
 * y bounding box. Los archivos se leen en streaming (STL binario por bloques,
 * ASCII/OBJ línea a línea); los vértices de un OBJ se guardan empaquetados
 * (24 bytes por vértice, 3 doubles) para no reventar la memoria con PHP arrays.
 *
 * Igual que el visor del frontend (StlViewer::processGeometry): si la dimensión
 * máxima es < 1 se asume que el modelo está en metros y se escala ×1000.
 */
class MeshVolumeService
{
    /** Triángulos procesados por bloque al leer STL binario. */
    private const BINARY_CHUNK_TRIANGLES = 4096;

    /** Normal con componente < -0.5 en un eje = cara en voladizo (mismo umbral que el frontend). */
    private const OVERHANG_THRESHOLD = -0.5;

    private float $signedVolume = 0.0;
    private float $area = 0.0;
    private float $downX = 0.0;
    private float $downY = 0.0;
    private float $downZ = 0.0;
    private int $triangles = 0;
    private float $minX = INF;
    private float $minY = INF;
    private float $minZ = INF;
    private float $maxX = -INF;
    private float $maxY = -INF;
    private float $maxZ = -INF;

    /**
     * @param string      $path       Ruta del archivo en disco.
     * @param string|null $extension  'stl' | 'obj' (si es null se deduce de la ruta).
     * @return array{
     *   volume_mm3: float, total_area_mm2: float, support_area_mm2: float,
     *   down_area_mm2: array{x: float, y: float, z: float},
     *   bbox: array{min: float[], max: float[], size: float[]},
     *   triangles: int, unit_scale: float, format: string
     * }
     * @throws InvalidMeshException
     */
    public function analyze(string $path, ?string $extension = null): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidMeshException('El archivo no existe o no se puede leer.');
        }

        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));
        $this->reset();

        if ($extension === 'obj') {
            $format = 'obj';
            $this->parseObj($path);
        } elseif ($extension === 'stl') {
            $format = $this->parseStl($path);
        } else {
            throw new InvalidMeshException('Formato no soportado: solo STL u OBJ.');
        }

        if ($this->triangles === 0) {
            throw new InvalidMeshException('El archivo no contiene triángulos.');
        }

        $volume = abs($this->signedVolume);
        if (!is_finite($volume) || !is_finite($this->area) || $volume <= 0.0) {
            throw new InvalidMeshException('La malla no tiene volumen (¿superficie abierta o plana?).');
        }

        $size = [$this->maxX - $this->minX, $this->maxY - $this->minY, $this->maxZ - $this->minZ];
        $maxDim = max($size);

        // Normalización metros → mm (misma regla que el visor)
        $unitScale = ($maxDim > 0 && $maxDim < 1) ? 1000.0 : 1.0;
        $s2 = $unitScale * $unitScale;
        $s3 = $s2 * $unitScale;

        return [
            'volume_mm3'       => $volume * $s3,
            'total_area_mm2'   => $this->area * $s2,
            // Área "hacia abajo" en el eje Z del archivo (orientación de impresión típica de un STL)
            'support_area_mm2' => $this->downZ * $s2,
            'down_area_mm2'    => [
                'x' => $this->downX * $s2,
                'y' => $this->downY * $s2,
                'z' => $this->downZ * $s2,
            ],
            'bbox' => [
                'min'  => [$this->minX * $unitScale, $this->minY * $unitScale, $this->minZ * $unitScale],
                'max'  => [$this->maxX * $unitScale, $this->maxY * $unitScale, $this->maxZ * $unitScale],
                'size' => [$size[0] * $unitScale, $size[1] * $unitScale, $size[2] * $unitScale],
            ],
            'triangles'  => $this->triangles,
            'unit_scale' => $unitScale,
            'format'     => $format,
        ];
    }

    // ------------------------------------------------------------------
    // STL
    // ------------------------------------------------------------------

    /** @return string 'stl-binary' | 'stl-ascii' */
    private function parseStl(string $path): string
    {
        $size = filesize($path);
        if ($size === false || $size < 15) {
            throw new InvalidMeshException('Archivo STL vacío o truncado.');
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new InvalidMeshException('No se pudo abrir el archivo.');
        }

        try {
            $header = fread($fh, 84);
            $count = null;
            if ($header !== false && strlen($header) === 84) {
                $count = unpack('V', $header, 80)[1];
            }
            $startsWithSolid = $header !== false && strncasecmp(ltrim($header), 'solid', 5) === 0;

            $isBinary = false;
            if ($count !== null && $count > 0) {
                $expected = 84 + $count * 50;
                if ($expected === $size) {
                    $isBinary = true;
                } elseif (!$startsWithSolid && $size >= $expected) {
                    // Binario con bytes sobrantes al final
                    $isBinary = true;
                }
            }

            if ($isBinary) {
                $this->parseBinaryStl($fh, (int) $count);
                return 'stl-binary';
            }

            if (!$startsWithSolid) {
                throw new InvalidMeshException('El archivo no parece un STL válido.');
            }

            rewind($fh);
            $this->parseAsciiStl($fh);
            return 'stl-ascii';
        } finally {
            fclose($fh);
        }
    }

    /** @param resource $fh Posicionado justo después de la cabecera (byte 84). */
    private function parseBinaryStl($fh, int $count): void
    {
        $remaining = $count;
        while ($remaining > 0) {
            $batch = min(self::BINARY_CHUNK_TRIANGLES, $remaining);
            $bytes = $batch * 50;
            $chunk = '';
            while (strlen($chunk) < $bytes) {
                $part = fread($fh, $bytes - strlen($chunk));
                if ($part === false || $part === '') {
                    throw new InvalidMeshException('STL binario truncado.');
                }
                $chunk .= $part;
            }

            for ($i = 0; $i < $batch; $i++) {
                // 12 bytes de normal (ignorada) + 9 floats LE + 2 bytes de atributos
                $v = unpack('g9c', $chunk, $i * 50 + 12);
                $this->addTriangle(
                    $v['c1'], $v['c2'], $v['c3'],
                    $v['c4'], $v['c5'], $v['c6'],
                    $v['c7'], $v['c8'], $v['c9']
                );
            }
            $remaining -= $batch;
        }
    }

    /** @param resource $fh */
    private function parseAsciiStl($fh): void
    {
        $loop = [];
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            $keyword = strtolower($parts[0]);

            if ($keyword === 'vertex') {
                if (count($parts) < 4 || !is_numeric($parts[1]) || !is_numeric($parts[2]) || !is_numeric($parts[3])) {
                    throw new InvalidMeshException('Vértice ASCII inválido.');
                }
                $loop[] = [(float) $parts[1], (float) $parts[2], (float) $parts[3]];
            } elseif ($keyword === 'endloop' || $keyword === 'endfacet') {
                $this->addPolygon($loop);
                $loop = [];
            }
        }
        // Archivo sin "endloop" final
        $this->addPolygon($loop);
    }

    // ------------------------------------------------------------------
    // OBJ
    // ------------------------------------------------------------------

    private function parseObj(string $path): void
    {
        // Pasada 1: vértices empaquetados como doubles (24 bytes c/u)
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new InvalidMeshException('No se pudo abrir el archivo.');
        }
        $vertices = '';
        $vertexCount = 0;
        try {
            while (($line = fgets($fh)) !== false) {
                if (!isset($line[1]) || $line[0] !== 'v' || ($line[1] !== ' ' && $line[1] !== "\t")) {
                    continue;
                }
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) < 4 || !is_numeric($parts[1]) || !is_numeric($parts[2]) || !is_numeric($parts[3])) {
                    throw new InvalidMeshException('Vértice OBJ inválido.');
                }
                // 'e' = double LE: mantiene la precisión del archivo
                $vertices .= pack('e3', (float) $parts[1], (float) $parts[2], (float) $parts[3]);
                $vertexCount++;
            }
        } finally {
            fclose($fh);
        }

        if ($vertexCount < 3) {
            throw new InvalidMeshException('El OBJ no tiene suficientes vértices.');
        }

        // Pasada 2: caras (triangulación en abanico, índices negativos relativos)
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new InvalidMeshException('No se pudo abrir el archivo.');
        }
        $seen = 0; // vértices definidos hasta la línea actual (para índices negativos)
        try {
            while (($line = fgets($fh)) !== false) {
                if (!isset($line[1])) {
                    continue;
                }
                if ($line[0] === 'v' && ($line[1] === ' ' || $line[1] === "\t")) {
                    $seen++;
                    continue;
                }
                if ($line[0] !== 'f' || ($line[1] !== ' ' && $line[1] !== "\t")) {
                    continue;
                }
                $parts = preg_split('/\s+/', trim($line));
                $n = count($parts) - 1;
                if ($n < 3) {
                    continue; // cara degenerada
                }
                $poly = [];
                for ($k = 1; $k <= $n; $k++) {
                    $ref = strstr($parts[$k], '/', true);
                    if ($ref === false) {
                        $ref = $parts[$k];
                    }
                    if ($ref === '' || !preg_match('/^-?\d+$/', $ref)) {
                        throw new InvalidMeshException('Índice de cara OBJ inválido.');
                    }
                    $idx = (int) $ref;
                    $idx = $idx > 0 ? $idx - 1 : $seen + $idx;
                    if ($idx < 0 || $idx >= $vertexCount) {
                        throw new InvalidMeshException('Índice de cara OBJ fuera de rango.');
                    }
                    $v = unpack('e3', $vertices, $idx * 24);
                    $poly[] = [$v[1], $v[2], $v[3]];
                }
                $this->addPolygon($poly);
            }
        } finally {
            fclose($fh);
        }
    }

    // ------------------------------------------------------------------
    // Acumulación
    // ------------------------------------------------------------------

    /** @param array<int, array{0: float, 1: float, 2: float}> $poly Triangulación en abanico. */
    private function addPolygon(array $poly): void
    {
        $n = count($poly);
        if ($n < 3) {
            return;
        }
        [$ax, $ay, $az] = $poly[0];
        for ($i = 1; $i < $n - 1; $i++) {
            [$bx, $by, $bz] = $poly[$i];
            [$cx, $cy, $cz] = $poly[$i + 1];
            $this->addTriangle($ax, $ay, $az, $bx, $by, $bz, $cx, $cy, $cz);
        }
    }

    private function addTriangle(
        float $ax, float $ay, float $az,
        float $bx, float $by, float $bz,
        float $cx, float $cy, float $cz
    ): void {
        if (!is_finite($ax + $ay + $az + $bx + $by + $bz + $cx + $cy + $cz)) {
            throw new InvalidMeshException('La malla contiene coordenadas no numéricas.');
        }

        $this->triangles++;

        // Volumen con signo del tetraedro (origen, a, b, c) = a · (b × c) / 6
        $this->signedVolume += (
            $ax * ($by * $cz - $bz * $cy)
            + $ay * ($bz * $cx - $bx * $cz)
            + $az * ($bx * $cy - $by * $cx)
        ) / 6.0;

        // Normal geométrica (según el orden de vértices)
        $e1x = $bx - $ax; $e1y = $by - $ay; $e1z = $bz - $az;
        $e2x = $cx - $ax; $e2y = $cy - $ay; $e2z = $cz - $az;
        $nx = $e1y * $e2z - $e1z * $e2y;
        $ny = $e1z * $e2x - $e1x * $e2z;
        $nz = $e1x * $e2y - $e1y * $e2x;
        $len = sqrt($nx * $nx + $ny * $ny + $nz * $nz);

        if ($len > 0.0) {
            $triArea = $len / 2.0;
            $this->area += $triArea;
            if ($nx / $len < self::OVERHANG_THRESHOLD) $this->downX += $triArea;
            if ($ny / $len < self::OVERHANG_THRESHOLD) $this->downY += $triArea;
            if ($nz / $len < self::OVERHANG_THRESHOLD) $this->downZ += $triArea;
        }

        $this->minX = min($this->minX, $ax, $bx, $cx);
        $this->minY = min($this->minY, $ay, $by, $cy);
        $this->minZ = min($this->minZ, $az, $bz, $cz);
        $this->maxX = max($this->maxX, $ax, $bx, $cx);
        $this->maxY = max($this->maxY, $ay, $by, $cy);
        $this->maxZ = max($this->maxZ, $az, $bz, $cz);
    }

    private function reset(): void
    {
        $this->signedVolume = 0.0;
        $this->area = 0.0;
        $this->downX = 0.0;
        $this->downY = 0.0;
        $this->downZ = 0.0;
        $this->triangles = 0;
        $this->minX = INF;
        $this->minY = INF;
        $this->minZ = INF;
        $this->maxX = -INF;
        $this->maxY = -INF;
        $this->maxZ = -INF;
    }
}
