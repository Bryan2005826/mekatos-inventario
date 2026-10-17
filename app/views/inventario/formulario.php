<div class="k-pantalla inv-pantalla" data-token="<?= e($area['token']) ?>" data-turno="<?= e($turno) ?>">
  <header class="inv-cabecera">
    <p class="k-local"><?= e($establecimiento) ?> · Inventario</p>
    <h1 class="inv-area"><?= e($area['nombre']) ?></h1>
  </header>

  <!-- Paso 1: quién -->
  <section id="paso-quien">
    <h2 class="k-titulo">¿Quién hace el inventario?</h2>
    <ul class="k-grilla" role="list">
      <?php foreach ($trabajadores as $t): ?>
        <li>
          <button type="button" class="k-ficha" data-trabajador="<?= (int)$t['id'] ?>" data-nombre="<?= e($t['nombre']) ?>" style="--c: <?= e($t['color']) ?>">
            <span class="k-ficha-inicial" aria-hidden="true"><?= e(initials($t['nombre'])) ?></span>
            <span class="k-ficha-nombre"><?= e($t['nombre']) ?></span>
          </button>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- Paso 3: turno -->
  <section id="paso-turno" hidden>
    <h2 class="k-titulo">Hola, <span data-nombre-trab></span>. ¿Qué turno es?</h2>
    <div class="inv-turnos">
      <button type="button" class="inv-turno" data-turno-btn="mediodia"><span>Mediodía</span></button>
      <button type="button" class="inv-turno" data-turno-btn="noche"><span>Noche</span></button>
    </div>
    <button type="button" class="btn btn-claro" data-salir>Salir</button>
  </section>
</div>

<!-- Paso 4: lista de elementos -->
<section id="paso-lista" class="inv-hoja" hidden aria-labelledby="lista-titulo">
  <div class="inv-hoja-cabecera">
    <div>
      <h2 id="lista-titulo"><?= e($area['nombre']) ?> · <span data-turno-nombre></span></h2>
      <p class="sub"><span data-fecha></span> · <span data-nombre-trab></span></p>
    </div>
    <button type="button" class="btn btn-quieto btn-sm" data-cambiar-turno>Cambiar turno</button>
  </div>
  <p class="aviso aviso-error" id="lista-enviado" hidden></p>
  <p class="aviso aviso-info" id="lista-borrador" hidden></p>
  <p class="k-error" id="lista-error" role="alert" hidden></p>
  <ol class="inv-items" id="items" role="list"></ol>
  <label class="campo inv-obs"><span>Observaciones generales (opcional)</span><textarea id="obs" rows="2" maxlength="1000" placeholder="Ej.: se rompieron dos platos en el almuerzo"></textarea></label>
  <div class="inv-barra">
    <span class="inv-guardado" id="guardado" aria-live="polite"></span>
    <button type="button" class="btn btn-quieto" id="btn-borrador">Guardar borrador</button>
    <button type="button" class="btn btn-primario" id="btn-revisar">Revisar</button>
  </div>
</section>

<!-- Paso 5: revisión -->
<section id="paso-revision" class="inv-hoja" hidden aria-labelledby="rev-titulo">
  <h2 id="rev-titulo">Revisa antes de enviar</h2>
  <p class="sub"><?= e($area['nombre']) ?> · <span data-turno-nombre></span> · <span data-fecha></span></p>
  <div class="tabla-envoltura">
    <table class="tabla inv-resumen">
      <thead><tr><th>Elemento</th><th class="num">Contaste</th><th>Estado</th></tr></thead>
      <tbody id="resumen"></tbody>
    </table>
  </div>
  <p id="rev-obs" class="sub"></p>
  <p class="aviso aviso-info">Revisa bien: al enviar, las cantidades quedan fijas y no se pueden cambiar.</p>
  <p class="k-error" id="rev-error" role="alert" hidden></p>
  <div class="inv-barra">
    <button type="button" class="btn btn-quieto" id="btn-volver">Corregir</button>
    <button type="button" class="btn btn-primario btn-enviar" id="btn-enviar">ENVIAR INVENTARIO</button>
  </div>
</section>

<!-- Paso 6: explicar diferencias (después de enviar) -->
<section id="paso-porque" class="inv-hoja" hidden aria-labelledby="porque-titulo">
  <h2 id="porque-titulo">Tu conteo quedó guardado</h2>
  <p class="sub">Algunas cantidades no cuadran con lo que debe haber. Cuéntanos por qué. Las cantidades ya no se pueden cambiar.</p>
  <ol class="inv-items" id="preguntas" role="list"></ol>
  <p class="k-error" id="porque-error" role="alert" hidden></p>
  <div class="inv-barra">
    <button type="button" class="btn btn-primario btn-enviar" id="btn-explicar">Enviar explicación</button>
  </div>
</section>

<!-- PIN -->
<section class="k-capa" id="capa-pin" hidden aria-labelledby="pin-titulo">
  <div class="k-panel k-panel-pin">
    <button class="k-cerrar" type="button" data-cerrar-pin aria-label="Cancelar">×</button>
    <p class="k-panel-hola">Hola,</p>
    <h2 id="pin-titulo" class="k-panel-nombre" data-nombre-trab></h2>
    <p class="k-ayuda">Escribe tu PIN de 6 dígitos</p>
    <div class="k-pin-puntos" id="pin-puntos" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>
    <p class="k-error" id="pin-error" role="alert" hidden></p>
    <div class="k-teclado" id="teclado">
      <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n): ?><button type="button" data-digito="<?= $n ?>"><?= $n ?></button><?php endforeach; ?>
      <button type="button" data-borrar aria-label="Borrar">⌫</button>
      <button type="button" data-digito="0">0</button>
      <button type="button" data-borrar-todo aria-label="Borrar todo">C</button>
    </div>
  </div>
</section>

<!-- Enviado -->
<section class="k-capa" id="capa-listo" hidden aria-live="polite">
  <div class="k-panel k-resultado ok">
    <div class="k-resultado-icono" aria-hidden="true"></div>
    <p class="k-resultado-titulo">Inventario enviado correctamente</p>
    <p class="k-resultado-hora" id="listo-hora"></p>
    <p class="k-resultado-extra" id="listo-extra"></p>
    <button type="button" class="btn btn-primario btn-ancho" id="btn-compartir">Compartir PDF (WhatsApp)</button>
    <a class="btn btn-quieto btn-ancho" id="btn-descargar" href="#">Descargar PDF</a>
    <button type="button" class="btn btn-quieto btn-ancho" data-salir>Terminar</button>
  </div>
</section>
