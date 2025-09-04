<?php
if (!defined('ABSPATH')) exit;

class ICPW_Updater {
  private $plugin_file;
  private $github_repo; // 例: ichimaru-plus/ichimaruplus-program-works

  public function __construct($plugin_file, $github_repo) {
    $this->plugin_file = $plugin_file;
    $this->github_repo = $github_repo;

    // 管理画面・自動更新チェックのときのみ走らせる（フロントでの負荷/事故を抑止）
    if (is_admin()) {
      add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
      add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }
  }

  private function get_latest_release() {
    $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
    $args = [
      'headers' => [
        // GitHub API は User-Agent 必須
        'User-Agent' => 'ichimaruplus-program-works-updater',
        'Accept'     => 'application/vnd.github+json',
      ],
      'timeout' => 10,
    ];
    $res = wp_remote_get($url, $args);
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code !== 200) return null;
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body);
    return $json ?: null;
  }

  private function current_version() {
    // get_plugin_data を使う前に必ず読み込み
    if (!function_exists('get_plugin_data')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $data = get_plugin_data($this->plugin_file, false, false);
    return $data['Version'] ?? '0.0.0';
  }

  public function check_for_update($transient) {
    // 予防的にオブジェクト化
    if (!is_object($transient)) {
      $transient = (object) ['checked' => [], 'response' => []];
    }
    if (empty($transient->checked)) return $transient;

    $release = $this->get_latest_release();
    if (!$release || empty($release->tag_name)) return $transient;

    $latest = ltrim($release->tag_name, 'v'); // v1.1.1 → 1.1.1
    $current = $this->current_version();

    if (version_compare($current, $latest, '<')) {
      $slug = plugin_basename($this->plugin_file);

      // ダウンロードURLの決定（Assets優先 → なければZipball）
      $package = '';
      if (!empty($release->assets) && is_array($release->assets) && !empty($release->assets[0]->browser_download_url)) {
        $package = $release->assets[0]->browser_download_url;
      } elseif (!empty($release->zipball_url)) {
        $package = $release->zipball_url;
      }

      $transient->response[$slug] = (object) [
        'slug'        => $slug,
        'new_version' => $latest,
        'url'         => $release->html_url ?? '',
        'package'     => $package,
      ];
    }

    return $transient;
  }

  public function plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information') return $res;

    $slug = plugin_basename($this->plugin_file);
    // $args->slug は ファイル名なしのスラッグが来ることもあるため、両対応
    if (!isset($args->slug) || ($args->slug !== $slug && strpos($slug, $args->slug) === false)) {
      return $res;
    }

    $release = $this->get_latest_release();
    if (!$release) return $res;

    $latest = ltrim($release->tag_name ?? '', 'v');
    if (!$latest) return $res;

    $download = '';
    if (!empty($release->assets) && is_array($release->assets) && !empty($release->assets[0]->browser_download_url)) {
      $download = $release->assets[0]->browser_download_url;
    } elseif (!empty($release->zipball_url)) {
      $download = $release->zipball_url;
    }

    return (object) [
      'name'          => 'Ichimaru+ Program Works',
      'slug'          => $slug,
      'version'       => $latest,
      'author'        => '<a href="https://ichimaruplus.com">Ichimaru+</a>',
      'homepage'      => 'https://ichimaruplus.com/programs/',
      'download_link' => $download,
      'sections'      => [
        'description' => !empty($release->body) ? wp_kses_post($release->body) : 'GitHub リリースから自動更新に対応。',
      ],
    ];
  }
}