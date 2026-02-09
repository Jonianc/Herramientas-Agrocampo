<?php
if (!defined('ABSPATH')) exit;

class HAC_Utils {

  public static function opt($key, $default='') {
    $v = get_option($key, $default);
    return is_string($v) ? $v : $default;
  }

  public static function slug(): string {
    return sanitize_title((string) self::opt('hac_slug', 'herramientas-a-cargo-tecnico'));
  }

  public static function base_url(): string {
    return home_url('/' . self::slug() . '/');
  }

  public static function sanitize_rut($rut): string {
    $rut = (string)$rut;
    $rut = strtoupper($rut);
    $rut = preg_replace('/[^0-9K\-\.]/', '', $rut);
    $rut = str_replace('.', '', $rut);
    return trim($rut);
  }

  public static function allowed_mimes(): array {
    return [
      'jpg|jpeg|jpe' => 'image/jpeg',
      'png' => 'image/png',
      'pdf' => 'application/pdf',
    ];
  }

  public static function attachment_url(int $attachment_id): string {
    if ($attachment_id <= 0) return '';
    $u = wp_get_attachment_url($attachment_id);
    return is_string($u) ? $u : '';
  }

  public static function attachment_link_html(int $attachment_id, string $label='Ver'): string {
    $u = self::attachment_url($attachment_id);
    if ($u === '') return '';
    return '<a class="link" href="' . esc_url($u) . '" target="_blank" rel="noopener">' . esc_html($label) . '</a>';
  }

  public static function h($s): string { return esc_html((string)$s); }
  public static function a($s): string { return esc_attr((string)$s); }

  public static function notice_from_code(string $code): string {
    $map = [
      'created' => ['success', 'Ficha creada.'],
      'updated' => ['success', 'Ficha actualizada.'],
      'deleted' => ['success', 'Ficha eliminada.'],
      'notfound'=> ['error',   'Registro no encontrado.'],
      'badnonce'=> ['error',   'Solicitud inválida.'],
      'invalid' => ['error',   'Datos inválidos.'],
      'upload'  => ['error',   'No se pudo subir el adjunto.'],
      't_created' => ['success', 'Técnico creado.'],
      't_updated' => ['success', 'Técnico actualizado.'],
      't_deleted' => ['success', 'Técnico eliminado.'],
      't_inuse'   => ['error',   'No se puede eliminar: técnico en uso.'],
    ];
    if (!isset($map[$code])) return '';
    [$t,$m] = $map[$code];
    $cls = $t === 'success' ? 'ok' : 'err';
    return '<div class="notice ' . $cls . '">' . esc_html($m) . '</div>';
  }

  public static function upload_scanned_file(string $file_key='scanned_file'): int {
    if (empty($_FILES[$file_key]) || empty($_FILES[$file_key]['name'])) return 0;

    if (!function_exists('wp_handle_upload')) require_once ABSPATH . 'wp-admin/includes/file.php';
    if (!function_exists('wp_insert_attachment')) require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = $_FILES[$file_key];
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return 0;

    if (isset($file['size']) && (int)$file['size'] > 10 * 1024 * 1024) return 0;

    $res = wp_handle_upload($file, [
      'test_form' => false,
      'mimes'     => self::allowed_mimes(),
    ]);
    if (!is_array($res) || isset($res['error'])) return 0;

    $filetype = wp_check_filetype($res['file']);
    $attachment = [
      'post_mime_type' => $filetype['type'],
      'post_title'     => sanitize_file_name(basename($res['file'])),
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $res['file']);
    if (!$attach_id || is_wp_error($attach_id)) return 0;

    $meta = wp_generate_attachment_metadata($attach_id, $res['file']);
    if (is_array($meta)) wp_update_attachment_metadata($attach_id, $meta);

    return (int)$attach_id;
  }
}
