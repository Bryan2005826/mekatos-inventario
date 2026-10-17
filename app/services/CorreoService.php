<?php
declare(strict_types=1);

/**
 * Envío de correo sin librerías externas.
 *  - SMTP (Gmail con contraseña de aplicación, Outlook, Zoho, el del dominio...)
 *  - API HTTPS de Brevo (plan gratuito), útil si el hosting bloquea los puertos SMTP.
 * Devuelve ok=true solo si el servidor ACEPTÓ el mensaje (SMTP 250 / HTTP 201).
 */
final class CorreoService
{
    private const TIMEOUT = 20;

    public static function configurado(): bool
    {
        if (!Settings::bool('correo_activo') || trim(Settings::get('correo_remitente')) === '') {
            return false;
        }
        return Settings::get('correo_metodo') === 'brevo'
            ? Secreto::descifrar(Settings::get('brevo_api_key')) !== ''
            : trim(Settings::get('smtp_host')) !== '';
    }

    public static function destinos(): array
    {
        return self::validarDestinos(Settings::get('correo_destinos'));
    }

    /** @return string[] */
    public static function validarDestinos(string $lista): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $lista) as $d) {
            $d = strtolower(trim($d));
            if ($d !== '' && filter_var($d, FILTER_VALIDATE_EMAIL)) {
                $out[$d] = true;
            }
        }
        return array_slice(array_keys($out), 0, 10);
    }

    /**
     * @param array $adjuntos [['nombre' => 'a.pdf', 'ruta' => '/ruta/a.pdf', 'tipo' => 'application/pdf']]
     * @return array{ok:bool, id:?string, error:?string, metodo:string}
     */
    public static function enviar(array $destinos, string $asunto, string $html, string $texto, array $adjuntos = []): array
    {
        $metodo = Settings::get('correo_metodo') === 'brevo' ? 'brevo' : 'smtp';
        if (!$destinos) {
            return ['ok' => false, 'id' => null, 'error' => 'No hay destinatarios configurados.', 'metodo' => $metodo];
        }
        try {
            return $metodo === 'brevo'
                ? self::brevo($destinos, $asunto, $html, $texto, $adjuntos)
                : self::smtp($destinos, $asunto, $html, $texto, $adjuntos);
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => mb_substr($e->getMessage(), 0, 450), 'metodo' => $metodo];
        }
    }

    /* ---------------- Brevo (HTTPS) ---------------- */

    private static function brevo(array $destinos, string $asunto, string $html, string $texto, array $adjuntos): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('El hosting no tiene la extensión cURL.');
        }
        $datos = [
            'sender'      => ['name' => Settings::get('correo_nombre') ?: Settings::get('nombre_establecimiento'), 'email' => Settings::get('correo_remitente')],
            'to'          => array_map(fn($d) => ['email' => $d], $destinos),
            'subject'     => $asunto,
            'htmlContent' => $html,
            'textContent' => $texto,
        ];
        foreach ($adjuntos as $a) {
            $datos['attachment'][] = ['name' => $a['nombre'], 'content' => base64_encode((string)file_get_contents($a['ruta']))];
        }
        $url = (string)(config('brevo_url') ?: 'https://api.brevo.com/v3/smtp/email');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . Secreto::descifrar(Settings::get('brevo_api_key')),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($datos, JSON_UNESCAPED_UNICODE),
        ]);
        $resp = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException('No se pudo conectar con Brevo: ' . $err);
        }
        $j = json_decode((string)$resp, true) ?: [];
        if ($codigo === 201 || $codigo === 200) {
            return ['ok' => true, 'id' => (string)($j['messageId'] ?? ''), 'error' => null, 'metodo' => 'brevo'];
        }
        throw new RuntimeException("Brevo rechazó el envío (HTTP $codigo): " . ($j['message'] ?? mb_substr((string)$resp, 0, 200)));
    }

    /* ---------------- SMTP ---------------- */

    private static function smtp(array $destinos, string $asunto, string $html, string $texto, array $adjuntos): array
    {
        $host = trim(Settings::get('smtp_host'));
        $puerto = Settings::int('smtp_puerto') ?: 587;
        $seg = Settings::get('smtp_seguridad'); // tls | ssl | ninguna
        $usuario = Settings::get('smtp_usuario');
        $clave = Secreto::descifrar(Settings::get('smtp_clave'));
        $remitente = Settings::get('correo_remitente');

        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $dir = ($seg === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;
        $s = @stream_socket_client($dir, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) {
            throw new RuntimeException("No se pudo conectar a $host:$puerto ($errstr). Si el hosting bloquea SMTP, usa Brevo.");
        }
        stream_set_timeout($s, self::TIMEOUT);
        $leer = function () use ($s): array {
            $txt = '';
            while (($l = fgets($s, 1024)) !== false) {
                $txt .= $l;
                if (strlen($l) < 4 || $l[3] === ' ') {
                    break;
                }
            }
            if ($txt === '') {
                throw new RuntimeException('El servidor de correo cerró la conexión.');
            }
            return [(int)substr($txt, 0, 3), trim($txt)];
        };
        $cmd = function (string $c, array $esperados, string $mostrar = '') use ($s, $leer): string {
            fwrite($s, $c . "\r\n");
            [$cod, $txt] = $leer();
            if (!in_array($cod, $esperados, true)) {
                throw new RuntimeException('SMTP ' . ($mostrar ?: strtok($c, ' ')) . ": $txt");
            }
            return $txt;
        };

        try {
            [$cod, $txt] = $leer();
            if ($cod !== 220) {
                throw new RuntimeException("Saludo SMTP inesperado: $txt");
            }
            $yo = preg_replace('/[^A-Za-z0-9.-]/', '', parse_url(base_url(), PHP_URL_HOST) ?: 'localhost') ?: 'localhost';
            $ehlo = $cmd("EHLO $yo", [250]);
            if ($seg === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('No se pudo iniciar la conexión segura (STARTTLS).');
                }
                $ehlo = $cmd("EHLO $yo", [250]);
            }
            if ($usuario !== '') {
                if (stripos($ehlo, 'AUTH') === false) {
                    throw new RuntimeException('El servidor no permite iniciar sesión sin conexión segura. Revisa el tipo de seguridad.');
                }
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($usuario), [334], 'usuario');
                $cmd(base64_encode($clave), [235], 'contraseña (revisa usuario y contraseña de aplicación)');
            }
            $cmd('MAIL FROM:<' . $remitente . '>', [250]);
            foreach ($destinos as $d) {
                $cmd('RCPT TO:<' . $d . '>', [250, 251]);
            }
            $cmd('DATA', [354]);
            [$msgId, $mime] = self::mime($remitente, $destinos, $asunto, $html, $texto, $adjuntos);
            // "dot stuffing": líneas que empiezan con punto
            $mime = preg_replace('/^\./m', '..', $mime);
            fwrite($s, $mime . "\r\n.\r\n");
            [$cod, $txt] = $leer();
            if ($cod !== 250) {
                throw new RuntimeException("El servidor no aceptó el mensaje: $txt");
            }
            // Algunos servidores devuelven su propio identificador: "250 2.0.0 OK 1700000000 abc123 - gsmtp"
            $idServidor = trim(substr($txt, 4));
            try {
                $cmd('QUIT', [221]);
            } catch (Throwable) {
                // ya fue aceptado
            }
            return ['ok' => true, 'id' => mb_substr($msgId . ' · ' . $idServidor, 0, 200), 'error' => null, 'metodo' => 'smtp'];
        } finally {
            fclose($s);
        }
    }

    private static function encabezado(string $texto): string
    {
        return preg_match('/[^\x20-\x7E]/', $texto) ? '=?UTF-8?B?' . base64_encode($texto) . '?=' : $texto;
    }

    /** @return array{0:string,1:string} [Message-ID, mensaje MIME completo] */
    public static function mime(string $de, array $para, string $asunto, string $html, string $texto, array $adjuntos): array
    {
        $dominio = substr(strrchr($de, '@') ?: '@localhost', 1);
        $msgId = '<' . bin2hex(random_bytes(10)) . '@' . $dominio . '>';
        $limiteMixto = 'mix_' . bin2hex(random_bytes(8));
        $limiteAlt = 'alt_' . bin2hex(random_bytes(8));
        $nombre = Settings::get('correo_nombre') ?: Settings::get('nombre_establecimiento');
        $h = [
            'Date: ' . date('r'),
            'From: ' . self::encabezado($nombre) . ' <' . $de . '>',
            'To: ' . implode(', ', $para),
            'Subject: ' . self::encabezado($asunto),
            'Message-ID: ' . $msgId,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $limiteMixto . '"',
        ];
        $c = implode("\r\n", $h) . "\r\n\r\n";
        $c .= "--$limiteMixto\r\nContent-Type: multipart/alternative; boundary=\"$limiteAlt\"\r\n\r\n";
        $c .= "--$limiteAlt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto)) . "\r\n";
        $c .= "--$limiteAlt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n";
        $c .= "--$limiteAlt--\r\n";
        foreach ($adjuntos as $a) {
            $n = self::encabezado($a['nombre']);
            $c .= "--$limiteMixto\r\nContent-Type: " . ($a['tipo'] ?? 'application/octet-stream') . "; name=\"$n\"\r\n"
                . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$n\"\r\n\r\n"
                . chunk_split(base64_encode((string)file_get_contents($a['ruta']))) . "\r\n";
        }
        $c .= "--$limiteMixto--";
        return [$msgId, $c];
    }
}
