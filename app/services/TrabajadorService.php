<?php
declare(strict_types=1);

final class TrabajadorService
{
    public const COLORES = ['#1F6F5C', '#2F5BD3', '#B4531F', '#7A3E9D', '#A8326E', '#3E7A1F', '#8A6D0B', '#1F5F7A'];

    public static function activos(): array
    {
        return Database::all('SELECT id, nombre, color FROM trabajadores WHERE activo = 1 ORDER BY orden, nombre');
    }

    public static function todos(): array
    {
        return Database::all(
            "SELECT t.*,
                    (SELECT MAX(fecha_hora) FROM marcaciones m WHERE m.trabajador_id = t.id) AS ultima_marca,
                    (SELECT COUNT(*) FROM jornadas j WHERE j.trabajador_id = t.id AND j.estado = 'abierta') AS abiertas
             FROM trabajadores t ORDER BY t.activo DESC, t.orden, t.nombre"
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::one('SELECT * FROM trabajadores WHERE id = ?', [$id]);
    }

    /** PIN de 6 dígitos sin patrones obvios (111111, 123456, 654321...) */
    public static function generarPin(): string
    {
        do {
            $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::esTrivial($pin));
        return $pin;
    }

    public static function esTrivial(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) <= 2) {
            return true;
        }
        $asc = $desc = true;
        for ($i = 1; $i < 6; $i++) {
            $d = (int)$pin[$i] - (int)$pin[$i - 1];
            $asc = $asc && $d === 1;
            $desc = $desc && $d === -1;
        }
        return $asc || $desc;
    }

    private static function validarNombre(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            throw new InvalidArgumentException('El nombre debe tener entre 2 y 60 caracteres.');
        }
        return $nombre;
    }

    private static function validarColor(?string $color): string
    {
        return ($color && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) ? strtoupper($color) : self::COLORES[0];
    }

    /** @return array{id:int, pin:string} */
    public static function crear(string $nombre, ?string $documento, ?string $color): array
    {
        $nombre = self::validarNombre($nombre);
        $pin = self::generarPin();
        $orden = (int)Database::value('SELECT COALESCE(MAX(orden), 0) + 1 FROM trabajadores');
        $ahora = now_utc();
        $id = Database::insert('trabajadores', [
            'nombre'       => $nombre,
            'documento'    => $documento !== '' ? $documento : null,
            'pin_hash'     => password_hash($pin, PASSWORD_DEFAULT),
            'pin_generado' => $ahora,
            'activo'       => 1,
            'orden'        => $orden,
            'color'        => $color ? self::validarColor($color) : self::COLORES[($orden - 1) % count(self::COLORES)],
            'creado'       => $ahora,
            'actualizado'  => $ahora,
        ]);
        Audit::log('trabajador_creado', 'trabajador', $id, null, null, ['nombre' => $nombre]);
        return ['id' => $id, 'pin' => $pin];
    }

    public static function actualizar(int $id, string $nombre, ?string $documento, ?string $color, int $orden, string $motivo = ''): void
    {
        $t = self::buscar($id) ?? throw new InvalidArgumentException('El trabajador no existe.');
        $nombre = self::validarNombre($nombre);
        $nuevo = [
            'nombre'      => $nombre,
            'documento'   => $documento !== '' ? $documento : null,
            'color'       => self::validarColor($color),
            'orden'       => $orden,
            'actualizado' => now_utc(),
        ];
        Database::update('trabajadores', $nuevo, 'id = :id', ['id' => $id]);
        Audit::log('trabajador_editado', 'trabajador', $id, $motivo ?: null,
            ['nombre' => $t['nombre'], 'documento' => $t['documento'], 'color' => $t['color'], 'orden' => (int)$t['orden']],
            ['nombre' => $nombre, 'documento' => $nuevo['documento'], 'color' => $nuevo['color'], 'orden' => $orden]);
    }

    public static function restablecerPin(int $id, string $motivo): string
    {
        $t = self::buscar($id) ?? throw new InvalidArgumentException('El trabajador no existe.');
        $pin = self::generarPin();
        Database::update('trabajadores', [
            'pin_hash' => password_hash($pin, PASSWORD_DEFAULT), 'pin_generado' => now_utc(), 'actualizado' => now_utc(),
        ], 'id = :id', ['id' => $id]);
        RateLimiter::clear(Auth::workerKey($id));
        Audit::log('pin_restablecido', 'trabajador', $id, $motivo ?: null, null, ['nombre' => $t['nombre']]);
        return $pin;
    }

    public static function cambiarEstado(int $id, bool $activo, string $motivo): void
    {
        $t = self::buscar($id) ?? throw new InvalidArgumentException('El trabajador no existe.');
        Database::update('trabajadores', ['activo' => $activo ? 1 : 0, 'actualizado' => now_utc()], 'id = :id', ['id' => $id]);
        Audit::log($activo ? 'trabajador_activado' : 'trabajador_desactivado', 'trabajador', $id, $motivo ?: null,
            ['activo' => (int)$t['activo']], ['activo' => $activo ? 1 : 0]);
    }

    /** Cuántos registros tiene (si tiene, no se puede borrar: son el soporte de lo que se le pagó) */
    public static function registros(int $id): int
    {
        $n = (int)Database::value('SELECT COUNT(*) FROM jornadas WHERE trabajador_id = ?', [$id])
           + (int)Database::value('SELECT COUNT(*) FROM marcaciones WHERE trabajador_id = ?', [$id]);
        if (Database::value("SHOW TABLES LIKE 'inventarios'")) {
            $n += (int)Database::value('SELECT COUNT(*) FROM inventarios WHERE trabajador_id = ?', [$id]);
        }
        if (Database::value("SHOW TABLES LIKE 'planillas'")) {
            $n += (int)Database::value("SELECT COUNT(*) FROM planillas WHERE trabajador_id = ? AND estado = 'pagada'", [$id]);
        }
        return $n;
    }

    /**
     * Elimina por completo a un trabajador sin registros (por ejemplo, creado por error).
     * Si ya tiene jornadas, planillas pagadas o inventarios, se desactiva en su lugar.
     * @return string 'eliminado' | 'desactivado'
     */
    public static function eliminar(int $id, string $motivo): string
    {
        $t = self::buscar($id) ?? throw new InvalidArgumentException('El trabajador no existe.');
        if (self::registros($id) > 0) {
            if ((int)$t['activo']) {
                self::cambiarEstado($id, false, $motivo ?: 'Retiro');
            }
            return 'desactivado';
        }
        Database::transaction(function () use ($id) {
            if (Database::value("SHOW TABLES LIKE 'planillas'")) {
                Database::run('DELETE FROM planillas WHERE trabajador_id = ?', [$id]);
            }
            if (Database::value("SHOW TABLES LIKE 'inventarios'")) {
                Database::run("DELETE FROM inventarios WHERE trabajador_id = ? AND estado = 'borrador'", [$id]);
            }
            Database::run('UPDATE intentos_rechazados SET trabajador_id = NULL WHERE trabajador_id = ?', [$id]);
            Database::run('DELETE FROM limites_acceso WHERE clave = ?', [Auth::workerKey($id)]);
            Database::run('DELETE FROM trabajadores WHERE id = ?', [$id]);
        });
        Audit::log('trabajador_eliminado', 'trabajador', $id, $motivo ?: null, ['nombre' => $t['nombre']], null);
        return 'eliminado';
    }

    public static function registrarConsentimiento(int $id): void
    {
        Database::update('trabajadores', ['consentimiento_datos' => now_utc()], 'id = :id AND consentimiento_datos IS NULL', ['id' => $id]);
        Audit::log('consentimiento_datos', 'trabajador', $id, 'Aceptó la autorización de tratamiento de datos en el kiosco');
    }
}
