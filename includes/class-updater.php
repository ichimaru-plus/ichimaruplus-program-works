<?php
/**
 * Safe GitHub Updater (standalone)
 *
 * - 公式ディレクトリ未登録のプラグインを GitHub Releases から更新
 * - Assets の ZIP を最優先（フォルダ名が壊れない）
 * - zipball は最後のフォールバック
 * - 「プラグインの詳細」モーダルにも Release 情報を表示
 *
 * 使い方：
 * new ICPW_Updater(__FILE__, 'ichimaru-plus/ichimaruplus-program-works');
 */

if (!defined('ABSPATH')) { exit; }

if (!class_exists('ICPW_Updater')):

class ICPW_Updater {

	/** @var string プラグインメインファイル */
	private $plugin_file;

	/** @var string プラグインのベース名 (dir/file.php) */
	private $plugin_basename;

	/** @var string GitHub "owner/repo" */
	private $repo;

	/** @var string API ベース */
	private $api_base = 'https://api.github.com';

	/** @var string GitHub API UA */
	private $user_agent = 'WordPress; IchimaruPlus-Updater';

	/** @var string キャッシュ用 key */
	private $cache_key;

	/** @var int キャッシュ（秒） */
	private $cache_ttl = 15 * MINUTE_IN_SECONDS;

	public function __construct($plugin_file, $repo) {
		$this->plugin_file    = $plugin_file;
		$this->plugin_basename= plugin_basename($plugin_file);
		$this->repo           = trim($repo);
		$this->cache_key      = 'icpw_updater_' . md5($this->repo);

		add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
		add_filter('plugins_api',                             [$this, 'plugins_api'], 20, 3);
		add_filter('upgrader_source_selection',               [$this, 'normalize_folder_name'], 10, 4);
	}

	/* -----------------------------------------------------------
	 * Update injection
	 * -------------------------------------------------------- */
	public function inject_update($transient) {
		if (empty($transient->checked)) return $transient;

		$current_version = $this->get_current_version();
		$rel = $this->get_latest_release();
		if (!$rel) return $transient;

		$latest = ltrim((string)($rel->tag_name ?? ''), 'v');
		if (!$latest || version_compare($latest, $current_version, '<=')) {
			return $transient;
		}

		$download = $this->resolve_download_url($rel);
		if (!$download) return $transient;

		$transient->response[$this->plugin_basename] = (object)[
			'slug'        => dirname($this->plugin_basename),
			'plugin'      => $this->plugin_basename,
			'new_version' => $latest,
			'package'     => $download,
			'url'         => $rel->html_url ?? 'https://github.com/'.$this->repo,
			'icons'       => [], // あれば設定
			'banners'     => [],
			'tested'      => null, // readme.txt があれば後述 API に出る
			'requires'    => null,
		];

		return $transient;
	}

