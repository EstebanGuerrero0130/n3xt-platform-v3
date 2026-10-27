<?php

namespace Tests\Unit;

use App\Exceptions\InvalidMeshException;
use App\Services\MeshVolumeService;
use PHPUnit\Framework\TestCase;

class MeshVolumeServiceTest extends TestCase
{
    /** @var string[] */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /** 8 vértices de un cubo [0,L]^3 */
    private static function cubeVertices(float $l): array
    {
        return [
            [0, 0, 0], [$l, 0, 0], [$l, $l, 0], [0, $l, 0],
            [0, 0, $l], [$l, 0, $l], [$l, $l, $l], [0, $l, $l],
        ];
    }

    /** 6 caras (quads) con winding CCW visto desde fuera (normales hacia afuera). */
    private static function cubeQuads(): array
    {
        return [
            [0, 3, 2, 1], // z = 0 (abajo)
            [4, 5, 6, 7], // z = L (arriba)
            [0, 1, 5, 4], // y = 0
            [2, 3, 7, 6], // y = L
            [1, 2, 6, 5], // x = L
            [0, 4, 7, 3], // x = 0
        ];
    }

    private static function cubeTriangles(float $l): array
    {
        $v = self::cubeVertices($l);
        $tris = [];
        foreach (self::cubeQuads() as [$a, $b, $c, $d]) {
            $tris[] = [$v[$a], $v[$b], $v[$c]];
            $tris[] = [$v[$a], $v[$c], $v[$d]];
        }
        return $tris;
    }

    private function tmp(string $ext, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mesh') . '.' . $ext;
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;
        return $path;
    }

    private function binaryStl(float $l): string
    {
        $tris = self::cubeTriangles($l);
        $out = str_pad('binary cube', 80, ' ') . pack('V', count($tris));
        foreach ($tris as $t) {
            $out .= pack('g3', 0, 0, 0);
            foreach ($t as $p) {
                $out .= pack('g3', $p[0], $p[1], $p[2]);
            }
            $out .= pack('v', 0);
        }
        return $out;
    }

    private function asciiStl(float $l): string
    {
        $out = "solid cube\n";
        foreach (self::cubeTriangles($l) as $t) {
            $out .= "  facet normal 0 0 0\n    outer loop\n";
            foreach ($t as $p) {
                $out .= sprintf("      vertex %.6e %.6e %.6e\n", $p[0], $p[1], $p[2]);
            }
            $out .= "    endloop\n  endfacet\n";
        }
        return $out . "endsolid cube\n";
    }

    private function assertCube(array $r, float $l = 10.0): void
    {
        $this->assertEqualsWithDelta($l ** 3, $r['volume_mm3'], 1e-6);
        $this->assertEqualsWithDelta(6 * $l * $l, $r['total_area_mm2'], 1e-6);
        $this->assertEqualsWithDelta($l * $l, $r['support_area_mm2'], 1e-6); // cara z=0
        $this->assertEqualsWithDelta($l * $l, $r['down_area_mm2']['y'], 1e-6);
        $this->assertEqualsWithDelta($l, $r['bbox']['size'][2], 1e-9);
        $this->assertSame(12, $r['triangles']);
    }

    public function test_binary_stl_cube(): void
    {
        $r = (new MeshVolumeService())->analyze($this->tmp('stl', $this->binaryStl(10)));
        $this->assertSame('stl-binary', $r['format']);
        $this->assertCube($r);
    }

    public function test_binary_stl_whose_header_starts_with_solid(): void
    {
        $bin = $this->binaryStl(10);
        $bin = 'solid' . substr($bin, 5);
        $r = (new MeshVolumeService())->analyze($this->tmp('stl', $bin));
        $this->assertSame('stl-binary', $r['format']);
        $this->assertCube($r);
    }

    public function test_ascii_stl_cube(): void
    {
        $r = (new MeshVolumeService())->analyze($this->tmp('stl', $this->asciiStl(10)));
        $this->assertSame('stl-ascii', $r['format']);
        $this->assertCube($r);
    }

    public function test_bed_contact_area_excludes_overhangs_above_the_bed(): void
    {
        // Cubo: su cara de abajo apoya en la cama en cualquier eje
        $r = (new MeshVolumeService())->analyze($this->tmp('stl', $this->asciiStl(10)));
        foreach (['x', 'y', 'z'] as $k) {
            $this->assertEqualsWithDelta(100, $r['bed_contact_mm2'][$k], 1e-6, $k);
        }

        // Dos cubos, el segundo flotando 20 mm más arriba en Z: su cara de abajo es voladizo
        $out = "solid two\n";
        foreach ([0, 20] as $dz) {
            foreach (self::cubeTriangles(10) as $t) {
                $out .= "facet normal 0 0 0\nouter loop\n";
                foreach ($t as $p) {
                    $out .= sprintf("vertex %F %F %F\n", $p[0], $p[1], $p[2] + $dz);
                }
                $out .= "endloop\nendfacet\n";
            }
        }
        $r = (new MeshVolumeService())->analyze($this->tmp('stl', $out . "endsolid two\n"));
        $this->assertEqualsWithDelta(200, $r['down_area_mm2']['z'], 1e-6);
        $this->assertEqualsWithDelta(100, $r['bed_contact_mm2']['z'], 1e-6);
        $this->assertEqualsWithDelta(200, $r['bed_contact_mm2']['x'], 1e-6);
    }

    public function test_obj_cube_with_quads(): void
    {
        $obj = "# cube\no cube\n";
        foreach (self::cubeVertices(10) as $p) {
            $obj .= "v {$p[0]} {$p[1]} {$p[2]}\n";
        }
        $obj .= "vn 0 0 1\n";
        foreach (self::cubeQuads() as $q) {
            // índices 1-based con formato v//vn
            $obj .= 'f ' . implode(' ', array_map(fn ($i) => ($i + 1) . '//1', $q)) . "\n";
        }
        $r = (new MeshVolumeService())->analyze($this->tmp('obj', $obj));
        $this->assertSame('obj', $r['format']);
        $this->assertCube($r);
    }

    public function test_obj_cube_with_negative_indices(): void
    {
        $obj = '';
        foreach (self::cubeVertices(10) as $p) {
            $obj .= "v {$p[0]} {$p[1]} {$p[2]}\n";
        }
        foreach (self::cubeQuads() as $q) {
            $obj .= 'f ' . implode(' ', array_map(fn ($i) => (string) ($i - 8), $q)) . "\n";
        }
        $r = (new MeshVolumeService())->analyze($this->tmp('obj', $obj));
        $this->assertCube($r);
    }

    public function test_meters_are_normalized_to_mm(): void
    {
        $r = (new MeshVolumeService())->analyze($this->tmp('stl', $this->binaryStl(0.01)));
        $this->assertSame(1000.0, $r['unit_scale']);
        $this->assertEqualsWithDelta(1000.0, $r['volume_mm3'], 1e-3);
    }

    public function test_invalid_files_throw(): void
    {
        $svc = new MeshVolumeService();
        foreach ([['stl', 'esto no es una malla'], ['obj', "v 0 0 0\nf 1 2 3\n"], ['stl', '']] as [$ext, $content]) {
            try {
                $svc->analyze($this->tmp($ext, $content));
                $this->fail("Se esperaba InvalidMeshException para .$ext");
            } catch (InvalidMeshException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }
}
