<div class="encabezado encabezado-acciones">
  <div>
    <h1>Guía de uso</h1>
    <p class="sub">Todo lo que necesitas para manejar el sistema día a día.</p>
  </div>
  <button type="button" class="btn btn-quieto" data-imprimir>Imprimir guía</button>
</div>

<nav class="bloque guia-indice" aria-label="Contenido de la guía">
  <ol>
    <li><a href="#g-inicio">Primera puesta en marcha</a></li>
    <li><a href="#g-tablet">Preparar la tablet del local</a></li>
    <li><a href="#g-dia">El día a día</a></li>
    <li><a href="#g-trabajadores">Trabajadores y PIN</a></li>
    <li><a href="#g-planillas">Planillas y pagos</a></li>
    <li><a href="#g-inventario">Inventario por QR</a></li>
    <li><a href="#g-correo">Correo automático</a></li>
    <li><a href="#g-respaldo">Copias de seguridad</a></li>
    <li><a href="#g-problemas">Si algo sale mal</a></li>
  </ol>
</nav>

<article class="guia">
<section class="bloque" id="g-inicio">
  <h2>1. Primera puesta en marcha</h2>
  <ol>
    <li>Activa el <strong>SSL gratuito</strong> en el panel de InfinityFree y espera a que tu dominio abra con <code>https://</code>. Sin eso la cámara del kiosco no funciona.</li>
    <li>En <a href="<?= e(url('admin/configuracion')) ?>">Configuración</a> revisa el nombre del establecimiento y, si quieres, agrega la IP del internet del local (entra desde el Wi-Fi del local y pulsa "Autorizar IP").</li>
    <li>En <a href="<?= e(url('admin/trabajadores')) ?>">Trabajadores</a> revisa los nombres. Entrega a cada uno su PIN en persona.</li>
    <li>Autoriza la tablet del local (sección siguiente).</li>
    <li>En <a href="<?= e(url('admin/inventario')) ?>">Inventario por áreas</a> sube la foto de cada utensilio y la cantidad que debe haber.</li>
    <li>En <a href="<?= e(url('admin/qr')) ?>">Códigos QR</a> imprime los tres códigos y pégalos en Cocina, Fríos y Caja.</li>
    <li>Configura el <a href="<?= e(url('admin/correo')) ?>">correo</a> y envía una prueba.</li>
  </ol>
</section>

<section class="bloque" id="g-tablet">
  <h2>2. Preparar la tablet del local</h2>
  <ol>
    <li>Usa Google Chrome actualizado. Conecta la tablet al Wi-Fi del local.</li>
    <li>Desde tu celular, en <a href="<?= e(url('admin/dispositivos')) ?>">Kiosco</a>, escribe un nombre (por ejemplo "Tablet entrada") y pulsa <strong>Generar código</strong>.</li>
    <li>En la tablet abre <code><?= e(url('activar')) ?></code> y escribe el código. Vence en 15 minutos.</li>
    <li>Menú de Chrome › <strong>Agregar a la pantalla principal</strong> / <strong>Instalar aplicación</strong>. Así abre a pantalla completa.</li>
    <li>La primera vez que alguien marque, Chrome pedirá permiso para la cámara: toca <strong>Permitir</strong>.</li>
    <li>Ajustes de Android › Pantalla: pon el apagado de pantalla en el tiempo máximo, y deja la tablet conectada al cargador.</li>
    <li>Recomendado: Ajustes › Seguridad › <strong>Fijar pantalla</strong> (o "Fijación de aplicaciones"), para que nadie salga de la app sin el PIN del dispositivo.</li>
    <li>No borres los datos de Chrome en la tablet: ahí vive la autorización. Si se borran, genera otro código y actívala de nuevo.</li>
  </ol>
  <p>Si la tablet se pierde o la roban: en <a href="<?= e(url('admin/dispositivos')) ?>">Kiosco</a> pulsa <strong>Revocar</strong>. Deja de servir de inmediato.</p>
</section>

<section class="bloque" id="g-dia">
  <h2>3. El día a día</h2>
  <ul>
    <li><strong>Marcar:</strong> el trabajador toca su nombre, escribe el PIN, pulsa Registrar entrada o Registrar salida y mira a la cámara. La hora la pone el servidor, en hora militar.</li>
    <li><strong>Resumen del día:</strong> quién está trabajando, quién no ha llegado, jornadas sin salida, inventarios pendientes e intentos rechazados.</li>
    <li><strong>Si alguien olvidó marcar la salida:</strong> después de 16 horas la jornada queda "incompleta" y no se suma. Ábrela en <a href="<?= e(url('admin/asistencia')) ?>">Asistencia</a>, pon la hora real de salida y escribe el motivo.</li>
    <li><strong>Si el kiosco no tenía internet:</strong> Asistencia › Registrar jornada manual, con el motivo.</li>
    <li><strong>Fotos de evidencia:</strong> en cada jornada puedes ver la foto de la entrada y de la salida. Si alguien marcó por otro, ahí se ve.</li>
    <li>Toda corrección guarda quién la hizo, cuándo, el antes, el después y el motivo (<a href="<?= e(url('admin/auditoria')) ?>">Auditoría</a>). Las marcaciones originales nunca se borran.</li>
  </ul>