	/* -----------------------------------------------------------
	 * Plugin details modal (「詳細を表示」)
	 * -------------------------------------------------------- */
	public function plugins_api($result, $action, $args) {
		if ($action !== 'plugin_information') return $result;

		$slug = dirname($this->plugin_basename);
		if (empty($args->slug) || $args->slug !== $slug) return $result;

		$rel = $this->get_latest_release();
		if (!$rel) return $result;

		$ver  = ltrim((string)($rel->tag_name ?? ''), 'v');
		$dl   = $this->resolve_download_url($rel);
		$ch   = $this->format_changelog($rel);

		$info = (object)[
			'name'          => $this->human_name(),
			'slug'          => $slug,
			'version'       => $ver ?: $this->get_current_version(),
			'author'        => '<a href="https://ichimaru.plus" target="_blank" rel="noopener">Ichimaru+</a>',
			'homepage'      => $rel->html_url ?? 'https://github.com/'.$this->repo,
			'download_link' => $dl,
			'sections'      => [
				'description'  => wp_kses_post($this->release_body_to_html($rel)),
				'changelog'    => $ch,
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

	/* -----------------------------------------------------------
	 * Normalize folder name on install from zipball
	 * -------------------------------------------------------- */
	public function normalize_folder_name($source, $remote_source, $upgrader, $hook_extra) {
		// 自プラグイン更新時のみ介入
		if (empty($hook_extra['plugin'])) return $source;

		$targets = is_array($hook_extra['plugin']) ? $hook_extra['plugin'] : [$hook_extra['plugin']];
		$match   = false;
		foreach ($targets as $p) {
			if (strpos($p, $this->plugin_basename) !== false) {
				$match = true; break;
			}
		}
		if (!$match) return $source;

		$desired_folder = dirname($this->plugin_basename); // ichimaruplus-program-works
		$basename       = basename($source);
		if ($basename === $desired_folder) return $source;

		$dest = trailingslashit($remote_source) . $desired_folder;
		// 既に存在する場合は一旦削除/リネーム（安全側）
		if (is_dir($dest)) {
			$this->rrmdir($dest);
		}
		@rename($source, $dest);
		return is_dir($dest) ? $dest : $source;
	}

	/* -----------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------- */
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

	/** GitHub latest release を取得（結果は短時間キャッシュ） */
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

	/** Assets から ZIP のダウンロード URL を優先的に解決 */
	private function resolve_download_url($release) {
		// 1) Assets の .zip を最優先（フォルダ名が壊れない）
		if (!empty($release->assets) && is_array($release->assets)) {
			foreach ($release->assets as $asset) {
				$name = (string)($asset->name ?? '');
				if ($name && preg_match('/\.zip$/i', $name)) {
					return (string)($asset->browser_download_url ?? '');
				}
			}
		}
		// 2) なければ zipball_url（コミットハッシュつきフォルダ名に注意）
		if (!empty($release->zipball_url)) {
			return (string)$release->zipball_url;
		}
		return '';
	}

	/** Release body → HTML（簡易整形） */
	private function release_body_to_html($release) {
		$body = (string)($release->body ?? '');
		if ($body === '') {
			return esc_html__('No description provided.', 'ichimaruplus-pw');
		}
		// 箇条書きマークダウンを簡易的に <ul><li> 化
		$lines = preg_split('/\R/', $body);
		$out   = [];
		foreach ($lines as $ln) {
			if (preg_match('/^\s*[-*+]\s+(.+)/', $ln, $m)) {
				$out[] = '<li>' . esc_html($m[1]) . '</li>';
			} else {
				$out[] = '<p>' . esc_html($ln) . '</p>';
			}
		}
		// <li> が一つでもあれば <ul> で囲む
		$html = implode("\n", $out);
		if (strpos($html, '<li>') !== false) {
			$html = preg_replace('/(?:\s*<p>\s*<\/p>\s*)+/', '', $html); // 空 <p> を軽く除去
			$html = '<ul>' . preg_replace('/<\/p>\s*<li>/', '<li>', $html) . '</ul>';
			$html = str_replace(['<p><li>', '</li></p>'], ['<li>', '</li>'], $html);
		}
		return $html;
	}

	/** Changelog セクション用（Release 情報から） */
	private function format_changelog($release) {
		$ver  = esc_html(ltrim((string)($release->tag_name ?? ''), 'v'));
		$date = !empty($release->published_at) ? gmdate('Y-m-d', strtotime($release->published_at)) : '';
		$hdr  = sprintf('<h4>%s %s</h4>', $ver ?: 'Latest', $date ? '(' . esc_html($date) . ')' : '');
		return $hdr . $this->release_body_to_html($release);
	}

	/** 再帰削除（フォルダ正規化時の安全措置） */
	private function rrmdir($dir) {
		if (!is_dir($dir)) return;
		$items = scandir($dir);
		foreach ($items as $item) {
			if ($item === '.' || $item === '.') continue;
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) $this->rrmdir($path);
			else @unlink($path);
		}
		@rmdir($dir);
	}
}

endif;