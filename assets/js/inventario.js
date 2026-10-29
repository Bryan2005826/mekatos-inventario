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
      input.type = 'number'; input.min = '0'; input.max = '100000';
      // en las listas de pedido se puede contar medio (0.5): media canasta, media bandeja...
      input.inputMode = datos.pedido ? 'decimal' : 'numeric'; input.step = datos.pedido ? '0.5' : '1';
      input.placeholder = '0'; input.setAttribute('aria-label', 'Cantidad de ' + it.nombre); input.className = 'inv-cantidad';
      if (v && v.cantidad !== null && v.cantidad !== undefined) input.value = v.cantidad;
      const mas = document.createElement('button'); mas.type = 'button'; mas.textContent = '+'; mas.setAttribute('aria-label', 'Sumar uno'); mas.dataset.paso = '1';
      fila.append(menos, input, mas);
      cuerpo.appendChild(fila);
      if (it.unidad) {
        const u = document.createElement('p'); u.className = 'inv-esperada'; u.textContent = 'Cuenta en ' + it.unidad + (datos.pedido ? ' (medio = 0.5)' : ''); cuerpo.appendChild(u);
      }
    }
    if (datos.pedido && it.tipo === 'cantidad') {
      const e = document.createElement('p'); e.className = 'inv-esperada inv-meta';
      const u = it.unidad ? ' ' + it.unidad : '';
      const partes = [];
      if (it.minimo !== null) partes.push('Mínimo ' + it.minimo + u);
      if (it.optimo !== null) partes.push('Debe haber ' + it.optimo + u);
      e.textContent = partes.length ? partes.join(' · ') : 'Sin cantidades definidas';
      h.after(e);
    }
    if (it.tipo === 'pregunta') {
      const g = document.createElement('div');
      g.className = 'inv-estados inv-respuestas'; g.setAttribute('role', 'radiogroup'); g.setAttribute('aria-label', it.nombre);
      Object.entries(datos.respuestas).forEach(([k, txt]) => {
        const b = document.createElement('button');
        b.type = 'button'; b.dataset.respuesta = k; b.textContent = txt;
        b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', String(!!v && v.respuesta === k));
        g.appendChild(b);
      });
      cuerpo.appendChild(g);
    }
    if (it.tipo === 'texto') {
      const tx = document.createElement('input');
      tx.className = 'inv-texto'; tx.maxLength = 255; tx.placeholder = 'Escribe la respuesta';
      tx.setAttribute('aria-label', it.nombre);
      if (v && v.respuesta) tx.value = v.respuesta;
      cuerpo.appendChild(tx);
    }
    if (it.tipo === 'pregunta' || it.tipo === 'texto' || datos.pedido) {
      li.classList.add('sin-estado');
      li.appendChild(cuerpo);
      if (it.tipo !== 'texto') {
        const obs0 = document.createElement('input');
        obs0.className = 'inv-obs-item'; obs0.maxLength = 255; obs0.placeholder = 'Observación (opcional)';
        obs0.setAttribute('aria-label', 'Observación de ' + it.nombre);
        if (v && v.observacion) obs0.value = v.observacion;
        cuerpo.appendChild(obs0);
      } else {
        const oculto = document.createElement('input'); oculto.type = 'hidden'; oculto.className = 'inv-obs-item'; cuerpo.appendChild(oculto);
      }
      return li;
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
      const r = $('[data-respuesta][aria-checked="true"]', li);
      const tx = $('.inv-texto', li);
      lineas[li.dataset.id] = {
        cantidad: c ? c.value.trim() : '',
        respuesta: r ? r.dataset.respuesta : (tx ? tx.value.trim() : ''),
        estado: est && est.dataset.estado ? est.dataset.estado : 'bien',
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
    $('#obs').placeholder = r.ayudaObs;
    $('#obs-titulo').textContent = r.ayudaObs;
    $('#btn-enviar').textContent = r.pedido ? 'ENVIAR PEDIDO' : 'ENVIAR INVENTARIO';
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
    const sinResp = $$('.inv-item').filter(li => (li.dataset.tipo === 'pregunta' && !$('[data-respuesta][aria-checked="true"]', li))
      || (li.dataset.tipo === 'texto' && !$('.inv-texto', li).value.trim()));
    if (sinResp.length) {
      error($('#lista-error'), 'Responde: ' + sinResp.map(li => $('h3', li).textContent).join(', '));
      sinResp[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    const sinExplicar = $$('.inv-item').filter(li => {
      const est = $('[data-estado][aria-checked="true"]', li);
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
      const est = $('[data-estado][aria-checked="true"]', li);
      const c = $('.inv-cantidad', li);
      const r = $('[data-respuesta][aria-checked="true"]', li);
      const tx = $('.inv-texto', li);
      const cant = c ? c.value : (r ? r.textContent : (tx ? tx.value : 'n/a'));
      if (est && est.dataset.estado !== 'bien') tr.className = 'fila-dif';
      [$('h3', li).textContent, cant, est ? est.textContent : '—'].forEach((v, i) => {
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
    b.disabled = false; b.textContent = datos.pedido ? 'ENVIAR PEDIDO' : 'ENVIAR INVENTARIO';
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
      t.textContent = 'El turno anterior contó ' + p.antes + ' y tú ' + p.contado + ': ' + (p.dif < 0 ? 'hay ' + (-p.dif) + ' menos' : 'hay ' + p.dif + ' más') + '. ¿Por qué?';
      li.appendChild(t);
      const sel = document.createElement('div'); sel.className = 'inv-motivos'; sel.dataset.valor = '';
      sel.setAttribute('role', 'radiogroup'); sel.setAttribute('aria-label', 'Motivo de ' + p.nombre);
      Object.entries(p.dif < 0 ? datos.motivos : datos.motivosSobra).forEach(([k, txt]) => {
        const b = document.createElement('button'); b.type = 'button'; b.dataset.motivo = k;
        b.textContent = k === 'mal_conteo' && p.anterior ? txt + ': ' + p.anterior : txt;
        b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', 'false');
        sel.appendChild(b);
      });
      li.appendChild(sel);
      const nota = document.createElement('p'); nota.className = 'inv-esperada inv-nota-motivo'; nota.hidden = true; li.appendChild(nota);
      sel.addEventListener('click', ev => {
        const b = ev.target.closest('[data-motivo]');
        if (!b) return;
        sel.dataset.valor = b.dataset.motivo;
        $$('[data-motivo]', sel).forEach(x => x.setAttribute('aria-checked', String(x === b)));
        const k = b.dataset.motivo;
        nota.hidden = !['mal_conteo', 'compro'].includes(k);
        nota.textContent = k === 'mal_conteo' ? 'Quedará en la observación: contó mal ' + (p.anterior || 'el turno anterior') + '. No se le cobra a nadie.'
          : 'Se registra como loza nueva. No se le cobra a nadie.';
        sel.dispatchEvent(new Event('change'));
      });
      if (p.dif < 0) {
        // ¿Quién lo partió? (solo si se rompió)
        const quien = document.createElement('div'); quien.className = 'inv-quien'; quien.hidden = true;
        const lq = document.createElement('span'); lq.className = 'inv-porque-titulo'; lq.textContent = '¿Quién lo partió?'; quien.appendChild(lq);
        const sq = document.createElement('select'); sq.className = 'inv-responsable'; sq.setAttribute('aria-label', 'Quién partió ' + p.nombre);
        const q0 = document.createElement('option'); q0.value = ''; q0.textContent = 'Elige la persona…'; sq.appendChild(q0);
        datos.personal.forEach(t => { const o = document.createElement('option'); o.value = t.id; o.textContent = t.nombre; sq.appendChild(o); });
        const qo = document.createElement('option'); qo.value = 'otro'; qo.textContent = 'Otro (cliente u otra persona)'; sq.appendChild(qo);
        const otro = document.createElement('input'); otro.className = 'inv-responsable-otro'; otro.maxLength = 120; otro.hidden = true;
        otro.placeholder = '¿Quién? Ej.: lo partió un cliente'; otro.setAttribute('aria-label', 'Quién lo partió');
        quien.append(sq, otro);
        li.appendChild(quien);
        sel.addEventListener('change', () => { quien.hidden = sel.dataset.valor !== 'roto'; });
        sq.addEventListener('change', () => { otro.hidden = sq.value !== 'otro'; if (!otro.hidden) otro.focus(); });
      }
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
    const motivo = li => $('.inv-motivos', li).dataset.valor;
    const sin = items.filter(li => !motivo(li));
    if (sin.length) {
      error($('#porque-error'), 'Elige el motivo de: ' + sin.map(li => $('h3', li).textContent).join(', ') + '.');
      sin[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      return $('[data-motivo]', sin[0]).focus();
    }
    const sinTexto = items.filter(li => ['otro', 'otro_sobra'].includes(motivo(li)) && !$('.inv-obs-item', li).value.trim());
    if (sinTexto.length) {
      error($('#porque-error'), 'Escribe qué pasó con: ' + sinTexto.map(li => $('h3', li).textContent).join(', ') + '.');
      return $('.inv-obs-item', sinTexto[0]).focus();
    }
    const sinQuien = items.filter(li => motivo(li) === 'roto' && (!$('.inv-responsable', li).value
      || ($('.inv-responsable', li).value === 'otro' && $('.inv-responsable-otro', li).value.trim().length < 3)));
    if (sinQuien.length) {
      error($('#porque-error'), 'Di quién partió: ' + sinQuien.map(li => $('h3', li).textContent).join(', ') + '.');
      return $('.inv-responsable', sinQuien[0]).focus();
    }
    items.forEach(li => {
      const rq = $('.inv-responsable', li);
      respuestas[li.dataset.linea] = {
        motivo: motivo(li), observacion: $('.inv-obs-item', li).value.trim(),
        responsable: rq ? rq.value : '', responsable_otro: rq ? $('.inv-responsable-otro', li).value.trim() : '',
      };
    });
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
    $('#listo-titulo').textContent = r.pedido ? 'Pedido enviado correctamente' : 'Inventario enviado correctamente';
    $('#listo-extra').textContent = (r.pedido
      ? (r.porPedir ? 'Hay ' + r.porPedir + ' producto(s) por pedir.' : 'No hay que pedir nada.')
      : (r.diferencias ? 'Se reportaron ' + r.diferencias + ' diferencia(s) o novedad(es).' : 'Sin diferencias.')) + (r.correo ? ' ' + r.correo : '');
    const wa = $('#btn-whatsapp');
    wa.href = r.whatsapp;
    wa.textContent = r.pedido ? 'Enviar pedido por WhatsApp' : 'Avisar por WhatsApp';
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
      const actual = parseFloat(String(input.value).replace(',', '.')) || 0;
      input.value = Math.max(0, Math.round((actual + parseInt(paso.dataset.paso, 10)) * 100) / 100);
      return marcarCambio();
    }
    const rsp = e.target.closest('[data-respuesta]');
    if (rsp) {
      $$('[data-respuesta]', rsp.parentElement).forEach(b => b.setAttribute('aria-checked', String(b === rsp)));
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
