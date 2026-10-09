/* global window */
/**
 * GPI ESC/POS — generador de tickets en ESC/POS crudo.
 *
 * Único punto de generación de bytes: lo usan tanto el resguardo real del pedido
 * (GPIEscPos.ticketPedido) como la página de prueba de Ajustes (GPIEscPos.paginaPrueba),
 * de modo que la prueba valida exactamente el mismo código que se usa en producción.
 *
 * No depende de jQuery ni de QZ Tray: devuelve el ticket en base64 listo para enviarse
 * como 'raw' a la impresora.
 */
(function (window) {
  'use strict';

  var ESC = 0x1B, GS = 0x1D, LF = 0x0A;

  // ── Tablas de caracteres ───────────────────────────────────────────────────
  // Mitad alta (0x80–0xFF) de cada página de códigos, como cadena Unicode.
  var CP850_HIGH =
    'ÇüéâäàåçêëèïîìÄÅ' +
    'ÉæÆôöòûùÿÖÜø£Ø×ƒ' +
    'áíóúñÑªº¿®¬½¼¡«»' +
    '░▒▓│┤ÁÂÀ©╣║╗╝¢¥┐' +
    '└┴┬├─┼ãÃ╚╔╩╦╠═╬¤' +
    'ðÐÊËÈıÍÎÏ┘┌█▄¦Ì▀' +
    'ÓßÔÒõÕµþÞÚÛÙýÝ¯´' +
    '­±‗¾¶§÷¸°¨·¹³²■ ';

  // CP858 = CP850 con '€' en 0xD5 (en lugar de 'ı').
  var CP858_HIGH = CP850_HIGH.substring(0, 0x55) + '€' + CP850_HIGH.substring(0x56);

  // Windows-1252: 0x80–0x9F específicos; 0xA0–0xFF coinciden con Latin-1.
  var CP1252_80_9F = '€￿‚ƒ„…†‡ˆ‰Š‹Œ￿Ž￿￿‘’“”•–—˜™š›œ￿žŸ';
  var CP1252_HIGH = CP1252_80_9F;
  for (var i = 0xA0; i <= 0xFF; i++) { CP1252_HIGH += String.fromCharCode(i); }

  /**
   * Codificaciones soportadas. 'escT' es el número por defecto para el comando ESC t n
   * en impresoras compatibles Epson; se puede sobrescribir desde Ajustes porque algunos
   * clones usan otra numeración.
   */
  var ENCODINGS = {
    cp858:  { label: 'CP858 (Multilingüe + €)', escT: 19, high: CP858_HIGH },
    cp850:  { label: 'CP850 (Multilingüe)',     escT: 2,  high: CP850_HIGH },
    cp1252: { label: 'Windows-1252',            escT: 16, high: CP1252_HIGH }
  };

  var mapCache = {};
  function charMap(encoding) {
    if (mapCache[encoding]) return mapCache[encoding];
    var high = (ENCODINGS[encoding] || ENCODINGS.cp858).high, map = {};
    for (var j = 0; j < high.length; j++) {
      if (high.charAt(j) !== '￿') map[high.charAt(j)] = 0x80 + j;
    }
    return (mapCache[encoding] = map);
  }

  // Sustituciones para caracteres que no existen en la página de códigos.
  var FALLBACKS = { '“': '"', '”': '"', '‘': "'", '’': "'", '–': '-', '—': '-', '…': '...', '€': 'EUR', '\t': ' ' };

  /** Convierte texto Unicode a bytes de la página de códigos elegida. */
  function encodeText(str, encoding) {
    var map = charMap(encoding), out = [];
    str = String(str == null ? '' : str);
    for (var k = 0; k < str.length; k++) {
      var ch = str.charAt(k), code = str.charCodeAt(k);
      if (code === 0x0A || (code >= 0x20 && code < 0x7F)) { out.push(code); continue; }
      if (map[ch] !== undefined) { out.push(map[ch]); continue; }
      var fb = FALLBACKS[ch];
      if (fb === undefined && ch.normalize) {
        // Quitar diacríticos (ŝ → s) antes de rendirse con '?'.
        fb = ch.normalize('NFD').replace(/[̀-ͯ]/g, '');
        if (!/^[\x20-\x7E]+$/.test(fb)) fb = undefined;
      }
      fb = fb === undefined ? '?' : fb;
      for (var f = 0; f < fb.length; f++) out.push(fb.charCodeAt(f));
    }
    return out;
  }

  /** Bytes UTF-8 (para el contenido del QR, que no usa la página de códigos). */
  function utf8Bytes(str) {
    var bin = unescape(encodeURIComponent(String(str))), out = [];
    for (var k = 0; k < bin.length; k++) out.push(bin.charCodeAt(k));
    return out;
  }

  function repeat(ch, n) { return n > 0 ? new Array(n + 1).join(ch) : ''; }

  /** Parte un texto en líneas de como máximo 'width' caracteres (respetando palabras). */
  function wrap(text, width) {
    var lines = [];
    String(text == null ? '' : text).split(/\r?\n/).forEach(function (para) {
      var line = '';
      para.split(/\s+/).forEach(function (word) {
        if (!word) return;
        while (word.length > width) {               // palabra más larga que la línea
          if (line) { lines.push(line); line = ''; }
          lines.push(word.substring(0, width));
          word = word.substring(width);
        }
        if (!line) line = word;
        else if (line.length + 1 + word.length <= width) line += ' ' + word;
        else { lines.push(line); line = word; }
      });
      lines.push(line);
    });
    return lines;
  }

  /** Formato de importe español: 1.234,50 */
  function money(n) {
    var v = Math.round((parseFloat(n) || 0) * 100) / 100;
    var parts = v.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return parts.join(',');
  }

  // ── Constructor de comandos ────────────────────────────────────────────────

  /**
   * @param {Object} cfg  { columnas, codificacion, codepage }
   */
  function Builder(cfg) {
    this.cfg      = cfg;
    this.cols     = Math.max(16, parseInt(cfg.columnas, 10) || 42);
    this.encoding = ENCODINGS[cfg.codificacion] ? cfg.codificacion : 'cp858';
    this.widthMul = 1;
    this.bytes    = [];
  }

  Builder.prototype = {
    raw: function (arr) { Array.prototype.push.apply(this.bytes, arr); return this; },

    /** ESC @ (reset) + ESC t n (página de códigos). */
    init: function () {
      var escT = this.cfg.codepage !== '' && this.cfg.codepage != null && !isNaN(parseInt(this.cfg.codepage, 10))
        ? parseInt(this.cfg.codepage, 10)
        : ENCODINGS[this.encoding].escT;
      this.widthMul = 1;
      return this.raw([ESC, 0x40, ESC, 0x74, escT & 0xFF]);
    },

    /** Columnas útiles con el tamaño de letra actual. */
    width: function () { return Math.floor(this.cols / this.widthMul); },

    text: function (str) { return this.raw(encodeText(str, this.encoding)); },
    line: function (str) { return this.text(str == null ? '' : str).raw([LF]); },
    /** Texto largo partido en líneas según el ancho actual. */
    para: function (str) {
      var self = this;
      wrap(str, this.width()).forEach(function (l) { self.line(l); });
      return this;
    },

    align: function (a) { return this.raw([ESC, 0x61, { left: 0, center: 1, right: 2 }[a] || 0]); },
    bold:  function (on) { return this.raw([ESC, 0x45, on ? 1 : 0]); },

    /** GS ! — multiplicadores de ancho y alto (1–8). */
    size: function (w, h) {
      w = Math.min(8, Math.max(1, w || 1));
      h = Math.min(8, Math.max(1, h || 1));
      this.widthMul = w;
      return this.raw([GS, 0x21, ((w - 1) << 4) | (h - 1)]);
    },

    feed: function (n) { return this.raw([ESC, 0x64, Math.min(255, n || 1)]); },
    hr:   function (ch) { return this.line(repeat(ch || '-', this.width())); },

    /** Línea con texto a la izquierda y a la derecha, rellenando con espacios. */
    cols2: function (left, right) {
      var w = this.width();
      right = String(right);
      left  = String(left);
      if (left.length + right.length + 1 > w) {
        // No cabe: izquierda en su(s) línea(s) y derecha alineada debajo.
        this.para(left);
        return this.line(repeat(' ', w - right.length) + right);
      }
      return this.line(left + repeat(' ', w - left.length - right.length) + right);
    },

    /** QR modelo 2 (GS ( k). size = tamaño de módulo 1–16. */
    qr: function (data, size) {
      var d = utf8Bytes(data), len = d.length + 3;
      this.raw([GS, 0x28, 0x6B, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00]);           // modelo 2
      this.raw([GS, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x43, Math.min(16, Math.max(1, size || 6))]);
      this.raw([GS, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x45, 0x31]);                 // corrección M
      this.raw([GS, 0x28, 0x6B, len & 0xFF, (len >> 8) & 0xFF, 0x31, 0x50, 0x30]).raw(d);
      return this.raw([GS, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30]).raw([LF]);
    },

    /** Código de barras CODE128 (juego B) con texto legible debajo. */
    barcode: function (data) {
      var d = encodeText(String(data).replace(/[^\x20-\x7E]/g, ''), 'cp858');
      var module = this.cols >= 40 ? 2 : 1;   // en 58 mm el módulo 2 no cabe
      this.raw([GS, 0x68, 80]);               // alto en puntos
      this.raw([GS, 0x77, module]);           // ancho de módulo
      this.raw([GS, 0x48, 2]);                // HRI debajo
      this.raw([GS, 0x66, 0]);                // fuente HRI A
      this.raw([GS, 0x6B, 73, d.length + 2, 0x7B, 0x42]).raw(d);
      return this.raw([LF]);
    },

    /** Avanza papel y corta (corte parcial con avance). */
    cut: function () { return this.raw([GS, 0x56, 0x42, 0x03]); },

    /** Pulso al cajón portamonedas (pin 2). */
    drawer: function () { return this.raw([ESC, 0x70, 0x00, 0x19, 0xFA]); },

    toBase64: function () {
      var bin = '';
      for (var k = 0; k < this.bytes.length; k += 0x8000) {
        bin += String.fromCharCode.apply(null, this.bytes.slice(k, k + 0x8000));
      }
      return window.btoa(bin);
    }
  };

  // ── Bloques comunes del ticket (compartidos por prueba y producción) ───────

  function bloqueCabecera(b, cfg, subtitulo) {
    b.align('center').bold(true).size(2, 2);
    b.para(cfg.comercio || '');
    b.size(1, 1).bold(false);
    if (cfg.cabecera) b.para(cfg.cabecera);
    if (subtitulo) b.bold(true).line(subtitulo).bold(false);
    return b.align('left').hr();
  }

  /** items: [{ desc, qty, price }] — descripción y, debajo, "qty x precio .... importe". */
  function bloqueLineas(b, items) {
    var total = 0;
    items.forEach(function (it) {
      var qty = parseFloat(it.qty) || 0, price = parseFloat(it.price) || 0, imp = qty * price;
      total += imp;
      b.para(it.desc);
      b.cols2('  ' + String(qty).replace('.', ',') + ' x ' + money(price), money(imp));
    });
    return total;
  }

  /** Totales con desglose de IVA incluido si cfg.iva > 0. */
  function bloqueTotales(b, cfg, total) {
    var iva = parseFloat(cfg.iva) || 0;
    b.hr();
    if (iva > 0) {
      var base = total / (1 + iva / 100);
      b.cols2('Base imponible', money(base));
      b.cols2('IVA ' + String(iva).replace('.', ',') + '%', money(total - base));
    }
    b.bold(true).size(1, 2).cols2('TOTAL', money(total) + ' €').size(1, 1).bold(false);
  }

  function bloqueCodigos(b, cfg, qrData, barcodeData) {
    if (qrData) b.align('center').feed(1).qr(qrData, b.cols >= 40 ? 6 : 4);
    if (barcodeData) b.align('center').feed(1).barcode(barcodeData);
    return b.align('left');
  }

  function bloquePie(b, cfg, extra) {
    b.hr().align('center');
    if (extra) b.para(extra);
    if (cfg.pie) b.para(cfg.pie);
    return b.align('left');
  }

  /**
   * Monta el documento completo: init + cuerpo × copias, con corte tras cada copia
   * y apertura de cajón (una sola vez) si está activada.
   */
  function documento(cfg, cuerpo) {
    var b = new Builder(cfg);
    var copias = Math.min(10, Math.max(1, parseInt(cfg.copias, 10) || 1));
    for (var c = 0; c < copias; c++) {
      b.init();
      cuerpo(b, c);
      b.feed(3).cut();
    }
    if (cfg.cajon) b.drawer();
    return b.toBase64();
  }

  // ── Documentos ─────────────────────────────────────────────────────────────

  /**
   * Resguardo real de un pedido.
   * @param {Object} d   Datos del endpoint gpi_ticket_data.
   * @param {Object} cfg Ajustes de impresión guardados.
   */
  function ticketPedido(d, cfg) {
    return documento(cfg, function (b) {
      bloqueCabecera(b, cfg, 'RESGUARDO DE PEDIDO');

      b.align('center').bold(true).size(2, 2).para(d.numero).size(1, 1);
      b.line('[ ' + String(d.estado || '').toUpperCase() + ' ]').bold(false).align('left').hr();

      b.cols2('Fecha:', d.fecha);
      b.cols2('Cliente:', d.solicitante);
      if (d.telefono) b.cols2('Teléfono:', d.telefono);
      if (d.email)    b.cols2('Email:', d.email);
      if (d.etiqueta) b.cols2('Etiqueta:', d.etiqueta);

      if (d.descripcion) {
        b.hr().bold(true).line('Descripción').bold(false).para(d.descripcion);
      }

      if (parseFloat(d.presupuesto) > 0) {
        b.hr();
        var total = bloqueLineas(b, [{ desc: 'Presupuesto', qty: 1, price: d.presupuesto }]);
        bloqueTotales(b, cfg, total);
        b.align('right').line(d.pagado ? '** PAGADO **' : 'Pendiente de pago').align('left');
      }

      if (d.historial && d.historial.length) {
        b.hr().bold(true).line('Historial').bold(false);
        d.historial.forEach(function (h) {
          b.para(h.fecha + ' ' + h.estado + (h.nota ? ' · ' + h.nota : ''));
        });
      }

      b.hr();
      bloqueCodigos(b, cfg, cfg.qr ? d.tracking_url : '', cfg.barcode ? d.numero : '');
      bloquePie(b, cfg, cfg.qr ? 'Escanea el QR para ver el estado del pedido.' : d.tracking_url);
      b.align('center').line(d.impreso).align('left');
    });
  }

  /**
   * Página de prueba. Usa los mismos bloques que el ticket real para validar
   * ancho, codificación, estilos, códigos, corte y cajón.
   * @param {Object} cfg Valores actuales del formulario (aunque no estén guardados).
   */
  function paginaPrueba(cfg) {
    var cols = Math.max(16, parseInt(cfg.columnas, 10) || 42);
    return documento(cfg, function (b) {
      bloqueCabecera(b, cfg, 'PÁGINA DE PRUEBA');

      b.cols2('Impresora:', cfg.impresora || '(sin nombre)');
      b.cols2('Fecha:', new Date().toLocaleString('es-ES'));
      b.cols2('Columnas:', String(cols));
      b.cols2('Codificación:', (ENCODINGS[cfg.codificacion] || ENCODINGS.cp858).label);

      // Regla de columnas: la última cifra debe caer justo en el borde derecho.
      b.hr().line('Regla de columnas:');
      var dec = '', uni = '';
      for (var c = 1; c <= cols; c++) {
        dec += c % 10 === 0 ? String((c / 10) % 10) : ' ';
        uni += String(c % 10);
      }
      b.line(dec).line(uni).line(repeat('=', cols));

      b.hr().line('Acentos y eñes:').bold(true).line('áéíóú ñ Ñ € ¿?').bold(false);
      b.line('ÁÉÍÓÚ ü Ü ç Ç ¡! ºª');

      b.hr().text('Normal ').bold(true).text('Negrita').bold(false).raw([LF]);
      b.size(1, 2).line('Doble alto');
      b.size(2, 1).line('Doble ancho');
      b.size(2, 2).line('Doble');
      b.size(1, 1);

      b.hr().align('left').line('Izquierda');
      b.align('center').line('Centro');
      b.align('right').line('Derecha').align('left');

      b.hr();
      var total = bloqueLineas(b, [
        { desc: 'Café con leche', qty: 2, price: 1.5 },
        { desc: 'Tostada de jamón ibérico con tomate y aceite de oliva', qty: 1, price: 4.25 },
        { desc: 'Agua mineral 50 cl', qty: 3, price: 1 }
      ]);
      bloqueTotales(b, cfg, total);

      b.hr();
      bloqueCodigos(b, cfg, 'https://qz.io/ prueba GPI ñ', 'PED-20260101-0001');
      bloquePie(b, cfg, cfg.cajon ? 'Cajón: se abrirá al terminar.' : 'Cajón: desactivado.');
      b.align('center').line('*** FIN DE LA PRUEBA ***').align('left');
    });
  }

  window.GPIEscPos = {
    ENCODINGS: ENCODINGS,
    Builder: Builder,
    encodeText: encodeText,
    wrap: wrap,
    money: money,
    ticketPedido: ticketPedido,
    paginaPrueba: paginaPrueba
  };
}(window));
