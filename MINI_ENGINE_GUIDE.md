# Mini Engine 開發指南

> 本文件由 AI 掃描原始碼後自動生成，供後續 AI 快速了解 mini-engine 框架使用，無需重新掃描原始檔。

## 概覽

Mini Engine 是歐噴有限公司（OpenFun Ltd.）自行開發的輕量 PHP MVC framework。

- **核心理念**：只需 `include` 一個 `mini-engine.php` 即可獲得完整功能
- **無外部依賴**：不綁定第三方 library，向下相容，避免更新後失效
- **版本**：`0.1.0`（常數 `MINI_ENGINE_VERSION`）
- **授權**：BSD 3-Clause License

---

## 目錄結構（標準專案）

```
your-app/
├── mini-engine.php          # 核心框架（單一檔案）
├── init.inc.php             # 初始化，設定 include_path、載入 config
├── config.inc.php           # 環境設定（不進 git）
├── config.sample.inc.php    # 設定範本
├── index.php                # 入口，呼叫 MiniEngine::dispatch()
├── .htaccess                # Apache rewrite 規則
├── controllers/             # Controller 類別（命名：XxxController.php）
├── views/                   # View 模板（按 controller/action 放置）
│   ├── common/              # 共用 partial（header、footer 等）
│   ├── index/               # IndexController 對應的 views
│   └── error/               # ErrorController 對應的 views
├── models/                  # Model 類別（MiniEngine_Table 子類別）
├── libraries/               # Helper、第三方 library
└── static/                  # 靜態資源（CSS、JS、圖片）
```

> `src/MiniEngine/` 資料夾包含額外的選用元件（如 FormGroup），未包含在 mini-engine.php 主檔內，需另外 include。

---

## 快速開始

### 1. 建立新專案

```bash
mkdir your-app && cd your-app
wget https://raw.githubusercontent.com/openfunltd/mini-engine/main/mini-engine.php
php mini-engine.php init   # 自動建立標準目錄結構與樣板檔案
```

### 2. 設定環境變數

編輯 `config.inc.php`（從 `config.sample.inc.php` 複製）：

```php
<?php
putenv('APP_NAME=My Application');
putenv('DATABASE_URL=pgsql://user:password@localhost:5432/dbname');
putenv('SESSION_SECRET=your_random_secret');
putenv('SESSION_DOMAIN=');  // 留空則使用 HTTP_HOST
putenv('ENV=production');   // 設為 production 時關閉 debug 輸出
```

支援的 DATABASE_URL 格式：
- PostgreSQL：`pgsql://user:pass@host:5432/dbname`
- MySQL：`mysql://user:pass@host:3306/dbname`
- SQLite：`sqlite:/path/to/file.db`

### 3. `init.inc.php` 標準寫法

```php
<?php
define('MINI_ENGINE_LIBRARY', true);   // 告知 mini-engine.php 以 library 模式載入
define('MINI_ENGINE_ROOT', __DIR__);   // 設定專案根目錄
require_once(__DIR__ . '/mini-engine.php');
if (file_exists(__DIR__ . '/config.inc.php')) {
    include(__DIR__ . '/config.inc.php');
}
set_include_path(
    __DIR__ . '/libraries'
    . PATH_SEPARATOR . __DIR__ . '/models'
);
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once(__DIR__ . '/vendor/autoload.php');
}
MiniEngine::initEnv();  // 註冊 autoloader，設定 error_reporting
```

> ⚠️ 必須在 `require_once` mini-engine.php **之前**定義 `MINI_ENGINE_LIBRARY`，否則會執行 CLI 模式。

### 4. `index.php` 入口

```php
<?php
include(__DIR__ . '/init.inc.php');

MiniEngine::dispatch(function($uri) {
    // 自訂路由（可選）
    if ($uri == '/robots.txt') {
        return ['index', 'robots'];  // [controller, action]
    }
    return null;  // 回傳 null 使用預設路由規則
});
```

