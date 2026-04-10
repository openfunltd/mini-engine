# 建立 Library

共用邏輯不要塞在 Controller 裡，抽成 library 放 `libraries/` 目錄。

## 放置位置

`libraries/` 已在 `set_include_path` 中，class 名稱對應檔名即可自動載入：

- `libraries/Session.php` → `class Session`
- `libraries/MailHelper.php` → `class MailHelper`
- `libraries/MiniEngine/FormGroup.php` → `class MiniEngine_FormGroup`（底線轉路徑）

## 常見類型

### Session Helper

幾乎每個專案都有，集中管理登入狀態：

```php
<?php

class Session
{
    public static function getLoginUser()
    {
        $user_id = MiniEngine::getSession('user_id');
        if (!$user_id) return null;
        $user = User::find($user_id);
        if (!$user or !$user->isActive()) {
            self::logoutUser();
            return null;
        }
        return $user;
    }

    public static function loginUser($user, $method = 'password')
    {
        MiniEngine::setSession('user_id', $user->id);
        $user->update(['last_logined_at' => time()]);
    }

    public static function logoutUser()
    {
        MiniEngine::setSession('user_id', null);
    }
}
```

### 工具類 Helper

靜態方法為主，不需要 instance：

```php
<?php

class CommonHelper
{
    public static function isDevMode()
    {
        return getenv('ENV') != 'production';
    }

    public static function getStaticVersion()
    {
        return match (getenv('ENV')) {
            'dev' => time(),
            default => md5(file_get_contents(MiniEngine::getRoot() . '/version')),
        };
    }
}
```

### Log Helper

記錄操作紀錄：

```php
<?php

class LogHelper
{
    public static function log($category, $action, $data = [])
    {
        $log_dir = getenv('LOG_DIR') ?: '/tmp';
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $line = json_encode([
            'time' => $time,
            'action' => $action,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
        $path = "{$log_dir}/{$category}/{$date}.jsonl";
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($path, $line . "\n", FILE_APPEND);
    }
}
```

### Mail Helper

寄信功能（搭配 PHPMailer）：

```php
<?php

class MailHelper
{
    public static function sendmail($to, $title, $body)
    {
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_SERVER');
        $mail->Port = getenv('SMTP_PORT');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER');
        $mail->Password = getenv('SMTP_PASSWORD');
        $mail->setFrom(getenv('SES_MAIL'), getenv('APP_NAME'));
        $mail->Subject = $title;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';
        foreach ((array)$to as $addr) {
            $mail->addAddress($addr);
        }
        return $mail->send();
    }

    // 用 view 當信件模板
    public static function notify($type, $addresses, $params)
    {
        $view_obj = new MiniEngine_Controller_ViewObject();
        foreach ($params as $key => $value) {
            $view_obj->{$key} = $value;
        }
        $body = $view_obj->partial(__DIR__ . "/../views/mail/{$type}.php");
        $title = $view_obj->title;
        return self::sendmail($addresses, $title, $body);
    }
}
```

### 業務邏輯類

較複雜的計算或資料處理，從 controller 中抽出：

```php
<?php

class DataHelper
{
    public static function getData($api_schema, $params = [])
    {
        // 複雜的資料查詢、轉換邏輯
    }

    public static function getDataRow($api_schema, $id, $params = [])
    {
        // 單筆資料查詢
    }
}
```

## 原則

- 一個 class 一個檔案，檔名 = 類別名
- 用 static method 為主，除非需要保持狀態
- Controller 只負責收參數、呼叫 library、傳資料給 view，邏輯放 library
- 信件模板放 `views/mail/`，用 `MiniEngine_Controller_ViewObject` 渲染
