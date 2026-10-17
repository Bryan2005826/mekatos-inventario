/* Kiosco de asistencia: selección de trabajador, PIN, foto de evidencia y registro. */
(function () {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const BASE = $('meta[name="base"]').content.replace(/\/$/, '');
  let csrf = $('meta[name="csrf"]').content;
  const conf = JSON.parse(($('#k-config') || {}).textContent || '{}');

  const INACTIVIDAD_MS = 60000;
  const CIERRE_RESULTADO_MS = 7000;

  let trabajador = null;   // {id, nombre}
  let pin = '';
  let ocupado = false;
  let stream = null;
  let temporizador = null;
  let cierreResultado = null;

  /* ---------- utilidades ---------- */
  async function api(ruta, opciones = {}) {
    const resp = await fetch(BASE + '/' + ruta, {
      method: opciones.method || 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: opciones.body ? JSON.stringify(opciones.body) : undefined,
    });
    let data;
    try { data = await resp.json(); } catch (e) { data = { ok: false, error: 'Respuesta inválida del servidor.' }; }
    if (!resp.ok && data.ok === undefined) data.ok = false;
    if (data.csrf === true) { location.reload(); }
    return data;
  }

  function capa(id) {
    $$('.k-capa').forEach(c => { c.hidden = c.id !== id; });
    const visible = id && $('#' + id);
    if (visible) {
      const foco = visible.querySelector('[autofocus], .k-teclado button, .k-accion:not(:disabled), .btn-primario, button');
      if (foco) setTimeout(() => foco.focus({ preventScroll: true }), 50);
    }
    reiniciarInactividad();
  }

  function mostrarError(el, msg) {
    el.textContent = msg;
    el.hidden = !msg;
  }

  function reiniciarInactividad() {
    clearTimeout(temporizador);
    if (trabajador) temporizador = setTimeout(terminar, INACTIVIDAD_MS);
  }

  function terminar() {
    detenerCamara();
    clearTimeout(cierreResultado);
    clearTimeout(temporizador);
    if (trabajador) api('api/kiosco/salir', { method: 'POST' });
    trabajador = null;
    pin = '';
    ocupado = false;
    pintarPin();
    capa(null);
  }

  /* ---------- reloj con la hora oficial del servidor ---------- */
  let desfase = 0;
  let zona = 'America/Bogota';
  const fmtHora = () => new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: zona });
  const fmtFecha = () => new Intl.DateTimeFormat('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: zona });

  async function sincronizar() {
    try {
      const t0 = Date.now();
      const r = await api('api/hora');
      const t1 = Date.now();
      if (r.ok) {
        desfase = r.epoch * 1000 - (t0 + t1) / 2;
        zona = r.tz || zona;
      }
    } catch (e) { /* sin red: se usa el reloj local */ }
  }

  function tic() {
    const ahora = new Date(Date.now() + desfase);
    const partes = fmtHora().formatToParts(ahora);
    const valor = t => (partes.find(p => p.type === t) || {}).value || '';
    const hora = valor('hour') + ':' + valor('minute');
    const sufijo = valor('dayPeriod');
    const reloj = $('#k-reloj');
    if (reloj) {
      reloj.textContent = hora;
      if (sufijo) {
        const s = document.createElement('small');
        s.textContent = sufijo;
        reloj.appendChild(s);
      }
    }
    const f = $('#k-fecha');
    if (f) { const t = fmtFecha().format(ahora); f.textContent = t.charAt(0).toUpperCase() + t.slice(1); }
  }

  /* ---------- PIN ---------- */
  function pintarPin() {
    $$('#pin-puntos span').forEach((s, i) => s.classList.toggle('lleno', i < pin.length));
  }

  function abrirTrabajador(btn) {
    trabajador = { id: parseInt(btn.dataset.trabajador, 10), nombre: btn.dataset.nombre };
    pin = '';
    pintarPin();
    $$('[data-nombre-trab]').forEach(el => { el.textContent = trabajador.nombre; });
    mostrarError($('#pin-error'), '');
    capa('capa-pin');
  }

  async function enviarPin() {
    if (ocupado) return;
    ocupado = true;
    $('#teclado').classList.add('ocupado');
    const r = await api('api/kiosco/login', { method: 'POST', body: { trabajador_id: trabajador.id, pin } });
    ocupado = false;
    $('#teclado').classList.remove('ocupado');
    if (!r.ok) {
      pin = '';
      pintarPin();
      const puntos = $('#pin-puntos');
      puntos.classList.remove('sacudir');
      void puntos.offsetWidth;
      puntos.classList.add('sacudir');
      mostrarError($('#pin-error'), r.error || 'No se pudo verificar el PIN.');
      return;
    }
    if (r.csrf) csrf = r.csrf;
    trabajador.estado = r;
    if (conf.fotoObligatoria && !r.consentimiento && r.puede_marcar) {
      capa('capa-consentimiento');
    } else {
      mostrarAcciones();
    }
  }

  function teclear(e) {
    const b = e.target.closest('button');
    if (!b || ocupado) return;
    reiniciarInactividad();
    if (b.hasAttribute('data-borrar')) pin = pin.slice(0, -1);
    else if (b.hasAttribute('data-borrar-todo')) pin = '';
    else if (b.dataset.digito !== undefined && pin.length < 6) pin += b.dataset.digito;
    mostrarError($('#pin-error'), '');
    pintarPin();
    if (pin.length === 6) enviarPin();
  }

  /* ---------- acciones ---------- */
  function mostrarAcciones() {
    const s = trabajador.estado;
    const ent = $('[data-marcar="entrada"]');
    const sal = $('[data-marcar="salida"]');
    mostrarError($('#acc-error'), '');
    if (!s.puede_marcar) {
      ent.disabled = sal.disabled = true;
      $('#acc-estado').textContent = 'Este dispositivo no está autorizado. Marca tu entrada o salida en el kiosco del local.';
    } else {
      ent.disabled = !s.puede_entrada;
      sal.disabled = !s.puede_salida;
      $('#acc-estado').textContent = s.puede_salida
        ? 'Tienes una entrada abierta desde las ' + s.entrada_hora
        : 'No tienes una jornada abierta.';
    }
    capa('capa-acciones');
  }

  async function aceptarConsentimiento() {
    const r = await api('api/kiosco/consentimiento', { method: 'POST' });
    if (!r.ok) {
      if (r.expirada) return terminar();
      return;
    }
    trabajador.estado.consentimiento = true;
    mostrarAcciones();
  }

  /* ---------- cámara ---------- */
  function detenerCamara() {
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
    const v = $('#video');
    if (v) v.srcObject = null;
  }

  function mensajeCamara(err) {
    if (!window.isSecureContext) return 'La cámara solo funciona con conexión segura (https). Avisa al administrador.';
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return 'Este navegador no permite usar la cámara. Usa Chrome actualizado.';
    switch (err && err.name) {
      case 'NotAllowedError': return 'El permiso de cámara está bloqueado. Actívalo en la configuración del navegador para este sitio y vuelve a intentarlo.';
      case 'NotFoundError': return 'No se encontró una cámara en este dispositivo.';
      case 'NotReadableError': return 'Otra aplicación está usando la cámara. Ciérrala y vuelve a intentarlo.';
      default: return 'No se pudo abrir la cámara. Vuelve a intentarlo.';
    }
  }

  const esperar = ms => new Promise(r => setTimeout(r, ms));

  async function tomarFoto() {
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error(mensajeCamara(null));
    }
    capa('capa-camara');
    $('#cam-ayuda').textContent = 'Ubica tu rostro dentro del óvalo';
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 1280 } }, audio: false });
    } catch (err) {
      throw new Error(mensajeCamara(err));
    }
    const video = $('#video');
    video.srcObject = stream;
    await video.play().catch(() => {});
    await esperar(400);

    const cuenta = $('#cuenta');
    for (let n = 3; n > 0; n--) {
      if (!stream) throw new Error('cancelado');
      cuenta.textContent = n;
      await esperar(800);
    }
    cuenta.textContent = '';
    if (!stream) throw new Error('cancelado');

    const lienzo = $('#lienzo');
    const w = video.videoWidth || 640;
    const h = video.videoHeight || 480;
    const escala = Math.min(1, 640 / w);
    lienzo.width = Math.round(w * escala);
    lienzo.height = Math.round(h * escala);
    const ctx = lienzo.getContext('2d');
    ctx.drawImage(video, 0, 0, lienzo.width, lienzo.height);
    const visor = $('.k-visor');
    visor.classList.remove('destello');
    void visor.offsetWidth;
    visor.classList.add('destello');

    // Revisión simple de luz: si la imagen está casi negra, se pide repetir
    const muestra = ctx.getImageData(0, 0, lienzo.width, lienzo.height).data;
    let suma = 0, n = 0;
    for (let i = 0; i < muestra.length; i += 4 * 40) { suma += 0.299 * muestra[i] + 0.587 * muestra[i + 1] + 0.114 * muestra[i + 2]; n++; }
    detenerCamara();
    if (n && suma / n < 35) throw new Error('Hay muy poca luz para la foto. Acércate a una zona iluminada y vuelve a intentarlo.');
    return lienzo.toDataURL('image/jpeg', 0.8);
  }

  async function marcar(tipo) {
    if (ocupado) return;
    ocupado = true;
    mostrarError($('#acc-error'), '');
    let foto = null;
    try {
      if (conf.fotoObligatoria) foto = await tomarFoto();
    } catch (err) {
      ocupado = false;
      detenerCamara();
      if (err.message === 'cancelado' || !trabajador) return;
      capa('capa-acciones');
      mostrarError($('#acc-error'), err.message);
      return;
    }
    const r = await api('api/kiosco/marcar', { method: 'POST', body: { tipo, foto } });
    ocupado = false;
    if (r.expirada) {
      terminar();
      return;
    }
    if (r.requiere_consentimiento) {
      capa('capa-consentimiento');
      return;
    }
    mostrarResultado(r, tipo);
  }

  function mostrarResultado(r, tipo) {
    const caja = $('#resultado');
    caja.className = 'k-panel k-resultado ' + (r.ok ? 'ok ' + tipo : 'error');
    if (r.ok) {
      $('#res-titulo').textContent = (tipo === 'entrada' ? 'Entrada' : 'Salida') + ' registrada correctamente';
      $('#res-hora').textContent = r.hora;
      const extra = [];
      if (r.trabajado) extra.push('Tiempo de esta jornada: ' + r.trabajado + '.');
      if (r.aviso) extra.push(r.aviso);
      extra.push(tipo === 'entrada' ? '¡Buen turno, ' + trabajador.nombre + '!' : '¡Hasta luego, ' + trabajador.nombre + '!');
      $('#res-extra').textContent = extra.join(' ');
      trabajador = null; // la sesión ya se cerró en el servidor
    } else {
      $('#res-titulo').textContent = 'No se registró';
      $('#res-hora').textContent = '';
      $('#res-extra').textContent = r.error || 'Ocurrió un error. Inténtalo de nuevo.';
    }
    capa('capa-resultado');
    clearTimeout(cierreResultado);
    cierreResultado = setTimeout(terminar, CIERRE_RESULTADO_MS);
  }

  /* ---------- historial ---------- */
  async function verHistorial() {
    const r = await api('api/kiosco/historial');
    if (!r.ok) {
      if (r.expirada) return terminar();
      return;
    }
    const tb = $('#hist-filas');
    tb.textContent = '';
    if (!r.jornadas.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 4;
      td.textContent = 'No tienes registros en los últimos 30 días.';
      tr.appendChild(td);
      tb.appendChild(tr);
    }
    r.jornadas.forEach(j => {
      const tr = document.createElement('tr');
      const tiempo = j.trabajado || (j.estado === 'abierta' ? 'En curso' : 'Incompleta');
      [j.fecha, j.entrada, j.salida, tiempo + (j.corregida ? ' (corregida)' : '')].forEach(v => {
        const td = document.createElement('td');
        td.textContent = v;
        tr.appendChild(td);
      });
      tb.appendChild(tr);
    });
    $('#hist-total').textContent = r.quincena + ': ' + r.quincena_total;
    capa('capa-historial');
  }

  /* ---------- eventos ---------- */
  document.addEventListener('click', e => {
    const ficha = e.target.closest('button.k-ficha[data-trabajador]');
    if (ficha) return abrirTrabajador(ficha);
    if (e.target.closest('[data-cerrar]')) {
      const enHistorial = !$('#capa-historial').hidden;
      if (enHistorial && trabajador) return mostrarAcciones();
      if (!$('#capa-camara').hidden) { detenerCamara(); ocupado = false; return mostrarAcciones(); }
      return terminar();
    }
    const m = e.target.closest('[data-marcar]');
    if (m && !m.disabled) return marcar(m.dataset.marcar);
    if (e.target.closest('#btn-historial')) return verHistorial();
    if (e.target.closest('#btn-aceptar')) return aceptarConsentimiento();
  });
  const teclado = $('#teclado');
  if (teclado) teclado.addEventListener('click', teclear);

  document.addEventListener('keydown', e => {
    if ($('#capa-pin') && !$('#capa-pin').hidden) {
      if (/^\d$/.test(e.key) && pin.length < 6) { pin += e.key; pintarPin(); if (pin.length === 6) enviarPin(); }
      else if (e.key === 'Backspace') { pin = pin.slice(0, -1); pintarPin(); }
    }
    if (e.key === 'Escape' && trabajador) terminar();
    reiniciarInactividad();
  });
  ['pointerdown', 'touchstart'].forEach(ev => document.addEventListener(ev, reiniciarInactividad, { passive: true }));

  if ($('#k-reloj')) {
    sincronizar().then(tic);
    setInterval(tic, 1000);
    setInterval(sincronizar, 10 * 60 * 1000);
    tic();
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(BASE + '/sw.js').catch(() => {});
  }
})();
