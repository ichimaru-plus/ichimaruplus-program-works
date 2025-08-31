<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_ImportExport {

  public static $columns = ['id','title','description','excerpt','repo_url','default_branch','version','license','docs_url','platforms','features','requirements','changelog','screenshots','auto_fill'];

  public static function register_menu(){
    add_management_page(
      __('Program Works CSV','ichimaruplus-pw'),
      __('Program Works CSV','ichimaruplus-pw'),
      'manage_options',
      'icpw-pw-csv',
      [__CLASS__,'render_page']
    );
    add_action('admin_post_icpw_pw_export', [__CLASS__,'handle_export']);
    add_action('admin_post_icpw_pw_import', [__CLASS__,'handle_import']);
  }

  public static function render_page(){
    if (!current_user_can('manage_options')){ wp_die('forbidden'); } ?>
    <div class="wrap">
      <h1><?php echo esc_html__('Program Works CSV', 'ichimaruplus-pw'); ?></h1>
      <p>プログラム作品 (post type: <code>icpw_prog</code>) の一括登録/更新に使うCSVをエクスポート/インポートできます。</p>
      <p class="description">設定ページは「<strong>設定 → Program Works 設定</strong>」です（色やGitHub情報表示、コピー用ボタンのON/OFF）。</p>

      <h2>エクスポート</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('icpw_pw_export'); ?>
        <input type="hidden" name="action" value="icpw_pw_export">
        <p><label><input type="checkbox" name="only_ids" value="1"> 投稿IDを指定（下欄をカンマ区切り）</label></p>
        <p><input type="text" name="ids" class="regular-text" placeholder="例: 12,34,56"></p>
        <p><button class="button button-primary">CSVをダウンロード</button></p>
        <p class="description">列: <?php echo esc_html(implode(', ', self::$columns)); ?></p>
      </form>

      <hr/>
      <h2>インポート</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('icpw_pw_import'); ?>
        <input type="hidden" name="action" value="icpw_pw_import">
        <p><input type="file" name="csv" accept=".csv" required></p>
        <p><label><input type="checkbox" name="update_by_title" value="1"> タイトル一致で更新（同タイトルがあれば上書き、なければ新規）</label></p>
        <p><button class="button button-primary">インポートを実行</button></p>
        <p class="description">UTF-8 CSV / 1行目ヘッダ必須。列: <?php echo esc_html(implode(', ', self::$columns)); ?></p>
      </form>

      <hr/>
      <p class="description">REST: <code>/wp-json/icpw/v1/works</code>（GET/POST/PUT/DELETE）。ショートコードは <code>[icpw_works]</code> を固定ページ等に貼り付け。</p>
    </div>
    <?php
  }

  public static function handle_export(){
    if (!current_user_can('manage_options')){ wp_die('forbidden'); }
    check_admin_referer('icpw_pw_export');

    $ids = [];
    if (!empty($_POST['only_ids']) && !empty($_POST['ids'])){
      $ids = array_filter(array_map('absint', explode(',', sanitize_text_field($_POST['ids']))));
    }

    $args = ['post_type'=>'icpw_prog','posts_per_page'=>-1];
    if ($ids){ $args['post__in'] = $ids; $args['orderby']='post__in'; }
    $q = new WP_Query($args);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="program-works.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, self::$columns);

    foreach ($q->posts as $p){
      $row = [
        $p->ID,
        $p->post_title,
        wp_strip_all_tags($p->post_content),
        wp_strip_all_tags($p->post_excerpt),
      ];
      foreach (array_slice(self::$columns,4) as $k){
        $row[] = get_post_meta($p->ID, "_icpw_$k", true);
      }
      fputcsv($out, $row);
    }
    fclose($out);
    exit;
  }

  public static function handle_import(){
    if (!current_user_can('manage_options')){ wp_die('forbidden'); }
    check_admin_referer('icpw_pw_import');
    if (empty($_FILES['csv']['tmp_name'])){ wp_die('no file'); }

    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    if (!$fh){ wp_die('open failed'); }
    $header = fgetcsv($fh);
    $cols = self::$columns;
    if (array_map('strtolower',$header) != array_map('strtolower',$cols)){
      fclose($fh);
      wp_die('ヘッダが一致しません。期待: '.implode(', ', $cols));
    }

    $count=0; $updated=0; $created=0;
    while(($row=fgetcsv($fh))!==false){
      $data = array_combine($cols, $row);
      $count++;

      $post_id = 0;
      if (!empty($data['id'])){
        $post_id = absint($data['id']);
      } elseif (!empty($_POST['update_by_title']) && !empty($data['title'])){
        $p = get_page_by_title(sanitize_text_field($data['title']), OBJECT, 'icpw_prog');
        if ($p){ $post_id = $p->ID; }
      }

      $arr = [
        'post_type'   => 'icpw_prog',
        'post_title'  => sanitize_text_field($data['title']),
        'post_status' => 'publish',
        'post_content'=> wp_kses_post($data['description']),
        'post_excerpt'=> sanitize_textarea_field($data['excerpt']),
      ];
      if ($post_id){
        $arr['ID'] = $post_id;
        $post_id = wp_update_post($arr, true);
        if (!is_wp_error($post_id)) $updated++;
      } else {
        $post_id = wp_insert_post($arr, true);
        if (!is_wp_error($post_id)) $created++;
      }
      if (is_wp_error($post_id)) continue;

      foreach (array_slice(self::$columns,4) as $k){
        update_post_meta($post_id, "_icpw_$k", sanitize_text_field($data[$k]));
      }
    }
    fclose($fh);

    wp_safe_redirect( admin_url('tools.php?page=icpw-pw-csv&imported=1&count='.$count.'&created='.$created.'&updated='.$updated) );
    exit;
  }
}
