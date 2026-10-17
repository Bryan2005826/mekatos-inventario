<?php
declare(strict_types=1);

final class Audit
{
    public static function log(string $accion, ?string $entidad = null, ?int $entidadId = null, ?string $motivo = null, $antes = null, $despues = null): void
    {
        $admin = Auth::admin();
        $trab = Auth::kioscoWorker();
        if ($admin) {
            [$tipo, $id, $nombre] = ['admin', (int)$admin['id'], $admin['nombre']];
        } elseif ($trab) {
            [$tipo, $id, $nombre] = ['trabajador', (int)$trab['id'], $trab['nombre']];
        } else {
            [$tipo, $id, $nombre] = ['sistema', null, 'Sistema'];
        }
        Database::insert('auditoria', [
            'actor_tipo'   => $tipo,
            'actor_id'     => $id,
            'actor_nombre' => $nombre,
            'accion'       => $accion,
            'entidad'      => $entidad,
            'entidad_id'   => $entidadId,
            'motivo'       => $motivo,
            'antes'        => $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE),
            'despues'      => $despues === null ? null : json_encode($despues, JSON_UNESCAPED_UNICODE),
            'ip'           => client_ip(),
            'creado'       => now_utc(),
        ]);
    }

    public static function rechazo(string $accion, string $motivo, ?int $trabajadorId = null, ?int $dispositivoId = null): void
    {
        Database::insert('intentos_rechazados', [
            'trabajador_id'  => $trabajadorId,
            'accion'         => $accion,
            'motivo'         => mb_substr($motivo, 0, 255),
            'ip'             => client_ip(),
            'dispositivo_id' => $dispositivoId,
            'user_agent'     => user_agent(),
            'creado'         => now_utc(),
        ]);
    }
}
