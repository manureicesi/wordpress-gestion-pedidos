/* global window, qz, jQuery, GPI */
/**
 * GPI QZ — puente con QZ Tray para imprimir ESC/POS en crudo.
 *
 * - Conecta por websocket bajo demanda (solo en pantallas que imprimen) y,
 *   una vez conectado, reconecta automáticamente si la conexión se pierde.
 * - Firma opcional de peticiones (si el servidor tiene certificado configurado);
 *   sin firma QZ Tray pedirá permiso ("Allow") al conectar.
 * - Traduce los errores de QZ a mensajes claros en español.
 */
(function (window, $) {
  'use strict';

  var listeners    = [];
  var connecting   = null;   // promesa de conexión en curso
  var everOk       = false;  // solo reconectamos si alguna vez hubo conexión
  var manualClose  = false;
  var retryTimer   = null;
  var retryDelay   = 2000;
  var status       = 'desconectado';
  var lastError    = null;  // último error de conexión (para clasificarlo)

  function setStatus(s, detail) {
    status = s;
    listeners.forEach(function (cb) { try { cb(s, detail); } catch (e) {} });
  }

  function ajax(action, data) {
    return new Promise(function (resolve, reject) {
      $.post(GPI.ajax_url, $.extend({ action: action, nonce: GPI.nonce }, data || {}))
        .done(function (res) {
          if (res && res.success) resolve(res.data);
          else reject(new Error((res && res.data) || 'Respuesta no válida del servidor.'));
        })
        .fail(function (xhr) { reject(new Error('Error de red (' + xhr.status + ').')); });
    });
  }

  /** Configura certificado y firma una sola vez. */
  var securityReady = false;
  function setupSecurity() {
    if (securityReady || typeof qz === 'undefined') return;
    securityReady = true;
    var signing = GPI.qz && GPI.qz.signing;

    qz.security.setCertificatePromise(function (resolve, reject) {
      if (!signing) { resolve(); return; }           // modo anónimo: QZ pedirá permiso
      ajax('gpi_qz_certificado').then(resolve, reject);
    });

    qz.security.setSignatureAlgorithm('SHA512');
    qz.security.setSignaturePromise(function (toSign) {
      return function (resolve, reject) {
        if (!signing) { resolve(); return; }
        ajax('gpi_qz_firmar', { request: toSign }).then(resolve, reject);
      };
    });

    qz.websocket.setClosedCallbacks(function () {
      if (!everOk) return;   // cierre de un intento fallido: lo gestiona connect()
      setStatus('desconectado');
      scheduleReconnect();
    });
    qz.websocket.setErrorCallbacks(function (evt) {
      if (window.console) console.warn('QZ Tray websocket error', evt);
    });
  }

  function scheduleReconnect() {
    if (manualClose || !everOk || retryTimer) return;
    setStatus('reconectando');
    retryTimer = window.setTimeout(function () {
      retryTimer = null;
      connect().catch(function () {
        retryDelay = Math.min(retryDelay * 2, 30000);
        scheduleReconnect();
      });
    }, retryDelay);
  }

  /** Conecta (o reutiliza la conexión activa). */
  function connect() {
    if (typeof qz === 'undefined') {
      return Promise.reject(new Error('La librería qz-tray.js no se ha cargado.'));
    }
    setupSecurity();
    if (qz.websocket.isActive()) return Promise.resolve();
    if (connecting) return connecting;

    manualClose = false;
    setStatus('conectando');
    connecting = qz.websocket.connect({ retries: 1, delay: 1 }).then(function () {
      everOk = true;
      retryDelay = 2000;
      lastError = null;
      connecting = null;
      setStatus('conectado', qz.websocket.getConnectionInfo && qz.websocket.getConnectionInfo());
    }, function (err) {
      connecting = null;
      lastError = err;
      setStatus('error', friendlyError(err));
      throw markConnectionError(err);
    });
    return connecting;
  }

  function markConnectionError(err) {
    err = err instanceof Error ? err : new Error(String(err));
    if (!/blocked|denied|rejected/i.test(err.message)) err.gpiQzUnavailable = true;
    return err;
  }

  function disconnect() {
    manualClose = true;
    if (retryTimer) { window.clearTimeout(retryTimer); retryTimer = null; }
    if (typeof qz !== 'undefined' && qz.websocket.isActive()) return qz.websocket.disconnect();
    return Promise.resolve();
  }

  /** Lista de impresoras que ve QZ Tray. */
  function findPrinters() {
    return connect().then(function () { return qz.printers.find(); });
  }

  /**
   * Envía bytes ESC/POS (base64) a la impresora indicada.
   * Comprueba primero que la impresora existe para dar un error concreto.
   */
  function printRaw(printerName, base64) {
    if (!printerName) {
      return Promise.reject(new Error('No hay impresora configurada. Indícala en Pedidos → Ajustes → Impresora térmica.'));
    }
    return connect()
      .then(function () {
        return qz.printers.find(printerName).catch(function () {
          var e = new Error('Impresora «' + printerName + '» no encontrada en QZ Tray. Usa «Detectar impresoras» y comprueba el nombre exacto.');
          e.gpiPrinterNotFound = true;
          throw e;
        });
      })
      .then(function (found) {
        var cfg = qz.configs.create(found);
        return qz.print(cfg, [{ type: 'raw', format: 'command', flavor: 'base64', data: base64 }]);
      });
  }

  /** Mensaje claro para el usuario a partir de un error de QZ / red. */
  function friendlyError(err) {
    var msg = (err && (err.message || err)) ? String(err.message || err) : 'Error desconocido.';
    if (err && err.gpiPrinterNotFound) return msg;
    if (/blocked|denied|rejected|untrusted/i.test(msg)) {
      return 'Permiso denegado por QZ Tray: pulsa «Allow» en el aviso de QZ Tray (o revisa Site Manager en QZ Tray si se bloqueó este sitio).';
    }
    if (/unable to establish|connection refused|not connected|websocket|closed|qz-tray\.js/i.test(msg) || (err && err.gpiQzUnavailable)) {
      return 'QZ Tray no está abierto o no responde en este PC. Ábrelo (icono en la bandeja del sistema) y vuelve a intentarlo.';
    }
    if (/could not be found|not found/i.test(msg)) {
      return 'Impresora no encontrada en QZ Tray. Usa «Detectar impresoras» y comprueba el nombre exacto.';
    }
    if (/sign/i.test(msg)) return 'Error al firmar la petición para QZ Tray: ' + msg;
    return msg;
  }

  /** ¿El error indica que QZ Tray no está disponible (→ usar fallback)? */
  function isUnavailable(err) {
    return !!(err && (err.gpiQzUnavailable || /no está abierto|no se ha cargado/i.test(friendlyError(err))));
  }

  window.GPIQZ = {
    connect: connect,
    disconnect: disconnect,
    findPrinters: findPrinters,
    printRaw: printRaw,
    friendlyError: friendlyError,
    isUnavailable: isUnavailable,
    onStatus: function (cb) { listeners.push(cb); cb(status); },
    getStatus: function () { return status; },
    getLastError: function () { return lastError; }
  };
}(window, jQuery));
