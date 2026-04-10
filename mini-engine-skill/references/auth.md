# 登入與權限驗證

## Session 機制

Mini Engine 的 session 是 cookie-based（HMAC-SHA256 簽名），不依賴 PHP session。

```php
MiniEngine::setSession('user_id', $user->id);   // 寫入
MiniEngine::getSession('user_id');               // 讀取
MiniEngine::setSession('user_id', null);         // 刪除
```

## 慣用的 Session Helper

建立 `libraries/Session.php`，集中管理登入狀態：

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

    public static function loginUser($user)
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

## Controller 中的權限驗證

在 `init()` 中做，每個 action 執行前都會經過：

```php
public function init()
{
    $this->init_csrf();
    $this->view->user = Session::getLoginUser();

    // 需要登入的頁面
    if (is_null($this->view->user)) {
        return $this->redirect('/user/login?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
}
```

### 管理員限定

```php
public function init()
{
    $this->init_csrf();
    $this->view->user = Session::getLoginUser();
    if (is_null($this->view->user)) {
        return $this->redirect('/user/login');
    }
    if (!$this->view->user->isPlatformAdmin()) {
        return $this->redirect('/');
    }
}
```

## 登入流程

```php
// UserController
public function loginAction()
{
    if ($_POST) {
        if ($_POST['sToken'] != $this->view->csrf_token) {
            return $this->alert('CSRF token mismatch.', '/user/login');
        }
        $user = User::find_by_email($_POST['email']);
        if (!$user or !$user->checkPassword($_POST['password'])) {
            return $this->alert('帳號或密碼錯誤', '/user/login');
        }
        if (!$user->isActive()) {
            return $this->alert('帳號已停用', '/user/login');
        }
        Session::loginUser($user);
        $next = $_GET['next'] ?? '/';
        return $this->redirect($next);
    }
}

public function logoutAction()
{
    Session::logoutUser();
    return $this->redirect('/user/login');
}
```

## User Model 權限欄位慣例

```php
// permission 欄位慣例
// 0 = 一般成員, 1 = 管理員, -1/-2 = 停用

public function isActive() { return in_array($this->permission, [0, 1]); }
public function isPlatformAdmin() { return $this->org_id == 1; }
public function isOrganizationAdmin() { return $this->permission == 1; }
```

## 密碼處理

```php
// User Model 中
public function setPassword($password)
{
    $this->update(['data' => array_merge(
        (array)$this->data,
        ['auth' => password_hash($password, PASSWORD_BCRYPT)]
    )]);
}

public function checkPassword($password)
{
    return password_verify($password, $this->data->auth ?? '');
}
```

密碼存在 `data` jsonb 欄位的 `auth` key 中，用 bcrypt hash。
