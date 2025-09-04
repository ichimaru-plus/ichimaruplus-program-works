<?php
/**
 * Safe GitHub Updater (standalone)
 *
 * 公式ディレクトリ未登録のプラグインを、GitHub Releases から安全に自動更新するためのクラス。
 * - Release Assets の ZIP を最優先（フォルダ名が壊れない）
 * - zipball（Source code.zip）は最後のフォールバック
 * - zipball でも展開直後にフォルダ名を正規化（owner-repo-<hash>/... を補正）
 * - 「プラグインの詳細を表示」モーダルにも Release 情報を表示
 *
 * 使い方（プラグイン本体で呼び出し）:
 *   require_once plugin_dir_path(__FILE__) . 'includes/class-updater.php';
 *   add_action('plugins_loaded', function(){
 *     if (is_admin()) {
 *       new ICPW_Updater(__FILE__, 'ichimaru-plus/ichimaruplus-program-works'); // owner/repo に置換
 *     }
 *   });
 */

if (!defined('ABSPATH')) { exit; }

if (!class_exists('ICPW_Updater')):

class ICPW_Updater {

	/** @var string プラグインのメインファイル（__FILE__ を渡す） */
	private $plugin_file;

	/** @var string プラグインのベース名 (dir/file.php) */
	private $plugin_basename;

	/** @var string GitHub "owner/repo" */
	private $repo;

	/** @var string GitHub API ベースURL */
	private $api_base = 'https://api.github.com';

	/** @var string GitHub API User-Agent（必須） */
	private $user_agent = 'WordPress; IchimaruPlus-Updater';

	/** @var string サイトトランジェントキー（最新Releaseキャッシュ） */
	private $cache_key;

	/** @var int キャッシュTTL（秒） */
	private $cache_ttl = 15 * MINUTE_IN_SECONDS;

	public function __construct($plugin_file, $repo) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename($plugin_file);
		$this->repo            = trim($repo);
		$this->cache_key       = 'icpw_updater_' . md5($this->repo);

