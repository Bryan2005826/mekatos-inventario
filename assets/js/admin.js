/* Panel administrativo: confirmaciones, impresión y cálculo en vivo de planillas. */
(function () {
  'use strict';
  document.addEventListener('submit', e => {
    const f = e.target.closest('form[data-confirmar]');
    if (f && !window.confirm(f.dataset.confirmar)) e.preventDefault();
  });
  document.addEventListener('click', e => {
    if (e.target.closest('[data-imprimir]')) window.print();
  });
  document.addEventListener('change', e => {
    const s = e.target.closest('select[data-autoenviar]');
    if (s && s.form) s.form.submit();
  });
  if (document.querySelector('[data-auto-imprimir]')) {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
  }

  // Planilla: horas × valor + bono − descuentos, mientras se escribe
  const form = document.getElementById('form-planilla');
  if (form) {
    const minutos = parseInt(form.dataset.minutos, 10) || 0;
    const pesos = v => (v < 0 ? '−' : '') + '$' + Math.round(Math.abs(v)).toLocaleString('es-CO');
    const val = n => parseFloat((form.elements[n] || {}).value) || 0;
    const fijo = document.getElementById('pago-fijo');
    const pintar = () => {
      const esFijo = fijo && fijo.checked;
      document.getElementById('campo-hora').hidden = esFijo;
      document.getElementById('campo-fijo').hidden = !esFijo;
      document.querySelectorAll('[data-etiqueta-pago]').forEach(el => { el.textContent = esFijo ? 'Pago fijo' : 'Pago por horas'; });
      const pago = esFijo ? Math.round(val('valor_fijo')) : Math.round(minutos * val('valor_hora') / 60);
      const bono = val('bono_valor');
      const desc = val('desc_comida') + val('desc_adelanto') + val('desc_otros');
      const salida = { pago: pago, bono: bono, descuentos: desc, entregar: pago + bono - desc };
      document.querySelectorAll('[data-salida]').forEach(el => { el.textContent = pesos(salida[el.dataset.salida]); });
    };
    form.addEventListener('input', pintar);
    form.addEventListener('change', pintar);
  }

  // Códigos QR (librería qrcode-generator, incluida localmente)
  function dibujarQr(canvas, texto, conEtiqueta) {
    if (!window.qrcode) return;
    const qr = window.qrcode(0, 'M');
    qr.addData(texto);
    qr.make();
    const n = qr.getModuleCount();
    const margen = 4;
    const etiqueta = conEtiqueta ? Math.round(canvas.width * 0.16) : 0;
    const lado = canvas.width;
    const celda = Math.floor(lado / (n + margen * 2));
    const offset = Math.floor((lado - celda * n) / 2);
    canvas.height = lado + etiqueta;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000';
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (qr.isDark(r, c)) ctx.fillRect(offset + c * celda, offset + r * celda, celda, celda);
      }
    }
    if (conEtiqueta) {
      ctx.fillStyle = '#123B36';
      ctx.font = 'bold ' + Math.round(etiqueta * 0.55) + 'px Arial, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(conEtiqueta, lado / 2, lado + etiqueta * 0.6);
    }
  }
  document.querySelectorAll('canvas[data-qr]').forEach(c => dibujarQr(c, c.dataset.qr));
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-qr-png]');
    if (!b) return;
    const origen = b.closest('.qr-tarjeta').querySelector('canvas[data-qr]');
    const c = document.createElement('canvas');
    c.width = 1200;
    dibujarQr(c, origen.dataset.qr, b.dataset.qrPng);
    const a = document.createElement('a');
    a.href = c.toDataURL('image/png');
    a.download = 'QR_' + b.dataset.qrPng.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^\w]+/g, '_') + '.png';
    document.body.appendChild(a);
    a.click();
    a.remove();
  });

  const base = document.querySelector('meta[name="base"]');
  if (base && 'serviceWorker' in navigator) {
    navigator.serviceWorker.register(base.content.replace(/\/$/, '') + '/sw.js').catch(() => {});
  }
})();
