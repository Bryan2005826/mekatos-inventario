<div class="k-pantalla">
  <header class="k-cabecera">
    <div>
      <p class="k-local"><?= e($establecimiento) ?></p>
      <p class="k-fecha" id="k-fecha">&nbsp;</p>
    </div>
    <p class="k-reloj" id="k-reloj" aria-live="off">--:--</p>
  </header>

  <?php require APP_PATH . '/views/partials/flashes.php'; ?>

  <?php if (!$dispositivo): ?>
    <div class="k-banda">
      <p><strong>Este dispositivo no está autorizado para registrar asistencia.</strong>
      Puedes consultar tu historial, pero la entrada y la salida solo se marcan en el kiosco del local.</p>
      <a class="btn btn-claro btn-sm" href="<?= e(url('activar')) ?>">Autorizar este dispositivo</a>
    </div>
  <?php endif; ?>

  <h1 class="k-titulo">¿Quién eres?</h1>

  <?php if (!$trabajadores): ?>
    <p class="k-vacio">Todavía no hay trabajadores. El administrador puede agregarlos en el panel.</p>
  <?php endif; ?>

  <ul class="k-grilla" role="list">
    <?php foreach ($trabajadores as $t): ?>
      <li>
        <button type="button" class="k-ficha" data-trabajador="<?= (int)$t['id'] ?>" data-nombre="<?= e($t['nombre']) ?>" style="--c: <?= e($t['color']) ?>">
          <span class="k-ficha-inicial" aria-hidden="true"><?= e(initials($t['nombre'])) ?></span>
          <span class="k-ficha-nombre"><?= e($t['nombre']) ?></span>
        </button>
      </li>
    <?php endforeach; ?>
    <li>
      <a class="k-ficha k-ficha-admin" href="<?= e(url('admin/login')) ?>">
        <span class="k-ficha-inicial" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        </span>
        <span class="k-ficha-nombre">Administrador</span>
      </a>
    </li>
  </ul>
</div>

<!-- Paso 1: PIN -->
<section class="k-capa" id="capa-pin" hidden aria-labelledby="pin-titulo">
  <div class="k-panel k-panel-pin">
    <button class="k-cerrar" type="button" data-cerrar aria-label="Cancelar">×</button>
    <p class="k-panel-hola">Hola,</p>
    <h2 id="pin-titulo" class="k-panel-nombre" data-nombre-trab></h2>
    <p class="k-ayuda">Escribe tu PIN de 6 dígitos</p>
    <div class="k-pin-puntos" id="pin-puntos" aria-hidden="true">
      <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
    <p class="k-error" id="pin-error" role="alert" hidden></p>
    <div class="k-teclado" id="teclado">
      <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n): ?>
        <button type="button" data-digito="<?= $n ?>"><?= $n ?></button>
      <?php endforeach; ?>
      <button type="button" data-borrar aria-label="Borrar">⌫</button>
      <button type="button" data-digito="0">0</button>
      <button type="button" data-borrar-todo aria-label="Borrar todo">C</button>
    </div>
  </div>
</section>

<!-- Paso 1b: autorización de datos (solo la primera vez) -->
<section class="k-capa" id="capa-consentimiento" hidden aria-labelledby="cons-titulo">
  <div class="k-panel k-panel-texto">
    <h2 id="cons-titulo">Autorización de tratamiento de datos</h2>
    <div class="k-legal">
      <p>Autorizo a <strong><?= e($establecimiento) ?></strong> para tratar mis datos personales conforme a la Ley 1581 de 2012: mi nombre, la fecha y hora de mis entradas y salidas, y una <strong>foto de evidencia</strong> que se toma en cada registro.</p>
      <p>La finalidad es controlar la asistencia y liquidar el pago de las horas trabajadas. Las fotos no se usan para reconocimiento facial automático, solo el administrador puede verlas y se eliminan después de <?= (int)Settings::int('retencion_fotos_dias') ?> días.</p>
      <p>Puedo conocer, actualizar, rectificar y solicitar la supresión de mis datos pidiéndolo al administrador.</p>
    </div>
    <div class="k-fila-botones">
      <button type="button" class="btn btn-quieto" data-cerrar>No acepto</button>
      <button type="button" class="btn btn-primario" id="btn-aceptar">Acepto</button>
    </div>
  </div>
</section>

<!-- Paso 2: entrada o salida -->
<section class="k-capa" id="capa-acciones" hidden aria-labelledby="acc-titulo">
  <div class="k-panel k-panel-acciones">
    <button class="k-cerrar" type="button" data-cerrar aria-label="Terminar">×</button>
    <h2 id="acc-titulo" class="k-panel-nombre" data-nombre-trab></h2>
    <p class="k-estado" id="acc-estado"></p>
    <p class="k-error" id="acc-error" role="alert" hidden></p>
    <div class="k-acciones">
      <button type="button" class="k-accion k-accion-entrada" data-marcar="entrada">
        <span class="k-accion-flecha" aria-hidden="true">↘</span>
        <span>Registrar entrada</span>
      </button>
      <button type="button" class="k-accion k-accion-salida" data-marcar="salida">
        <span class="k-accion-flecha" aria-hidden="true">↗</span>
        <span>Registrar salida</span>
      </button>
    </div>
    <button type="button" class="btn btn-quieto" id="btn-historial">Ver mi historial</button>
  </div>
</section>

<!-- Paso 3: foto de evidencia -->
<section class="k-capa k-capa-oscura" id="capa-camara" hidden aria-labelledby="cam-titulo">
  <div class="k-camara">
    <h2 id="cam-titulo" class="k-camara-titulo">Mira a la cámara</h2>
    <div class="k-visor">
      <video id="video" playsinline muted autoplay></video>
      <div class="k-guia" aria-hidden="true"></div>
      <p class="k-cuenta" id="cuenta" aria-live="assertive"></p>
    </div>
    <p class="k-ayuda k-ayuda-clara" id="cam-ayuda">Ubica tu rostro dentro del óvalo</p>
    <button type="button" class="btn btn-claro" data-cerrar>Cancelar</button>
    <canvas id="lienzo" hidden></canvas>
  </div>
</section>

<!-- Paso 4: resultado -->
<section class="k-capa" id="capa-resultado" hidden aria-live="polite">
  <div class="k-panel k-resultado" id="resultado">
    <div class="k-resultado-icono" aria-hidden="true"></div>
    <p class="k-resultado-titulo" id="res-titulo"></p>
    <p class="k-resultado-hora" id="res-hora"></p>
    <p class="k-resultado-extra" id="res-extra"></p>
    <button type="button" class="btn btn-primario" data-cerrar>Listo</button>
  </div>
</section>

<!-- Historial personal -->
<section class="k-capa" id="capa-historial" hidden aria-labelledby="hist-titulo">
  <div class="k-panel k-panel-texto">
    <button class="k-cerrar" type="button" data-cerrar aria-label="Cerrar">×</button>
    <h2 id="hist-titulo">Mis registros (últimos 30 días)</h2>
    <p class="k-ayuda" id="hist-total"></p>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Tiempo</th></tr></thead>
        <tbody id="hist-filas"></tbody>
      </table>
    </div>
  </div>
</section>

<script type="application/json" id="k-config"><?= json_encode([
    'fotoObligatoria' => $fotoObligatoria,
    'autorizado' => $dispositivo !== null,
], JSON_HEX_TAG) ?></script>