		// 更新チェックに差し込み
		add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);

		// プラグイン詳細モーダル
		add_filter('plugins_api', [$this, 'plugins_api'], 20, 3);

		// zipball など展開時にフォルダ名を正規化
		add_filter('upgrader_source_selection', [$this, 'normalize_folder_name'], 10, 4);
	}

	/* =========================================================
	 * 更新情報を WP に注入
	 * ======================================================= */
	public function inject_update($transient) {
		if (empty($transient) || !is_object($transient) || empty($transient->checked)) {
			return $transient;
		}

		$current_version = $this->get_current_version();
		$rel = $this->get_latest_release();
		if (!$rel) return $transient;

		$latest = ltrim((string)($rel->tag_name ?? ''), 'v');
		if (!$latest || version_compare($latest, $current_version, '<=')) {
			return $transient; // 最新、または取得不可
		}

		$download = $this->resolve_download_url($rel);
		if (!$download) return $transient;

		$transient->response[$this->plugin_basename] = (object)[
			'slug'        => dirname($this->plugin_basename),
			'plugin'      => $this->plugin_basename,
			'new_version' => $latest,
			'package'     => $download,
			'url'         => $rel->html_url ?? ('https://github.com/' . $this->repo),
			'icons'       => [],
			'banners'     => [],
			'tested'      => null,
			'requires'    => null,
		];

		return $transient;
	}

	/* =========================================================
	 * 「プラグインの詳細を表示」モーダル
	 * ======================================================= */
	public function plugins_api($result, $action, $args) {
		if ($action !== 'plugin_information') return $result;

		$slug = dirname($this->plugin_basename);
		if (empty($args->slug) || $args->slug !== $slug) return $result;

		$rel = $this->get_latest_release();
		if (!$rel) return $result;

		$ver  = ltrim((string)($rel->tag_name ?? ''), 'v');
		$dl   = $this->resolve_download_url($rel);

		$info = (object)[
			'name'          => $this->human_name(),
			'slug'          => $slug,
			'version'       => $ver ?: $this->get_current_version(),
			'author'        => '<a href="https://ichimaru.plus" target="_blank" rel="noopener">Ichimaru+</a>',
			'homepage'      => $rel->html_url ?? ('https://github.com/' . $this->repo),
			'download_link' => $dl,
			'sections'      => [
				'description'  => wp_kses_post($this->release_body_to_html($rel)),
				'changelog'    => $this->format_changelog($rel),
			],
			'last_updated'  => !empty($rel->published_at) ? gmdate('Y-m-d H:i:s', strtotime($rel->published_at)) : null,
			'icons'         => [],
			'banners'       => [],
			'contributors'  => ['ichimaruplus' => 'https://github.com/ichimaru-plus'],
			'rating'        => 0,
			'num_ratings'   => 0,
			'active_installs' => 0,
			'tested'        => null,
			'requires'      => null,
			'requires_php'  => null,
		];

		return $info;
	}

	/* =========================================================
	 * zip 展開直後にフォルダ名を正規化（zipball対策）
	 * - owner-repo-<hash>/ichimaruplus-program-works/ を持ち上げる
	 * - 既存の目的フォルダがあれば安全に置換
	 * ======================================================= */
	public function normalize_folder_name($source, $remote_source, $upgrader, $hook_extra) {
		// 自プラグインの更新時のみ介入
		if (empty($hook_extra['plugin'])) return $source;

		$targets = is_array($hook_extra['plugin']) ? $hook_extra['plugin'] : [$hook_extra['plugin']];
		$match   = false;
		foreach ($targets as $p) {
			if (strpos($p, $this->plugin_basename) !== false) { $match = true; break; }
		}
		if (!$match) return $source;

		$desired  = dirname($this->plugin_basename);      // 'ichimaruplus-program-works'
		$basename = basename($source);
		$dest     = trailingslashit($remote_source) . $desired;

		// パターン1: zipball のトップ (owner-repo-hash/) 直下に目的フォルダがある
		$inner = trailingslashit($source) . $desired;
		if (is_dir($inner)) {
			if (is_dir($dest)) { $this->rrmdir($dest); }
			@rename($inner, $dest);
			// 外側の owner-repo-hash フォルダを掃除
			$this->rrmdir($source);
			return is_dir($dest) ? $dest : $source;
		}

		// パターン2: トップ階層のフォルダ名が目的名と異なる（そのままリネーム）
		if ($basename !== $desired) {
			if (is_dir($dest)) { $this->rrmdir($dest); }
			@rename($source, $dest);
			return is_dir($dest) ? $dest : $source;
		}

		// 既に目的名
		return $source;
	}

	/* =========================================================
	 * Helpers
	 * ======================================================= */
	private function get_current_version() {
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data($this->plugin_file, false, false);
		return isset($data['Version']) ? $data['Version'] : '0.0.0';
	}

	private function human_name() {
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data($this->plugin_file, false, false);
		return isset($data['Name']) ? $data['Name'] : $this->plugin_basename;
	}

	/** 最新 Release を GitHub API から取得（短期キャッシュ） */
	private function get_latest_release() {
		$cached = get_site_transient($this->cache_key);
		if ($cached) return $cached;

		$url  = $this->api_base . '/repos/' . $this->repo . '/releases/latest';
		$resp = wp_remote_get($url, [
			'timeout' => 12,
			'headers' => ['User-Agent' => $this->user_agent],
		]);
		if (is_wp_error($resp)) return null;

		$data = json_decode(wp_remote_retrieve_body($resp));
		if (!$data || !isset($data->tag_name)) return null;

		set_site_transient($this->cache_key, $data, $this->cache_ttl);
		return $data;
	}

	/** ダウンロードURLを解決：Assets の .zip を最優先、なければ zipball_url */
	private function resolve_download_url($release) {
		// 1) Assets の .zip を最優先（フォルダ名が壊れず安定）
		if (!empty($release->assets) && is_array($release->assets)) {
			foreach ($release->assets as $asset) {
				$name = (string)($asset->name ?? '');
				if ($name && preg_match('/\.zip$/i', $name)) {
					return (string)($asset->browser_download_url ?? '');
				}
			}
		}
		// 2) フォールバック：zipball（owner-repo-hash フォルダになる点に注意）
		if (!empty($release->zipball_url)) {
			return (string)$release->zipball_url;
		}
		return '';
	}

	/** Release body を簡易HTML整形（箇条書き→<ul><li>） */
	private function release_body_to_html($release) {
		$body = (string)($release->body ?? '');
		if ($body === '') {
			return esc_html__('No description provided.', 'ichimaruplus-pw');
		}
		$lines = preg_split('/\R/', $body);
		$out   = [];
		foreach ($lines as $ln) {
			if (preg_match('/^\s*[-*+]\s+(.+)/', $ln, $m)) {
				$out[] = '<li>' . esc_html($m[1]) . '</li>';
			} else {
				$out[] = '<p>' . esc_html($ln) . '</p>';
			}
		}
		$html = implode("\n", $out);
		if (strpos($html, '<li>') !== false) {
			$html = preg_replace('/(?:\s*<p>\s*<\/p>\s*)+/', '', $html);
			$html = '<ul>' . preg_replace('/<\/p>\s*<li>/', '<li>', $html) . '</ul>';
			$html = str_replace(['<p><li>', '</li></p>'], ['<li>', '</li>'], $html);
		}
		return $html;
	}

	/** Changelog セクション（Release 情報から生成） */
	private function format_changelog($release) {
		$ver  = esc_html(ltrim((string)($release->tag_name ?? ''), 'v'));
		$date = !empty($release->published_at) ? gmdate('Y-m-d', strtotime($release->published_at)) : '';
		$hdr  = sprintf('<h4>%s %s</h4>', $ver ?: 'Latest', $date ? '(' . esc_html($date) . ')' : '');
		return $hdr . $this->release_body_to_html($release);
	}

	/** 再帰削除（フォルダ名正規化時の安全措置） */
	private function rrmdir($dir) {
		if (!is_dir($dir)) return;
		$items = scandir($dir);
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') continue;
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) $this->rrmdir($path);
			else @unlink($path);
		}
		@rmdir($dir);
	}
}

endif;