### 5. `.htaccess`

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [QSA,L]
```

---

## 路由機制

### 預設路由規則

URL 格式：`/{controller}/{action}/{param1}/{param2}/...`

| URL | Controller | Action | Params |
|-----|-----------|--------|--------|
| `/` | `IndexController` | `indexAction()` | `[]` |
| `/law` | `LawController` | `indexAction()` | `[]` |
| `/law/show/123` | `LawController` | `showAction('123')` | `['123']` |
| `/law/show/123/detail` | `LawController` | `showAction('123', 'detail')` | `['123', 'detail']` |

- Controller 名稱：`foo_bar` → `FooBarController`（底線轉 CamelCase）
- 靜態資源：URL 以 `/static` 開頭且檔案存在時，直接回傳檔案（自動帶 Content-Type）

### 自訂路由

在 `MiniEngine::dispatch()` 傳入 callback，回傳 `[controller, action, params]` 陣列，或回傳 `null` 使用預設規則：

```php
MiniEngine::dispatch(function($uri) {
    if (preg_match('#^/api/v1/users/(\d+)$#', $uri, $m)) {
        return ['api', 'user', [$m[1]]];
    }
    return null;
});
```

---

## MVC 架構

### Controller

**命名規則**：`controllers/XxxController.php`，類別名稱 `XxxController`，繼承 `MiniEngine_Controller`。

```php
<?php
class ProductController extends MiniEngine_Controller
{
    public function init()
    {
        // 每個 action 執行前都會呼叫，可做權限驗證
    }

    public function listAction()
    {
        // 設定 view 資料
        $this->view->products = Product::search([])->toArray();
    }

    public function showAction($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->notfound("Product not found");
        }
        $this->view->product = $product;
    }
}
```

**Controller 可用方法**：

| 方法 | 說明 |
|------|------|
| `$this->view->xxx = $val` | 傳遞資料到 view |
| `$this->noview()` | 不渲染 view（拋出 `MiniEngine_Controller_NoView`） |
| `$this->redirect($uri, $code)` | HTTP 重新導向（預設 302） |
| `$this->json($data)` | 輸出 JSON（自動帶 Content-Type header） |
| `$this->cors_json($data)` | 輸出 JSON + CORS header（`Access-Control-Allow-Origin: *`） |
| `$this->alert($msg, $uri)` | 輸出 JS alert()，可選擇性跳轉 |
| `$this->notfound($msg)` | 拋出 `MiniEngine_Controller_NotFound`（404） |
| `$this->init_csrf()` | 初始化 CSRF token，存到 `$this->view->csrf_token` |

### View

**對應規則**：`views/{controller}/{action}.php`

View 檔案中的 `$this` 是 `MiniEngine_Controller_ViewObject` 物件，可直接讀寫 Controller 設定的資料：

```php
<!-- views/product/list.php -->
<?= $this->partial('common/header') ?>
<ul>
<?php foreach ($this->products as $p) { ?>
    <li><?= $this->escape($p['name']) ?></li>
<?php } ?>
</ul>
<?= $this->partial('common/footer') ?>
```

**ViewObject 可用方法**：

| 方法 | 說明 |
|------|------|
| `$this->escape($str)` | HTML encode（`htmlspecialchars` UTF-8） |
| `$this->partial($file, $data)` | 引入子 view；`$file` 可為相對於 `views/` 的路徑（不含 `.php`）或絕對路徑；`$data` 可選，傳入時會暫時替換目前的資料 |
| `$this->if($cond, $true, $false)` | 三元運算 helper，方便在 HTML 中內嵌 |
| `$this->yield($name)` | 輸出 named block 的內容 |
| `$this->yield_start($name)` | 開始捕捉 named block |
| `$this->yield_end()` | 結束捕捉 |
| `$this->yield_set($name, $val)` | 直接設定 named block 內容 |

#### yield 用法（Layout 機制）

**Layout 檔案**（如 `views/layout/app.php`）：
```php
<!DOCTYPE html>
<html>
<head>
    <?= $this->yield('head-load') ?>
</head>
<body>
    <?= $this->yield('content') ?>
