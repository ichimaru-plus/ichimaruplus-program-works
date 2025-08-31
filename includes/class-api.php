<?php
if (!defined('ABSPATH')) { exit; }

class ICPW_PW_API {
  public static function register_routes(){
    register_rest_route('icpw/v1','/works', [
      ['methods'=>'GET', 'callback'=>[__CLASS__,'list'],            'permission_callback'=>'__return_true'],
      ['methods'=>'POST','callback'=>[__CLASS__,'create_or_update'],'permission_callback'=>function(){ return current_user_can('edit_posts'); }],
      ['methods'=>'PUT', 'callback'=>[__CLASS__,'create_or_update'],'permission_callback'=>function(){ return current_user_can('edit_posts'); }],
    ]);
    register_rest_route('icpw/v1','/works/(?P<id>\\d+)', [
      ['methods'=>'GET',   'callback'=>[__CLASS__,'detail'], 'permission_callback'=>'__return_true'],
      ['methods'=>'DELETE','callback'=>[__CLASS__,'delete'], 'permission_callback'=>function(){ return current_user_can('delete_posts'); }],
    ]);
  }

  public static function list(WP_REST_Request $req){
    $args = [
      'post_type'      => 'icpw_prog',
      'posts_per_page' => min(100, max(1, (int)($req->get_param('per_page') ?: 20))),
      'paged'          => max(1, (int)($req->get_param('page') ?: 1)),
      'orderby'        => sanitize_text_field($req->get_param('orderby') ?: 'date'),
      'order'          => sanitize_text_field($req->get_param('order') ?: 'DESC'),
      's'              => sanitize_text_field($req->get_param('s') ?: ''),
    ];
    $q = new WP_Query($args);
    $items = [];
    foreach ($q->posts as $p){ $items[] = self::format_post($p, $req, false); }
    return new WP_REST_Response([
      'total'=>(int)$q->found_posts,
      'page' =>(int)$args['paged'],
      'items'=>apply_filters('icpw_pw_api_list_items',$items,$req),
    ], 200);
  }

  public static function detail(WP_REST_Request $req){
    $p = get_post((int)$req['id']);
    if (!$p || $p->post_type!=='icpw_prog' || $p->post_status==='trash'){
      return new WP_Error('not_found','not found',['status'=>404]);
    }
    $res = self::format_post($p, $req, true);
    return new WP_REST_Response(apply_filters('icpw_pw_api_detail',$res,$p,$req), 200);
  }

  public static function create_or_update(WP_REST_Request $req){
    $title = sanitize_text_field($req->get_param('title') ?: '');
    if ($title===''){ return new WP_Error('invalid','title required',['status'=>400]); }

    $post_id = (int)$req->get_param('id') ?: 0;
    $data = [
      'post_type'   => 'icpw_prog',
      'post_title'  => $title,
      'post_status' => 'publish',
      'post_content'=> wp_kses_post($req->get_param('description') ?: ''),
      'post_excerpt'=> sanitize_textarea_field($req->get_param('excerpt') ?: ''),
    ];
    $post_id = $post_id ? wp_update_post(['ID'=>$post_id] + $data, true) : wp_insert_post($data, true);
    if (is_wp_error($post_id)) return $post_id;

    foreach (array_keys(ICPW_PW_Meta::schema()) as $k){
      if ($req->offsetExists($k)){
        update_post_meta($post_id, "_icpw_$k", sanitize_text_field($req->get_param($k)));
      }
    }
    do_action('icpw_pw_api_saved',$post_id,$req);
    return new WP_REST_Response(['id'=>$post_id], 200);
  }

  public static function delete(WP_REST_Request $req){
    $post_id = (int)$req['id'];
    $res = wp_trash_post($post_id);
    if(!$res){ return new WP_Error('delete_failed','delete failed',['status'=>400]); }
    return new WP_REST_Response(['deleted'=>true,'id'=>$post_id], 200);
  }

  private static function format_post(WP_Post $p, WP_REST_Request $req, $with_gh=false){
    $meta = [];
    foreach (ICPW_PW_Meta::schema() as $k=>$def){ $meta[$k] = get_post_meta($p->ID, "_icpw_$k", true); }
    $gh = false;
    if ($with_gh && !empty($meta['repo_url']) && ($meta['auto_fill']!=='0')){
      $gh = ICPW_PW_GitHub::fetch($meta['repo_url']);
      $meta = ICPW_PW_GitHub::fill_meta($meta, $gh);
    }
    return [
      'id'        => (int)$p->ID,
      'title'     => get_the_title($p),
      'description'=> apply_filters('the_excerpt', $p->post_excerpt ?: wp_trim_words($p->post_content, 40)),
      'permalink' => get_permalink($p),
      'thumbnail' => get_the_post_thumbnail_url($p,'medium') ?: '',
      'meta'      => $meta,
      'github'    => $gh ?: (object)[],
    ];
  }
}
