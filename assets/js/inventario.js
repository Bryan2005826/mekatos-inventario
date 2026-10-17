/* Formulario de inventario por área (se abre desde el QR). */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const BASE = $('meta[name="base"]').content.replace(/\/$/, '');
  let csrf = $('meta[name="csrf"]').content;
  const raiz = $('.inv-pantalla');
  const TOKEN = raiz.dataset.token;

  let trabajador = null;
  let turno = null;
  let pin = '';
  let ocupado = false;
  let datos = null;       // respuesta de api/inv/datos
  let enviado = null;     // respuesta de api/inv/enviar
  let cambios = false;
  let autoguardado = null;

  async function api(ruta, opciones = {}) {
    const metodo = opciones.method || 'GET';
    let url = BASE + '/' + ruta;
    let body;
    if (metodo === 'GET') {
      url += '?' + new URLSearchParams(Object.assign({ a: TOKEN }, opciones.query || {}));
    } else {
      body = JSON.stringify(Object.assign({ a: TOKEN }, opciones.body || {}));
    }
    try {
      const r = await fetch(url, {
        method: metodo, credentials: 'same-origin', body,
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      });
      const d = await r.json();
      if (d.expirada || d.csrf === true) { alert(d.error); location.reload(); }
      return d;
    } catch (e) {
      return { ok: false, error: 'Sin conexión con el servidor. Revisa el internet y vuelve a intentarlo.' };
    }
  }

  function error(el, msg) { el.textContent = msg || ''; el.hidden = !msg; }
  function mostrar(id) {
    ['paso-quien', 'paso-turno', 'paso-lista', 'paso-revision', 'paso-porque'].forEach(p => { $('#' + p).hidden = p !== id; });
    $('.inv-pantalla').hidden = !['paso-quien', 'paso-turno'].includes(id);
    window.scrollTo(0, 0);
  }

  /* ---------- PIN ---------- */
  function pintarPin() { $$('#pin-puntos span').forEach((s, i) => s.classList.toggle('lleno', i < pin.length)); }
  async function enviarPin() {
    if (ocupado) return;
    ocupado = true;
    const r = await api('api/inv/login', { method: 'POST', body: { trabajador_id: trabajador.id, pin } });
    ocupado = false;
    if (!r.ok) {
      pin = ''; pintarPin();
      const p = $('#pin-puntos'); p.classList.remove('sacudir'); void p.offsetWidth; p.classList.add('sacudir');
      return error($('#pin-error'), r.error);
    }
    csrf = r.csrf || csrf;
    $('#capa-pin').hidden = true;
    $$('[data-nombre-trab]').forEach(e => { e.textContent = r.nombre; });
    $$('[data-turno-btn]').forEach(b => b.classList.toggle('sugerido', b.dataset.turnoBtn === (r.turno || raiz.dataset.turno)));
    mostrar('paso-turno');
  }

  /* ---------- lista ---------- */
  function tarjeta(it, v) {
    const li = document.createElement('li');
    li.className = 'inv-item';
    li.dataset.id = it.id;
    li.dataset.tipo = it.tipo;

    const foto = document.createElement('div');
    foto.className = 'inv-foto';
    if (it.foto) {
      const img = document.createElement('img');
      img.src = it.foto; img.alt = ''; img.loading = 'lazy'; img.width = 96; img.height = 96;
      foto.appendChild(img);
    } else {
      foto.textContent = 'Sin foto';
    }
    li.appendChild(foto);

    const cuerpo = document.createElement('div');
    cuerpo.className = 'inv-cuerpo';
    const h = document.createElement('h3'); h.textContent = it.nombre; cuerpo.appendChild(h);
    if (it.nuevo) {
      const e = document.createElement('p'); e.className = 'inv-esperada inv-base'; e.textContent = 'Primer conteo: cuenta bien, esta cantidad quedará como el stock.'; cuerpo.appendChild(e);
    }

    if (it.tipo === 'cantidad') {
      const fila = document.createElement('div');
      fila.className = 'inv-contador';
      const menos = document.createElement('button'); menos.type = 'button'; menos.textContent = '−'; menos.setAttribute('aria-label', 'Restar uno'); menos.dataset.paso = '-1';
      const input = document.createElement('input');
      input.type = 'number'; input.inputMode = 'numeric'; input.min = '0'; input.max = '100000'; input.step = '1';
      input.placeholder = '0'; input.setAttribute('aria-label', 'Cantidad de ' + it.nombre); input.className = 'inv-cantidad';
      if (v && v.cantidad !== null && v.cantidad !== undefined) input.value = v.cantidad;
      const mas = document.createElement('button'); mas.type = 'button'; mas.textContent = '+'; mas.setAttribute('aria-label', 'Sumar uno'); mas.dataset.paso = '1';
      fila.append(menos, input, mas);
      cuerpo.appendChild(fila);
    }

    const estados = document.createElement('div');
    estados.className = 'inv-estados';
    estados.setAttribute('role', 'radiogroup');
    estados.setAttribute('aria-label', 'Estado de ' + it.nombre);
    const actual = (v && v.estado) || 'bien';
    Object.entries(datos.estados).forEach(([k, txt]) => {
      const b = document.createElement('button');
      b.type = 'button'; b.dataset.estado = k; b.textContent = txt;
      b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', String(k === actual));
      estados.appendChild(b);
    });
    cuerpo.appendChild(estados);

    const obs = document.createElement('input');
    obs.className = 'inv-obs-item'; obs.maxLength = 255; obs.placeholder = 'Observación (opcional)';
    obs.setAttribute('aria-label', 'Observación de ' + it.nombre);
    if (v && v.observacion) obs.value = v.observacion;
    cuerpo.appendChild(obs);

    li.appendChild(cuerpo);
    return li;
  }

  function recoger() {
    const lineas = {};
    $$('.inv-item').forEach(li => {
      const est = $('[aria-checked="true"]', li);
      const c = $('.inv-cantidad', li);
      lineas[li.dataset.id] = {
        cantidad: c ? c.value.trim() : '',
        estado: est ? est.dataset.estado : 'bien',
        observacion: $('.inv-obs-item', li).value.trim(),
      };
    });
    return lineas;
  }

  async function abrirTurno(t) {
    turno = t;
    const r = await api('api/inv/datos', { query: { turno } });
    if (!r.ok) return alert(r.error);
    datos = r;
    $$('[data-turno-nombre]').forEach(e => { e.textContent = r.turno; });
    $$('[data-fecha]').forEach(e => { e.textContent = r.fecha; });
    const lista = $('#items');
    lista.textContent = '';
    if (!r.items.length) {
      const li = document.createElement('li'); li.textContent = 'Esta área no tiene elementos configurados. Avisa al administrador.'; lista.appendChild(li);
    }
    r.items.forEach(it => lista.appendChild(tarjeta(it, r.valores[it.id])));
    $('#obs').value = r.obs || '';
    const env = $('#lista-enviado');
    env.hidden = !r.enviado;
    if (r.enviado) env.textContent = 'Este turno ya lo envió ' + r.enviado.por + ' a las ' + r.enviado.hora + '. No se puede enviar otra vez; si hay un error, avisa al administrador.';
    $('#btn-revisar').disabled = !!r.enviado;
    const bor = $('#lista-borrador');
    bor.hidden = !r.borrador;
    if (r.borrador) bor.textContent = 'Recuperamos tu borrador guardado a las ' + r.borrador + '.';
    error($('#lista-error'), '');
    $('#guardado').textContent = '';
    cambios = false;
    mostrar('paso-lista');
  }

  async function guardarBorrador(silencioso) {
    if (!turno || (datos && datos.enviado)) return;
    const r = await api('api/inv/borrador', { method: 'POST', body: { turno, lineas: recoger(), obs: $('#obs').value } });
    if (r.ok) { cambios = false; $('#guardado').textContent = 'Borrador guardado ' + r.hora; }
    else if (!silencioso) error($('#lista-error'), r.error);
  }

  function marcarCambio() {
    cambios = true;
    $('#guardado').textContent = '';
    clearTimeout(autoguardado);
    autoguardado = setTimeout(() => guardarBorrador(true), 4000);
  }

  function revisar() {
    // validación en el celular antes de pasar a la revisión
    const faltan = $$('.inv-item').filter(li => li.dataset.tipo === 'cantidad' && $('.inv-cantidad', li).value.trim() === '');
    if (faltan.length) {
      error($('#lista-error'), 'Falta la cantidad de: ' + faltan.map(li => $('h3', li).textContent).join(', ') + '.');
      faltan[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      $('.inv-cantidad', faltan[0]).focus();
      return;
    }
    const sinExplicar = $$('.inv-item').filter(li => {
      const est = $('[aria-checked="true"]', li);
      return est && est.dataset.estado === 'danado' && !$('.inv-obs-item', li).value.trim();
    });
    if (sinExplicar.length) {
      error($('#lista-error'), 'Escribe en la observación qué pasó con: ' + sinExplicar.map(li => $('h3', li).textContent).join(', ') + '.');
      sinExplicar[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      $('.inv-obs-item', sinExplicar[0]).focus();
      return;
    }
    error($('#lista-error'), '');
    const tb = $('#resumen');
    tb.textContent = '';
    $$('.inv-item').forEach(li => {
      const tr = document.createElement('tr');
      const est = $('[aria-checked="true"]', li);
      const c = $('.inv-cantidad', li);
      const cant = c ? c.value : 'n/a';
      if (est && est.dataset.estado !== 'bien') tr.className = 'fila-dif';
      [$('h3', li).textContent, cant, est ? est.textContent : 'Bien'].forEach((v, i) => {
        const td = document.createElement('td'); td.textContent = v; if (i === 1) td.className = 'num'; tr.appendChild(td);
      });
      tb.appendChild(tr);
    });
    const o = $('#obs').value.trim();
    $('#rev-obs').textContent = o ? 'Observaciones: ' + o : '';
    error($('#rev-error'), '');
    guardarBorrador(true);
    mostrar('paso-revision');
  }

  async function enviar() {
    if (ocupado) return;
    ocupado = true;
    const b = $('#btn-enviar');
    b.disabled = true; b.textContent = 'Enviando…';
    clearTimeout(autoguardado);
    const r = await api('api/inv/enviar', { method: 'POST', body: { turno, lineas: recoger(), obs: $('#obs').value } });
    ocupado = false;
    b.disabled = false; b.textContent = 'ENVIAR INVENTARIO';
    if (!r.ok) return error($('#rev-error'), r.error);
    enviado = r;
    cambios = false;
    if (r.preguntas && r.preguntas.length) return mostrarPreguntas(r.preguntas);
    terminarEnvio(r);
  }

  /* ---------- después de enviar: explicar diferencias ---------- */
  function mostrarPreguntas(preg) {
    const lista = $('#preguntas');
    lista.textContent = '';
    preg.forEach(p => {
      const li = document.createElement('li');
      li.className = 'inv-item inv-pregunta' + (p.dif > 0 ? ' sobra' : '');
      li.dataset.linea = p.linea;
      li.dataset.dif = p.dif;
      const h = document.createElement('h3'); h.textContent = p.nombre; li.appendChild(h);
      const t = document.createElement('p'); t.className = 'inv-porque-titulo';
      t.textContent = 'Contaste ' + p.contado + ': ' + (p.dif < 0 ? 'faltan ' + (-p.dif) : 'sobran ' + p.dif) + '. ¿Por qué?';
      li.appendChild(t);
      const sel = document.createElement('select'); sel.className = 'inv-motivo';
      sel.setAttribute('aria-label', 'Motivo de ' + p.nombre);
      const o0 = document.createElement('option'); o0.value = ''; o0.textContent = 'Elige el motivo…'; sel.appendChild(o0);
      Object.entries(p.dif < 0 ? datos.motivos : datos.motivosSobra).forEach(([k, txt]) => {
        const o = document.createElement('option'); o.value = k; o.textContent = txt; sel.appendChild(o);
      });
      li.appendChild(sel);
      const obs = document.createElement('input'); obs.className = 'inv-obs-item'; obs.maxLength = 255;
      obs.placeholder = 'Cuéntale al administrador qué pasó (opcional)'; obs.setAttribute('aria-label', 'Detalle de ' + p.nombre);
      li.appendChild(obs);
      lista.appendChild(li);
    });
    error($('#porque-error'), '');
    mostrar('paso-porque');
  }

  async function enviarExplicacion() {
    if (ocupado) return;
    const respuestas = {};
    const items = $$('#preguntas .inv-pregunta');
    const sin = items.filter(li => !$('.inv-motivo', li).value);
    if (sin.length) {
      error($('#porque-error'), 'Elige el motivo de: ' + sin.map(li => $('h3', li).textContent).join(', ') + '.');
      return $('.inv-motivo', sin[0]).focus();
    }
    const sinTexto = items.filter(li => ['otro', 'otro_sobra'].includes($('.inv-motivo', li).value) && !$('.inv-obs-item', li).value.trim());
    if (sinTexto.length) {
      error($('#porque-error'), 'Escribe qué pasó con: ' + sinTexto.map(li => $('h3', li).textContent).join(', ') + '.');
      return $('.inv-obs-item', sinTexto[0]).focus();
    }
    items.forEach(li => { respuestas[li.dataset.linea] = { motivo: $('.inv-motivo', li).value, observacion: $('.inv-obs-item', li).value.trim() }; });
    ocupado = true;
    const b = $('#btn-explicar'); b.disabled = true;
    const r = await api('api/inv/explicar', { method: 'POST', body: { id: enviado.id, respuestas } });
    ocupado = false; b.disabled = false;
    if (!r.ok) return error($('#porque-error'), r.error);
    enviado = r;
    terminarEnvio(r);
  }

  function terminarEnvio(r) {
    $('#listo-hora').textContent = r.hora;
    $('#listo-extra').textContent = (r.diferencias ? 'Se reportaron ' + r.diferencias + ' diferencia(s) o novedad(es).' : 'Sin diferencias.') + (r.correo ? ' ' + r.correo : '');
    $('#btn-descargar').href = r.pdf + '&descargar=1';
    const puedeCompartir = !!(navigator.canShare && window.File);
    $('#btn-compartir').hidden = !puedeCompartir;
    $('#capa-listo').hidden = false;
  }

  async function compartir() {
    if (!enviado) return;
    try {
      const resp = await fetch(enviado.pdf, { credentials: 'same-origin' });
      const blob = await resp.blob();
      const file = new File([blob], enviado.archivo, { type: 'application/pdf' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: enviado.archivo, text: 'Inventario ' + $('.inv-area').textContent + ' · ' + datos.turno + ' · ' + datos.fecha });
        api('api/inv/compartido', { method: 'POST', body: { id: enviado.id, metodo: 'compartir' } });
      } else {
        location.href = enviado.pdf + '&descargar=1';
      }
    } catch (e) {
      if (e && e.name !== 'AbortError') location.href = enviado.pdf + '&descargar=1';
    }
  }

  /* ---------- eventos ---------- */
  document.addEventListener('click', e => {
    const f = e.target.closest('button.k-ficha[data-trabajador]');
    if (f) {
      trabajador = { id: parseInt(f.dataset.trabajador, 10), nombre: f.dataset.nombre };
      pin = ''; pintarPin(); error($('#pin-error'), '');
      $$('#capa-pin [data-nombre-trab]').forEach(el => { el.textContent = trabajador.nombre; });
      $('#capa-pin').hidden = false;
      return;
    }
    if (e.target.closest('[data-cerrar-pin]')) { $('#capa-pin').hidden = true; return; }
    const tb = e.target.closest('[data-turno-btn]');
    if (tb) return abrirTurno(tb.dataset.turnoBtn);
    if (e.target.closest('[data-cambiar-turno]')) { if (cambios) guardarBorrador(true); return mostrar('paso-turno'); }
    if (e.target.closest('[data-salir]')) { api('api/inv/salir', { method: 'POST' }).then(() => location.reload()); return; }
    const paso = e.target.closest('[data-paso]');
    if (paso) {
      const input = $('.inv-cantidad', paso.closest('.inv-item'));
      input.value = Math.max(0, (parseInt(input.value, 10) || 0) + parseInt(paso.dataset.paso, 10));
      return marcarCambio();
    }
    const est = e.target.closest('[data-estado]');
    if (est) {
      $$('[data-estado]', est.parentElement).forEach(b => b.setAttribute('aria-checked', String(b === est)));
      return marcarCambio();
    }
    if (e.target.closest('#btn-borrador')) return guardarBorrador(false);
    if (e.target.closest('#btn-revisar')) return revisar();
    if (e.target.closest('#btn-volver')) return mostrar('paso-lista');
    if (e.target.closest('#btn-enviar')) return enviar();
    if (e.target.closest('#btn-compartir')) return compartir();
    if (e.target.closest('#btn-explicar')) return enviarExplicacion();
  });

  document.addEventListener('change', e => {
    if (e.target.closest('#paso-lista .inv-motivo')) marcarCambio();
  });

  document.addEventListener('input', e => {
    if (e.target.closest('#paso-lista')) marcarCambio();
  });

  $('#teclado').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b || ocupado) return;
    if (b.hasAttribute('data-borrar')) pin = pin.slice(0, -1);
    else if (b.hasAttribute('data-borrar-todo')) pin = '';
    else if (b.dataset.digito !== undefined && pin.length < 6) pin += b.dataset.digito;
    error($('#pin-error'), '');
    pintarPin();
    if (pin.length === 6) enviarPin();
  });

  $('#btn-descargar').addEventListener('click', () => {
    if (enviado) api('api/inv/compartido', { method: 'POST', body: { id: enviado.id, metodo: 'descarga' } });
  });

  window.addEventListener('beforeunload', e => {
    if (cambios && !enviado) { guardarBorrador(true); e.preventDefault(); e.returnValue = ''; }
  });
})();
