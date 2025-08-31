<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_GitHub {
  public static function fetch($repo_url){
    if (!$repo_url || strpos($repo_url,'github.com')===false) return false;
    $api = rtrim(str_replace('https://github.com/', 'https://api.github.com/repos/', $repo_url), '/');
    $key = 'icpw_pw_gh_'.md5($api);
    if (($cached = get_transient($key)) !== false) return $cached;

    $args = ['timeout'=>8,'headers'=>['User-Agent'=>'WordPress; IchimaruPlus-Program-Works']];
    $res  = wp_remote_get($api, $args);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res)!==200) return false;
    $d = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($d)) return false;

    $latest = '';
    $rt = wp_remote_get(trailingslashit($api).'tags?per_page=1', $args);
    if(!is_wp_error($rt) && wp_remote_retrieve_response_code($rt)===200){
      $tags = json_decode(wp_remote_retrieve_body($rt), true);
      if (!empty($tags[0]['name'])) $latest = (string)$tags[0]['name'];
    }

    $info = [
      'full_name'      => $d['full_name'] ?? '',
      'description'    => $d['description'] ?? '',
      'html_url'       => $d['html_url'] ?? $repo_url,
      'homepage'       => $d['homepage'] ?? '',
      'stars'          => (int)($d['stargazers_count'] ?? 0),
      'forks'          => (int)($d['forks_count'] ?? 0),
      'open_issues'    => (int)($d['open_issues_count'] ?? 0),
      'language'       => $d['language'] ?? '',
      'license'        => is_array($d['license'] ?? null) ? ($d['license']['spdx_id'] ?? $d['license']['name'] ?? '') : '',
      'default_branch' => $d['default_branch'] ?? 'main',
      'latest_tag'     => $latest,
    ];
    set_transient($key, $info, HOUR_IN_SECONDS);
    return $info;
  }

  public static function fill_meta(array $meta, array $gh){
    if (!$gh) return $meta;
    $meta['default_branch'] = $meta['default_branch'] ?: ($gh['default_branch'] ?? '');
    $meta['version']        = $meta['version']        ?: ($gh['latest_tag']     ?? '');
    $meta['license']        = $meta['license']        ?: ($gh['license']        ?? '');
    $meta['docs_url']       = $meta['docs_url']       ?: (($gh['homepage'] ?? '') ?: ($gh['html_url'].'/blob/'.($gh['default_branch']??'main').'/README.md'));
    return $meta;
  }
}
