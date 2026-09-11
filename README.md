# iijmio-usage-checker

IIJmio のギガプラン等のデータ利用量を自動巡回（クローリング）して取得・分析し、当月末のデータ消費予測や LINE アラート通知、Web ダッシュボード表示を行う Google Cloud Functions (PHP) アプリケーションです。

---

## 主な機能

- **自動クローリング**: IIJmio 会員ページへログインし、当月のクーポン残量、契約ユーザーごとの月間累計データ利用量・前日データ利用量を自動取得。
- **ブレンディング予測アルゴリズム**:
  - 当月経過日数に応じて「前月最終実績／プラン上限に基づくベースライン」「当月累計消費率」「直近（約7日間）の消費傾向」をブレンドし、精度高く月末の着地点（予想消費量）を算出。
- **LINE アラート通知**:
  - 定期通知（指定日数ごと）または当月予想消費量が契約容量の 90% を超えた場合に、LINE Bot 経由でデータ利用レポートを自動送信。
- **Web ダッシュボード (BladeOne + Tailwind CSS + Chart.js)**:
  - **メイン画面 (`/`)**: 当月の契約容量、消費実績、月末予想、残量のサマリーおよび LINE 通知プレビュー表示。
  - **日別グラフ画面 (`/daily`)**: ユーザーごとの日別消費量・累計消費量の時系列チャート。
  - **月別グラフ画面 (`/monthly`)**: 過去月ごとの消費実績比較チャート。
  - **設定画面 (`/config`)**: IIJmio ログイン情報、ユーザーごとの契約容量（2GB, 5GB, 10GB, 15GB等）、LINE アラート送信先の設定およびプレビュー確認。

---

## アーキテクチャと構成

本アプリケーションは Google Cloud Functions (PHP 8.2+) と Cloud Firestore を基盤としています。

- **エントリポイント (`index.php`)**:
  - `main_http`: Web リクエストおよび非同期 JSON API エンドポイントを処理します。
  - `main_event`: Pub/Sub トリガーによる日次定期実行（データクローリング・履歴保存・LINE通知）を処理します。

### ディレクトリ構造

```text
iijmio-usage-checker/
├── docs/                 # 設計方針ドキュメント
├── src/                  # アプリケーションソースコード
│   ├── Controllers/      # HTTPコントローラー (ConfigController)
│   ├── Handlers/         # イベントハンドラー (EventHandler)
│   ├── Services/         # 設定・履歴管理サービス (Firestore / Mock)
│   ├── Utils/            # LINE送信、ログ出力等のユーティリティ
│   ├── AppConfig.php     # 実行環境別コンフィグ設定
│   ├── Firestore.php     # Firestore クライアント初期化
│   └── IijmioUsage.php   # IIJmioクローリング・利用量集計・予測ロジック
├── tests/                # PHPUnit ユニットテスト
├── views/                # Blade テンプレートビュー
├── index.php             # Cloud Functions エントリポイント
├── composer.json         # 依存ライブラリ設定
└── phpstan.neon          # PHPStan 静的解析設定
```

---

## 環境変数設定

本アプリケーションの動作には以下の環境変数を使用します。

| 変数名 | 説明 | 設定例・備考 |
| :--- | :--- | :--- |
| `APP_ENV` | 実行環境の識別 | `production`, `test`, `local` |
| `LINE_TOKENS_N_TARGETS` | LINE 送信用の Channel Access Token と送信先 ID のマッピング (JSON) | 詳細構造は後述 |
| `ADMIN_PASSWORD` | Web 設定画面 (`/config`) の Basic 認証パスワード | ユーザー名 `admin` |
| `MOCK_FIRESTORE` | `1` に設定すると Firestore 接続を行わずローカルモックモードで動作 | ローカル開発・画面確認用 |

### `LINE_TOKENS_N_TARGETS` の形式

```json
{
  "tokens": {
    "my_bot": "YOUR_LINE_CHANNEL_ACCESS_TOKEN"
  },
  "target_ids": {
    "my_group": "YOUR_LINE_TARGET_USER_OR_GROUP_ID"
  }
}
```

---

## ローカル開発と実行方法

### 1. 依存ライブラリのインストール

```bash
composer install --ignore-platform-req=ext-grpc
```

### 2. ローカルサーバーの起動 (モックモード)

`MOCK_FIRESTORE=1` を指定してサーバーを起動することで、Firestore や IIJmio への接続なしに画面確認や開発が行えます。

```bash
MOCK_FIRESTORE=1 FUNCTION_TARGET=main_http php -S localhost:8080 vendor/google/cloud-functions-framework/router.php
```

ブラウザで `http://localhost:8080` にアクセスします。クエリパラメータ `?mock=1` を付与することでもモックモードで動作可能です。

### 3. テストと静的解析の実行

- **ユニットテスト (PHPUnit)**:
  ```bash
  ./vendor/bin/phpunit tests
  ```

- **静的解析 (PHPStan)**:
  ```bash
  ./vendor/bin/phpstan analyze
  ```

---

## 開発方針・コーディング規約

- **日付操作**: 必ず `Carbon\Carbon` を使用し、タイムゾーンは `Asia/Tokyo` とします。
- **命名規則**: PHP / JavaScript の変数・メソッド名は `camelCase`、クラス名は `PascalCase` を使用します。
- **開発ガイドライン**: 詳細は `docs/implementation_policy.md` および `AGENTS.md` を参照してください。
