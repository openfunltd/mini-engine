# 建立 JSON API

## 兩種模式

### 1. 登入使用者的 API（搭配 CSRF）

用於前端 AJAX 呼叫，在 controller `init()` 中驗證登入。

```php
class DataController extends MiniEngine_Controller
{
    public function init()
    {
        $this->init_csrf();
        $this->view->user = Session::getLoginUser();
        if (!$this->view->user) {
            return $this->json(['error' => true, 'message' => 'Unauthorized']);
        }
    }

    // GET /data/list
    public function listAction()
    {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $rows = Product::search([], '*')
            ->order(['created_at' => 'DESC'])
            ->limit($limit)
            ->offset(($page - 1) * $limit);

        return $this->json([
            'error' => false,
            'data' => $rows->toArray(),
            'total' => Product::search([])->count(),
        ]);
    }

    // POST /data/update（需 CSRF）
    public function updateAction()
    {
        if ($_POST['sToken'] != $this->view->csrf_token) {
            return $this->json(['error' => true, 'message' => 'CSRF token mismatch']);
        }
        $product = Product::find($_POST['id']);
        $product->update(['name' => $_POST['name']]);
        return $this->json(['success' => true]);
    }
}
```

### 2. Token-based API（外部呼叫）

用於提供給第三方的 API，在 `init()` 中驗證 token。

```php
class ApiController extends MiniEngine_Controller
{
    public function init()
    {
        $token = $_GET['token'] ?? null;
        if (is_null($token) or !$token = Token::find_by_token($token)) {
            return $this->json(['error' => true, 'message' => 'Invalid or missing token.']);
        }
        if ($token->isExpired()) {
            return $this->json(['error' => true, 'message' => 'Token expired.']);
        }
        if ($token->status != 1) {
            return $this->json(['error' => true, 'message' => 'Token disabled.']);
        }
        $this->view->token = $token;
    }

    public function dataAction()
    {
        // ...
        return $this->json(['error' => false, 'data' => $result]);
    }
}
```

## JSON 回傳格式慣例

```php
// 成功
return $this->json(['error' => false, 'data' => $result]);
// 或
return $this->json(['success' => true, 'data' => $result]);

// 失敗
return $this->json(['error' => true, 'message' => '錯誤說明']);
// 或
return $this->json(['success' => false, 'error' => '錯誤說明']);
```

專案內保持一致即可，兩種風格都有在用。

## CORS

需要跨域存取時用 `$this->cors_json($data)` 取代 `$this->json($data)`。

## 搭配 Counter 做使用量統計

```php
Counter::inc('all', 'api-view-count');
Counter::inc($dataset, 'api-view-count:dataset');
Counter::inc($org_id, 'api-view-count:org');
```
