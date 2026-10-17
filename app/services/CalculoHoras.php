<?php
declare(strict_types=1);

/**
 * Cálculo puro de tiempos de una jornada (sin base de datos, para poder probarlo).
 * Las reglas de pago (recargos, festivos, extras) se aplican en la etapa 2
 * sobre estos minutos.
 */
final class CalculoHoras
{
    /**
     * @param string $entradaUtc 'Y-m-d H:i:s' en UTC
     * @param string $salidaUtc  'Y-m-d H:i:s' en UTC
     * @param array  $reglas descanso_minutos, descanso_umbral_minutos, nocturna_inicio 'HH:MM', nocturna_fin 'HH:MM', timezone
     * @return array{brutos:int, descanso:int, trabajados:int, nocturnos:int}
     */
    public static function calcular(string $entradaUtc, string $salidaUtc, array $reglas): array
    {
        $utc = new DateTimeZone('UTC');
        $tz = new DateTimeZone($reglas['timezone'] ?? 'America/Bogota');
        $ini = new DateTimeImmutable($entradaUtc, $utc);
        $fin = new DateTimeImmutable($salidaUtc, $utc);

        if ($fin <= $ini) {
            throw new InvalidArgumentException('La salida debe ser posterior a la entrada.');
        }

        $brutos = intdiv($fin->getTimestamp() - $ini->getTimestamp(), 60);

        $descansoConf = max(0, (int)($reglas['descanso_minutos'] ?? 0));
        $umbral = max(0, (int)($reglas['descanso_umbral_minutos'] ?? 0));
        $descanso = ($descansoConf > 0 && $brutos >= $umbral) ? min($descansoConf, $brutos) : 0;

        $nocturnos = self::minutosNocturnos(
            $ini->setTimezone($tz),
            $fin->setTimezone($tz),
            (string)($reglas['nocturna_inicio'] ?? '19:00'),
            (string)($reglas['nocturna_fin'] ?? '06:00')
        );

        $trabajados = $brutos - $descanso;

        return [
            'brutos'     => $brutos,
            'descanso'   => $descanso,
            'trabajados' => $trabajados,
            'nocturnos'  => min($nocturnos, $trabajados),
        ];
    }

    /** Minutos del intervalo que caen en la franja nocturna (hora local) */
    public static function minutosNocturnos(DateTimeImmutable $ini, DateTimeImmutable $fin, string $desde, string $hasta): int
    {
        if ($desde === $hasta) {
            return 0;
        }
        $total = 0;
        $dia = $ini->setTime(0, 0)->modify('-1 day');
        $ultimo = $fin->setTime(0, 0);
        [$dh, $dm] = array_map('intval', explode(':', $desde));
        [$hh, $hm] = array_map('intval', explode(':', $hasta));
        $cruza = ($dh * 60 + $dm) > ($hh * 60 + $hm); // p.ej. 19:00 → 06:00 del día siguiente

        while ($dia <= $ultimo) {
            $a = $dia->setTime($dh, $dm);
            $b = $cruza ? $dia->modify('+1 day')->setTime($hh, $hm) : $dia->setTime($hh, $hm);
            $s = max($a->getTimestamp(), $ini->getTimestamp());
            $f = min($b->getTimestamp(), $fin->getTimestamp());
            if ($f > $s) {
                $total += intdiv($f - $s, 60);
            }
            $dia = $dia->modify('+1 day');
        }
        return $total;
    }

    public static function reglasActuales(): array
    {
        return [
            'descanso_minutos'        => Settings::int('descanso_minutos'),
            'descanso_umbral_minutos' => Settings::int('descanso_umbral_minutos'),
            'nocturna_inicio'         => Settings::get('nocturna_inicio'),
            'nocturna_fin'            => Settings::get('nocturna_fin'),
            'timezone'                => config('timezone', 'America/Bogota'),
        ];
    }
}
