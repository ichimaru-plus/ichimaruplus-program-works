<?php
if (!defined('ABSPATH')) exit;

/**
 * 管理画面（設定 → Program Works）
 * - デザインプリセット（flat / wire / brand）
 * - アクセント色（#hex）
 * - アニメーション/影の無効化（完全フラット化）
 */
class ICPW_PW_Admin {

  /** メニュー登録：設定配下に追加 */
  public static function menu() {
    add_options_page(
      'Program Works 設定',
      'Program Works',
      'manage_options',
      'icpw_pw_settings',
      [__CLASS__, 'settings_page']
    );
  }

  /** 設定項目の登録 */
  public static function register_settings() {

    /** -------------------------
     * セクション：基本説明
     * ------------------------ */
    add_settings_section(
      'icpw_section_general',
      '基本設定',
      function () {
        echo '<p>プログラム作品（カスタム投稿 <code>icpw_prog</code>）の表示や見た目を調整します。<br>作品の単一ページでは、本文の直後に情報カードが自動で表示されます。</p>';
      },
      'icpw_pw_settings'
    );

    /** -------------------------
     * デザイン設定
     * ------------------------ */

    // スタイルプリセット
    register_setting('icpw_pw_settings', 'icpw_style', [
      'type'              => 'string',
      'default'           => 'flat',  // 初期値：Flat
      'sanitize_callback' => 'sanitize_text_field',
    ]);

    // アクセント色
    register_setting('icpw_pw_settings', 'icpw_accent', [
      'type'              => 'string',
      'default'           => '#1f2937',
      'sanitize_callback' => 'sanitize_hex_color',
    ]);

    // 動き/影 無効化（完全フラット化）：true=無効化
    register_setting('icpw_pw_settings', 'icpw_disable_motion', [
      'type'              => 'boolean',
      'default'           => true,   // 既定で「無効化（=フラット）」にする
      'sanitize_callback' => function($v){ return (bool)$v; },
    ]);

    add_settings_section(
      'icpw_section_style',
      'デザイン',
      function () {
        echo '<p>カードの見た目を切替えます。<br>
        ・<strong>Flat</strong>：標準 / <strong>Wire</strong>：線画・モノクロ / <strong>Brand</strong>：サイトカラー強調</p>';
      },
      'icpw_pw_settings'
    );

    // フィールド：スタイルプリセット
    add_settings_field(
      'icpw_style',
      'スタイルプリセット',
      function () {
        $v = get_option('icpw_style', 'flat'); ?>
        <select name="icpw_style">
          <option value="flat"  <?php selected($v, 'flat');  ?>>Flat（標準）</option>
          <option value="wire"  <?php selected($v, 'wire');  ?>>Wire（線画・モノクロ）</option>
          <option value="brand" <?php selected($v, 'brand'); ?>>Brand（アクセント色）</option>
        </select>
        <?php
      },
      'icpw_pw_settings',
      'icpw_section_style'
    );

    // フィールド：アクセント色
    add_settings_field(
      'icpw_accent',
      'アクセント色',
      function () {
        $v = get_option('icpw_accent', '#1f2937');
        echo '<input type="text" name="icpw_accent" value="' . esc_attr($v) . '" class="regular-text" placeholder="#1f2937">';
        echo '<p class="description">Brand/Flat で使用する色（例: <code>#ff6600</code>）。</p>';
      },
      'icpw_pw_settings',
      'icpw_section_style'
    );

    // フィールド：動き/影 無効化
    add_settings_field(
      'icpw_disable_motion',
      'アニメーション・影を無効化（完全フラット）',
      function () {
        $checked = get_option('icpw_disable_motion', true); ?>
        <label>
          <input type="checkbox" name="icpw_disable_motion" value="1" <?php checked($checked, true); ?>>
          有効（hover時のアニメーションや影を一切使わない）
        </label>
        <p class="description">チェックONで、カードとボタンの影・アニメーションを全て止めます。</p>
        <?php
      },
      'icpw_pw_settings',
      'icpw_section_style'
    );
  }

  /** 設定ページ本体 */
  public static function settings_page() {
    if (!current_user_can('manage_options')) return; ?>
    <div class="wrap">
      <h1>Ichimaru+ Program Works 設定</h1>

      <form method="post" action="options.php">
        <?php
          settings_fields('icpw_pw_settings');
          do_settings_sections('icpw_pw_settings');
          submit_button();
        ?>
      </form>

      <hr>
      <h2>使い方</h2>
      <p>
        管理画面の「<strong>Program Works</strong>」から作品を追加します。<br>
        作品の単一ページでは、<strong>タイトル/本文はテーマのまま</strong>に表示され、<strong>本文の直後</strong>に本プラグインのカードが追加されます。
      </p>
      <ul>
        <li><strong>スタイルプリセット</strong>：Flat / Wire / Brand を選択</li>
        <li><strong>アクセント色</strong>：サイトの基調色を #hex で指定</li>
        <li><strong>アニメーション・影を無効化</strong>：完全フラットにしたい場合はON</li>
      </ul>
      <p class="description">
        表示が変わらない場合は、キャッシュプラグインのキャッシュ削除・ブラウザのハードリロード（⌘+Shift+R）をお試しください。
      </p>
    </div>
    <?php
  }
}