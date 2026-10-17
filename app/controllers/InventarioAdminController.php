<?php
declare(strict_types=1);

final class InventarioAdminController
{
    private function preparar(): void
    {
        Auth::requireAdmin();
        InventarioService::asegurar();
    }

    /* ---------------- Catálogo ---------------- */

    public function catalogo(): void
    {
        $this->preparar();
        $areas = InventarioService::areas();
        $areaId = (int)input('area', 0) ?: (int)($areas[0]['id'] ?? 0);
        $area = InventarioService::area($areaId);
        view('admin/inventario_catalogo', [
            'areas'   => $areas,
            'area'    => $area,
            'items'   => $area ? InventarioService::items($areaId, false) : [],
            'seccion' => 'inventario',
        ]);
    }

    public function crearItem(): void
    {
        $this->preparar();
        try {
            $id = InventarioService::crearItem($_POST);
            if (!empty($_FILES['foto']['name'])) {
                try {
                    InventarioService::subirFoto($id, $_FILES['foto']);
                } catch (InvalidArgumentException $e) {
                    // Si la foto no sirve, no se deja el elemento a medias
                    Database::run('DELETE FROM stock_movimientos WHERE item_id = ?', [$id]);
                    Database::run('DELETE FROM inventario_items WHERE id = ?', [$id]);
                    throw $e;
                }
            }
            flash('ok', 'Elemento agregado.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/inventario', ['area' => (int)input('area_id')]);
    }

    public function guardarItem(): void
    {
        $this->preparar();
        $id = (int)input('id');
        try {
            InventarioService::editarItem($id, $_POST);
            if (!empty($_FILES['foto']['name'])) {
                InventarioService::subirFoto($id, $_FILES['foto']);
            }
            flash('ok', 'Elemento guardado.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/inventario', ['area' => (int)input('area_id')]);
    }

    public function estadoItem(): void
    {
        $this->preparar();
        $it = InventarioService::item((int)input('id'));
        if ($it) {
            InventarioService::estadoItem((int)$it['id'], input('activo') === '1');
            flash('ok', input('activo') === '1' ? 'Elemento activado.' : 'Elemento desactivado. Ya no aparece en el formulario; los inventarios anteriores lo conservan.');
        }
        redirect('admin/inventario', ['area' => (int)($it['area_id'] ?? 0)]);
    }

    public function guardarArea(): void
    {
        $this->preparar();
        $id = (int)input('id');
        $a = InventarioService::area($id);
        $nombre = trim((string)input('nombre', ''));
        if (!$a || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            flash('error', 'El nombre del área debe tener entre 2 y 60 caracteres.');
            redirect('admin/inventario', ['area' => $id]);
        }
        Database::update('areas', ['nombre' => $nombre], 'id = :id', ['id' => $id]);
        Audit::log('area_editada', 'area', $id, null, ['nombre' => $a['nombre']], ['nombre' => $nombre]);
        flash('ok', 'Área renombrada.');
        redirect('admin/inventario', ['area' => $id]);
    }

    public function crearArea(): void
    {
        $this->preparar();
        $nombre = trim((string)input('nombre', ''));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            flash('error', 'El nombre del área debe tener entre 2 y 60 caracteres.');
            redirect('admin/inventario');
        }
        $id = Database::insert('areas', [
            'nombre' => $nombre, 'token' => InventarioService::nuevoToken(),
            'orden' => (int)Database::value('SELECT COALESCE(MAX(orden), 0) + 1 FROM areas'), 'activo' => 1, 'creado' => now_utc(),
        ]);
        Audit::log('area_creada', 'area', $id, null, null, ['nombre' => $nombre]);
        flash('ok', 'Área creada. Descarga su QR en Códigos QR.');
        redirect('admin/inventario', ['area' => $id]);
    }

    /* ---------------- Códigos QR ---------------- */

    public function qr(): void
    {
        $this->preparar();
        view('admin/qr', ['areas' => InventarioService::areas(true), 'seccion' => 'qr']);
    }

    public function regenerarQr(): void
    {
        $this->preparar();
        $id = (int)input('id');
        $a = InventarioService::area($id);
        if ($a) {
            Database::update('areas', ['token' => InventarioService::nuevoToken()], 'id = :id', ['id' => $id]);
            Audit::log('qr_regenerado', 'area', $id, (string)input('motivo', '') ?: null, null, ['area' => $a['nombre']]);
            flash('ok', 'Código QR de ' . $a['nombre'] . ' cambiado. El QR impreso anterior ya no funciona: imprime y pega el nuevo.');
        }
        redirect('admin/qr');
    }

    public function imprimirQr(): void
    {
        $this->preparar();
        $id = (int)input('id', 0);
        $areas = $id ? array_filter([InventarioService::area($id)]) : InventarioService::areas(true);
        view('qr_impresion', ['areas' => array_values($areas), 'title' => 'Códigos QR'], 'impresion');
    }

    /* ---------------- Historial ---------------- */

    public function historial(): void
    {
        $this->preparar();
        $hoy = hoy_local();
        $ok = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d);
        $f = [
            'desde'         => $ok(input('desde')) ? input('desde') : (new DateTimeImmutable($hoy))->modify('-7 days')->format('Y-m-d'),
            'hasta'         => $ok(input('hasta')) ? input('hasta') : $hoy,
            'area_id'       => (int)input('area', 0) ?: null,
            'trabajador_id' => (int)input('trabajador', 0) ?: null,
            'turno'         => isset(InventarioService::TURNOS[(string)input('turno')]) ? input('turno') : null,
        ];
        view('admin/inventarios', [
            'f'            => $f,
            'filas'        => InventarioService::historial($f),
            'areas'        => InventarioService::areas(),
            'trabajadores' => Database::all('SELECT id, nombre FROM trabajadores ORDER BY activo DESC, orden, nombre'),
            'seccion'      => 'inventarios',
        ]);
    }

