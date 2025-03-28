# Sistema de Reservas VR – Plugin para WordPress

Este plugin permite gestionar reservas en un centro de realidad virtual, integrando un formulario personalizado con WooCommerce.

---

## ✅ Características

- Selección de juego, tipo de partida y número de jugadores.
- Calendario dinámico con horas disponibles en función de la ocupación real.
- Cálculo automático del precio según el día y tipo de partida.
- Integración con WooCommerce para procesar el pago.
- Panel de administración para gestionar juegos, boxes, tarifas y reservas.
- Ejemplos de juegos y boxes cargados al activar el plugin.

---

## 🚀 Instalación

1. Sube la carpeta del plugin (`vr-reservas`) a `wp-content/plugins/`.
2. Actívalo desde el panel de WordPress.
3. Crea un producto en WooCommerce llamado por ejemplo “Reserva VR”. Debe ser:
   - Tipo: producto simple
   - Virtual: ✅
   - Visible solo si quieres
   - Precio por defecto: (no importa, será sobrescrito)

4. Inserta el shortcode `[vr_reservar]` en cualquier página para mostrar el formulario.

---

## ⚙️ Configuración inicial

- Ve al menú **“Tarifas VR”** en el admin y define:
  - Precio entre semana (Mar-Jue)
  - Precio fin de semana (Vie-Dom)
- Los juegos y boxes de ejemplo se crean automáticamente:
  - Juegos: “Zombie Escape” y “Space Mission”
  - Boxes: “Box A” (8 personas) y “Box B” (4 personas)

---

## 💳 Flujo de reserva

1. El usuario elige juego, número de jugadores, tipo de partida, fecha y hora.
2. Se calcula el precio automáticamente.
3. Al hacer clic en “Reservar ahora”, se añade el producto al carrito con precio dinámico.
4. El usuario paga a través de WooCommerce.
5. Al completarse el pago, la reserva se confirma automáticamente.

---

## 🔧 Personalización

- Estilos: se incluyen estilos básicos en `/assets/css/vr-reservas.css`.
- JS: el calendario se conecta por AJAX para obtener horas disponibles.
- Puedes modificar la lógica de disponibilidad en `class-vr-reservas-frontend.php`.

---

## 🛠 Soporte y mejoras

Este plugin está pensado como base funcional. Se puede extender para:
- Múltiples tarifas por juego.
- Horarios flexibles.
- Integración con notificaciones por email.
- Editor visual para partidas y boxes en calendario.

---

Gracias por usar el Sistema de Reservas VR. ¡Explora otra realidad! 🎮
