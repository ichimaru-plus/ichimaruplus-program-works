<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_Meta {

  public static function schema(){
    $fields = [
      'repo_url'       => ['type'=>'string','label'=>'GitHubリポジトリURL'],
      'default_branch' => ['type'=>'string','label'=>'デフォルトブランチ'],
      'version'        => ['type'=>'string','label'=>'表示用バージョン'],
      'license'        => ['type'=>'string','label'=>'ライセンス'],
      'docs_url'       => ['type'=>'string','label'=>'ドキュメントURL'],
      'platforms'      => ['type'=>'string','label'=>'対応プラットフォーム(カンマ区切り)'],
      'features'       => ['type'=>'string','label'=>'主な特徴(テキスト)'],
      'requirements'   => ['type'=>'string','label'=>'要件'],
      'changelog'      => ['type'=>'string','label'=>'変更履歴'],
      'screenshots'    => ['type'=>'string','label'=>'スクリーンショットIDs(カンマ)'],
      'auto_fill'      => ['type'=>'string','label'=>'GitHub自動補完ON(1/0)'],
    ];
    return apply_filters('icpw_pw_meta_schema', $fields);
  }

  public static function register_meta(){
    foreach (self::schema() as $key=>$def){
      register_post_meta('icpw_prog', "_icpw_$key", [
        'type'         => $def['type'],
        'single'       => true,
        'show_in_rest' => true,
        'auth_callback'=> function(){ return current_user_can('edit_posts'); },
      ]);
    }
    add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
    add_action('save_post_icpw_prog', [__CLASS__, 'save'], 10, 2);
  }

  public static function add_metabox(){
    add_meta_box('icpw_prog_meta','プログラム詳細（GitHub自動補完対応）',[__CLASS__,'render_metabox'],'icpw_prog','normal','high');
  }

  public static function render_metabox(\WP_Post $post){
    wp_nonce_field('icpw_pw_meta','icpw_pw_meta_nonce');
    $vals = [];
    foreach(self::schema() as $k=>$def){ $vals[$k] = get_post_meta($post->ID, "_icpw_$k", true); }
    ?>
    <style>.icpw-field{margin:10px 0}.icpw-field label{display:block;font-weight:600;margin-bottom:4px}.icpw-field input,.icpw-field textarea{width:100%}</style>
    <p class="description">GitHubリポジトリURLを入れて保存すると、空欄の項目をAPIから自動補完できます（最新タグ/ライセンス/デフォルトブランチ/READMEリンク）。</p>
    <?php foreach (self::schema() as $k=>$def): ?>
      <div class="icpw-field">
        <label for="icpw_<?php echo esc_attr($k); ?>"><?php echo esc_html($def['label']); ?></label>
        <?php if (in_array($k, ['features','requirements','changelog'])): ?>
          <textarea id="icpw_<?php echo esc_attr($k); ?>" name="icpw_<?php echo esc_attr($k); ?>" rows="3"><?php echo esc_textarea($vals[$k]); ?></textarea>
        <?php else: ?>
          <input type="text" id="icpw_<?php echo esc_attr($k); ?>" name="icpw_<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($vals[$k]); ?>">
        <?php endif; ?>
      </div>
    <?php endforeach;
  }

  public static function save($post_id, $post){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['icpw_pw_meta_nonce']) || !wp_verify_nonce($_POST['icpw_pw_meta_nonce'],'icpw_pw_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $meta = [];
    foreach (self::schema() as $k=>$def){
      $val = isset($_POST["icpw_$k"]) ? sanitize_text_field($_POST["icpw_$k"]) : '';
      update_post_meta($post_id, "_icpw_$k", $val);
      $meta[$k] = $val;
    }

    // GitHub autofill (only fill blanks)
    if (!empty($meta['repo_url']) && ($meta['auto_fill'] === '' || $meta['auto_fill'] === '1')){
      $gh = ICPW_PW_GitHub::fetch($meta['repo_url']);
      if ($gh){
        $meta = ICPW_PW_GitHub::fill_meta($meta, $gh);
        foreach ($meta as $k=>$v){
          if (array_key_exists($k, self::schema())){
            update_post_meta($post_id, "_icpw_$k", $v);
          }
        }
      }
    }
  }
}
