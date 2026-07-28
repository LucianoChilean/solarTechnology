#!/bin/sh
# ==========================================================================
# SolarTechonolgy — provisión completa con WP-CLI (idempotente).
# Ejecutar:
#   docker compose run --rm --entrypoint /bin/sh wpcli -c "sh /seed/setup.sh"
# ==========================================================================
set -eu

# --- Valores por defecto (se sobreescriben con las variables del compose) ---
SITE_URL="${SITE_URL:-http://localhost:8080}"
SITE_TITLE="${SITE_TITLE:-SolarTechonolgy}"
ADMIN_USER="${ADMIN_USER:-st_admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Cambia.Este.Admin.2026}"
ADMIN_EMAIL="${ADMIN_EMAIL:-Ventas.isisolareingenieria@gmail.com}"
LOGIN_SLUG="${LOGIN_SLUG:-acceso-solar}"
WHATSAPP_NUMBER="${WHATSAPP_NUMBER:-56974089594}"
CONTACT_EMAIL="${CONTACT_EMAIL:-Ventas.isisolareingenieria@gmail.com}"
WP_LOCALE="${WP_LOCALE:-es_CL}"

WP="wp --path=/var/www/html"

log() { printf '\n\033[1;33m▶ %s\033[0m\n' "$1"; }

# --------------------------------------------------------------------------
log "Esperando a la base de datos…"
tries=0
until $WP db query "SELECT 1" >/dev/null 2>&1; do
  tries=$((tries+1))
  if [ "$tries" -gt 60 ]; then
    echo "La base de datos no respondió a tiempo." >&2
    exit 1
  fi
  sleep 3
done
echo "Base de datos lista."

# --------------------------------------------------------------------------
log "Instalando WordPress (si hace falta)…"
if $WP core is-installed >/dev/null 2>&1; then
  echo "WordPress ya estaba instalado."
else
  $WP core install \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
  echo "WordPress instalado."
fi

# Asegurar URLs correctas.
$WP option update home "$SITE_URL"
$WP option update siteurl "$SITE_URL"
$WP option update blogdescription "Energía solar para tu hogar o empresa — Chile"
$WP option update timezone_string "America/Santiago"
$WP option update date_format "d/m/Y"
$WP option update start_of_week 1

# Permalinks bonitos (necesarios para tienda y login oculto).
$WP rewrite structure '/%postname%/' --hard
$WP rewrite flush --hard

# --------------------------------------------------------------------------
log "Instalando el idioma español de Chile…"
# Sin esto, los textos del core y de WooCommerce ("Description", "Related
# products", "Add to cart"…) salen en inglés dentro de un sitio en español.
$WP language core install "$WP_LOCALE" --activate || true

# --------------------------------------------------------------------------
log "Instalando y activando WooCommerce…"
if ! $WP plugin is-installed woocommerce >/dev/null 2>&1; then
  $WP plugin install woocommerce --activate
else
  $WP plugin activate woocommerce || true
fi
$WP plugin update woocommerce || true
# Traducción de WooCommerce al idioma del sitio.
$WP language plugin install woocommerce "$WP_LOCALE" || true
$WP language plugin update --all || true

# --------------------------------------------------------------------------
log "Configurando WooCommerce (Chile / CLP / IVA 19%)…"
$WP option update woocommerce_default_country "CL:RM"
$WP option update woocommerce_currency "CLP"
$WP option update woocommerce_currency_pos "left"
$WP option update woocommerce_price_thousand_sep "."
$WP option update woocommerce_price_decimal_sep ","
$WP option update woocommerce_price_num_decimals "0"
$WP option update woocommerce_calc_taxes "yes"
$WP option update woocommerce_prices_include_tax "no"
$WP option update woocommerce_tax_based_on "base"
$WP option update woocommerce_default_customer_address "base"
# Los precios se publican netos: el sufijo lo deja explícito en la ficha, igual
# que las tarjetas del catálogo (que ya lo pintan desde el theme).
$WP option update woocommerce_price_display_suffix "+ IVA"

# Páginas de WooCommerce con slugs en español.
$WP wc --user="$ADMIN_USER" tool run install_pages >/dev/null 2>&1 || $WP wc tool run install_pages --user="$ADMIN_USER" >/dev/null 2>&1 || true

# Renombrar slugs de las páginas clave (idempotente).
set_slug() {
  # $1 = nombre opción de página woo, $2 = slug deseado
  page_id=$($WP option get "$1" 2>/dev/null || echo "")
  if [ -n "$page_id" ] && [ "$page_id" != "0" ]; then
    $WP post update "$page_id" --post_name="$2" >/dev/null 2>&1 || true
  fi
}
# La página de tienda pasa a ser el "Catálogo" (sin carrito ni pagos).
SHOP_ID=$($WP option get woocommerce_shop_page_id 2>/dev/null || echo "")
if [ -n "$SHOP_ID" ] && [ "$SHOP_ID" != "0" ]; then
  $WP post update "$SHOP_ID" --post_name="catalogo" --post_title="Catálogo" >/dev/null 2>&1 || true
