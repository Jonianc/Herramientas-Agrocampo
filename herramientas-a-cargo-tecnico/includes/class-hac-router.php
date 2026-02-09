<?php
if (!defined('ABSPATH')) exit;

class HAC_Router {

  public static function init() {
    add_action('init', [__CLASS__, 'add_rewrite_rule']);
    add_filter('query_vars', [__CLASS__, 'add_query_vars']);
    add_action('template_redirect', [__CLASS__, 'maybe_render_frontend']);

    // Fichas
    add_action('admin_post_nopriv_hac_create', [__CLASS__, 'handle_create']);
    add_action('admin_post_hac_create', [__CLASS__, 'handle_create']);

    add_action('admin_post_nopriv_hac_update', [__CLASS__, 'handle_update']);
    add_action('admin_post_hac_update', [__CLASS__, 'handle_update']);

    add_action('admin_post_nopriv_hac_delete', [__CLASS__, 'handle_delete']);
    add_action('admin_post_hac_delete', [__CLASS__, 'handle_delete']);

    add_action('admin_post_nopriv_hac_remove_attachment', [__CLASS__, 'handle_remove_attachment']);
    add_action('admin_post_hac_remove_attachment', [__CLASS__, 'handle_remove_attachment']);

    // Técnicos
    add_action('admin_post_nopriv_hac_t_create', [__CLASS__, 'handle_t_create']);
    add_action('admin_post_hac_t_create', [__CLASS__, 'handle_t_create']);

    add_action('admin_post_nopriv_hac_t_update', [__CLASS__, 'handle_t_update']);
    add_action('admin_post_hac_t_update', [__CLASS__, 'handle_t_update']);

    add_action('admin_post_nopriv_hac_t_delete', [__CLASS__, 'handle_t_delete']);
    add_action('admin_post_hac_t_delete', [__CLASS__, 'handle_t_delete']);
  }

  public static function add_rewrite_rule() {
    $slug = HAC_Utils::slug();
    add_rewrite_rule("^{$slug}/?$", 'index.php?hac_page=1', 'top');
  }

  public static function add_query_vars($vars) {
    $vars[] = 'hac_page';
    $vars[] = 'hac_view'; // list | new | edit | print | techs | t_new | t_edit
    $vars[] = 'hac_id';
    $vars[] = 'hac_msg';
    return $vars;
  }

  public static function maybe_render_frontend() {
    if ((int) get_query_var('hac_page', 0) !== 1) return;

    $view = sanitize_key((string) get_query_var('hac_view', 'list'));
    $id   = (int) get_query_var('hac_id', 0);

    $allowed = ['list','new','edit','print','techs','t_new','t_edit'];
    if (!in_array($view, $allowed, true)) $view = 'list';

    self::render_standalone($view, $id);
    exit;
  }

  private static function action_url(): string {
    return esc_url(admin_url('admin-post.php'));
  }

  private static function page_url(string $view='list', int $id=0, string $msg=''): string {
    $args = [];
    if ($view !== 'list') $args['hac_view'] = $view;
    if ($id > 0) $args['hac_id'] = $id;
    if ($msg !== '') $args['hac_msg'] = $msg;
    return add_query_arg($args, HAC_Utils::base_url());
  }

  private static function get_ficha_full(int $id) {
    global $wpdb;
    $tbl_fichas = $wpdb->prefix . 'hac_fichas';
    $tbl_items  = $wpdb->prefix . 'hac_items';
    $tbl_tec    = $wpdb->prefix . 'hac_tecnicos';

    $ficha = $wpdb->get_row($wpdb->prepare(
      "SELECT f.*, t.nombre AS tecnico_nombre, t.rut AS tecnico_rut
       FROM $tbl_fichas f
       LEFT JOIN $tbl_tec t ON t.id = f.tecnico_id
       WHERE f.id=%d",
      $id
    ), ARRAY_A);
    if (!$ficha) return null;

    $items = $wpdb->get_results($wpdb->prepare(
      "SELECT id, fecha, factura, detalle FROM $tbl_items WHERE ficha_id=%d ORDER BY fecha ASC, id ASC",
      $id
    ), ARRAY_A);

    return ['ficha'=>$ficha, 'items'=>$items ?: []];
  }

