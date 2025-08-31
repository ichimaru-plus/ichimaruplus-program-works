<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_Admin {
  public static function register_settings_page(){
    add_options_page('Program Works 設定','Program Works 設定','manage_options','icpw-pw-settings',[__CLASS__,'render_settings']);
  }
  public static function register_settings(){
    register_setting('icpw_pw_settings','icpw_pw_settings',[__CLASS__,'sanitize']);
    add_settings_section('icpw_pw_main','表示・動作設定',function(){
      echo '<p class="description">フロント表示の色や、GitHub情報の表示/コピー用ボタンの有効化を設定します。</p>';
    },'icpw_pw_settings');
    add_settings_field('accent_color','アクセントカラー（HEX）',[__CLASS__,'field_color'],'icpw_pw_settings','icpw_pw_main');
    add_settings_field('show_github','一覧カードにGitHub情報を表示',[__CLASS__,'field_github'],'icpw_pw_settings','icpw_pw_main');
    add_settings_field('enable_copy','コピー用ボタンを表示',[__CLASS__,'field_copy'],'icpw_pw_settings','icpw_pw_main');
  }
  public static function get_settings(){
    $def = ['accent_color'=>'#6366f1','show_github'=>1,'enable_copy'=>1];
    return wp_parse_args(get_option('icpw_pw_settings',[]), $def);
  }
  public static function sanitize($in){
    $out = [];
    $out['accent_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $in['accent_color'] ?? '') ? $in['accent_color'] : '#6366f1';
    $out['show_github']  = empty($in['show_github']) ? 0 : 1;
    $out['enable_copy']  = empty($in['enable_copy']) ? 0 : 1;
    return $out;
  }
  public static function field_color(){
    $opt = self::get_settings();
    echo '<input type="text" name="icpw_pw_settings[accent_color]" value="'.esc_attr($opt['accent_color']).'" class="regular-text" placeholder="#6366f1">';
  }
  public static function field_github(){
    $opt = self::get_settings();
    echo '<label><input type="checkbox" name="icpw_pw_settings[show_github]" value="1" '.checked(1,$opt['show_github'],false).'> 有効</label>';
  }
  public static function field_copy(){
    $opt = self::get_settings();
    echo '<label><input type="checkbox" name="icpw_pw_settings[enable_copy]" value="1" '.checked(1,$opt['enable_copy'],false).'> 有効</label>';
  }
  public static function render_settings(){
    echo '<div class="wrap"><h1>Program Works 設定</h1><p>メニュー：設定 → Program Works 設定</p>';
    echo '<form method="post" action="options.php">';
    settings_fields('icpw_pw_settings');
    do_settings_sections('icpw_pw_settings');
    submit_button();
    echo '</form></div>';
  }
}
