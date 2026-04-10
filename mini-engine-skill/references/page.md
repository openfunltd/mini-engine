# 建立有頁面的功能

## 流程

1. 建立 Controller（`controllers/XxxController.php`）
2. 在 `init()` 做權限驗證、初始化 CSRF、設定共用 view 資料
3. 在 action 中查詢資料，透過 `$this->view->xxx` 傳給 view
4. 建立對應 view（`views/{controller}/{action}.php`）
5. 用 `$this->partial()` 引入共用 header/footer

## Controller 範例

```php
<?php

class ProductController extends MiniEngine_Controller
{
    public function init()
    {
        $this->init_csrf();
        $this->view->user = Session::getLoginUser();
        if (is_null($this->view->user)) {
            return $this->redirect('/user/login?next=' . urlencode($_SERVER['REQUEST_URI']));
        }
    }

    public function listAction()
    {
        $this->view->products = Product::search([], '*')
            ->order(['created_at' => 'DESC'])
            ->toArray();
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

## View 範例

```php
<!-- views/product/list.php -->
<?php
$this->title = '產品列表';
$this->breadcrumbs = [['首頁', '/'], ['產品列表']];
?>
<?= $this->partial('common/header', $this) ?>

<table>
<?php foreach ($this->products as $product) { ?>
    <tr>
        <td><?= $this->escape($product['name']) ?></td>
        <td><a href="/product/show/<?= $product['id'] ?>">查看</a></td>
    </tr>
<?php } ?>
</table>

<?= $this->partial('common/footer') ?>
```

## 重點

- `$this->partial('common/header', $this)` — 傳 `$this` 讓 header 可讀取 title、breadcrumbs、user 等
- `$this->escape()` — 所有使用者輸入的文字都要 escape
- 設定 `$this->title`、`$this->breadcrumbs` 等 view 變數直接在 view 檔案最上方設定即可
- 靜態資源版本控制：用 `?v=` query string，開發環境用 `time()`，production 用檔案 hash
