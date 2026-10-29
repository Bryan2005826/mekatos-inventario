<?php
declare(strict_types=1);

/**
 * Autorización del kiosco: solo los navegadores activados con un código
 * del administrador pueden registrar asistencia.
 */
final class DispositivoService
{
    public const COOKIE = 'jt_kiosco';
    private const CODIGO_MINUTOS = 15;
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin 0/O ni 1/I

    private static ?array $actual = null;
    private static bool $resuelto = false;

    private static function hash(string $valor): string
    {
        return hash_hmac('sha256', $valor, (string)config('app_key'));
    }

    public static function normalizarCodigo(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    }

    /** Crea un dispositivo pendiente y devuelve el código de activación en claro (se muestra una sola vez) */
    public static function crearCodigo(string $nombre, ?int $dispositivoId = null, ?int $localId = null): array
    {
        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }
        $expira = gmdate('Y-m-d H:i:s', time() + self::CODIGO_MINUTOS * 60);
        if ($dispositivoId) {
            Database::update('dispositivos', [
                'codigo_hash' => self::hash($codigo), 'codigo_expira' => $expira,
            ], 'id = :id', ['id' => $dispositivoId]);
            $id = $dispositivoId;
        } else {
            $id = Database::insert('dispositivos', [
                'local_id'      => $localId ?? LocalService::actualId(),
                'nombre'        => $nombre,
                'codigo_hash'   => self::hash($codigo),
                'codigo_expira' => $expira,
                'activo'        => 1,
                'creado'        => now_utc(),
            ]);
        }
        return ['id' => $id, 'codigo' => substr($codigo, 0, 4) . '-' . substr($codigo, 4), 'minutos' => self::CODIGO_MINUTOS];
    }

    public static function activar(string $codigo): array
    {
        $key = 'activar_ip:' . client_ip();
        if (RateLimiter::tooMany($key, 8, 15)) {
            return ['ok' => false, 'error' => 'Demasiados intentos. Espera 15 minutos y pide un código nuevo.'];
        }
        $codigo = self::normalizarCodigo($codigo);
        $d = strlen($codigo) === 8 ? Database::one(
            'SELECT * FROM dispositivos WHERE codigo_hash = ? AND codigo_expira >= ? AND activo = 1 AND revocado IS NULL',
            [self::hash($codigo), now_utc()]
        ) : null;
        if (!$d) {
            RateLimiter::hit($key);
            Audit::rechazo('activar_kiosco', 'Código de activación inválido o vencido');
            return ['ok' => false, 'error' => 'El código no es válido o ya venció. Genera uno nuevo en el panel.'];
        }
        $token = bin2hex(random_bytes(32));
        Database::update('dispositivos', [
            'token_hash'    => self::hash($token),
            'codigo_hash'   => null,
            'codigo_expira' => null,
            'activado'      => now_utc(),
            'ultimo_uso'    => now_utc(),
            'ultima_ip'     => client_ip(),
            'user_agent'    => user_agent(),
        ], 'id = :id', ['id' => $d['id']]);
        Session::setCookie(self::COOKIE, $token, 3650);
        RateLimiter::clear($key);
        self::$resuelto = false;
        Audit::log('kiosco_activado', 'dispositivo', (int)$d['id'], null, null, ['nombre' => $d['nombre'], 'ip' => client_ip()]);
        return ['ok' => true, 'dispositivo' => $d];
    }

    /** Dispositivo autorizado del navegador actual, o null */
    public static function actual(): ?array
    {
        if (self::$resuelto) {
            return self::$actual;
        }
        self::$resuelto = true;
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return self::$actual = null;
        }
        $d = Database::one(
            'SELECT * FROM dispositivos WHERE token_hash = ? AND activo = 1 AND revocado IS NULL',
            [self::hash($token)]
        );
        if ($d && (!$d['ultimo_uso'] || strtotime($d['ultimo_uso'] . ' UTC') < time() - 300)) {
            Database::update('dispositivos', ['ultimo_uso' => now_utc(), 'ultima_ip' => client_ip()], 'id = :id', ['id' => $d['id']]);
        }
        return self::$actual = $d;
    }

    public static function revocar(int $id): void
    {
        Database::update('dispositivos', [
            'activo' => 0, 'revocado' => now_utc(), 'token_hash' => null, 'codigo_hash' => null,
        ], 'id = :id', ['id' => $id]);
        self::$resuelto = false;
    }

    public static function reset(): void
    {
        self::$resuelto = false;
        self::$actual = null;
    }
}