    public function ver(): void
    {
        $this->preparar();
        $inv = InventarioService::buscar((int)input('id')) ?? abort(404, 'El inventario no existe.');
        view('admin/inventario_ver', [
            'inv'       => $inv,
            'lineas'    => InventarioService::lineas((int)$inv['id']),
            'historial' => Database::all("SELECT * FROM auditoria WHERE entidad = 'inventario' AND entidad_id = ? ORDER BY creado DESC", [$inv['id']]),
            'seccion'   => 'inventarios',
        ]);
    }

    public function corregir(): void
    {
        $this->preparar();
        $id = (int)input('id');
        try {
            InventarioService::corregir($id, (array)($_POST['l'] ?? []), (string)input('observaciones', ''), (string)input('motivo', ''));
            flash('ok', 'Inventario corregido. El PDF se actualizó y el cambio quedó en la auditoría.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/inventarios/ver', ['id' => $id]);
    }

    /* ---------------- Control de loza (stock y faltantes) ---------------- */

    private function areaPedida(): ?array
    {
        $areas = InventarioService::areas(true);
        $id = (int)input('area', 0) ?: (int)($areas[0]['id'] ?? 0);
        return InventarioService::area($id);
    }

    public function stock(): void
    {
        $this->preparar();
        $area = $this->areaPedida();
        $hoy = hoy_local();
        view('admin/stock', [
            'areas'       => InventarioService::areas(true),
            'area'        => $area,
            'control'     => $area ? InventarioService::controlStock((int)$area['id']) : [],
            'faltantes'   => $area ? InventarioService::faltantes((new DateTimeImmutable($hoy))->modify('-30 days')->format('Y-m-d'), $hoy, (int)$area['id']) : [],
            'movimientos' => $area ? InventarioService::movimientos((int)$area['id']) : [],
            'seccion'     => 'stock',
        ]);
    }

    public function contarForm(): void
    {
        $this->preparar();
        $area = $this->areaPedida() ?? abort(404, 'El área no existe.');
        view('admin/stock_contar', [
            'areas' => InventarioService::areas(true),
            'area'  => $area,
            'items' => InventarioService::items((int)$area['id']),
            'turno' => InventarioService::turnoSugerido(),
            'seccion' => 'stock',
        ]);
    }

    public function contar(): void
    {
        $this->preparar();
        $areaId = (int)input('area_id');
        try {
            $r = InventarioService::contarAdmin($areaId, (string)input('turno', ''), (array)($_POST['l'] ?? []), (string)input('observaciones', ''), input('fijar_stock') === '1');
            flash('ok', input('fijar_stock') === '1'
                ? 'Conteo guardado. Esas cantidades quedaron como el stock del área; desde ahora cada inventario se compara contra ellas.'
                : 'Conteo guardado sin cambiar el stock.');
            redirect('admin/inventarios/ver', ['id' => $r['id']]);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('admin/stock/contar', ['area' => $areaId]);
        }
    }

    public function mover(): void
    {
        $this->preparar();
        $it = InventarioService::item((int)input('id'));
        try {
            InventarioService::moverStock((int)input('id'), (string)input('tipo'), (int)input('cantidad'), (string)input('motivo', ''));
            flash('ok', input('tipo') === 'baja' ? 'Se descontó del stock.' : 'Se sumó al stock.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/stock', ['area' => (int)($it['area_id'] ?? 0)]);
    }

    public function reiniciar(): void
    {
        $this->preparar();
        $areaId = (int)input('area_id');
        $n = InventarioService::reiniciarStock($areaId, (string)input('motivo', ''));
        flash('ok', $n ? "Listo: el próximo inventario de esta área fijará el stock de $n elemento(s)." : 'No había stock que reiniciar.');
        redirect('admin/stock', ['area' => $areaId]);
    }

    /* ---------------- Copia de seguridad ---------------- */

    public function respaldo(): void
    {
        $this->preparar();
        $zip = RespaldoService::crear();
        Audit::log('respaldo_descargado', null, null, null, null, ['archivo' => basename($zip)]);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zip) . '"');
        header('Content-Length: ' . filesize($zip));
        header('Cache-Control: no-store');
        readfile($zip);
        @unlink($zip);
        exit;
    }
}
