<?php
if (!defined('ABSPATH')) exit;

class ICPW_PW_Admin {

  public static function init(){
    add_action('admin_menu',[__CLASS__,'menu']);
    add_action('admin_init',[__CLASS__,'register_settings']);
  }

  public static function menu(){
    add_options_page(
      'Program Works 設定',
      'Program Works',
      'manage_options',
      'icpw_pw_settings',
      [__CLASS__,'settings_page']
    );
  }

  public static function register_settings(){
    register_setting('icpw_pw_settings','icpw_pw_settings',[__CLASS__,'sanitize']);

    add_settings_section('icpw_pw_main','基本設定',null,'icpw_pw_settings');

    add_settings_field('accent_color','アクセントカラー',[__CLASS__,'field_color'],'icpw_pw_settings','icpw_pw_main');
    add_settings_field('show_github','GitHub情報を表示',[__CLASS__,'field_showgithub'],'icpw_pw_settings','icpw_pw_main');
    add_settings_field('enable_copy','コピー用ボタンを表示',[__CLASS__,'field_copy'],'icpw_pw_settings','icpw_pw_main');

    // 追加：デザイン設定
    add_settings_field('border_radius','カードの角丸 (px)',[__CLASS__,'field_radius'],'icpw_pw_settings','icpw_pw_main');
    add_settings_field('shadow_strength','影の濃さ',[__CLASS__,'field_shadow'],'icpw_pw_settings','icpw_pw_main');
  }

  public static function sanitize($in){
    $out = [];
    $out['accent_color'] = preg_match('/^#[0-9a-fA-F]{6}$/',$in['accent_color']??'') ? $in['accent_color'] : '#6366f1';
    $out['show_github']  = empty($in['show_github']) ? 0 : 1;
    $out['enable_copy']  = empty($in['enable_copy']) ? 0 : 1;

    // 角丸
    $radius = isset($in['border_radius']) ? intval($in['border_radius']) : 6;
    if ($radius<0) $radius=0;
    if ($radius>30) $radius=30;
    $out['border_radius']=$radius;

    // 影
    $allowed=['none','soft','medium','strong'];
    $shadow=$in['shadow_strength']??'soft';
    $out['shadow_strength']=in_array($shadow,$allowed,true)?$shadow:'soft';

    return $out;
  }

  public static function get_settings(){
    $def=[
      'accent_color'=>'#6366f1',
      'show_github'=>1,
      'enable_copy'=>1,
      'border_radius'=>6,
      'shadow_strength'=>'soft'
    ];
    return wp_parse_args(get_option('icpw_pw_settings',[]),$def);
  }

  public static function settings_page(){
    ?>
    <div class="wrap">
      <h1>Program Works 設定</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('icpw_pw_settings');
        do_settings_sections('icpw_pw_settings');
        submit_button();
        ?>
      </form>
    </div>
    <?php
  }

  public static function field_color(){
    $opt=self::get_settings();
    echo '<input type="text" name="icpw_pw_settings[accent_color]" value="'.esc_attr($opt['accent_color']).'" class="regular-text" />';
  }

  public static function field_showgithub(){
    $opt=self::get_settings();
    echo '<input type="checkbox" name="icpw_pw_settings[show_github]" value="1" '.checked(1,$opt['show_github'],false).' />';
  }

  public static function field_copy(){
    $opt=self::get_settings();
    echo '<input type="checkbox" name="icpw_pw_settings[enable_copy]" value="1" '.checked(1,$opt['enable_copy'],false).' />';
  }

  public static function field_radius(){
    $opt=self::get_settings();
    echo '<input type="number" min="0" max="30" step="1" name="icpw_pw_settings[border_radius]" value="'.esc_attr($opt['border_radius']).'"> px';
  }

  public static function field_shadow(){
    $opt=self::get_settings();
    $val=$opt['shadow_strength'];
    $opts=['none'=>'なし','soft'=>'やわらかめ','medium'=>'普通','strong'=>'強め'];
    echo '<select name="icpw_pw_settings[shadow_strength]">';
    foreach($opts as $k=>$label){
      echo '<option value="'.$k.'" '.selected($val,$k,false).'>'.esc_html($label).'</option>';
    }
    echo '</select>';
  }
}