fi
set_slug woocommerce_cart_page_id carrito
set_slug woocommerce_checkout_page_id finalizar-compra
set_slug woocommerce_myaccount_page_id mi-cuenta

# --------------------------------------------------------------------------
log "Configurando IVA 19% (tasa estándar)…"
# Idempotente: solo inserta la tasa "IVA" si no existe ya (chequeo en PHP).
$WP eval '
  global $wpdb;
  $t = $wpdb->prefix . "woocommerce_tax_rates";
  $exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE tax_rate_name = \"IVA\"" );
  if ( $exists ) {
    echo "La tasa de IVA ya existía.\n";
  } else {
    $wpdb->insert( $t, array(
      "tax_rate_country"  => "CL",
      "tax_rate"          => "19.0000",
      "tax_rate_name"     => "IVA",
      "tax_rate_priority" => 1,
      "tax_rate_compound" => 0,
      "tax_rate_shipping" => 1,
      "tax_rate_order"    => 0,
      "tax_rate_class"    => "",
    ) );
    echo "Tasa de IVA creada.\n";
  }
'

# --------------------------------------------------------------------------
log "Activando el theme SolarTechonolgy…"
$WP theme activate solartechonolgy

# --------------------------------------------------------------------------
log "Importando los 24 productos del catálogo…"
$WP eval-file /seed/import-products.php

# --------------------------------------------------------------------------
log "Guardando opciones del sitio (WhatsApp, contacto, login oculto)…"
$WP option update st_whatsapp_number "$WHATSAPP_NUMBER"
$WP option update st_contact_email "$CONTACT_EMAIL"
$WP option update st_login_slug "$LOGIN_SLUG"
$WP option update st_hero_badge "Oferta del Mes"
# Modo catálogo: sitio sin carrito ni pagos (para reabrir la tienda: st_catalog_mode = no).
$WP option update st_catalog_mode "yes"
$WP option update st_catalog_prices "yes"

# Portada estática (usa front-page.php del theme).
$WP option update show_on_front "posts"

# --------------------------------------------------------------------------
log "Creando el menú principal…"
if ! $WP menu list --format=csv 2>/dev/null | grep -q "Principal"; then
  $WP menu create "Principal" || true
fi
# Vincular al location 'primary'.
MENU_ID=$($WP menu list --fields=term_id,name --format=csv 2>/dev/null | awk -F, '$2=="Principal"{print $1}' | head -n1)
if [ -n "${MENU_ID:-}" ]; then
  # Evitar duplicar ítems: solo añadir si el menú está vacío.
  ITEM_COUNT=$($WP menu item list "$MENU_ID" --format=count 2>/dev/null || echo "0")
  if [ "${ITEM_COUNT:-0}" = "0" ]; then
    $WP menu item add-custom "$MENU_ID" "Catálogo" "$SITE_URL/catalogo/" || true
    $WP menu item add-custom "$MENU_ID" "Beneficios" "$SITE_URL/#beneficios" || true
    $WP menu item add-custom "$MENU_ID" "Cómo funciona" "$SITE_URL/#proceso" || true
    $WP menu item add-custom "$MENU_ID" "Contacto" "$SITE_URL/#contacto" || true
  else
    # El menú ya existía de una corrida anterior: reapuntar el ítem de tienda
    # al catálogo (antes se llamaba "Productos" y enlazaba a /tienda/).
    $WP menu item list "$MENU_ID" --fields=db_id,link --format=csv 2>/dev/null \
      | awk -F, 'NR>1 && ($2 ~ /\/tienda\/?$/ || $2 ~ /\/shop\/?$/) {print $1}' \
      | while read -r ITEM_ID; do
          [ -n "$ITEM_ID" ] || continue
          $WP menu item update "$ITEM_ID" --title="Catálogo" --link="$SITE_URL/catalogo/" || true
        done
  fi
  $WP menu location assign "$MENU_ID" primary || true
fi

# --------------------------------------------------------------------------
log "Ajustes finales de seguridad…"
# Desactivar registro público y comentarios por defecto.
$WP option update users_can_register 0
$WP option update default_comment_status closed
$WP option update default_ping_status closed
# Habilitar el gateway de Flow si hay credenciales.
$WP option patch insert woocommerce_st_flow_settings enabled yes >/dev/null 2>&1 || true

$WP rewrite flush --hard

log "✅ Provisión completa."
echo ""
echo "  Sitio:    $SITE_URL"
echo "  Catálogo: $SITE_URL/catalogo/"
echo "  Login:    $SITE_URL/$LOGIN_SLUG"
echo "  Admin:    $ADMIN_USER"
echo ""
echo "  Recuerda: wp-admin y wp-login.php devuelven 404 a propósito."
