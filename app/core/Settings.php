<?php
declare(strict_types=1);

/** Configuración editable desde el panel (tabla configuracion) */
final class Settings
{
    public const DEFAULTS = [
        'nombre_establecimiento'   => 'Mi establecimiento',
        'instalado'                => '0',
        // Descanso: se descuenta solo si la jornada dura al menos el umbral.
        // Por defecto 0: el trabajador marca salida cuando sale a descansar.
        'descanso_minutos'         => '0',
        'descanso_umbral_minutos'  => '360',
        // Jornada nocturna (informativa en etapa 1; se liquida en etapa 2)
        'nocturna_inicio'          => '19:00',
        'nocturna_fin'             => '06:00',
        // Seguridad de marcación
        'max_horas_jornada'        => '16',
        'min_minutos_entre_marcas' => '2',
        'foto_obligatoria'         => '1',
        'validar_red'              => '0',
        'mensaje_informal'         => '0',
        // Accesos
        'pin_intentos_max'         => '5',
        'pin_bloqueo_minutos'      => '15',
        'retencion_fotos_dias'     => '90',
        // Planillas
        'valor_hora_defecto'       => '0',
        'mensaje_planilla'         => '¡Su ayuda hace que seamos mejores! ¡Muchas gracias!',
        // Correo (etapa 4)
        'correo_activo'            => '0',
        'correo_metodo'            => 'smtp',
        'smtp_host'                => 'smtp.gmail.com',
        'smtp_puerto'              => '587',
        'smtp_seguridad'           => 'tls',
        'smtp_usuario'             => '',
        'smtp_clave'               => '',
        'brevo_api_key'            => '',
        'correo_remitente'         => '',
        'correo_nombre'            => '',
        'correo_destinos'          => '',
        'notificar_inventario'     => '1',
        'notificar_faltantes'      => '1',
        'notificar_cierre'         => '1',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (Database::all('SELECT clave, valor FROM configuracion') as $r) {
                    self::$cache[$r['clave']] = $r['valor'];
                }
            } catch (PDOException) {
                // tabla aún no creada
            }
        }
        return self::$cache;
    }

    public static function get(string $key): string
    {
        return (string)(self::all()[$key] ?? '');
    }

    public static function int(string $key): int
    {
        return (int)self::get($key);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function set(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO configuracion (clave, valor, actualizado) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado = VALUES(actualizado)',
            [$key, $value, now_utc()]
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
