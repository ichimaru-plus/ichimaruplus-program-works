<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_GitHub {

  /**
   * リポジトリURLから GitHub API 情報を取得し、表示用に整形
   * - /repos/:owner/:repo
   * - /repos/:owner/:repo/tags?per_page=1
   * - /repos/:owner/:repo/releases/latest
   */
  public static function fetch($repo_url){
    if (!$repo_url || strpos($repo_url,'github.com')===false) return false;

    $api_base = rtrim(str_replace('https://github.com/', 'https://api.github.com/repos/', $repo_url), '/');
    $cache_key = 'icpw_pw_gh_'.md5($api_base);

    // 1時間キャッシュ
    if (($cached = get_transient($cache_key)) !== false) {
      return $cached;
    }

    $args = [
      'timeout' => 10,
      'headers' => ['User-Agent' => 'WordPress; IchimaruPlus-Program-Works']
    ];

    // 基本情報 /repos/:owner/:repo
    $res_repo = wp_remote_get($api_base, $args);
    if (is_wp_error($res_repo) || wp_remote_retrieve_response_code($res_repo) !== 200) return false;
    $repo = json_decode(wp_remote_retrieve_body($res_repo), true);
    if (!is_array($repo)) return false;

    // 最新タグ /tags?per_page=1
    $latest_tag = '';
    $res_tags = wp_remote_get(trailingslashit($api_base).'tags?per_page=1', $args);
    if (!is_wp_error($res_tags) && wp_remote_retrieve_response_code($res_tags)===200) {
      $tags = json_decode(wp_remote_retrieve_body($res_tags), true);
      if (!empty($tags[0]['name'])) $latest_tag = (string)$tags[0]['name'];
    }

    // 最新リリース /releases/latest
    $release_tag = ''; $release_published_at = ''; $release_zip = ''; $release_tar = '';
    $res_rel = wp_remote_get(trailingslashit($api_base).'releases/latest', $args);
    if (!is_wp_error($res_rel) && wp_remote_retrieve_response_code($res_rel)===200) {
      $rel = json_decode(wp_remote_retrieve_body($res_rel), true);
      if (is_array($rel)) {
        $release_tag = (string)($rel['tag_name'] ?? '');
        $release_published_at = (string)($rel['published_at'] ?? '');
        $release_zip = (string)($rel['zipball_url'] ?? '');
        $release_tar = (string)($rel['tarball_url'] ?? '');
      }
    }

    // 表示用にまとめる
    $default_branch = $repo['default_branch'] ?? 'main';
    $html_url       = $repo['html_url'] ?? $repo_url;

    $info = [
      // リポジトリ基本
      'full_name'       => $repo['full_name'] ?? '',
      'description'     => $repo['description'] ?? '',
      'html_url'        => $html_url,
      'homepage'        => $repo['homepage'] ?? '',
      'language'        => $repo['language'] ?? '',
      'license'         => is_array($repo['license'] ?? null) ? ($repo['license']['spdx_id'] ?? $repo['license']['name'] ?? '') : '',
      'default_branch'  => $default_branch,
      'pushed_at'       => $repo['pushed_at'] ?? '',
      'updated_at'      => $repo['updated_at'] ?? '',
      'size'            => (int)($repo['size'] ?? 0),

      // カウント系
      'stars'           => (int)($repo['stargazers_count'] ?? 0),
      'watchers'        => (int)($repo['subscribers_count'] ?? 0), // 無い場合もある
      'forks'           => (int)($repo['forks_count'] ?? 0),
      'open_issues'     => (int)($repo['open_issues_count'] ?? 0),

      // タグ/リリース
      'latest_tag'           => $latest_tag,
      'latest_release_tag'   => $release_tag,
      'latest_release_date'  => $release_published_at,

      // ダウンロードリンク（リリースが無い場合はブランチZIPにフォールバック）
      'download_zip'     => $release_zip ?: trailingslashit($html_url).'archive/refs/heads/'.$default_branch.'.zip',
      'download_tar'     => $release_tar ?: '',
      'download_tag_zip' => ($latest_tag ? trailingslashit($html_url).'archive/refs/tags/'.rawurlencode($latest_tag).'.zip' : ''),
      'download_branch_zip' => trailingslashit($html_url).'archive/refs/heads/'.rawurlencode($default_branch).'.zip',
    ];

    set_transient($cache_key, $info, HOUR_IN_SECONDS);
    return $info;
  }

  /**
   * 既存メタを GitHub 情報で「空欄のみ」補完
   */
  public static function fill_meta(array $meta, array $gh){
    if (!$gh) return $meta;
    $meta['default_branch'] = $meta['default_branch'] ?: ($gh['default_branch'] ?? '');
    // version は「リリースタグ」優先 → 無ければ latest_tag
    $meta['version'] = $meta['version'] ?: ($gh['latest_release_tag'] ?: ($gh['latest_tag'] ?? ''));
    $meta['license'] = $meta['license'] ?: ($gh['license'] ?? '');
    $meta['docs_url'] = $meta['docs_url'] ?: (($gh['homepage'] ?? '') ?: ($gh['html_url'].'/blob/'.($gh['default_branch']??'main').'/README.md'));
    return $meta;
  }
}