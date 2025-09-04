<?php
/**
 * プラグイン内テンプレート: single-icpw_prog.php
 * - テーマに依存せず、プラグイン内で個別ページを描画
 * - 本文（the_content）の後に、[icpw_works] ショートコードでカードUIを1枚表示
 */
if (!defined('ABSPATH')) { exit; }

// ヘッダー
get_header();

// スタイル/JS（プラグインで登録済みのハンドルを利用）
wp_enqueue_style('ichimaruplus-program-works');
wp_enqueue_script('ichimaruplus-program-works');

// アクセントカラー（設定ページの値を反映）
if (class_exists('ICPW_PW_Admin')) {
  $opt = ICPW_PW_Admin::get_settings();
  $accent = $opt['accent_color'] ?? '#6366f1';
  wp_add_inline_style('ichimaruplus-program-works', ':root{--icpw-accent:' . esc_attr($accent) . '}');
}
?>
<main id="primary" class="site-main" style="margin: 2rem auto; max-width: 1000px; padding: 0 1rem;">
  <?php if (have_posts()): while (have_posts()): the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <header class="entry-header">
        <h1 class="entry-title"><?php echo esc_html(get_the_title()); ?></h1>
      </header>

      <div class="entry-content">
        <?php
          // 通常の本文
          the_content();

          // この投稿（1件）のカードUIを追加表示（GitHub情報/Download/コピー対応）
          echo do_shortcode('[icpw_works ids="' . get_the_ID() . '" per_page="1" github="1"]');
        ?>
      </div>

      <footer class="entry-footer">
        <?php
          // 任意: タグ・カテゴリなど必要なら出力（デフォルトは未使用）
          // the_terms(get_the_ID(), 'post_tag');
        ?>
      </footer>
    </article>

    <?php
      // 任意: コメントを使う場合
      if (comments_open() || get_comments_number()) {
        comments_template();
      }
    ?>

  <?php endwhile; else: ?>
    <p>該当する投稿が見つかりませんでした。</p>
  <?php endif; ?>
</main>

<?php
// フッター
get_footer();