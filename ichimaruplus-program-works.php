<?php
/**
 * Plugin Name: Ichimaru+ Program Works
 * Plugin URI:  https://github.com/ichimaru-plus/ichimaruplus-program-works
 * Description: プログラム作品（プラグイン/アプリ/ツール）を登録・公開。GitHub連携、CSV入出力、REST API、自動更新、ショートコード、色設定、コピー用ボタン対応。
 * Version:     1.1.5
 * Author:      Ichimaru+
 * Author URI:  https://ichimaru.plus
 * Update URI:  ichimaruplus-program-works
 * Text Domain: ichimaruplus-pw
 * License:     GPL-2.0+
 */

if (!defined('ABSPATH')) { exit; }

/** ============================================================================
 * 定数・ヘルパー
 * ========================================================================== */
define('ICPW_PW_PATH', plugin_dir_path(__FILE__));
define('ICPW_PW_URL',  plugin_dir_url(__FILE__));

/**
 * プラグインバージョンをヘッダーから取得
 * → 「Version:」を唯一の真実にして、他ファイルでの手動更新を不要にする
 */
function icpw_pw_version() {
	static $v = null;
	if ($v !== null) return $v;
	if (!function_exists('get_plugin_data')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$data = get_plugin_data(__FILE__, false, false);
	$v = isset($data['Version']) ? $data['Version'] : '0.0.0';
	return $v;
}

/** ============================================================================
 * 読み込み
 * ========================================================================== */
require_once ICPW_PW_PATH . 'includes/class-cpt.php';
require_once ICPW_PW_PATH . 'includes/class-meta.php';
require_once ICPW_PW_PATH . 'includes/class-github.php';
require_once ICPW_PW_PATH . 'includes/class-api.php';
require_once ICPW_PW_PATH . 'includes/class-import-export.php';
require_once ICPW_PW_PATH . 'includes/class-admin.php';
require_once ICPW_PW_PATH . 'includes/class-frontend.php';
require_once ICPW_PW_PATH . 'includes/class-updater.php';

/** ============================================================================
 * 初期化
 * ========================================================================== */
add_action('init', function () {
	// カスタム投稿タイプ・メタ登録
	ICPW_PW_CPT::register();
	ICPW_PW_Meta::register_meta();

	// ショートコード登録
	ICPW_PW_Frontend::register_shortcode();

	// 国際化（/languages がある場合）
	load_plugin_textdomain('ichimaruplus-pw', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/** REST API ルート */
add_action('rest_api_init', ['ICPW_PW_API', 'register_routes']);

/** 管理画面メニュー・設定 */
add_action('admin_menu', ['ICPW_PW_ImportExport', 'register_menu']); // CSVインポート/エクスポート
add_action('admin_menu', ['ICPW_PW_Admin', 'menu']);                  // 「設定 → Program Works」
add_action('admin_init', ['ICPW_PW_Admin', 'register_settings']);     // 設定項目登録

/** アセット（フロント） */
add_action('wp_enqueue_scripts', function () {
	wp_register_style(
		'ichimaruplus-program-works',
		ICPW_PW_URL . 'assets/css/frontend.css',
		[],
		icpw_pw_version()
	);
	wp_register_script(
		'ichimaruplus-program-works',
		ICPW_PW_URL . 'assets/js/frontend.js',
		[],
		icpw_pw_version(),
		true
	);
});

/** 有効化/無効化時のパーマリンク再生成 */
register_activation_hook(__FILE__, function () {
	ICPW_PW_CPT::register();
	flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () {
	flush_rewrite_rules();
});

/** 個別ページをプラグイン内テンプレートに切り替え（/programs/...） */
add_filter('template_include', function ($template) {
	if (is_singular('icpw_prog')) {
		$custom = ICPW_PW_PATH . 'templates/single-icpw_prog.php';
		if (file_exists($custom)) {
			return $custom;
		}
	}
	return $template;
});

/** ============================================================================
 * GitHub Releases による自動更新（管理画面のみ）
 * ========================================================================== */
add_action('plugins_loaded', function () {
	if (is_admin()) {
		new ICPW_Updater(__FILE__, 'ichimaru-plus/ichimaruplus-program-works');
	}
});