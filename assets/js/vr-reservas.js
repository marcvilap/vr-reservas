jQuery(document).ready(function ($) {
  // Mostrar calendario en modo inline y detectar selección
  flatpickr("#calendario-fecha", {
    inline: true,
    minDate: "today",
    dateFormat: "Y-m-d",
    locale: "es",
    onChange: function (selectedDates, dateStr, instance) {
      $("#fecha").val(dateStr);
      cargarHorasDisponibles();
    },
  });

  // Detectar cambios relevantes
  $("#jugadores, #tipo, #juego").on("change", function () {
    if ($("#fecha").val()) {
      cargarHorasDisponibles();
    }
  });

  function cargarHorasDisponibles() {
    const fecha = $("#fecha").val();
    const jugadores = $("#jugadores").val();
    const tipo = $("#tipo").val();
    const juego = $("#juego").val();

    if (fecha && jugadores && tipo && juego) {
      $.post(
        vrReservasData.ajax_url,
        {
          action: "vr_obtener_horas_disponibles",
          nonce: vrReservasData.nonce,
          fecha,
          jugadores,
          tipo,
          juego,
        },
        function (response) {
          const contenedor = $("#contenedor-horas");
          contenedor.empty();

          if (response && response.length > 0) {
            response.forEach((h) => {
              const texto = `${h.hora}<br><small>Quedan ${h.plazas} plazas </small>`;
              const btn = $("<button>")
                .addClass("vr-hora-tag")
                .attr("type", "button")
                .attr("data-value", h.hora)
                .html(texto);

              contenedor.append(btn);
            });

            $(".vr-hora-tag").on("click", function () {
              $(".vr-hora-tag").removeClass("selected");
              $(this).addClass("selected");
              $("#hora").val($(this).attr("data-value"));
            });
          } else {
            contenedor.html("<p>No hay horas disponibles</p>");
          }
        }
      );
    }
  }

  // Calcular precio estimado
  $("#jugadores, #tipo").on("change", function () {
    const jugadores = parseInt($("#jugadores").val());
    const tipo = $("#tipo").val();
    const fecha = $("#fecha").val();

    if (!jugadores || !fecha) return;

    const dia = new Date(fecha).getDay();
    const tarifaSemana = 20;
    const tarifaFinde = 26;
    const esFinde = dia === 5 || dia === 6 || dia === 0;
    const tarifa = esFinde ? tarifaFinde : tarifaSemana;

    let facturados = jugadores;
    if (tipo === "privada") {
      const capacidades = [];
      $(".vr-hora-tag").each(function () {
        const texto = $(this).text();
        const match = texto.match(/\((\d+) plazas disponibles\)/);
        if (match) capacidades.push(parseInt(match[1]));
      });

      capacidades.sort((a, b) => a - b);
      let restantes = jugadores;
      let suma = 0;

      for (let cap of capacidades) {
        suma += cap;
        if (suma >= jugadores) {
          facturados = suma;
          break;
        }
      }
    }

    const total = facturados * tarifa;
    $("#precio-estimado").text(`Precio estimado: ${total.toFixed(2)} €`);
  });
});
