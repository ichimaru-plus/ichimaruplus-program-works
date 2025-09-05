<?php
/**
 * Template: Single for icpw_prog (full page)
 */
if (!defined('ABSPATH')) exit;

get_header();

while (have_posts()): the_post();

  // メタ両対応（icpw_* / _icpw_*）
  $getm = function($key){
    $id = get_the_ID();
    $v  = get_post_meta($id, $key, true);
    if ($v !== '' && $v !== null) return $v;
    return get_post_meta($id, '_' . $key, true);
  };

  $meta = [
    'repo_url'    => $getm('icpw_repo_url'),
    'branch'      => $getm('icpw_repo_branch') ?: $getm('icpw_default_branch'),
    'display_ver' => $getm('icpw_display_version') ?: $getm('icpw_version'),
    'license'     => $getm('icpw_license'),
    'site_url'    => $getm('icpw_site_url'),
    'docs_url'    => $getm('icpw_docs_url'),
  ];

  $gh = [
    'stars'  => (int) (get_post_meta(get_the_ID(), 'icpw_gh_stars', true)  ?: get_post_meta(get_the_ID(), '_icpw_gh_stars', true)),
    'forks'  => (int) (get_post_meta(get_the_ID(), 'icpw_gh_forks', true)  ?: get_post_meta(get_the_ID(), '_icpw_gh_forks', true)),
    'issues' => (int) (get_post_meta(get_the_ID(), 'icpw_gh_issues', true) ?: get_post_meta(get_the_ID(), '_icpw_gh_issues', true)),
    'lang'   => (string) ((get_post_meta(get_the_ID(), 'icpw_gh_language', true) ?: get_post_meta(get_the_ID(), '_icpw_gh_language', true)) ?: ''),
  ];

  // 必要CSS/JSを確実に読み込む
  wp_enqueue_style('ichimaruplus-program-works', ICPW_PW_URL.'assets/css/frontend.css', [], function_exists('icpw_pw_version')? icpw_pw_version():'1.0.0');
  wp_enqueue_script('ichimaruplus-program-works', ICPW_PW_URL.'assets/js/frontend.js', [], function_exists('icpw_pw_version')? icpw_pw_version():'1.0.0', true);
?>
  <main id="primary" class="site-main">
    <article <?php post_class('icpw-card'); ?>>

      <header class="icpw-head">
        <h1 class="icpw-title"><?php the_title(); ?></h1>

        <div class="icpw-ghmeta" aria-label="リポジトリ統計">
          <?php if ($gh['stars']): ?>
            <span class="icpw-ico" title="スター数">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17.27l6.18 3.73-1.64-7.03L21.5 9.24l-7.12-.61L12 2 9.62 8.63l-7.12.61 4.96 4.73L6.82 21z"/></svg>
              <span><?php echo number_format_i18n($gh['stars']); ?></span>
            </span>
          <?php endif; ?>
          <?php if ($gh['forks']): ?>
            <span class="icpw-ico" title="フォーク数">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 3a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM7 17a2 2 0 100 4 2 2 0 000-4zm10-8a3 3 0 01-3 3H10a3 3 0 01-3-3V9h2v1a1 1 0 001 1h4a1 1 0 001-1V9h2v0zM8 14h2v2H8v-2zm6 0h2v2h-2v-2z"/></svg>
              <span><?php echo number_format_i18n($gh['forks']); ?></span>
            </span>
          <?php endif; ?>
          <?php if ($gh['issues']): ?>
            <span class="icpw-ico" title="未解決イシュー">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 7h2v6h-2V7zm0 8h2v2h-2v-2zm1-13C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
              <span><?php echo number_format_i18n($gh['issues']); ?></span>
            </span>
          <?php endif; ?>
          <?php if ($gh['lang']): ?>
            <span class="icpw-ico" title="主要言語">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.4 16.6L5.8 13l3.6-3.6L8 8l-5 5 5 5 1.4-1.4zm5.2 0L18.2 13l-3.6-3.6L16 8l5 5-5 5-1.4-1.4z"/></svg>
              <span><?php echo esc_html($gh['lang']); ?></span>
            </span>
          <?php endif; ?>
        </div>
      </header>

      <div class="icpw-description">
        <?php the_content(); ?>
      </div>

      <dl class="icpw-info">
        <?php if ($meta['display_ver']): ?><dt>表示バージョン</dt><dd><?php echo esc_html($meta['display_ver']); ?></dd><?php endif; ?>
        <?php if ($meta['license']):     ?><dt>ライセンス</dt><dd><?php echo esc_html($meta['license']); ?></dd><?php endif; ?>
        <?php if ($meta['branch']):      ?><dt>デフォルトブランチ</dt><dd><?php echo esc_html($meta['branch']); ?></dd><?php endif; ?>
        <?php if ($meta['repo_url']):    ?><dt>Gitリポジトリ</dt><dd><a href="<?php echo esc_url($meta['repo_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($meta['repo_url']); ?></a></dd><?php endif; ?>
        <?php if ($meta['site_url']):    ?><dt>製品サイト</dt><dd><a href="<?php echo esc_url($meta['site_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($meta['site_url']); ?></a></dd><?php endif; ?>
        <?php if ($meta['docs_url']):    ?><dt>ドキュメント</dt><dd><a href="<?php echo esc_url($meta['docs_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($meta['docs_url']); ?></a></dd><?php endif; ?>
      </dl>

      <div class="icpw-buttons">
        <?php if ($meta['repo_url']): ?>
          <a class="icpw-btn" href="<?php echo esc_url($meta['repo_url']); ?>" target="_blank" rel="noopener">GitHubで見る</a>
          <div class="icpw-code" style="margin-left:0">
            <?php $cmd = 'git clone ' . $meta['repo_url']; ?>
            <button class="icpw-copy-btn" data-icpw-copy="<?php echo esc_attr($cmd); ?>">コピー</button>
            <code><?php echo esc_html($cmd); ?></code>
          </div>
        <?php endif; ?>
        <?php if ($meta['docs_url']): ?>
          <a class="icpw-btn icpw-btn--ghost" href="<?php echo esc_url($meta['docs_url']); ?>" target="_blank" rel="noopener">ドキュメント</a>
        <?php endif; ?>
      </div>

    </article>
  </main>
<?php endwhile;

get_footer();