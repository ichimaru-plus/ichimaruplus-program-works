<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_Frontend {
  public static function register_shortcode(){
    add_shortcode('icpw_works', [__CLASS__,'shortcode']);
  }

  public static function shortcode($atts){
    $a = shortcode_atts([
      'ids'      => '',
      'per_page' => 12,
      'columns'  => 3,
      'github'   => '1',
    ], $atts, 'icpw_works');

    wp_enqueue_style('ichimaruplus-program-works');
    wp_enqueue_script('ichimaruplus-program-works');

    $opt = ICPW_PW_Admin::get_settings();
    $accent = $opt['accent_color'];
    $show_gh = ($a['github']==='1') && $opt['show_github'];
    $enable_copy = $opt['enable_copy'];

    // Inline CSS var override
    $inline = ':root{--icpw-accent:'.esc_attr($accent).'}';
    wp_add_inline_style('ichimaruplus-program-works',$inline);

    $args = ['post_type'=>'icpw_prog','posts_per_page'=>intval($a['per_page'])];
    if (!empty($a['ids'])){
      $ids = array_filter(array_map('absint', explode(',', $a['ids'])));
      if ($ids){ $args['post__in']=$ids; $args['orderby']='post__in'; }
    }

    $q = new WP_Query($args);
    if (!$q->have_posts()){ return '<p>作品がありません。</p>'; }

    ob_start();
    echo '<div class="icpw-grid">';
    foreach ($q->posts as $p){
      $meta = [];
      foreach (ICPW_PW_Meta::schema() as $k=>$def){ $meta[$k] = get_post_meta($p->ID, "_icpw_$k", true); }
      $gh = false;
      if ($show_gh && !empty($meta['repo_url']) && ($meta['auto_fill']!=='0')){
        $gh = ICPW_PW_GitHub::fetch($meta['repo_url']);
      }
      $clone_cmd = !empty($meta['repo_url']) ? 'git clone '.esc_url_raw($meta['repo_url']) : '';
      ?>
      <div class="icpw-card">
        <h3 class="icpw-title"><a href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a></h3>
        <div class="icpw-desc"><?php echo esc_html(wp_trim_words($p->post_excerpt ?: wp_strip_all_tags($p->post_content), 30)); ?></div>
        <?php if ($show_gh && $gh): ?>
          <div class="icpw-badges">
            <span class="icpw-badge">⭐ <?php echo intval($gh['stars']); ?></span>
            <span class="icpw-badge">🍴 <?php echo intval($gh['forks']); ?></span>
            <span class="icpw-badge">🐞 <?php echo intval($gh['open_issues']); ?></span>
            <?php if (!empty($gh['language'])): ?><span class="icpw-badge"><?php echo esc_html($gh['language']); ?></span><?php endif; ?>
            <?php if (!empty($gh['latest_tag'])): ?><span class="icpw-badge"><?php echo esc_html($gh['latest_tag']); ?></span><?php endif; ?>
          </div>
          <div class="icpw-small"><a href="<?php echo esc_url($gh['html_url']); ?>" target="_blank" rel="noopener">GitHub リポジトリ</a></div>
        <?php endif; ?>

        <div class="icpw-meta">
          <?php if(!empty($meta['version'])): ?>Version: <?php echo esc_html($meta['version']); ?><?php endif; ?>
          <?php if(!empty($meta['license'])): ?> / License: <?php echo esc_html($meta['license']); ?><?php endif; ?>
        </div>

        <div class="icpw-actions">
          <?php if ($enable_copy && $clone_cmd): ?>
            <button type="button" class="icpw-btn copy" data-copy="<?php echo esc_attr($clone_cmd); ?>">コピー</button>
          <?php endif; ?>
          <?php if(!empty($meta['docs_url'])): ?>
            <a class="icpw-btn" href="<?php echo esc_url($meta['docs_url']); ?>" target="_blank" rel="noopener">Docs</a>
          <?php endif; ?>
        </div>
      </div>
      <?php
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
  }
}
