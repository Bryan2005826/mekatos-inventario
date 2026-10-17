<?php
declare(strict_types=1);

final class CorreoController
{
    public function index(): void
    {
        Auth::requireAdmin();
        NotificacionService::asegurar();
        NotificacionService::procesarPendientes();
        view('admin/correo', [
            's'       => Settings::all(),
            'envios'  => Database::all('SELECT id, tipo, referencia_id, destinos, asunto, estado, intentos, ultimo_error, mensaje_id, metodo, creado, enviado, proximo_intento FROM envios ORDER BY id DESC LIMIT 100'),
            'listo'   => CorreoService::configurado(),
            'seccion' => 'correo',
        ]);
    }

    public function guardar(): void
    {
        Auth::requireAdmin();
        $metodo = input('correo_metodo') === 'brevo' ? 'brevo' : 'smtp';
        $seg = in_array(input('smtp_seguridad'), ['tls', 'ssl', 'ninguna'], true) ? input('smtp_seguridad') : 'tls';
        $puerto = (int)input('smtp_puerto', 587);
        $remitente = strtolower(trim((string)input('correo_remitente', '')));
        $destinos = CorreoService::validarDestinos((string)input('correo_destinos', ''));
        $errores = [];
        if ($remitente !== '' && !filter_var($remitente, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo remitente no es válido.';
        }
        if ($puerto < 1 || $puerto > 65535) {
            $errores[] = 'El puerto no es válido.';
        }
        if (input('correo_activo') === '1' && !$destinos) {
            $errores[] = 'Escribe al menos un correo destinatario.';
        }
        if ($errores) {
            foreach ($errores as $er) {
                flash('error', $er);
            }
            redirect('admin/correo');
        }
        $campos = [
            'correo_activo'        => input('correo_activo') === '1' ? '1' : '0',
            'correo_metodo'        => $metodo,
            'smtp_host'            => mb_substr(trim((string)input('smtp_host', '')), 0, 120),
            'smtp_puerto'          => (string)$puerto,
            'smtp_seguridad'       => $seg,
            'smtp_usuario'         => mb_substr(trim((string)input('smtp_usuario', '')), 0, 160),
            'correo_remitente'     => $remitente,
            'correo_nombre'        => mb_substr(trim((string)input('correo_nombre', '')), 0, 80),
            'correo_destinos'      => implode(', ', $destinos),
            'notificar_inventario' => input('notificar_inventario') === '1' ? '1' : '0',
            'notificar_faltantes'  => input('notificar_faltantes') === '1' ? '1' : '0',
            'notificar_cierre'     => input('notificar_cierre') === '1' ? '1' : '0',
        ];
        // Las claves solo se reemplazan si se escribe una nueva
        if (($c = (string)($_POST['smtp_clave'] ?? '')) !== '') {
            $campos['smtp_clave'] = Secreto::cifrar(str_replace(' ', '', $c));
        }
        if (($k = trim((string)($_POST['brevo_api_key'] ?? ''))) !== '') {
            $campos['brevo_api_key'] = Secreto::cifrar($k);
        }
        $antes = Settings::all();
        foreach ($campos as $k => $v) {
            Settings::set($k, $v);
        }
        $visible = fn($a) => array_diff_key($a, ['smtp_clave' => 1, 'brevo_api_key' => 1]);
        Audit::log('correo_configurado', 'configuracion', null, null,
            $visible(array_intersect_key($antes, $campos)), $visible($campos) + (isset($campos['smtp_clave']) ? ['smtp_clave' => '(cambiada)'] : []) + (isset($campos['brevo_api_key']) ? ['brevo_api_key' => '(cambiada)'] : []));
        flash('ok', 'Configuración de correo guardada. Envía un correo de prueba para confirmar que funciona.');
        redirect('admin/correo');
    }

    public function probar(): void
    {
        Auth::requireAdmin();
        $destino = strtolower(trim((string)input('destino', '')));
        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Escribe un correo válido para la prueba.');
            redirect('admin/correo');
        }
        if (!CorreoService::configurado()) {
            flash('error', 'Activa el envío y completa los datos del servidor antes de probar.');
            redirect('admin/correo');
        }
        $e = NotificacionService::prueba($destino);
        if ($e['estado'] === 'enviado') {
            flash('ok', "El servidor aceptó el correo de prueba para $destino. Revisa la bandeja (y la carpeta de spam).");
        } else {
            flash('error', 'No se pudo enviar: ' . $e['ultimo_error']);
        }
        redirect('admin/correo');
    }

    public function reintentar(): void
    {
        Auth::requireAdmin();
        try {
            $e = NotificacionService::reintentar((int)input('id'));
            flash($e['estado'] === 'enviado' ? 'ok' : 'error', $e['estado'] === 'enviado' ? 'Correo enviado.' : 'Sigue fallando: ' . $e['ultimo_error']);
        } catch (InvalidArgumentException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect('admin/correo');
    }
}
