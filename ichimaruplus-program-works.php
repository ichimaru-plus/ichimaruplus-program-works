<?php
/**
 * Plugin Name: Ichimaru+ Program Works
 * Plugin URI:  https://github.com/ichimaru-plus/ichimaruplus-program-works
 * Description: プログラム作品（プラグイン/アプリ/ツール）を登録・公開。GitHub連携、CSV入出力、REST API、自動更新、ショートコード、色設定、コピー用ボタン対応。
 * Version:     1.1.0
 * Author:      Ichimaru+
 * Author URI:  https://ichimaru.plus
 * Update URI:  ichimaruplus-program-works
 * Text Domain: ichimaruplus-pw
 * License:     GPL-2.0+
 */

if (!defined('ABSPATH')) { exit; }

define('ICPW_PW_VER','1.1.0');
define('ICPW_PW_PATH',plugin_dir_path(__FILE__));
define('ICPW_PW_URL', plugin_dir_url(__FILE__));

require_once ICPW_PW_PATH.'includes/class-cpt.php';
require_once ICPW_PW_PATH.'includes/class-meta.php';
require_once ICPW_PW_PATH.'includes/class-github.php';
require_once ICPW_PW_PATH.'includes/class-api.php';
require_once ICPW_PW_PATH.'includes/class-import-export.php';
require_once ICPW_PW_PATH.'includes/class-admin.php';
require_once ICPW_PW_PATH.'includes/class-frontend.php';

add_action('init', function(){
  ICPW_PW_CPT::register();
  ICPW_PW_Meta::register_meta();
});

add_action('rest_api_init',['ICPW_PW_API','register_routes']);
add_action('admin_menu',['ICPW_PW_ImportExport','register_menu']);
add_action('admin_menu',['ICPW_PW_Admin','register_settings_page']);
add_action('admin_init',['ICPW_PW_Admin','register_settings']);

add_action('wp_enqueue_scripts', function(){
  wp_register_style('ichimaruplus-program-works', ICPW_PW_URL.'assets/css/frontend.css', [], ICPW_PW_VER);
  wp_register_script('ichimaruplus-program-works', ICPW_PW_URL.'assets/js/frontend.js', [], ICPW_PW_VER, true);
});

register_activation_hook(__FILE__,function(){ ICPW_PW_CPT::register(); flush_rewrite_rules(); });
register_deactivation_hook(__FILE__,function(){ flush_rewrite_rules(); });

/** GitHub Releases 自動更新（★org/repo は実リポに合わせて変更） */
add_filter('pre_set_site_transient_update_plugins', function($transient){
  if(empty($transient->checked)) return $transient;
  $plugin  = plugin_basename(__FILE__);
  $current = ICPW_PW_VER;
  $api = 'https://api.github.com/repos/ichimaru-plus/ichimaruplus-program-works/releases/latest';
  $res = wp_remote_get($api, ['timeout'=>8, 'headers'=>['User-Agent'=>'WordPress; IchimaruPlus-Program-Works']]);
  if (is_wp_error($res)) return $transient;
  $d = json_decode(wp_remote_retrieve_body($res));
  if (!$d || empty($d->tag_name) || empty($d->zipball_url)) return $transient;
  $latest = ltrim($d->tag_name, 'v');
  if (version_compare($latest, $current, '>')) {
    $transient->response[$plugin] = (object)[
      'slug'        => dirname($plugin),
      'plugin'      => $plugin,
      'new_version' => $latest,
      'url'         => $d->html_url ?? 'https://github.com/ichimaru-plus/ichimaruplus-program-works',
      'package'     => $d->zipball_url,
    ];
  }
  return $transient;
});

// ショートコード登録
add_action('init', ['ICPW_PW_Frontend','register_shortcode']);
