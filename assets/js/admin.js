/* global GPI, jQuery */
(function ($) {
  'use strict';

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

    $.post(GPI.ajax_url, {
      action:    'gpi_cambiar_estado',
      nonce:      GPI.nonce,
      pedido_id:  pedidoId,
      estado_id:  estadoId,
      nota:       '',
    }, function (res) {
      if (res.success) {
        $badge.text(res.data.estado_nombre)
               .css('background', res.data.estado_color);
      } else {
        alert('Error al cambiar estado: ' + (res.data || ''));
      }
    });
  });

  // ── Toggle pagado ───────────────────────────────────────────────────────
  $(document).on('click', '.gpi-btn-pagado', function () {
    var $btn = $(this);
    var id   = $btn.data('id');

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
        // Actualizar el badge de pagado en la celda
        var $row = $btn.closest('tr');
        var $cell = $row.find('td:nth-child(5)');
        var txt = $cell.text().replace(' ✅', '');
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
    var id = $(this).data('id');
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

}(jQuery));
