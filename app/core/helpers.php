<?php
declare(strict_types=1);

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    $configured = config('base_url');
    if ($configured) {
        return rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function url(string $path = '', array $query = []): string
{
    $u = base_url() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? (string)filemtime($file) : APP_VERSION;
    return url($path) . '?v=' . $v;
}

function is_https(): bool
{
    return str_starts_with(base_url(), 'https://');
}

/** Hora actual del servidor en UTC, formato MySQL */
function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function app_tz(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone(config('timezone', 'America/Bogota'));
}

function utc_to_local(?string $utc): ?DateTimeImmutable
{
    if (!$utc) {
        return null;
    }
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(app_tz());
}

function local_to_utc(string $local): string
{
    return (new DateTimeImmutable($local, app_tz()))
        ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function hoy_local(): string
{
    return (new DateTimeImmutable('now', app_tz()))->format('Y-m-d');
}

/** Hora militar: "07:58", "17:12" */
function fmt_hora(?string $utc): string
{
    $d = utc_to_local($utc);
    return $d ? $d->format('H:i') : '—';
}

/** 8244 min -> "137.40" (horas en decimal, como la planilla) */
function fmt_horas_dec(int $min): string
{
    return number_format($min / 60, 2, '.', '');
}

/** 61750 -> "$61.750" */
function fmt_pesos($valor): string
{
    $v = (float)$valor;
    return ($v < 0 ? '−' : '') . '$' . number_format(abs($v), 0, ',', '.');
}

function nombre_mes(int $m, bool $corto = false): string
{
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $corto ? mb_substr($meses[$m], 0, 3) : $meses[$m];
}

function fmt_fecha(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }
    $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $d = new DateTimeImmutable(substr($fecha, 0, 10));
    return $dias[(int)$d->format('w')] . ' ' . $d->format('j') . ' ' . $meses[(int)$d->format('n')] . ' ' . $d->format('Y');
}

function fmt_fecha_hora(?string $utc): string
{
    $d = utc_to_local($utc);
    return $d ? fmt_fecha($d->format('Y-m-d')) . ', ' . fmt_hora($utc) : '—';
}

/** 485 -> "8 h 05 min" */
function fmt_minutos(?int $min): string
{
    if ($min === null) {
        return '—';
    }
    $neg = $min < 0;
    $min = abs($min);
    $txt = intdiv($min, 60) . ' h ' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) . ' min';
    return $neg ? '−' . $txt : $txt;
}

function client_ip(): string
{
    $header = config('ip_header');
    if ($header && !empty($_SERVER[$header])) {
        $ip = trim(explode(',', (string)$_SERVER[$header])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function user_agent(): string
{
    return mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query));
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function view(string $template, array $data = [], ?string $layout = 'admin'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_PATH . '/views/' . $template . '.php';
    $content = ob_get_clean();
    if ($layout === null) {
        echo $content;
        return;
    }
    require APP_PATH . '/views/layouts/' . $layout . '.php';
}

function input(string $key, $default = null)
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function json_input(): array
{
    static $data = null;
    if ($data === null) {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }
    }
    return $data;
}

function abort(int $code, string $message = '', ?string $titulo = null): never
{
    http_response_code($code);
    $titles = [403 => 'Acceso no permitido', 404 => 'Página no encontrada', 500 => 'Error del servidor'];
    view('errors/error', ['code' => $code, 'title' => $titulo ?? ($titles[$code] ?? 'Error'), 'message' => $message], 'plain');
    exit;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1));
    if (count($parts) > 1) {
        $i .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    return $i;
}