</section>

<section class="bloque" id="g-trabajadores">
  <h2>4. Trabajadores y PIN</h2>
  <ul>
    <li><strong>Agregar:</strong> escribe el nombre y se genera un PIN de 6 dígitos que se muestra una sola vez. Imprímelo o anótalo y entrégalo en persona.</li>
    <li><strong>Olvidó el PIN o quedó bloqueado:</strong> Editar › Generar PIN nuevo. El anterior deja de funcionar y se quita el bloqueo.</li>
    <li>Tras 5 PIN incorrectos el trabajador queda bloqueado 15 minutos (se configura).</li>
    <li><strong>Retiro:</strong> Editar › Desactivar. Deja de aparecer en el kiosco y en los QR, pero se conservan su historial y sus planillas.</li>
    <li><strong>Eliminar:</strong> solo para alguien sin registros (por ejemplo, creado por error). Si ya trabajó, el sistema lo desactiva en vez de borrarlo, porque sus horas son el soporte de lo que se le pagó.</li>
  </ul>
</section>

<section class="bloque" id="g-planillas">
  <h2>5. Planillas y pagos</h2>
  <ul>
    <li>Cada trabajador tiene una planilla por quincena: del 1 al 15 y del 16 al último día del mes. Cada salida suma las horas y minutos de la jornada.</li>
    <li>El día 15 y el último del mes, a la medianoche, la quincena se cierra sola (al primer uso del panel después de esa hora) y empieza la nueva. Nada se borra.</li>
    <li>Abre la planilla de cada uno, escribe el <strong>valor de la hora</strong> y el total se calcula solo. Agrega bono, comida, adelanto u otros descuentos si aplica. El valor de la hora se recuerda para la siguiente quincena.</li>
    <li><strong>Trabajador de pago fijo:</strong> en la planilla activa esa casilla y escribe cuánto se le paga en la quincena. Ese valor reemplaza el cálculo por horas (las horas se siguen mostrando). Las siguientes quincenas de ese trabajador quedan igual hasta que lo cambies.</li>
    <li><strong>Imprimir todas</strong> saca una hoja por trabajador con el formato del local, lista para que firme.</li>
    <li>Cuando pagues y firmen, pulsa <strong>Marcar como pagada</strong>: las jornadas de esa quincena quedan bloqueadas. Si hay un error, <strong>Reabrir</strong> con motivo.</li>
    <li>No se puede marcar como pagada una planilla con jornadas sin salida.</li>
  </ul>
</section>

<section class="bloque" id="g-inventario">
  <h2>6. Inventario por QR</h2>
  <ul>
    <li>El trabajador escanea el QR del área con su celular, toca su nombre, escribe el PIN, elige el turno (mediodía o noche) y cuenta. Ve la foto de cada utensilio.</li>
    <li><strong>El primer inventario fija el stock:</strong> lo hace el trabajador del turno, como siempre, y lo que cuente queda como "lo que debe haber". No le pregunta nada.</li>
    <li><strong>Conteo a ciegas:</strong> el trabajador nunca ve cuánto debe haber. Cuenta, revisa y envía; al enviar, las cantidades quedan fijas y no se pueden cambiar.</li>
    <li>Después de enviar, si <strong>falta o sobra</strong> algo, le dice cuánto y le pregunta <strong>por qué</strong> (se rompió, no aparece, está en otra área, llegó nuevo…). Si todo cuadra, no pregunta nada. Si cierra sin responder, el inventario queda marcado "Sin explicar".</li>
    <li>Cuando falta o sobra, te llega un correo con el asunto "FALTAN…" o "SOBRAN…", y aparece en el Resumen del día.</li>
    <li>En <a href="<?= e(url('admin/stock')) ?>">Control de loza</a> ves el stock, el último conteo y el motivo. Lo roto o perdido lo das de baja; lo nuevo lo sumas. Así los siguientes inventarios no repiten la misma diferencia.</li>
    <li>Si el primer conteo quedó mal: Control de loza › Reiniciar stock, y el siguiente inventario lo vuelve a fijar. También puedes contar tú mismo con "Hacer conteo como administrador".</li>
    <li>Puede guardar borrador; se guarda solo cada pocos segundos.</li>
    <li>Al enviar se genera el PDF, se guarda en el panel, llega al correo (si está configurado) y el trabajador puede compartirlo por WhatsApp.</li>
    <li>Cada área y turno se envía una sola vez por día. Si hay un error, corrígelo tú en <a href="<?= e(url('admin/inventarios')) ?>">Historial de inventarios</a>, con motivo; el PDF se actualiza.</li>
    <li>Si un QR se daña o alguien lo copió para usarlo fuera del local: Códigos QR › Cambiar este código, e imprime el nuevo.</li>
  </ul>