</body>
</html>
```

**頁面 View**（如 `views/index/index.php`）：
```php
<?php
$this->yield_start('content');
?>
<h1>Hello World</h1>
<?php
$this->yield_end();
?>
<?= $this->partial('layout/app') ?>
```

### Error Controller

必須建立 `controllers/ErrorController.php`，會在例外發生時自動被呼叫：

```php
<?php
class ErrorController extends MiniEngine_Controller
{
    public function errorAction($error)
    {
        MiniEngine::defaultErrorHandler($error);
    }
}
```

`defaultErrorHandler` 行為：
- **production 環境**：`MiniEngine_Controller_NotFound` → 輸出 404；其他例外 → 輸出 500（無詳細資訊）
- **非 production 環境**：輸出詳細的錯誤訊息與堆疊

---

## Database（MiniEngine_Table）

### 定義 Model

```php
<?php
// models/User.php
class User extends MiniEngine_Table
{
    public function init()
    {
        // 欄位定義
        $this->_columns['id']         = ['type' => 'serial'];
        $this->_columns['email']      = ['type' => 'text'];
        $this->_columns['name']       = ['type' => 'text'];
        $this->_columns['config']     = ['type' => 'jsonb'];
        $this->_columns['created_at'] = ['type' => 'int'];

        // 選填：自訂資料表名稱（預設由類別名稱轉換：User → user, MeetingMember → meeting_member）
        // $this->_name = 'custom_table_name';

        // 選填：主鍵（預設為第一個欄位）
        // $this->_primary_keys = ['id'];

        // 選填：複合主鍵
        // $this->_primary_keys = ['user_id', 'token'];

        // 索引
        $this->_indexes['user_email'] = ['columns' => ['email'], 'unique' => true];
        $this->_indexes['created_at'] = ['columns' => ['created_at']];

        // 選填：使用非 default 的資料庫群組
        // $this->_db_group = 'secondary';

        // 選填：關聯定義
        $this->_relations['tokens'] = [
            'rel' => 'has_many',
            'type' => 'Token',
            'foreign_key' => 'user_id',
        ];
    }
}
```

**支援的欄位型別**：
`serial`, `integer`/`int`, `bigint`, `double`/`float`/`real`, `bool`/`boolean`, `text`, `jsonb`, `uuid`, `geometry`, `varchar`, `char`

### 建立資料表

```php
User::createTable();  // 自動建立資料表與索引
```

### CRUD 操作

```php
// 新增
$user = User::insert([
    'email' => 'test@example.com',
    'name' => '測試',
    'config' => ['role' => 'admin'],  // jsonb 自動 json_encode
]);

// 查詢單筆（by primary key）
$user = User::find(1);

// 查詢單筆（by 其他欄位）
$user = User::find_by_email('test@example.com');
$user = User::find_by_email_and_name('test@example.com', '測試');

// 查詢多筆（Rowset，lazy 查詢）
$users = User::search(['name' => '測試'], '*');

// Rowset 操作（鏈式）
$users = User::search(['name' => '測試'], '*')
    ->order(['created_at' => 'desc'])
    ->limit(10)
    ->offset(20);

// 使用 IN 查詢
$users = User::search(['id' => [1, 2, 3]], '*');

// 純 SQL where 字串（危險！需自行確保安全）
$users = User::search(['created_at > 1700000000'], '*');

// 計算筆數
$count = User::search(['name' => '測試'])->count();

// 取第一筆
$user = User::search(['email' => 'test@example.com'], '*')->first();

// 轉換成陣列
$arr = User::search([], '*')->toArray();         // 全欄位陣列
$emails = User::search([], '*')->toArray('email'); // 單欄位陣列

// 更新
$user->name = '新名稱';
$user->save();  // 只 UPDATE 有變更的欄位

$user->update(['name' => '新名稱', 'email' => 'new@example.com']);  // 等同上面兩步

// 刪除
$user->delete();

// 讀取資料（欄位名稱）
echo $user->name;
echo $user->config->role;  // jsonb 自動 json_decode

// 轉成陣列
$arr = $user->toArray();
```

### 關聯（Relations）

```php
// has_many
foreach ($user->tokens as $token) {  // 回傳 Rowset
    echo $token->token;
}

// has_one（透過 foreign_key 指向其他 table 的 primary key）
// $this->_relations['user'] = ['rel' => 'has_one', 'type' => 'User', 'foreign_key' => 'user_id'];
$user = $token->user;
```

### 批次新增

```php
foreach ($records as $record) {
    User::bulkInsert($record);  // 累積到 500 筆自動 commit
}
User::bulkCommit();  // 強制 flush 剩餘資料

