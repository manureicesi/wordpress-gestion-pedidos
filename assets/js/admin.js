/* global GPI, jQuery */
(function ($) {
  'use strict';

  $(document).ready(function () {
    // Debug: verificar que GPI está disponible
    if (typeof GPI === 'undefined') {
      console.error('Objeto GPI no disponible');
      return;
    }
    console.log('GPI object:', GPI);

  // ── Crear pedido ────────────────────────────────────────────────────────
  $('#gpi-form-nuevo').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn  = $form.find('button[type=submit]');
    var $msg  = $('#gpi-form-msg');

    $btn.prop('disabled', true).text('Guardando…');
    $msg.html('');

    $.post(GPI.ajax_url, {
      action:       'gpi_crear_pedido',
      nonce:         GPI.nonce,
      solicitante:   $form.find('#solicitante').val(),
      descripcion:   $form.find('#descripcion').val(),
      notas:         $form.find('#notas').val(),
      presupuesto:   $form.find('#presupuesto').val(),
      pagado:        $form.find('#pagado').is(':checked') ? 1 : 0,
      telefono:      $form.find('#telefono').val(),
      email:         $form.find('#email').val(),
      etiqueta_id:   $form.find('#etiqueta_id').val() || 0,
    }, function (res) {
      if (res.success) {
        $msg.html('<div class="gpi-notice gpi-notice-success">✅ Pedido <strong>' + res.data.numero + '</strong> creado. Redirigiendo…</div>');
        setTimeout(function () { window.location = res.data.redirect; }, 1200);
      } else {
        $msg.html('<div class="gpi-notice gpi-notice-error">❌ ' + (res.data || 'Error desconocido.') + '</div>');
        $btn.prop('disabled', false).text('Crear Pedido');
      }
    }).fail(function () {
      $msg.html('<div class="gpi-notice gpi-notice-error">❌ Error de red. Inténtalo de nuevo.</div>');
      $btn.prop('disabled', false).text('Crear Pedido');
    });
  });

  // ── Autocomplete solicitante (nuevo pedido) ──────────────────────────────
  var gpiAcTimer = null;

  $('#solicitante').on('input', function () {
    var q     = $(this).val();
    var $list = $('#gpi-solicitante-ac');
    clearTimeout(gpiAcTimer);

    if (q.length < 3) {
      $list.hide().empty();
      return;
    }

    gpiAcTimer = setTimeout(function () {
      $.post(GPI.ajax_url, {
        action: 'gpi_buscar_solicitantes',
        nonce:   GPI.nonce,
        q:       q,
      }, function (res) {
        $list.empty();
        if (res.success && res.data.length) {
          res.data.forEach(function (c) {
            var parts = [c.solicitante];
            if (c.telefono) parts.push(c.telefono);
            if (c.email)    parts.push(c.email);
            var $item = $('<div class="gpi-ac-item">').text(parts.join(' — ')).data('contact', c);
            $item.on('click', function () {
              $('#solicitante').val(c.solicitante);
              $('#telefono').val(c.telefono || '');
              $('#email').val(c.email || '');
              $list.hide().empty();
            });
            $list.append($item);
          });
          $list.show();
        } else {
          $list.hide();
        }
      });
    }, 300);
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.gpi-ac-wrap').length) {
      $('#gpi-solicitante-ac').hide();
    }
  });

  // ── Editar pedido ───────────────────────────────────────────────────────
  $('#gpi-form-editar').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn  = $form.find('button[type=submit]');
    var $msg  = $('#gpi-form-msg');

    $btn.prop('disabled', true).text('Guardando…');
    $msg.html('');

    $.post(GPI.ajax_url, {
      action:       'gpi_editar_pedido',
      nonce:         GPI.nonce,
      pedido_id:     $form.find('#pedido_id').val(),
      solicitante:   $form.find('#solicitante').val(),
      descripcion:   $form.find('#descripcion').val(),
      notas:         $form.find('#notas').val(),
      presupuesto:   $form.find('#presupuesto').val(),
      pagado:        $form.find('#pagado').is(':checked') ? 1 : 0,
      telefono:      $form.find('#telefono').val(),
      email:         $form.find('#email').val(),
      etiqueta_id:   $form.find('#etiqueta_id').val() || 0,
    }, function (res) {
      if (res.success) {
        $msg.html('<div class="gpi-notice gpi-notice-success">✅ Pedido actualizado. Redirigiendo…</div>');
        setTimeout(function () { window.location = res.data.redirect; }, 1200);
      } else {
        $msg.html('<div class="gpi-notice gpi-notice-error">❌ ' + (res.data || 'Error desconocido.') + '</div>');
        $btn.prop('disabled', false).text('Guardar Cambios');
      }
    }).fail(function () {
      $msg.html('<div class="gpi-notice gpi-notice-error">❌ Error de red. Inténtalo de nuevo.</div>');
      $btn.prop('disabled', false).text('Guardar Cambios');
    });
  });

  // ── Cambiar estado inline ────────────────────────────────────────────────
  $(document).on('change', '.gpi-estado-select', function () {
    var $sel      = $(this);
    var pedidoId  = $sel.data('pedido');
    var estadoId  = $sel.val();
    var $badge    = $('.gpi-badge-' + pedidoId);

    console.log('Cambiando estado - Pedido:', pedidoId, 'Estado:', estadoId);

    $.post(GPI.ajax_url, {
      action:    'gpi_cambiar_estado',
      nonce:      GPI.nonce,
      pedido_id:  pedidoId,
      estado_id:  estadoId,
      nota:       '',
    }, function (res) {
      console.log('Respuesta cambiar estado:', res);
      if (res.success) {
        $badge.text(res.data.estado_nombre)
               .css('background', res.data.estado_color);
      } else {
        alert('Error al cambiar estado: ' + (res.data || ''));
      }
    }).fail(function (xhr, status, error) {
      console.error('Error en AJAX cambiar estado:', error);
      alert('Error de red al cambiar estado: ' + error);
    });
  });

  // ── Toggle pagado ───────────────────────────────────────────────────────
  $(document).on('click', '.gpi-btn-pagado', function () {
    var $btn   = $(this);
    var id     = $btn.data('id');
    var pagado = $btn.hasClass('pagado');
    var msg    = pagado ? '¿Marcar este pedido como NO pagado?' : '¿Marcar este pedido como pagado?';
    if (!confirm(msg)) return;

    $.post(GPI.ajax_url, {
      action:    'gpi_toggle_pagado',
      nonce:      GPI.nonce,
      pedido_id:  id,
    }, function (res) {
      if (res.success) {
        var pagado = res.data.pagado;
        $btn.toggleClass('pagado', pagado)
            .attr('title', pagado ? 'Marcar como no pagado' : 'Marcar como pagado')
            .html(pagado ? '💰' : '🪙');
        var $row  = $btn.closest('tr');
        var $cell = $row.find('td:nth-child(7)');
        var txt   = $cell.text().replace(' ✅', '');
        if (pagado) {
          $cell.html(txt + ' <span class="gpi-pagado-badge" title="Pagado">✅</span>');
        } else {
          $cell.html(txt);
        }
      } else {
        alert('Error: ' + (res.data || ''));
      }
    });
  });

  // ── Imprimir ticket ──────────────────────────────────────────────────────
  $(document).on('click', '.gpi-btn-print', function () {
    var id  = $(this).data('id');
    var url = GPI.print_url + '&pedido_id=' + id + '&autoprint=1';
    window.open(url, '_blank', 'width=500,height=750,scrollbars=yes');
  });

  // ── Eliminar pedido ──────────────────────────────────────────────────────
  $(document).on('click', '.gpi-btn-delete', function () {
    var $btn = $(this);
    var id   = $btn.data('id');
    if (!confirm('¿Eliminar este pedido? Esta acción no se puede deshacer.')) return;

    $.post(GPI.ajax_url, {
      action:    'gpi_eliminar_pedido',
      nonce:      GPI.nonce,
      pedido_id:  id,
    }, function (res) {
      if (res.success) {
        $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
      } else {
        alert('Error al eliminar: ' + (res.data || ''));
      }
    });
  });

  // ── Exportar CSV ─────────────────────────────────────────────────────────
  $('#gpi-btn-csv').on('click', function (e) {
    e.preventDefault();
    var $form    = $('.gpi-filter-form');
    var estado   = $form.find('[name=estado]').val()   || '';
    var etiqueta = $form.find('[name=etiqueta]').val() || '';
    var search   = $form.find('[name=s]').val()        || '';
    var listos   = $form.find('[name=mostrar_listos]').is(':checked');

    var url = GPI.export_url;
    if (estado)    url += '&estado='   + encodeURIComponent(estado);
    if (etiqueta)  url += '&etiqueta=' + encodeURIComponent(etiqueta);
    if (search)    url += '&s='        + encodeURIComponent(search);
    if (listos) url += '&mostrar_listos=1';

    window.location.href = url;
  });

  // ── AJUSTES: Estados ─────────────────────────────────────────────────────

  function showMsgEstados(msg, ok) {
    var cls = ok ? 'gpi-notice-success' : 'gpi-notice-error';
    $('#gpi-msg-estados').html('<div class="gpi-notice ' + cls + '">' + msg + '</div>');
    setTimeout(function () { $('#gpi-msg-estados').html(''); }, 3000);
  }

  function resetFormEstado() {
    $('#gpi-estado-id').val('0');
    $('#gpi-estado-nombre').val('');
    $('#gpi-estado-color').val('#3b82f6');
    $('#gpi-estado-activo').prop('checked', true);
    $('#gpi-estado-ocultar').prop('checked', false);
    $('#gpi-form-estado-title').text('Añadir nuevo estado');
    $('#gpi-btn-guardar-estado').text('Añadir estado');
    $('#gpi-btn-cancelar-estado').hide();
  }

  // Debug: verificar que los elementos existen
  if ($('#gpi-btn-guardar-estado').length === 0) {
    console.error('Elemento #gpi-btn-guardar-estado no encontrado');
  }
  if ($('#gpi-btn-guardar-etiqueta').length === 0) {
    console.error('Elemento #gpi-btn-guardar-etiqueta no encontrado');
  }

  // Mover estado arriba/abajo
  $(document).on('click', '.gpi-btn-mover-estado', function () {
    var $btn = $(this);
    var id   = $btn.data('id');
    var dir  = $btn.data('dir');

    $.post(GPI.ajax_url, {
      action:    'gpi_mover_estado',
      nonce:      GPI.nonce,
      id:         id,
      direction:  dir,
    }, function (res) {
      if (res.success) {
        window.location.reload();
      } else {
        showMsgEstados(res.data || 'Error al reordenar.', false);
      }
    });
  });

  // Editar estado (carga datos en el form)
  $(document).on('click', '.gpi-btn-edit-estado', function () {
    var $btn = $(this);
    $('#gpi-estado-id').val($btn.data('id'));
    $('#gpi-estado-nombre').val($btn.data('nombre'));
    $('#gpi-estado-color').val($btn.data('color'));
    $('#gpi-estado-activo').prop('checked', $btn.data('activo') == 1);
    $('#gpi-estado-ocultar').prop('checked', $btn.data('ocultar') == 1);
    $('#gpi-form-estado-title').text('Editar estado');
    $('#gpi-btn-guardar-estado').text('Guardar cambios');
    $('#gpi-btn-cancelar-estado').show();
    $('html, body').animate({ scrollTop: $('#gpi-form-estado').offset().top - 50 }, 300);
  });

  // Cancelar edición estado
  $('#gpi-btn-cancelar-estado').on('click', function () {
    resetFormEstado();
  });

  // Guardar estado (nuevo o edición)
  $(document).on('click', '#gpi-btn-guardar-estado', function () {
    var nombre  = $('#gpi-estado-nombre').val().trim();
    var color   = $('#gpi-estado-color').val();
    var activo  = $('#gpi-estado-activo').is(':checked') ? 1 : 0;
    var ocultar = $('#gpi-estado-ocultar').is(':checked') ? 1 : 0;
    var id      = parseInt($('#gpi-estado-id').val(), 10);

    if (!nombre) { showMsgEstados('El nombre es obligatorio.', false); return; }

    var $btn = $(this);
    $btn.prop('disabled', true).text('Guardando…');

    console.log('Guardando estado - ID:', id, 'Nombre:', nombre, 'Activo:', activo, 'Ocultar:', ocultar);

    $.post(GPI.ajax_url, {
      action:              'gpi_save_estado_item',
      nonce:                GPI.nonce,
      id:                   id,
      nombre:               nombre,
      color:                color,
      activo:               activo,
      ocultar_por_defecto:  ocultar,
    }, function (res) {
      console.log('Respuesta guardar estado:', res);
      if (res.success) {
        showMsgEstados('Estado guardado correctamente.', true);
        setTimeout(function () { window.location.reload(); }, 800);
      } else {
        var errorMsg = res.data || 'Error al guardar.';
        if (typeof res.data === 'object') {
          errorMsg = res.data.message || JSON.stringify(res.data);
        }
        showMsgEstados(errorMsg, false);
        $btn.prop('disabled', false).text(id > 0 ? 'Guardar cambios' : 'Añadir estado');
      }
    }).fail(function (xhr, status, error) {
      console.error('Error en AJAX guardar estado:', error, xhr.responseText);
      showMsgEstados('Error de red: ' + error, false);
      $btn.prop('disabled', false).text(id > 0 ? 'Guardar cambios' : 'Añadir estado');
    });
  });

  // Eliminar estado
  $(document).on('click', '.gpi-btn-delete-estado', function () {
    if (!confirm('¿Eliminar este estado? Solo es posible si ningún pedido lo usa.')) return;
    var id = $(this).data('id');

    $.post(GPI.ajax_url, {
      action: 'gpi_delete_estado_item',
      nonce:   GPI.nonce,
      id:      id,
    }, function (res) {
      if (res.success) {
        $('#gpi-estado-row-' + id).fadeOut(300, function () { $(this).remove(); });
        showMsgEstados('Estado eliminado.', true);
      } else {
        showMsgEstados(res.data || 'Error al eliminar.', false);
      }
    });
  });

  // ── AJUSTES: Etiquetas ───────────────────────────────────────────────────

  function showMsgEtiquetas(msg, ok) {
    var cls = ok ? 'gpi-notice-success' : 'gpi-notice-error';
    $('#gpi-msg-etiquetas').html('<div class="gpi-notice ' + cls + '">' + msg + '</div>');
    setTimeout(function () { $('#gpi-msg-etiquetas').html(''); }, 3000);
  }

  function resetFormEtiqueta() {
    $('#gpi-etiqueta-id').val('0');
    $('#gpi-etiqueta-nombre').val('');
    $('#gpi-etiqueta-color').val('#6b7280');
    $('#gpi-etiqueta-activo').prop('checked', true);
    $('#gpi-form-etiqueta-title').text('Añadir nueva etiqueta');
    $('#gpi-btn-guardar-etiqueta').text('Añadir etiqueta');
    $('#gpi-btn-cancelar-etiqueta').hide();
  }

  // Editar etiqueta
  $(document).on('click', '.gpi-btn-edit-etiqueta', function () {
    var $btn = $(this);
    $('#gpi-etiqueta-id').val($btn.data('id'));
    $('#gpi-etiqueta-nombre').val($btn.data('nombre'));
    $('#gpi-etiqueta-color').val($btn.data('color'));
    $('#gpi-etiqueta-activo').prop('checked', $btn.data('activo') == 1);
    $('#gpi-form-etiqueta-title').text('Editar etiqueta');
    $('#gpi-btn-guardar-etiqueta').text('Guardar cambios');
    $('#gpi-btn-cancelar-etiqueta').show();
    $('html, body').animate({ scrollTop: $('#gpi-form-etiqueta').offset().top - 50 }, 300);
  });

  // Cancelar edición etiqueta
  $('#gpi-btn-cancelar-etiqueta').on('click', function () {
    resetFormEtiqueta();
  });

  // Guardar etiqueta
  $(document).on('click', '#gpi-btn-guardar-etiqueta', function () {
    var nombre = $('#gpi-etiqueta-nombre').val().trim();
    var color  = $('#gpi-etiqueta-color').val();
    var activo = $('#gpi-etiqueta-activo').is(':checked') ? 1 : 0;
    var id     = parseInt($('#gpi-etiqueta-id').val(), 10);

    if (!nombre) { showMsgEtiquetas('El nombre es obligatorio.', false); return; }

    var $btn = $(this);
    $btn.prop('disabled', true).text('Guardando…');

    console.log('Guardando etiqueta - ID:', id, 'Nombre:', nombre, 'Activo:', activo);

    $.post(GPI.ajax_url, {
      action:  'gpi_save_etiqueta',
      nonce:    GPI.nonce,
      id:       id,
      nombre:   nombre,
      color:    color,
      activo:   activo,
    }, function (res) {
      console.log('Respuesta guardar etiqueta:', res);
      if (res.success) {
        showMsgEtiquetas('Etiqueta guardada correctamente.', true);
        setTimeout(function () { window.location.reload(); }, 800);
      } else {
        var errorMsg = res.data || 'Error al guardar.';
        if (typeof res.data === 'object') {
          errorMsg = res.data.message || JSON.stringify(res.data);
        }
        showMsgEtiquetas(errorMsg, false);
        $btn.prop('disabled', false).text(id > 0 ? 'Guardar cambios' : 'Añadir etiqueta');
      }
    }).fail(function (xhr, status, error) {
      console.error('Error en AJAX guardar etiqueta:', error, xhr.responseText);
      showMsgEtiquetas('Error de red: ' + error, false);
      $btn.prop('disabled', false).text(id > 0 ? 'Guardar cambios' : 'Añadir etiqueta');
    });
  });

  // Eliminar etiqueta
  $(document).on('click', '.gpi-btn-delete-etiqueta', function () {
    if (!confirm('¿Eliminar esta etiqueta? Solo es posible si ningún pedido la usa.')) return;
    var id = $(this).data('id');

    $.post(GPI.ajax_url, {
      action: 'gpi_delete_etiqueta',
      nonce:   GPI.nonce,
      id:      id,
    }, function (res) {
      if (res.success) {
        $('#gpi-etiqueta-row-' + id).fadeOut(300, function () { $(this).remove(); });
        showMsgEtiquetas('Etiqueta eliminada.', true);
      } else {
        showMsgEtiquetas(res.data || 'Error al eliminar.', false);
      }
    });
  });

  }); // End document.ready

}(jQuery));