</section>

<section class="bloque" id="g-correo">
  <h2>7. Correo automático</h2>
  <p><strong>Con Gmail</strong> (la opción más fácil):</p>
  <ol>
    <li>En la cuenta de Google que enviará los correos activa la <strong>verificación en dos pasos</strong>.</li>
    <li>Entra a <code>myaccount.google.com/apppasswords</code>, crea una contraseña de aplicación llamada "Inventario" y copia los 16 caracteres.</li>
    <li>En <a href="<?= e(url('admin/correo')) ?>">Correo y envíos</a>: método SMTP, servidor <code>smtp.gmail.com</code>, puerto 587, STARTTLS, usuario y remitente = tu Gmail, contraseña = la de aplicación.</li>
    <li>Escribe los destinatarios, activa el envío y pulsa <strong>Enviar correo de prueba</strong>.</li>
  </ol>
  <p><strong>Si la prueba dice "No se pudo conectar"</strong>, el hosting está bloqueando SMTP: usa <strong>Brevo</strong> (gratis, 300 correos al día). Crea la cuenta en brevo.com, verifica tu correo remitente, genera una clave API y pégala en el método Brevo.</p>
  <p>Un correo solo aparece como "Enviado" cuando el servidor lo acepta. Si falla, se reintenta solo hasta 5 veces; también puedes pulsar Reintentar.</p>
  <p><strong>WhatsApp:</strong> no existe una forma gratuita y permitida de enviar archivos automáticamente a un grupo. Por eso el trabajador comparte el PDF desde su celular con el botón "Compartir PDF", y el sistema conserva todos los PDF aunque se borren del grupo.</p>
</section>

<section class="bloque" id="g-respaldo">
  <h2>8. Copias de seguridad</h2>
  <ul>
    <li>Una vez por semana, en <a href="<?= e(url('admin/configuracion')) ?>">Configuración</a> pulsa <strong>Descargar copia de seguridad</strong>. Guarda el ZIP en tu computador y en Google Drive.</li>
    <li>El ZIP trae toda la base de datos (<code>respaldo.sql</code>) y los archivos: fotos de evidencia, fotos de utensilios y PDF de inventarios.</li>
    <li><strong>Restaurar:</strong> en phpMyAdmin importa <code>respaldo.sql</code> sobre la base de datos, y con el File Manager sube la carpeta <code>storage</code> a <code>htdocs/storage</code>.</li>
    <li>El archivo contiene datos personales: no lo compartas.</li>
  </ul>
</section>

<section class="bloque" id="g-problemas">
  <h2>9. Si algo sale mal</h2>
  <dl class="guia-problemas">
    <dt>"Este dispositivo no está autorizado"</dt>
    <dd>Se borraron los datos de Chrome en la tablet o la abrieron en otro navegador. Genera un código nuevo en Kiosco y actívala.</dd>
    <dt>"No fue posible validar que estés en el establecimiento… internet del local"</dt>
    <dd>Activaste la validación de red y la IP cambió (pasa cuando se reinicia el router o cambias de proveedor). Entra al panel desde el Wi-Fi del local y en Configuración autoriza la IP nueva.</dd>
    <dt>La cámara no abre</dt>
    <dd>Revisa que la página abra con https://. En Chrome toca el candado › Permisos › Cámara › Permitir. Mientras lo resuelves puedes apagar "Exigir foto de evidencia" en Configuración.</dd>
    <dt>Un trabajador dice que sí marcó</dt>
    <dd>Busca en Intentos rechazados: ahí aparece cada intento con la hora y el motivo (PIN incorrecto, dispositivo no autorizado, marcación doble…).</dd>
    <dt>La quincena no se cerró</dt>
    <dd>Se cierra la primera vez que alguien abre el panel después de la medianoche del 15 o del último día. Abre Planillas y se cerrará.</dd>
    <dt>No llegan los correos</dt>
    <dd>Mira el Registro de envíos en Correo y envíos: ahí está el error exacto. Revisa también la carpeta de spam.</dd>
  </dl>
</section>
</article>
