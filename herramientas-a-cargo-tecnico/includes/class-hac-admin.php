<?php
if (!defined('ABSPATH')) exit;

class HAC_Admin {

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
  }

  public static function menu() {
    add_menu_page(
      'Herramientas a Cargo',
      'Herramientas a Cargo',
      'manage_options',
      'hac_settings',
      [__CLASS__, 'page_settings'],
      'dashicons-clipboard',
      58
    );
  }

  public static function register_settings() {
    register_setting('hac_settings_group', 'hac_slug');
    register_setting('hac_settings_group', 'hac_org_name');
  }

  public static function page_settings() {
    if (!current_user_can('manage_options')) return;

    $slug = esc_attr((string) get_option('hac_slug', 'herramientas-a-cargo-tecnico'));
    $org  = esc_attr((string) get_option('hac_org_name', ''));

    ?>
    <div class="wrap">
      <h1>Ajustes — Herramientas a Cargo</h1>

      <form method="post" action="options.php">
        <?php settings_fields('hac_settings_group'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label>Slug URL (standalone)</label></th>
            <td>
              <input type="text" name="hac_slug" value="<?php echo $slug; ?>" class="regular-text">
              <p class="description">Ej: herramientas-a-cargo-tecnico</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label>Organización (opcional)</label></th>
            <td>
              <input type="text" name="hac_org_name" value="<?php echo $org; ?>" class="regular-text">
            </td>
          </tr>
        </table>
        <?php submit_button('Guardar'); ?>
      </form>

      <hr>
      <p><strong>URL:</strong> <?php echo esc_html(home_url('/' . sanitize_title((string) get_option('hac_slug', 'herramientas-a-cargo-tecnico')) . '/')); ?></p>
      <p class="description">Si cambias el slug: Ajustes → Enlaces permanentes → Guardar cambios.</p>
      <p class="description">Eliminar el plugin NO borra datos (tablas) por diseño.</p>
    </div>
    <?php
  }
}
