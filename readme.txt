=== Ichimaru+ Program Works ===
Contributors: ichimaruplus
Tags: program, works, showcase, github, csv, rest-api, auto-update
Requires at least: 6.0
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

プログラム作品（プラグイン / アプリ / ツール）を登録・公開できる WordPress プラグイン。
GitHub 連携、CSV入出力、REST API、自動更新、ショートコード、色設定、コピー用ボタン対応。

== Description ==

Ichimaru+ Program Works は、プログラム系の制作物を WordPress 上で一覧・紹介するためのプラグインです。  
GitHub からリポジトリ情報を取得して表示したり、作品ごとのカードを自動生成して公開できます。  

**主な機能:**
- プログラム系作品の投稿・一覧表示
- GitHub リポジトリからの情報取得
- CSV インポート / エクスポート
- REST API 連携
- GitHub Releases による自動更新通知
- 色や角丸・影などデザイン調整
- コード部分に「コピー」ボタンを自動追加

== Installation ==

1. [Releases](https://github.com/ichimaru-plus/ichimaruplus-program-works/releases) から ZIP をダウンロード  
2. WordPress 管理画面 → プラグイン → 新規追加 → アップロード  
3. 有効化して利用開始  

== Frequently Asked Questions ==

= Q. バージョンアップはどうすればいいですか？ =
A. GitHub 上で新しい Release を公開すると、WordPress 側に更新通知が表示されます。  
　管理画面からクリック一つで更新できます。

= Q. デザインを調整できますか？ =
A. 設定画面からカードの角丸や影の濃さを調整可能です。

== Screenshots ==

1. プログラム作品の一覧カード表示
2. 投稿編集画面（GitHub 情報の入力フィールド）
3. 設定画面（デザインや色の調整）

== Changelog ==

= 1.1.4 =
* カードUIを日本語・モノクロアイコンで刷新
* タイトルを省略せず全文表示
* ボタン文言を日本語化、コピーUIを改善

= 1.1.3 =
* カードの横幅を 100% に変更（レスポンシブ対応を改善）

= 1.1.2 =
* バージョン番号の一元化（ヘッダーから自動参照）
* 設定画面のフック修正による致命的エラー解消
* デザイン設定の安定化（角丸・影の調整）

= 1.1.1 =
* デザイン設定ページを追加
* GitHub 情報カードの表示を改善
* コードブロックに「コピー」ボタンを追加

= 1.1.0 =
* 初期リリース版

== Upgrade Notice ==

= 1.1.4 =
カードデザインを大幅に刷新しました。更新を推奨します。