  private static function get_tecnico(int $id) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'hac_tecnicos';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id=%d", $id), ARRAY_A);
  }

  private static function list_tecnicos(string $q='') {
    global $wpdb;
    $tbl = $wpdb->prefix . 'hac_tecnicos';
    if ($q === '') {
      return $wpdb->get_results("SELECT * FROM $tbl ORDER BY nombre ASC LIMIT 500", ARRAY_A);
    }
    $like = '%' . $wpdb->esc_like($q) . '%';
    return $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $tbl WHERE nombre LIKE %s OR rut LIKE %s ORDER BY nombre ASC LIMIT 500",
      $like, $like
    ), ARRAY_A);
  }

  private static function tecnicos_for_select() {
    $rows = self::list_tecnicos('');
    $out = [];
    foreach ($rows as $r) {
      $out[] = [
        'id' => (int)$r['id'],
        'label' => trim((string)$r['nombre']) . ' — ' . trim((string)$r['rut']),
      ];
    }
    return $out;
  }

  private static function query_list_fichas(array $filters) {
    global $wpdb;
    $tbl_fichas = $wpdb->prefix . 'hac_fichas';
    $tbl_items  = $wpdb->prefix . 'hac_items';
    $tbl_tec    = $wpdb->prefix . 'hac_tecnicos';

    $q = $filters['q'] ?? '';
    $rut = $filters['rut'] ?? '';
    $from = $filters['from'] ?? '';
    $to = $filters['to'] ?? '';
    $tec = (int)($filters['tecnico_id'] ?? 0);

    $where = "WHERE 1=1";
    $params = [];

    if ($q !== '') {
      $where .= " AND (f.nombre LIKE %s OR f.periodo LIKE %s OR EXISTS(SELECT 1 FROM $tbl_items i WHERE i.ficha_id=f.id AND i.factura LIKE %s))";
      $like = '%' . $wpdb->esc_like($q) . '%';
      $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($rut !== '') {
      $where .= " AND f.rut LIKE %s";
      $likeRut = '%' . $wpdb->esc_like($rut) . '%';
      $params[] = $likeRut;
    }
    if ($tec > 0) {
      $where .= " AND f.tecnico_id = %d";
      $params[] = $tec;
    }
    if ($from !== '') {
      $where .= " AND f.created_at >= %s";
      $params[] = $from . " 00:00:00";
    }
    if ($to !== '') {
      $where .= " AND f.created_at <= %s";
      $params[] = $to . " 23:59:59";
    }

    $sql = "SELECT f.*,
      t.nombre AS tecnico_nombre,
      t.rut AS tecnico_rut,
      (SELECT COUNT(*) FROM $tbl_items i WHERE i.ficha_id=f.id) as items_count
      FROM $tbl_fichas f
      LEFT JOIN $tbl_tec t ON t.id = f.tecnico_id
      $where
      ORDER BY f.id DESC
      LIMIT 500";

    return $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
  }

  private static function render_standalone(string $view, int $id) {
    header('Content-Type: text/html; charset=utf-8');

    $org = (string) HAC_Utils::opt('hac_org_name', '');
    $msg = sanitize_key((string) get_query_var('hac_msg', ''));
    $notice = $msg ? HAC_Utils::notice_from_code($msg) : '';

    $filters = [
      'q' => sanitize_text_field((string)($_GET['q'] ?? '')),
      'rut' => sanitize_text_field((string)($_GET['rut'] ?? '')),
      'from' => sanitize_text_field((string)($_GET['from'] ?? '')),
      'to' => sanitize_text_field((string)($_GET['to'] ?? '')),
      'tecnico_id' => (int)($_GET['tecnico_id'] ?? 0),
    ];

    $data = null;
    if (in_array($view, ['edit','print'], true)) {
      $data = self::get_ficha_full($id);
      if (!$data) {
        $view = 'list';
        $notice = HAC_Utils::notice_from_code('notfound');
      }
    }

    $tec_data = null;
    if ($view === 't_edit') {
      $tec_data = self::get_tecnico($id);
      if (!$tec_data) {
        $view = 'techs';
        $notice = HAC_Utils::notice_from_code('notfound');
      }
    }

    $base = HAC_Utils::base_url();
    $action = self::action_url();

    // Shared styles (same as v2.1.0, small additions)
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Herramientas a Cargo Técnico</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif;margin:0;background:#f3f4f6;color:#111827}
    .wrap{max-width:1180px;margin:0 auto;padding:24px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 18px rgba(0,0,0,.05)}
    .topbar{position:sticky;top:0;background:#f3f4f6;padding:12px 0;margin-bottom:10px;z-index:5}
    .toprow{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
    h1{font-size:18px;margin:0}
    .muted{color:#6b7280;font-size:12px}
    .notice{margin:12px 0;padding:10px 12px;border-radius:12px;border:1px solid #e5e7eb}
    .notice.ok{background:#ecfdf5;border-color:#a7f3d0}
    .notice.err{background:#fef2f2;border-color:#fecaca}
    .btn{border:0;border-radius:10px;padding:10px 12px;background:#111827;color:#fff;cursor:pointer;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
    .btn.secondary{background:#374151}
    .btn.danger{background:#991b1b}
    .btn.light{background:#fff;color:#111827;border:1px solid #e5e7eb}
    .grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
    label{font-size:12px;color:#374151;display:block;margin:0 0 6px}
    input[type=text],input[type=date],select{width:100%;padding:11px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    input[type=file]{width:100%}
    textarea{width:100%;min-height:52px;padding:10px;border:1px solid #d1d5db;border-radius:10px;resize:vertical}
    table{width:100%;border-collapse:collapse;margin-top:12px;border-radius:12px;overflow:hidden}
    th,td{border:1px solid #e5e7eb;padding:10px;vertical-align:top}
    th{background:#f9fafb;font-size:12px;text-align:left}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
    .numcol{width:54px;text-align:center}
    .right{text-align:right}
    .link{color:#2563eb;text-decoration:none}
    .link:hover{text-decoration:underline}
    .badge{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:#111827;color:#fff;font-size:12px}
    .subbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}
    .spacer{flex:1}
    @media (max-width: 920px){ .grid{grid-template-columns:1fr} .numcol{width:44px} .right{text-align:left} }
    @media print{
      body{background:#fff}
      .no-print{display:none !important}
      .card{border:0;box-shadow:none}
      .wrap{max-width:none;padding:0}
      th,td{border:1px solid #999}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar no-print">
      <div class="toprow">
        <div>
          <h1>HERRAMIENTAS A CARGO TÉCNICOS</h1>
          <?php if ($org !== ''): ?><div class="muted"><?php echo HAC_Utils::h($org); ?></div><?php endif; ?>
          <div class="muted">Gestor público (sin login).</div>
        </div>
        <div class="actions" style="margin-top:0">
          <a class="btn light" href="<?php echo esc_url(self::page_url('list')); ?>">📋 Fichas</a>
          <a class="btn light" href="<?php echo esc_url(self::page_url('techs')); ?>">👷 Técnicos</a>
          <a class="btn" href="<?php echo esc_url(self::page_url('new')); ?>">➕ Nueva ficha</a>
        </div>
      </div>
    </div>

    <?php if ($notice !== '') echo $notice; ?>

    <div class="card">
      <?php if ($view === 'list'): ?>
        <?php
          $rows = self::query_list_fichas($filters);
          $count = is_array($rows) ? count($rows) : 0;
          $tec_options = self::tecnicos_for_select();
        ?>

        <form class="no-print" method="get" action="<?php echo esc_url($base); ?>">
          <div class="grid">
            <div>
              <label>Buscar (nombre/periodo/factura)</label>
              <input type="text" name="q" value="<?php echo HAC_Utils::a($filters['q']); ?>">
            </div>
            <div>
              <label>RUT (responsable)</label>
              <input type="text" name="rut" value="<?php echo HAC_Utils::a($filters['rut']); ?>">
            </div>
            <div>
              <label>Técnico</label>
              <select name="tecnico_id">
                <option value="0">Todos</option>
                <?php foreach ($tec_options as $opt): ?>
                  <option value="<?php echo (int)$opt['id']; ?>" <?php selected((int)$filters['tecnico_id'], (int)$opt['id']); ?>>
                    <?php echo HAC_Utils::h($opt['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Desde</label>
              <input type="date" name="from" value="<?php echo HAC_Utils::a($filters['from']); ?>">
            </div>
            <div>
              <label>Hasta</label>
              <input type="date" name="to" value="<?php echo HAC_Utils::a($filters['to']); ?>">
            </div>
          </div>
          <div class="subbar">
            <button class="btn secondary" type="submit">🔎 Filtrar</button>
            <a class="btn light" href="<?php echo esc_url(self::page_url('list')); ?>">🧹 Limpiar</a>
            <span class="spacer"></span>
            <span class="muted">Mostrando: <strong><?php echo (int)$count; ?></strong> fichas</span>
          </div>
        </form>

        <table>
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th>Técnico</th>
              <th>Nombre</th>
              <th style="width:140px">RUT</th>
              <th>Periodo</th>
              <th style="width:70px">Ítems</th>
              <th style="width:90px">Adj</th>
              <th style="width:170px">Creada</th>
              <th style="width:250px" class="no-print">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9">Sin resultados.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <?php
                $fid = (int)$r['id'];
                $att_id = (int)($r['scanned_attachment_id'] ?? 0);
                $att_cell = $att_id > 0 ? HAC_Utils::attachment_link_html($att_id, '✅ Ver') : '—';
                $print_url = self::page_url('print', $fid);
                $edit_url  = self::page_url('edit', $fid);
                $tec_label = trim((string)($r['tecnico_nombre'] ?? '')) !== ''
                  ? trim((string)$r['tecnico_nombre']) . ' — ' . trim((string)($r['tecnico_rut'] ?? ''))
                  : '—';
              ?>
              <tr>
                <td><?php echo (int)$fid; ?></td>
                <td><?php echo HAC_Utils::h($tec_label); ?></td>
                <td><?php echo HAC_Utils::h($r['nombre']); ?></td>
                <td><?php echo HAC_Utils::h($r['rut']); ?></td>
                <td><?php echo HAC_Utils::h($r['periodo']); ?></td>
                <td><span class="badge"><?php echo (int)$r['items_count']; ?></span></td>
                <td><?php echo $att_cell; ?></td>
                <td><?php echo HAC_Utils::h($r['created_at']); ?></td>
                <td class="no-print">
                  <a class="btn light" href="<?php echo esc_url($edit_url); ?>">✏️ Editar</a>
                  <a class="btn light" target="_blank" href="<?php echo esc_url($print_url); ?>">🖨️</a>
                  <form method="post" action="<?php echo $action; ?>" style="display:inline">
                    <?php wp_nonce_field('hac_delete_' . $fid, 'hac_nonce'); ?>
                    <input type="hidden" name="action" value="hac_delete">
                    <input type="hidden" name="ficha_id" value="<?php echo (int)$fid; ?>">
                    <button class="btn danger" type="submit" onclick="return confirm('¿Eliminar esta ficha?');">🗑️</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>

      <?php elseif ($view === 'new'): ?>
        <?php self::render_form_create($action); ?>

      <?php elseif ($view === 'edit' && $data): ?>
        <?php self::render_form_edit($action, $data); ?>

      <?php elseif ($view === 'print' && $data): ?>
        <?php self::render_print($data); ?>

      <?php elseif ($view === 'techs'): ?>
        <?php self::render_tecnicos_list($action); ?>

      <?php elseif ($view === 't_new'): ?>
        <?php self::render_tecnico_form_create($action); ?>

      <?php elseif ($view === 't_edit' && $tec_data): ?>
        <?php self::render_tecnico_form_edit($action, $tec_data); ?>

      <?php endif; ?>
    </div>
  </div>
</body>
</html>
<?php
  }

  // ---------- Frontend UI: Técnicos ----------
  private static function render_tecnicos_list(string $action_url) {
    $q = sanitize_text_field((string)($_GET['tq'] ?? ''));
    $rows = self::list_tecnicos($q);
?>
  <div class="subbar no-print">
    <h2 style="margin:0">Técnicos</h2>
    <span class="spacer"></span>
    <a class="btn" href="<?php echo esc_url(self::page_url('t_new')); ?>">➕ Nuevo técnico</a>
  </div>

  <form class="no-print" method="get" action="<?php echo esc_url(HAC_Utils::base_url()); ?>">
    <input type="hidden" name="hac_view" value="techs">
    <div class="grid">
      <div>
        <label>Buscar (nombre o RUT)</label>
        <input type="text" name="tq" value="<?php echo HAC_Utils::a($q); ?>" placeholder="Ej: Juan / 12.345.678-9">
      </div>
      <div style="align-self:end">
        <button class="btn secondary" type="submit">🔎 Buscar</button>
        <a class="btn light" href="<?php echo esc_url(self::page_url('techs')); ?>">🧹 Limpiar</a>
      </div>
    </div>
  </form>

  <table>
    <thead>
      <tr>
        <th style="width:70px">ID</th>
        <th>Nombre</th>
        <th style="width:170px">RUT</th>
        <th style="width:240px" class="no-print">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="4">Sin resultados.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php $tid = (int)$r['id']; ?>
        <tr>
          <td><?php echo (int)$tid; ?></td>
          <td><?php echo HAC_Utils::h($r['nombre']); ?></td>
          <td><?php echo HAC_Utils::h($r['rut']); ?></td>
          <td class="no-print">
            <a class="btn light" href="<?php echo esc_url(self::page_url('t_edit', $tid)); ?>">✏️ Editar</a>
            <form method="post" action="<?php echo $action_url; ?>" style="display:inline">
              <?php wp_nonce_field('hac_t_delete_' . $tid, 'hac_nonce'); ?>
              <input type="hidden" name="action" value="hac_t_delete">
              <input type="hidden" name="tecnico_id" value="<?php echo (int)$tid; ?>">
              <button class="btn danger" type="submit" onclick="return confirm('¿Eliminar este técnico?');">🗑️</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <div class="muted" style="margin-top:10px">* Eliminar está bloqueado si el técnico está usado en fichas.</div>
<?php
  }

  private static function render_tecnico_form_create(string $action_url) {
?>
  <div class="subbar no-print">
    <h2 style="margin:0">Nuevo técnico</h2>
    <span class="spacer"></span>
    <a class="btn light" href="<?php echo esc_url(self::page_url('techs')); ?>">Volver</a>
  </div>

  <form method="post" action="<?php echo $action_url; ?>" class="no-print">
    <?php wp_nonce_field('hac_t_create', 'hac_nonce'); ?>
    <input type="hidden" name="action" value="hac_t_create">

    <div class="grid">
      <div>
        <label>Nombre (obligatorio)</label>
        <input type="text" name="nombre" required maxlength="190" placeholder="Nombre y apellido">
      </div>
      <div>
        <label>RUT (obligatorio)</label>
        <input type="text" name="rut" required maxlength="20" placeholder="12.345.678-9">
      </div>
    </div>

    <div class="actions">
      <button class="btn" type="submit">💾 Guardar</button>
      <a class="btn light" href="<?php echo esc_url(self::page_url('techs')); ?>">Cancelar</a>
    </div>
  </form>
<?php
  }

  private static function render_tecnico_form_edit(string $action_url, array $t) {
    $tid = (int)$t['id'];
?>
  <div class="subbar no-print">
    <h2 style="margin:0">Editar técnico #<?php echo (int)$tid; ?></h2>
    <span class="spacer"></span>
    <a class="btn light" href="<?php echo esc_url(self::page_url('techs')); ?>">Volver</a>
  </div>

  <form method="post" action="<?php echo $action_url; ?>" class="no-print">
    <?php wp_nonce_field('hac_t_update_' . $tid, 'hac_nonce'); ?>
    <input type="hidden" name="action" value="hac_t_update">
    <input type="hidden" name="tecnico_id" value="<?php echo (int)$tid; ?>">

    <div class="grid">
      <div>
        <label>Nombre (obligatorio)</label>
        <input type="text" name="nombre" required maxlength="190" value="<?php echo HAC_Utils::a($t['nombre']); ?>">
      </div>
      <div>
        <label>RUT (obligatorio)</label>
        <input type="text" name="rut" required maxlength="20" value="<?php echo HAC_Utils::a($t['rut']); ?>">
      </div>
    </div>

    <div class="actions">
      <button class="btn" type="submit">💾 Guardar cambios</button>
      <a class="btn light" href="<?php echo esc_url(self::page_url('techs')); ?>">Cancelar</a>
    </div>
  </form>
<?php
  }

  // ---------- UI: Fichas forms ----------
  private static function render_form_create(string $action_url) {
    $tec_options = self::tecnicos_for_select();
?>
  <h2 style="margin:0 0 8px 0">Nueva ficha</h2>

  <?php if (empty($tec_options)): ?>
    <div class="notice err no-print">
      Debes crear al menos 1 técnico antes de generar fichas.
      <a class="link" href="<?php echo esc_url(self::page_url('t_new')); ?>">Crear técnico</a>
    </div>
  <?php endif; ?>

  <form method="post" action="<?php echo $action_url; ?>" enctype="multipart/form-data" class="no-print" id="hacCreateForm">
    <?php wp_nonce_field('hac_create', 'hac_nonce'); ?>
    <input type="hidden" name="action" value="hac_create">

    <div class="grid">
      <div>
        <label>Técnico (obligatorio)</label>
        <select name="tecnico_id" required>
          <option value="">Seleccionar…</option>
          <?php foreach ($tec_options as $opt): ?>
            <option value="<?php echo (int)$opt['id']; ?>"><?php echo HAC_Utils::h($opt['label']); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted" style="margin-top:6px"><a class="link" href="<?php echo esc_url(self::page_url('techs')); ?>">Administrar técnicos</a></div>
      </div>
      <div>
        <label>Nombre (responsable)</label>
        <input type="text" name="nombre" required maxlength="190" placeholder="Nombre y apellido">
      </div>
      <div>
        <label>RUT (responsable)</label>
        <input type="text" name="rut" required maxlength="20" placeholder="12.345.678-9">
      </div>
      <div>
        <label>Entrega / Periodo</label>
        <input type="text" name="periodo" required maxlength="190" placeholder="Ej: Enero 2026 / Entrega 03">
      </div>
    </div>

    <div style="margin-top:12px">
      <label>Adjuntar ficha escaneada (opcional) — PDF/JPG/PNG</label>
      <input type="file" name="scanned_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
      <div class="muted" style="margin-top:6px">Máx 10MB.</div>
    </div>

    <div class="subbar" style="margin-top:12px">
      <strong>Ítems</strong>
      <span class="spacer"></span>
      <button type="button" class="btn secondary" onclick="hacAddRow()">➕ Agregar fila</button>
    </div>

    <table id="hacItems">
      <thead>
        <tr>
          <th class="numcol">N°</th>
          <th style="width:160px">Fecha</th>
          <th style="width:180px">N° Factura</th>
          <th>Detalle</th>
          <th style="width:110px">Acción</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <div class="actions">
      <button type="submit" class="btn" <?php echo empty($tec_options) ? 'disabled' : ''; ?>>💾 Guardar</button>
      <a class="btn light" href="<?php echo esc_url(self::page_url('list')); ?>">Volver</a>
    </div>

    <div class="muted" style="margin-top:10px">* No incluye firma.</div>
  </form>

  <script>
    const tbody = document.querySelector('#hacItems tbody');
    let dirty = false;

    function hacRenumber(){
      const rows = tbody.querySelectorAll('tr');
      rows.forEach((tr, idx) => {
        const c = tr.querySelector('[data-col="n"]');
        if (c) c.textContent = (idx + 1);
      });
    }

    function hacRowTemplate(){
      const tr = document.createElement('tr');
      const today = new Date().toISOString().slice(0,10);
      tr.innerHTML = `
        <td class="numcol" data-col="n">1</td>
        <td><input type="date" name="items[fecha][]" required value="${today}"></td>
        <td><input type="text" name="items[factura][]" required maxlength="60" placeholder="Ej: 12345"></td>
        <td><textarea name="items[detalle][]" required placeholder="Detalle / herramientas"></textarea></td>
        <td><button type="button" class="btn danger" onclick="this.closest('tr').remove(); hacRenumber(); dirty=true;">Quitar</button></td>
      `;
      tr.querySelectorAll('input,textarea').forEach(el => el.addEventListener('input', () => dirty=true));
      return tr;
    }

    function hacAddRow(){
      tbody.appendChild(hacRowTemplate());
      hacRenumber();
      dirty = true;
    }

    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); hacAddRow(); }
    });

    window.addEventListener('beforeunload', (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });

    hacAddRow(); hacAddRow(); hacAddRow();
    document.getElementById('hacCreateForm').addEventListener('submit', () => { dirty = false; });
  </script>
<?php
  }

  private static function render_form_edit(string $action_url, array $data) {
    $f = $data['ficha'];
    $fid = (int)$f['id'];
    $tec_options = self::tecnicos_for_select();
    $selected_tec = (int)($f['tecnico_id'] ?? 0);

    $att_id = (int)($f['scanned_attachment_id'] ?? 0);
    $att_link = $att_id > 0 ? HAC_Utils::attachment_link_html($att_id, '✅ Ver adjunto actual') : '';
?>
  <h2 style="margin:0 0 8px 0">Editar ficha #<?php echo (int)$fid; ?></h2>

  <form method="post" action="<?php echo $action_url; ?>" enctype="multipart/form-data" class="no-print" id="hacEditForm">
    <?php wp_nonce_field('hac_update_' . $fid, 'hac_nonce'); ?>
    <input type="hidden" name="action" value="hac_update">
    <input type="hidden" name="ficha_id" value="<?php echo (int)$fid; ?>">

    <div class="grid">
      <div>
        <label>Técnico (obligatorio)</label>
        <select name="tecnico_id" required>
          <option value="">Seleccionar…</option>
          <?php foreach ($tec_options as $opt): ?>
            <option value="<?php echo (int)$opt['id']; ?>" <?php selected($selected_tec, (int)$opt['id']); ?>>
              <?php echo HAC_Utils::h($opt['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="muted" style="margin-top:6px"><a class="link" href="<?php echo esc_url(self::page_url('techs')); ?>">Administrar técnicos</a></div>
      </div>
      <div>
        <label>Nombre (responsable)</label>
        <input type="text" name="nombre" required maxlength="190" value="<?php echo HAC_Utils::a($f['nombre']); ?>">
      </div>
      <div>
        <label>RUT (responsable)</label>
        <input type="text" name="rut" required maxlength="20" value="<?php echo HAC_Utils::a($f['rut']); ?>">
      </div>
      <div>
        <label>Entrega / Periodo</label>
        <input type="text" name="periodo" required maxlength="190" value="<?php echo HAC_Utils::a($f['periodo']); ?>">
      </div>
    </div>

    <div style="margin-top:12px">
      <label>Adjunto (ficha escaneada)</label>
      <div class="muted"><?php echo $att_link !== '' ? $att_link : 'Sin adjunto.'; ?></div>
      <div class="subbar">
        <div class="actions" style="margin-top:0">
          <?php if ($att_id > 0): ?>
            <form method="post" action="<?php echo $action_url; ?>" style="display:inline">
              <?php wp_nonce_field('hac_remove_attachment_' . $fid, 'hac_nonce'); ?>
              <input type="hidden" name="action" value="hac_remove_attachment">
              <input type="hidden" name="ficha_id" value="<?php echo (int)$fid; ?>">
              <button class="btn danger" type="submit" onclick="return confirm('¿Quitar adjunto?');">🗑️ Quitar adjunto</button>
            </form>
          <?php endif; ?>
        </div>
        <span class="spacer"></span>
        <div class="muted">Reemplazar:</div>
        <input type="file" name="scanned_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
      </div>
    </div>

    <div class="subbar" style="margin-top:12px">
      <strong>Ítems</strong>
      <span class="spacer"></span>
      <button type="button" class="btn secondary" onclick="hacAddRow()">➕ Agregar fila</button>
    </div>

    <table id="hacItems">
      <thead>
        <tr>
          <th class="numcol">N°</th>
          <th style="width:160px">Fecha</th>
          <th style="width:180px">N° Factura</th>
          <th>Detalle</th>
          <th style="width:110px">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php $n=1; foreach (($data['items'] ?? []) as $it): ?>
          <tr>
            <td class="numcol" data-col="n"><?php echo (int)$n++; ?></td>
            <td><input type="date" name="items[fecha][]" required value="<?php echo HAC_Utils::a($it['fecha']); ?>"></td>
            <td><input type="text" name="items[factura][]" required maxlength="60" value="<?php echo HAC_Utils::a($it['factura']); ?>"></td>
            <td><textarea name="items[detalle][]" required><?php echo esc_textarea((string)$it['detalle']); ?></textarea></td>
            <td><button type="button" class="btn danger" onclick="this.closest('tr').remove(); hacRenumber(); dirty=true;">Quitar</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="actions">
      <button type="submit" class="btn">💾 Guardar cambios</button>
      <a class="btn light" target="_blank" href="<?php echo esc_url(self::page_url('print', $fid)); ?>">🖨️ Imprimir</a>
      <a class="btn light" href="<?php echo esc_url(self::page_url('list')); ?>">Volver</a>
    </div>
  </form>

  <script>
    const tbody = document.querySelector('#hacItems tbody');
    let dirty = false;

    function hacRenumber(){
      const rows = tbody.querySelectorAll('tr');
      rows.forEach((tr, idx) => {
        const c = tr.querySelector('[data-col="n"]');
        if (c) c.textContent = (idx + 1);
      });
    }

    function hacRowTemplate(){
      const tr = document.createElement('tr');
      const today = new Date().toISOString().slice(0,10);
      tr.innerHTML = `
        <td class="numcol" data-col="n">1</td>
        <td><input type="date" name="items[fecha][]" required value="${today}"></td>
        <td><input type="text" name="items[factura][]" required maxlength="60" placeholder="Ej: 12345"></td>
        <td><textarea name="items[detalle][]" required placeholder="Detalle / herramientas"></textarea></td>
        <td><button type="button" class="btn danger" onclick="this.closest('tr').remove(); hacRenumber(); dirty=true;">Quitar</button></td>
      `;
      tr.querySelectorAll('input,textarea').forEach(el => el.addEventListener('input', () => dirty=true));
      return tr;
    }

    function hacAddRow(){ tbody.appendChild(hacRowTemplate()); hacRenumber(); dirty = true; }

    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); hacAddRow(); }
    });

    window.addEventListener('beforeunload', (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });

    document.querySelectorAll('#hacEditForm input, #hacEditForm textarea, #hacEditForm select').forEach(el => el.addEventListener('input', () => dirty=true));
    hacRenumber();
    document.getElementById('hacEditForm').addEventListener('submit', () => { dirty = false; });
  </script>
<?php
  }

  private static function render_print(array $data) {
    $f = $data['ficha'];
    $fid = (int)$f['id'];
    $att_id = (int)($f['scanned_attachment_id'] ?? 0);
    $att_link = $att_id > 0 ? HAC_Utils::attachment_link_html($att_id, 'Abrir adjunto') : '';

    $tec_label = '';
    if (trim((string)($f['tecnico_nombre'] ?? '')) !== '') {
      $tec_label = trim((string)$f['tecnico_nombre']) . ' — ' . trim((string)($f['tecnico_rut'] ?? ''));
    }
?>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
    <div>
      <h1 style="margin:0 0 6px 0">HERRAMIENTAS A CARGO TÉCNICOS</h1>
      <div class="muted">ID: <strong><?php echo (int)$fid; ?></strong> · Emitida: <?php echo HAC_Utils::h($f['created_at']); ?></div>
    </div>
    <div class="right no-print actions" style="margin-top:0">
      <a class="btn light" href="<?php echo esc_url(self::page_url('edit', $fid)); ?>">✏️ Editar</a>
      <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
    </div>
  </div>

  <hr style="border:none;border-top:1px solid #e5e7eb;margin:12px 0">

  <div class="grid" style="margin-top:12px">
    <div><div class="muted">Técnico</div><div><strong><?php echo $tec_label !== '' ? HAC_Utils::h($tec_label) : '—'; ?></strong></div></div>
    <div><div class="muted">Nombre (responsable)</div><div><strong><?php echo HAC_Utils::h($f['nombre']); ?></strong></div></div>
    <div><div class="muted">RUT (responsable)</div><div><strong><?php echo HAC_Utils::h($f['rut']); ?></strong></div></div>
    <div><div class="muted">Entrega / Periodo</div><div><strong><?php echo HAC_Utils::h($f['periodo']); ?></strong></div></div>
  </div>

  <?php if ($att_link !== ''): ?>
    <div class="notice ok no-print" style="margin-top:12px">Adjunto: <?php echo $att_link; ?></div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th class="numcol">N°</th>
        <th style="width:140px">Fecha</th>
        <th style="width:160px">N° Factura</th>
        <th>Detalle</th>
      </tr>
    </thead>
    <tbody>
      <?php $n=1; foreach (($data['items'] ?? []) as $it): ?>
        <tr>
          <td class="numcol"><?php echo (int)$n++; ?></td>
          <td><?php echo HAC_Utils::h($it['fecha']); ?></td>
          <td><?php echo HAC_Utils::h($it['factura']); ?></td>
          <td><?php echo nl2br(esc_html((string)$it['detalle'])); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php
  }

  // ---------- Handlers ----------
  private static function parse_items_from_post(): array {
    $fechas   = $_POST['items']['fecha'] ?? [];
    $facturas = $_POST['items']['factura'] ?? [];
    $detalles = $_POST['items']['detalle'] ?? [];
    if (!is_array($fechas) || !is_array($facturas) || !is_array($detalles)) return [];
    $items = [];
    $n = max(count($fechas), count($facturas), count($detalles));
    for ($i=0; $i<$n; $i++) {
      $f  = sanitize_text_field((string)($fechas[$i] ?? ''));
      $fa = sanitize_text_field((string)($facturas[$i] ?? ''));
      $d  = sanitize_textarea_field((string)($detalles[$i] ?? ''));
      if ($f === '' && $fa === '' && $d === '') continue;
      if ($f === '' || $fa === '' || $d === '') continue;
      $items[] = ['fecha'=>$f, 'factura'=>$fa, 'detalle'=>$d];
    }
    return $items;
  }

  public static function handle_create() {
    $nonce = (string)($_POST['hac_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'hac_create')) { wp_safe_redirect(self::page_url('list', 0, 'badnonce')); exit; }

    $tecnico_id = (int)($_POST['tecnico_id'] ?? 0);
    $nombre  = sanitize_text_field((string)($_POST['nombre'] ?? ''));
    $rut     = HAC_Utils::sanitize_rut((string)($_POST['rut'] ?? ''));
    $periodo = sanitize_text_field((string)($_POST['periodo'] ?? ''));

    $items = self::parse_items_from_post();
    if ($tecnico_id <= 0 || $nombre === '' || $rut === '' || $periodo === '' || empty($items)) {
      wp_safe_redirect(self::page_url('new', 0, 'invalid')); exit;
    }

    $att_id = HAC_Utils::upload_scanned_file('scanned_file');

    global $wpdb;
    $tbl_fichas = $wpdb->prefix . 'hac_fichas';
    $tbl_items  = $wpdb->prefix . 'hac_items';

    $wpdb->insert($tbl_fichas, [
      'tecnico_id' => $tecnico_id,
      'nombre' => $nombre,
      'rut' => $rut,
      'periodo' => $periodo,
      'scanned_attachment_id' => $att_id > 0 ? $att_id : null,
      'created_at' => current_time('mysql'),
    ], ['%d','%s','%s','%s', ($att_id>0 ? '%d' : '%s'), '%s']);

    $fid = (int)$wpdb->insert_id;
    if ($fid <= 0) {
      if ($att_id > 0) wp_delete_attachment($att_id, true);
      wp_safe_redirect(self::page_url('new', 0, 'invalid')); exit;
    }

    foreach ($items as $it) {
      $wpdb->insert($tbl_items, [
        'ficha_id' => $fid,
        'fecha' => $it['fecha'],
        'factura' => $it['factura'],
        'detalle' => $it['detalle'],
      ], ['%d','%s','%s','%s']);
    }

    wp_safe_redirect(self::page_url('print', $fid, 'created'));
    exit;
  }

  public static function handle_update() {
    $fid = (int)($_POST['ficha_id'] ?? 0);
    if ($fid <= 0) { wp_safe_redirect(self::page_url('list', 0, 'notfound')); exit; }

    $nonce = (string)($_POST['hac_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'hac_update_' . $fid)) { wp_safe_redirect(self::page_url('edit', $fid, 'badnonce')); exit; }

    $tecnico_id = (int)($_POST['tecnico_id'] ?? 0);
    $nombre  = sanitize_text_field((string)($_POST['nombre'] ?? ''));
    $rut     = HAC_Utils::sanitize_rut((string)($_POST['rut'] ?? ''));
    $periodo = sanitize_text_field((string)($_POST['periodo'] ?? ''));

    $items = self::parse_items_from_post();
    if ($tecnico_id <= 0 || $nombre === '' || $rut === '' || $periodo === '' || empty($items)) {
      wp_safe_redirect(self::page_url('edit', $fid, 'invalid')); exit;
    }

    global $wpdb;
    $tbl_fichas = $wpdb->prefix . 'hac_fichas';
    $tbl_items  = $wpdb->prefix . 'hac_items';

    $row = $wpdb->get_row($wpdb->prepare("SELECT scanned_attachment_id FROM $tbl_fichas WHERE id=%d", $fid), ARRAY_A);
    $old_att = (int)($row['scanned_attachment_id'] ?? 0);

    $new_att = HAC_Utils::upload_scanned_file('scanned_file');
    $final_att = $old_att;
    if ($new_att > 0) $final_att = $new_att;

    $wpdb->update($tbl_fichas, [
      'tecnico_id' => $tecnico_id,
      'nombre' => $nombre,
      'rut' => $rut,
      'periodo' => $periodo,
      'scanned_attachment_id' => $final_att > 0 ? $final_att : null,
    ], ['id' => $fid], ['%d','%s','%s','%s', ($final_att>0 ? '%d' : '%s')], ['%d']);

    if ($new_att > 0 && $old_att > 0 && $old_att !== $new_att) {
      wp_delete_attachment($old_att, true);
    }

    $wpdb->delete($tbl_items, ['ficha_id'=>$fid], ['%d']);
    foreach ($items as $it) {
      $wpdb->insert($tbl_items, [
        'ficha_id' => $fid,
        'fecha' => $it['fecha'],
        'factura' => $it['factura'],
        'detalle' => $it['detalle'],
      ], ['%d','%s','%s','%s']);
    }

    wp_safe_redirect(self::page_url('print', $fid, 'updated'));
    exit;
  }

  public static function handle_delete() {
    $fid = (int)($_POST['ficha_id'] ?? 0);
    if ($fid <= 0) { wp_safe_redirect(self::page_url('list', 0, 'notfound')); exit; }

    $nonce = (string)($_POST['hac_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'hac_delete_' . $fid)) { wp_safe_redirect(self::page_url('list', 0, 'badnonce')); exit; }

    global $wpdb;
    $tbl_fichas = $wpdb->prefix . 'hac_fichas';
    $tbl_items  = $wpdb->prefix . 'hac_items';

    $row = $wpdb->get_row($wpdb->prepare("SELECT scanned_attachment_id FROM $tbl_fichas WHERE id=%d", $fid), ARRAY_A);
    $att = (int)($row['scanned_attachment_id'] ?? 0);

    $wpdb->delete($tbl_items, ['ficha_id'=>$fid], ['%d']);
    $wpdb->delete($tbl_fichas, ['id'=>$fid], ['%d']);

    if ($att > 0) wp_delete_attachment($att, true);

    wp_safe_redirect(self::page_url('list', 0, 'deleted'));
    exit;
  }

  public static function handle_remove_attachment() {
    $fid = (int)($_POST['ficha_id'] ?? 0);
    if ($fid <= 0) { wp_safe_redirect(self::page_url('list', 0, 'notfound')); exit; }

    $nonce = (string)($_POST['hac_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'hac_remove_attachment_' . $fid)) { wp_safe_redirect(self::page_url('edit', $fid, 'badnonce')); exit; }

    global $wpdb;
    $tbl_fichas = $wpdb->prefix . 'hac_fichas';

    $row = $wpdb->get_row($wpdb->prepare("SELECT scanned_attachment_id FROM $tbl_fichas WHERE id=%d", $fid), ARRAY_A);
    $att = (int)($row['scanned_attachment_id'] ?? 0);

    $wpdb->update($tbl_fichas, ['scanned_attachment_id'=>null], ['id'=>$fid], ['%s'], ['%d']);
    if ($att > 0) wp_delete_attachment($att, true);

    wp_safe_redirect(self::page_url('edit', $fid, 'updated'));
    exit;
  }

  // ---------- Técnicos handlers ----------
  public static function handle_t_create() {
    $nonce = (string)($_POST['hac_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'hac_t_create')) { wp_safe_redirect(self::page_url('techs', 0, 'badnonce')); exit; }

    $nombre = sanitize_text_field((string)($_POST['nombre'] ?? ''));
    $rut    = HAC_Utils::sanitize_rut((string)($_POST['rut'] ?? ''));

    if ($nombre === '' || $rut === '') { wp_safe_redirect(self::page_url('t_new', 0, 'invalid')); exit; }

    global $wpdb;
    $tbl = $wpdb->prefix . 'hac_tecnicos';
    $wpdb->insert($tbl, [
      'nombre' => $nombre,
      'rut' => $rut,
      'created_at' => current_time('mysql'),
    ], ['%s','%s','%s']);

    wp_safe_redirect(self::page_url('techs', 0, 't_created'));
    exit;
  }

  public static function handle_t_update() {
    $tid = (int)($_POST['tecnico_id'] ?? 0);
    if ($tid <= 0) { wp_safe_redirect(self::page_url('techs', 0, 'notfound')); exit; }

    $nonce = (string)($_POST['hac_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'hac_t_update_' . $tid)) { wp_safe_redirect(self::page_url('t_edit', $tid, 'badnonce')); exit; }

    $nombre = sanitize_text_field((string)($_POST['nombre'] ?? ''));
    $rut    = HAC_Utils::sanitize_rut((string)($_POST['rut'] ?? ''));

    if ($nombre === '' || $rut === '') { wp_safe_redirect(self::page_url('t_edit', $tid, 'invalid')); exit; }

    global $wpdb;
    $tbl = $wpdb->prefix . 'hac_tecnicos';
    $wpdb->update($tbl, ['nombre'=>$nombre, 'rut'=>$rut], ['id'=>$tid], ['%s','%s'], ['%d']);

    wp_safe_redirect(self::page_url('techs', 0, 't_updated'));
    exit;
  }

  public static function handle_t_delete() {
    $tid = (int)($_POST['tecnico_id'] ?? 0);
    if ($tid <= 0) { wp_safe_redirect(self::page_url('techs', 0, 'notfound')); exit; }

    $nonce = (string)($_POST['hac_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'hac_t_delete_' . $tid)) { wp_safe_redirect(self::page_url('techs', 0, 'badnonce')); exit; }

    global $wpdb;
    $tbl_tec = $wpdb->prefix . 'hac_tecnicos';
    $tbl_fichas = $wpdb->prefix . 'hac_fichas';

    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl_fichas WHERE tecnico_id=%d", $tid));
    if ($count > 0) {
      wp_safe_redirect(self::page_url('techs', 0, 't_inuse'));
      exit;
    }

    $wpdb->delete($tbl_tec, ['id'=>$tid], ['%d']);
    wp_safe_redirect(self::page_url('techs', 0, 't_deleted'));
    exit;
  }
}