// 選項
User::bulkInsert($record, ['ignore' => true]);   // INSERT OR IGNORE
User::bulkInsert($record, ['bulk_count' => 100]); // 自訂批次大小
User::bulkCommit(['ignore' => true]);
```

### 自訂 SQL 查詢（低階）

```php
// 使用 MiniEngine::dbExecute（default DB group）
$stmt = MiniEngine::dbExecute(
    "SELECT * FROM ::table WHERE ::col = :val",
    ['::table' => 'users', '::col' => 'email', ':val' => 'test@example.com']
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 取得 PDO 物件
$pdo = MiniEngine::getDb();           // default group，回傳 PDO
$db  = MiniEngine::getDb('default', 'MiniEngine_Db');  // 回傳 MiniEngine_Db

// 多資料庫群組
MiniEngine::setDbURL('secondary', 'pgsql://user:pass@host/db2');
$pdo2 = MiniEngine::getDb('secondary');
```

**SQL 參數規則**：
- `:param` — 值參數（PDO prepare）
- `::param` — 識別符（自動依 driver 加上 `"` 或 `` ` ``，防止 SQL injection）

---

## Session 管理

Session 以 **cookie（client-side，HMAC-SHA256 簽名）** 實作，不依賴 PHP session 機制。

```php
// 需設定環境變數 SESSION_SECRET
putenv('SESSION_SECRET=your_random_secret_here');

// 讀取
$user_id = MiniEngine::getSession('user_id');

// 寫入（自動寫入 cookie）
MiniEngine::setSession('user_id', 123);

// 刪除
MiniEngine::deleteSession('user_id');
```

- Cookie 名稱：PHP 預設 session 名稱（`PHPSESSID`）
- 有效期：30 天
- 安全旗標：`Secure: true`（僅 HTTPS）
- Domain：`SESSION_DOMAIN` 環境變數，若未設定則用 `HTTP_HOST`

---

## HTTP 工具

```php
// GET
$response = MiniEngine::http('https://api.example.com/data');

// POST with JSON body
$response = MiniEngine::http(
    'https://api.example.com/data',
    'POST',
    json_encode(['key' => 'value']),
    ['Content-Type: application/json', 'Authorization: Bearer token']
);
```

- Timeout：30 秒
- 非 2xx 狀態碼會拋出例外

---

## CLI 工具

```bash
# 初始化新專案
php mini-engine.php init

# 更新 mini-engine.php 到最新版本（從 GitHub 下載）
php mini-engine.php update

# 互動式 PHP REPL（需先有 init.inc.php）
php mini-engine.php prompt
# >> User::find(1)->name
# >> MiniEngine::dbExecute("SELECT COUNT(*) FROM users")->fetchColumn()
```

---

## FormGroup（選用元件）

位於 `src/MiniEngine/FormGroup.php`，需手動 include。提供 form 欄位定義、資料驗證、Bootstrap 5 HTML 渲染。

### 定義 Form

```php
<?php
// libraries/UserForm.php
class UserForm extends MiniEngine_FormGroup
{
    public function init()
    {
        $this->email    = ['type' => 'email', 'label' => 'Email', 'required' => true];
        $this->name     = ['type' => 'text',  'label' => '姓名',   'required' => true];
        $this->role     = ['type' => 'select', 'label' => '角色',
                            'options' => ['admin' => '管理員', 'user' => '一般使用者']];
        $this->bio      = ['type' => 'textarea', 'label' => '簡介', 'placeholder' => '請輸入...'];
        $this->agree    = ['type' => 'checkbox', 'options' => ['1' => '我同意條款']];
    }
}
```

### 在 Controller 中使用

```php
public function editAction($id)
{
    $user = User::find($id);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $errors = [];
        $data = UserForm::checkData($_POST, $errors);
        if (empty($errors)) {
            $user->update($data);
            return $this->redirect('/users');
        }
        UserForm::setErrors($errors);
    }

    UserForm::setData($user->toArray());
    $this->view->form = 'UserForm';  // 傳類別名稱到 view
}
```

### 在 View 中渲染

```php
<?= UserForm::e('email') ?>     <!-- 輸出 Bootstrap 5 form-group HTML -->
<?= UserForm::e('name') ?>
<?= UserForm::e('role') ?>
```

### 子表單（child_form）

```php
class AddressForm extends MiniEngine_FormGroup
{
    public function init()
    {
        $this->city    = ['type' => 'text', 'label' => '城市'];
        $this->zipcode = ['type' => 'text', 'label' => '郵遞區號'];
    }
}

class UserForm extends MiniEngine_FormGroup
{
    public function init()
    {
        $this->name = ['type' => 'text', 'label' => '姓名'];
        self::child_form('AddressForm', ['group' => 'address']);
        // 產生 address[city], address[zipcode] 欄位
    }
}
```

---

## Autoload 機制

`MiniEngine::initEnv()` 會呼叫 `spl_autoload_register`，規則：

```
類別名稱 → 底線/反斜線 轉成路徑分隔符，加上 .php 副檔名
```

| 類別名稱 | 載入路徑 |
|---------|---------|
| `UserHelper` | `libraries/UserHelper.php` |
| `MiniEngine_FormGroup` | `libraries/MiniEngine/FormGroup.php` |
| `User` | `models/User.php` |

搜尋順序依 `set_include_path` 設定的順序。

---

## 實際專案案例

### lawtrace（立法足跡）

- **用途**：立法院法律沿革查詢
- **特色**：無 database，純 API 呼叫（`LYAPI::apiQuery()`）
- **路由**：預設規則 + robots.txt 自訂
- **libraries**：大量 Helper 類別（`LawVersionHelper`, `DiffHelper`, `PolicyHelper` 等）
- **models**：無（使用外部 API 取代）

```php
// lawtrace/init.inc.php 設定額外環境變數
putenv('LYAPI_HOST=ly.govapi.tw/v2');
putenv('POLICYAPI_HOST=policy.join.govapi.tw');
```

### openfun-api（歐噴 API 後台）

- **用途**：API 使用量統計後台
- **特色**：有 database（Counter, CounterTime, Mapping, Token, User models）
- **models**：`User`, `Token`, `Counter`, `CounterTime`, `Mapping`
- **特殊用法**：`AdminController` 直接解析 URI 處理多層路徑

### dataly-v2（立院統合資料網）

- **用途**：立院資料視覺化平台
- **特色**：使用 `yield` 實作 layout（`views/layout/app.php`）
- **Layout 用法**：view 中定義 `content` block，再 partial layout

```php
// views/collection/list.php 範例概念
<?php
$this->yield_start('content');
// ... 頁面內容
$this->yield_end();
?>
<?= $this->partial('layout/app') ?>
```

---

## 環境變數一覽

| 變數名稱 | 必填 | 說明 |
|---------|------|------|
| `SESSION_SECRET` | ✅ | Session cookie 簽名密鑰 |
| `DATABASE_URL` | 有用 DB 時 | 預設資料庫連線字串 |
| `ENV` | 建議 | 設為 `production` 關閉 debug 輸出 |
| `APP_NAME` | 建議 | 應用程式名稱 |
| `SESSION_DOMAIN` | ❌ | Session cookie domain，留空用 HTTP_HOST |

---

## 常見注意事項

1. **`MINI_ENGINE_LIBRARY` 常數**：必須在 `require mini-engine.php` 前定義，否則會觸發 CLI 模式
2. **`MINI_ENGINE_ROOT` 常數**：若未定義，預設為 `mini-engine.php` 所在目錄（`__DIR__`）
3. **Session 安全性**：cookie 只設 `Secure: true`，必須使用 HTTPS
4. **SQL injection 防護**：資料表/欄位名稱用 `::param`（自動 escape），值用 `:param`（PDO prepare）
5. **重複主鍵**：`insert()` 遇重複時拋出 `MiniEngine_Table_DuplicateException`
6. **Rowset 是 lazy 的**：`search()` 不立即查詢，直到 `foreach`、`count()`、`first()`、`toArray()` 才執行
7. **Geometry 欄位**：自動處理 PostGIS `ST_GeomFromGeoJSON` / `ST_AsGeoJSON`
8. **`lazy` 欄位**：Column 設定 `'lazy' => true` 時，預設查詢不選取，需明確指定 `'*'` flag
9. **production 環境 SQL log**：`ENV=production` 時關閉 SQL log，非 production 時每次查詢都寫 `error_log`
10. **view 中的 `$this`**：永遠是 `MiniEngine_Controller_ViewObject`，Controller 設定的 `$this->view->xxx` 在 view 中用 `$this->xxx` 讀取
