<?php
if (!defined('ABSPATH')) exit;

class HAC_Activator {

  public static function activate() {
    self::create_tables();
    HAC_Router::add_rewrite_rule();
    flush_rewrite_rules();
  }

  public static function deactivate() {
    flush_rewrite_rules();
  }

  private static function create_tables() {
    global $wpdb;

    $charset = $wpdb->get_charset_collate();
    $tbl_fichas   = $wpdb->prefix . 'hac_fichas';
    $tbl_items    = $wpdb->prefix . 'hac_items';
    $tbl_tecnicos = $wpdb->prefix . 'hac_tecnicos';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sqlTec = "CREATE TABLE $tbl_tecnicos (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      nombre VARCHAR(190) NOT NULL,
      rut VARCHAR(20) NOT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY rut (rut)
    ) $charset;";

    $sql1 = "CREATE TABLE $tbl_fichas (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      tecnico_id BIGINT UNSIGNED NULL DEFAULT NULL,
      nombre VARCHAR(190) NOT NULL,
      rut VARCHAR(20) NOT NULL,
      periodo VARCHAR(190) NOT NULL,
      scanned_attachment_id BIGINT UNSIGNED NULL DEFAULT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY rut (rut),
      KEY tecnico_id (tecnico_id),
      KEY created_at (created_at),
      KEY periodo (periodo)
    ) $charset;";

    $sql2 = "CREATE TABLE $tbl_items (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      ficha_id BIGINT UNSIGNED NOT NULL,
      fecha DATE NOT NULL,
      factura VARCHAR(60) NOT NULL,
      detalle TEXT NOT NULL,
      PRIMARY KEY (id),
      KEY ficha_id (ficha_id),
      KEY factura (factura),
      KEY fecha (fecha)
    ) $charset;";

    // Create/Update
    dbDelta($sqlTec);
    dbDelta($sql1);
    dbDelta($sql2);

    add_option('hac_slug', 'herramientas-a-cargo-tecnico');
    add_option('hac_org_name', '');
  }
}
