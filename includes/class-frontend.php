<?php
if (!defined('ABSPATH')) exit;

/**
 * Frontend 出力（本文直後のカード差し込み & ショートコード）
 * - 単一ページ (icpw_prog) の本文直後にカードを自動追加
 * - ショートコード [icpw_prog id="..."] / [icpw_prog slug="..."] も利用可
 * - デザイン設定：スタイル(flat/wire/brand)・アクセント色・影/アニメ無効化
 */
class ICPW_PW_Frontend {

  /** 初期化（フィルタ/ショートコード登録） */
  public static function register_shortcode() {
    add_shortcode('icpw_prog', [__CLASS__, 'shortcode']);

    // 本文直後にカードを1回だけ追加（重複防止）
    if (!has_filter('the_content', [__CLASS__, 'append_card_after_content'])) {
      add_filter('the_content', [__CLASS__, 'append_card_after_content'], 20);
    }
  }

  /** 本文直後にカードを追加（icpw_prog 単一ページのみ） */
  public static function append_card_after_content($content) {
    if (!is_singular('icpw_prog') || !in_the_loop() || !is_main_query()) {
      return $content;
    }
    self::ensure_assets_and_inline_css();

    $card = self::render_card(get_the_ID());
    if (!$card) return $content;

    return $content . "\n\n" . $card;
  }

  /** ショートコード: [icpw_prog id="123"] or [icpw_prog slug="foo"] */
  public static function shortcode($atts = []) {
    $atts = shortcode_atts(['id'=>0, 'slug'=>''], $atts, 'icpw_prog');

    $post_id = 0;
    if ($atts['id']) {
      $post_id = (int) $atts['id'];
    } elseif ($atts['slug']) {
      $p = get_page_by_path(sanitize_title($atts['slug']), OBJECT, 'icpw_prog');
      if ($p) $post_id = (int) $p->ID;
    } else {
      $post_id = get_the_ID();
    }

    if (!$post_id || get_post_type($post_id) !== 'icpw_prog') return '';

    self::ensure_assets_and_inline_css();
    return self::render_card($post_id);
  }

  /** 必要アセットの読み込みとインラインCSS注入（設定反映） */
  private static function ensure_assets_and_inline_css() {
    // 事前登録されている想定（プラグイン本体で wp_register_* 済）
    wp_enqueue_style('ichimaruplus-program-works');
    wp_enqueue_script('ichimaruplus-program-works');

    // 設定値の取得
    $style   = get_option('icpw_style', 'flat');        // flat / wire / brand
    $accent  = get_option('icpw_accent', '#1f2937');    // #hex
    $noMove  = (bool) get_option('icpw_disable_motion', true); // 影・アニメ無効化

    // アクセント色と動作無効CSSを注入
    $css  = ':root{--icpw-accent:' . esc_attr($accent) . ';}' . "\n";
    if ($noMove) {
      $css .= <<<CSS
.icpw-card{box-shadow:none !important;transition:none !important;}
.icpw-card:hover{transform:none !important;}
.icpw-btn,.icpw-btn--ghost,.icpw-copy-btn{box-shadow:none !important;transition:none !important;}
.icpw-btn:hover,.icpw-btn--ghost:hover,.icpw-copy-btn:hover{transform:none !important;}
CSS;
    }
    // スタイルプリセットクラス（bodyではなくカードに付与するためCSSはここでは不要）
    // 必要なら共通の微調整もここで追加可能

    wp_add_inline_style('ichimaruplus-program-works', $css);
  }

  /** カード描画（タイトル/本文はテーマに任せる） */
  public static function render_card($post_id) {
    // icpw_* / _icpw_* 両対応メタ取得ヘルパ
    $getm = function($key) use ($post_id) {
      $v = get_post_meta($post_id, $key, true);
      if ($v !== '' && $v !== null) return $v;
      return get_post_meta($post_id, '_' . $key, true);
    };

    // メタ情報
    $repo_url    = $getm('icpw_repo_url');
    $branch      = $getm('icpw_repo_branch') ?: $getm('icpw_default_branch');
    $display_ver = $getm('icpw_display_version') ?: $getm('icpw_version');
    $license     = $getm('icpw_license');
    $site_url    = $getm('icpw_site_url');
    $docs_url    = $getm('icpw_docs_url');

    // GitHub 連携メタ（保存済み想定）
    $stars  = (int) $getm('icpw_gh_stars');
    $forks  = (int) $getm('icpw_gh_forks');
    $issues = (int) $getm('icpw_gh_issues');
    $lang   = (string) $getm('icpw_gh_language');

    // 何も出す項目がないなら描画しない
    if (!$repo_url && !$display_ver && !$license && !$branch && !$site_url && !$docs_url && !$stars && !$forks && !$issues && !$lang) {
      return '';
    }

    // スタイルプリセット（クラス付与）
    $style = get_option('icpw_style', 'flat'); // flat / wire / brand
    $style_class = 'icpw-style--' . sanitize_html_class($style);

    ob_start(); ?>
    <section class="icpw-wrap" aria-label="プログラム情報">
      <div class="icpw-card <?php echo esc_attr($style_class); ?>">

        <?php if ($stars || $forks || $issues || $lang): ?>
        <div class="icpw-ghmeta" role="group" aria-label="リポジトリ統計">
          <?php if ($stars):  ?><span class="icpw-ico">★ <?php echo number_format_i18n($stars); ?></span><?php endif; ?>
          <?php if ($forks):  ?><span class="icpw-ico">⎇ <?php echo number_format_i18n($forks); ?></span><?php endif; ?>
          <?php if ($issues): ?><span class="icpw-ico">⚠ <?php echo number_format_i18n($issues); ?></span><?php endif; ?>
          <?php if ($lang):   ?><span class="icpw-ico">⌘ <?php echo esc_html($lang); ?></span><?php endif; ?>
        </div>
        <?php endif; ?>

        <dl class="icpw-info">
          <?php if ($display_ver): ?><dt>バージョン</dt><dd><?php echo esc_html($display_ver); ?></dd><?php endif; ?>
          <?php if ($license):     ?><dt>ライセンス</dt><dd><?php echo esc_html($license); ?></dd><?php endif; ?>
          <?php if ($branch):      ?><dt>ブランチ</dt><dd><?php echo esc_html($branch); ?></dd><?php endif; ?>
          <?php if ($repo_url):    ?><dt>リポジトリ</dt><dd><a href="<?php echo esc_url($repo_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($repo_url); ?></a></dd><?php endif; ?>
          <?php if ($site_url):    ?><dt>製品サイト</dt><dd><a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($site_url); ?></a></dd><?php endif; ?>
          <?php if ($docs_url):    ?><dt>ドキュメント</dt><dd><a href="<?php echo esc_url($docs_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($docs_url); ?></a></dd><?php endif; ?>
        </dl>

        <?php if ($repo_url): ?>
        <div class="icpw-buttons">
          <a class="icpw-btn" href="<?php echo esc_url($repo_url); ?>" target="_blank" rel="noopener">GitHubで見る</a>
          <div class="icpw-code">
            <?php $cmd = 'git clone ' . $repo_url; ?>
            <!-- ★ frontend.js と合わせて data-copy を使用（互換） -->
            <button class="icpw-copy-btn" data-copy="<?php echo esc_attr($cmd); ?>">コピー</button>
            <code><?php echo esc_html($cmd); ?></code>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </section>
    <?php
    return ob_get_clean();
  }
}