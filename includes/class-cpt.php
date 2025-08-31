<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_CPT {
  public static function register(){
    register_post_type('icpw_prog', [
      'label'        => __('プログラム作品','ichimaruplus-pw'),
      'public'       => true,
      'show_in_rest' => true,
      'menu_position'=> 22,
      'menu_icon'    => 'dashicons-admin-tools',
      'supports'     => ['title','editor','thumbnail','excerpt'],
      'rewrite'      => ['slug'=>'programs'],
      'has_archive'  => true,
    ]);
  }
}
