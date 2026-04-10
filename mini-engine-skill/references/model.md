# 建立與操作 Model

## 建立 Model

檔案放 `models/Xxx.php`，繼承 `MiniEngine_Table`：

```php
<?php

class User extends MiniEngine_Table
{
    public function init()
    {
        // 欄位定義（第一個欄位預設為主鍵）
        $this->_columns['id']         = ['type' => 'serial'];
        $this->_columns['email']      = ['type' => 'text'];
        $this->_columns['name']       = ['type' => 'text'];
        $this->_columns['org_id']     = ['type' => 'bigint'];
        $this->_columns['permission'] = ['type' => 'bigint'];
        $this->_columns['data']       = ['type' => 'jsonb'];
        $this->_columns['created_at'] = ['type' => 'int'];

        // 索引（命名慣例：{table}_{column}）
        $this->_indexes['user_email'] = ['columns' => ['email'], 'unique' => true];
        $this->_indexes['user_org']   = ['columns' => ['org_id']];

        // 關聯
        $this->_relations['organization'] = [
            'rel' => 'has_one',
            'type' => 'Organization',
            'foreign_key' => 'org_id',
        ];
        $this->_relations['tokens'] = [
            'rel' => 'has_many',
            'type' => 'Token',
            'foreign_key' => 'user_id',
        ];
    }
}
```

### 支援的欄位型別

`serial`, `integer`/`int`, `bigint`, `double`/`float`/`real`, `bool`/`boolean`, `text`, `jsonb`, `uuid`, `geometry`, `varchar`, `char`

### 建立資料表

```php
User::createTable();  // 自動建立資料表與索引
```

批次建立所有 model 的資料表：
```php
foreach (glob(__DIR__ . "/../models/*.php") as $file) {
    $content = file_get_contents($file);
    if (preg_match('#class (.*) extends MiniEngine_Table$#m', $content, $matches)) {
        try { call_user_func([$matches[1], 'createTable']); } catch (Exception $e) {}
    }
}
```

## CRUD 操作

```php
// 新增
$user = User::insert([
    'email' => 'test@example.com',
    'name' => '測試',
    'data' => ['role' => 'admin'],  // jsonb 自動 json_encode
    'created_at' => time(),
]);

// 查詢單筆
$user = User::find(1);                           // by 主鍵
$user = User::find_by_email('test@example.com'); // by 任意欄位
$user = User::find_by_email_and_name('a@b.c', '測試'); // 複合條件

// 查詢多筆（Rowset，lazy）
$users = User::search(['org_id' => 1], '*')      // 注意要傳 '*' 才有全欄位
    ->order(['created_at' => 'DESC'])
    ->limit(20)
    ->offset(0);

// search 條件格式
User::search(['org_id' => 1], '*');              // 等值
User::search(['id' => [1, 2, 3]], '*');          // IN
User::search(["created_at > " . time()], '*');   // 原始 SQL（小心 injection）

// Rowset 消費
$users->count();                  // SELECT COUNT(*)
$users->first();                  // 第一筆
$users->toArray();                // 全部轉陣列
$users->toArray('email');         // 單欄位陣列 ['a@b.c', 'x@y.z']
foreach ($users as $user) { }    // 逐筆迭代

// 更新
$user->update(['name' => '新名稱', 'data' => ['role' => 'user']]);
// 或
$user->name = '新名稱';
$user->save();  // 只 UPDATE 有變更的欄位

// 刪除
$user->delete();
```

## 關聯

```php
// has_one — 讀取時自動查詢
$org = $user->organization;    // 回傳 OrganizationRow

// has_many — 回傳 Rowset
foreach ($user->tokens as $token) {
    echo $token->token;
}
```

## 批次新增

```php
foreach ($records as $record) {
    User::bulkInsert($record);  // 累積到 500 筆自動 flush
}
User::bulkCommit();  // 結束時必須呼叫，flush 剩餘資料

// 選項
User::bulkInsert($record, ['ignore' => true]);    // INSERT OR IGNORE
User::bulkInsert($record, ['bulk_count' => 100]); // 自訂批次大小
User::bulkCommit(['ignore' => true]);
```

## 自訂 SQL

```php
$stmt = MiniEngine::dbExecute(
    "SELECT * FROM ::table WHERE ::col = :val",
    ['::table' => 'users', '::col' => 'email', ':val' => 'test@example.com']
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 取得 PDO
$pdo = MiniEngine::getDb();
```

## 在 Model 中加自訂方法

在 Model 的 Row 物件上加方法，慣例是用 `XxxRow` 但 Mini Engine 不需要另外宣告 Row class，直接在 Model class 中加 instance method 即可：

```php
class User extends MiniEngine_Table
{
    public function init() { /* ... */ }

    public function isActive()
    {
        return in_array($this->permission, [0, 1]);
    }

    public function setPassword($password)
    {
        $this->update(['data' => array_merge(
            (array)$this->data,
            ['auth' => password_hash($password, PASSWORD_BCRYPT)]
        )]);
    }
}
```

## jsonb 欄位慣用法

jsonb 欄位自動 decode 為物件，適合存放彈性資料：

```php
// 讀取
$role = $user->data->role;

// 更新（需整個覆蓋或 merge）
$user->update(['data' => array_merge(
    (array)$this->data,
    ['new_key' => 'value']
)]);
```

## 複合主鍵

```php
$this->_primary_keys = ['user_id', 'token'];
```

## 使用非預設資料庫

```php
$this->_db_group = 'secondary';
// 需先在 init.inc.php 或 config 中設定：
// MiniEngine::setDbURL('secondary', 'pgsql://...');
```
