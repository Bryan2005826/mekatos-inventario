<?php
declare(strict_types=1);

final class TrabajadoresController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $pin = $_SESSION['_pin'] ?? null;   // PIN recién generado: se muestra una sola vez
        unset($_SESSION['_pin']);
        view('admin/trabajadores', [
            'trabajadores' => TrabajadorService::todos(),
            'colores'      => TrabajadorService::COLORES,
            'pin'          => $pin,
            'seccion'      => 'trabajadores',
        ]);
    }

    public function crear(): void
    {
        Auth::requireAdmin();
        try {
            $r = TrabajadorService::crear((string)input('nombre', ''), (string)input('documento', ''), (string)input('color', ''));
            $_SESSION['_pin'] = ['nombre' => trim((string)input('nombre')), 'pin' => $r['pin'], 'nuevo' => true];
            flash('ok', 'Trabajador creado.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/trabajadores');
    }

    public function editar(): void
    {
        Auth::requireAdmin();
        $t = TrabajadorService::buscar((int)input('id')) ?? abort(404, 'El trabajador no existe.');
        view('admin/trabajador_editar', ['t' => $t, 'colores' => TrabajadorService::COLORES, 'registros' => TrabajadorService::registros((int)$t['id']), 'seccion' => 'trabajadores']);
    }

    public function guardar(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            TrabajadorService::actualizar($id, (string)input('nombre', ''), (string)input('documento', ''), (string)input('color', ''), (int)input('orden', 0), (string)input('motivo', ''));
            flash('ok', 'Cambios guardados.');
            redirect('admin/trabajadores');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('admin/trabajadores/editar', ['id' => $id]);
        }
    }

    public function pin(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            $t = TrabajadorService::buscar($id) ?? throw new InvalidArgumentException('El trabajador no existe.');
            $pin = TrabajadorService::restablecerPin($id, (string)input('motivo', 'Restablecimiento de PIN'));
            $_SESSION['_pin'] = ['nombre' => $t['nombre'], 'pin' => $pin, 'nuevo' => false];
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/trabajadores');
    }

    public function estado(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        $activo = input('activo') === '1';
        $motivo = (string)input('motivo', '');
        if (!$activo && mb_strlen(trim($motivo)) < 3) {
            flash('error', 'Escribe el motivo para desactivar al trabajador.');
            redirect('admin/trabajadores/editar', ['id' => $id]);
        }
        try {
            TrabajadorService::cambiarEstado($id, $activo, $motivo);
            flash('ok', $activo ? 'Trabajador activado.' : 'Trabajador desactivado. Su historial se conserva.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/trabajadores');
    }

    public function eliminar(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            $t = TrabajadorService::buscar($id) ?? throw new InvalidArgumentException('El trabajador no existe.');
            $r = TrabajadorService::eliminar($id, (string)input('motivo', ''));
            flash($r === 'eliminado' ? 'ok' : 'info', $r === 'eliminado'
                ? $t['nombre'] . ' fue eliminado.'
                : $t['nombre'] . ' ya tiene horas, planillas o inventarios registrados, así que no se puede borrar: quedó desactivado. Ya no aparece en el kiosco ni en los QR, y su historial se conserva como soporte de lo que se le pagó.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/trabajadores');
    }
}
