<?php
if (!defined('ABSPATH')) exit;

class ICPW_PW_Frontend {
  public static function register_shortcode(){
    add_shortcode('icpw_works',[__CLASS__,'shortcode']);
  }

  public static function shortcode($atts){
    $a=shortcode_atts([
      'ids'=>'',
      'per_page'=>12,
      'columns'=>3,
      'github'=>'1',
      'size'=>'md'
    ],$atts,'icpw_works');

    wp_enqueue_style('ichimaruplus-program-works');
    wp_enqueue_script('ichimaruplus-program-works');

    $opt=class_exists('ICPW_PW_Admin')?ICPW_PW_Admin::get_settings():[
      'accent_color'=>'#6366f1','show_github'=>1,'enable_copy'=>1,'border_radius'=>6,'shadow_strength'=>'soft'
    ];

    $accent=$opt['accent_color'];
    $show_gh=($a['github']==='1')&&$opt['show_github'];
    $enable_copy=$opt['enable_copy'];

    $radius=intval($opt['border_radius']);
    $shadow_key=$opt['shadow_strength'];
    $shadow_map=[
      'none'=>'none',
      'soft'=>'0 2px 6px rgba(0,0,0,0.08)',
      'medium'=>'0 3px 10px rgba(0,0,0,0.12)',
      'strong'=>'0 4px 16px rgba(0,0,0,0.18)'
    ];
    $shadow_css=$shadow_map[$shadow_key]??$shadow_map['soft'];

    $inline=':root{--icpw-accent:'.esc_attr($accent).';--icpw-radius:'.$radius.'px;--icpw-shadow:'.$shadow_css.';}';
    wp_add_inline_style('ichimaruplus-program-works',$inline);

    $args=['post_type'=>'icpw_prog','posts_per_page'=>intval($a['per_page'])];
    if(!empty($a['ids'])){
      $ids=array_filter(array_map('absint',explode(',',$a['ids'])));
      if($ids){$args['post__in']=$ids;$args['orderby']='post__in';}
    }
    $q=new WP_Query($args);
    if(!$q->have_posts()){return '<p>作品がありません。</p>';}

    ob_start();
    $classes=['icpw-grid'];
    if($a['size']==='lg'){$classes[]='icpw-lg';}
    if(intval($a['columns'])===1){$classes[]='icpw-single';}
    $cols=max(1,intval($a['columns']));
    $style='';
    if(intval($a['columns'])>1){
      $style='grid-template-columns:repeat('.$cols.',minmax(320px,1fr));';
    }

    echo '<div class="'.esc_attr(implode(' ',$classes)).'" data-columns="'.esc_attr($cols).'" style="'.$style.'">';
    foreach($q->posts as $p){
      $meta=[];
      foreach(ICPW_PW_Meta::schema() as $k=>$def){$meta[$k]=get_post_meta($p->ID,"_icpw_$k",true);}
      $gh=false;
      if($show_gh && !empty($meta['repo_url']) && ($meta['auto_fill']!=='0')){
        $gh=ICPW_PW_GitHub::fetch($meta['repo_url']);
        if($gh){$meta=ICPW_PW_GitHub::fill_meta($meta,$gh);}
      }
      $title=get_the_title($p);
      $excerpt=wp_trim_words($p->post_excerpt?:wp_strip_all_tags($p->post_content),30);
      $clone=!empty($meta['repo_url'])?'git clone '.esc_url_raw($meta['repo_url']):'';
      $version=$meta['version']?:($gh['latest_release_tag']??$gh['latest_tag']??'');
      $license=$meta['license']?:($gh['license']??'');
      $branch=$meta['default_branch']?:($gh['default_branch']??'');
      $lang=$gh['language']??'';
      $stars=$gh['stars']??0;
      $forks=$gh['forks']??0;
      $issues=$gh['open_issues']??0;
      $updated=!empty($gh['pushed_at'])?mysql2date(get_option('date_format'),$gh['pushed_at']):'';
      $download_url='';
      if($gh){
        if(!empty($gh['download_zip'])){$download_url=$gh['download_zip'];}
        elseif(!empty($gh['download_tag_zip'])){$download_url=$gh['download_tag_zip'];}
        elseif(!empty($gh['download_branch_zip'])){$download_url=$gh['download_branch_zip'];}
      }
      ?>
      <div class="icpw-card">
        <h3 class="icpw-title"><a href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html($title); ?></a></h3>
        <div class="icpw-desc"><?php echo esc_html($excerpt); ?></div>
        <?php if($show_gh && $gh): ?>
          <div class="icpw-badges">
            <span class="icpw-badge">⭐ <?php echo intval($stars); ?></span>
            <span class="icpw-badge">🍴 <?php echo intval($forks); ?></span>
            <span class="icpw-badge">🐞 <?php echo intval($issues); ?></span>
            <?php if($lang): ?><span class="icpw-badge"><?php echo esc_html($lang); ?></span><?php endif; ?>
            <?php if($version): ?><span class="icpw-badge"><?php echo esc_html($version); ?></span><?php endif; ?>
          </div>
          <div class="icpw-meta">
            <div class="icpw-small">
              <?php if($branch): ?>Branch: <?php echo esc_html($branch); ?><?php endif; ?>
              <?php if($license): ?> / License: <?php echo esc_html($license); ?><?php endif; ?>
              <?php if($updated): ?> / Updated: <?php echo esc_html($updated); ?><?php endif; ?>
            </div>
            <div class="icpw-small"><a href="<?php echo esc_url($gh['html_url']); ?>" target="_blank" rel="noopener">GitHub リポジトリ</a></div>
          </div>
        <?php endif; ?>
        <div class="icpw-actions">
          <?php if($enable_copy && $clone): ?>
            <button type="button" class="icpw-btn copy" data-copy="<?php echo esc_attr($clone); ?>">コピー</button>
          <?php endif; ?>
          <?php if(!empty($meta['docs_url'])): ?>
            <a class="icpw-btn" href="<?php echo esc_url($meta['docs_url']); ?>" target="_blank" rel="noopener">Docs</a>
          <?php endif; ?>
          <?php if($download_url): ?>
            <a class="icpw-btn" href="<?php echo esc_url($download_url); ?>" target="_blank" rel="noopener">Download ZIP</a>
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