# 表單處理

## 標準流程

1. Controller `init()` 中呼叫 `$this->init_csrf()`
2. GET 請求：顯示表單
3. POST 請求：驗證 CSRF → 驗證資料 → 處理 → 重導或提示

## Controller 範例

```php
public function editAction($id)
{
    $product = Product::find($id);
    if (!$product) {
        return $this->notfound("Product not found");
    }

    if ($_POST) {
        // 1. CSRF 驗證
        if ($_POST['sToken'] != $this->view->csrf_token) {
            return $this->alert('CSRF token mismatch.', "/product/edit/{$id}");
        }

        // 2. 表單驗證
        $data = ProductForm::checkData($_POST, $errors);
        if ($errors) {
            ProductForm::setErrors($errors);
            ProductForm::setData($_POST);  // 保留使用者輸入
            // 不 return，繼續往下顯示表單
        } else {
            // 3. 儲存
            $product->update($data);
            return $this->alert('儲存成功', '/product/list');
        }
    }

    // 顯示表單（GET 或驗證失敗時）
    ProductForm::setData($product->toArray());
    $this->view->product = $product;
}
```

## View 表單範例

```php
<form action="/product/edit/<?= $this->product->id ?>" method="POST">
    <input type="hidden" name="sToken" value="<?= $this->csrf_token ?>">
    <?= ProductForm::e('name') ?>
    <?= ProductForm::e('description') ?>
    <button type="submit">儲存</button>
</form>
```

## FormGroup 定義

FormGroup 放在 `libraries/` 或 `forms/` 目錄（需加入 `set_include_path`）。

```php
<?php

class ProductForm extends MiniEngine_FormGroup
{
    public function init()
    {
        $this->name        = ['type' => 'text', 'label' => '名稱', 'required' => true];
        $this->description = ['type' => 'textarea', 'label' => '說明'];
        $this->category    = ['type' => 'select', 'label' => '分類',
                              'options' => ['A' => '分類A', 'B' => '分類B']];
        $this->is_active   = ['type' => 'checkbox', 'options' => ['1' => '啟用']];
    }
}
```

## 重點

- CSRF token 欄位固定用 `name="sToken"`
- `$this->alert($msg, $url)` 會輸出 JS `alert()` 然後跳轉，適合簡單提示
- 驗證失敗時用 `ProductForm::setData($_POST)` 保留使用者輸入，用 `ProductForm::setErrors($errors)` 顯示錯誤
- `FormGroup::checkData()` 回傳清理過的資料，`$errors` 是 pass by reference
- 子表單用 `self::child_form('AddressForm', ['group' => 'address'])` 產生 `address[city]` 等巢狀欄位

## 不使用 FormGroup 的簡單表單

小表單可以不用 FormGroup，直接手寫 HTML + 在 controller 中手動驗證：

```php
if ($_POST) {
    if ($_POST['sToken'] != $this->view->csrf_token) {
        return $this->alert('CSRF token mismatch.', '/current/page');
    }
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        return $this->alert('名稱不得為空', '/current/page');
    }
    $product->update(['name' => $name]);
    return $this->alert('儲存成功', '/product/list');
}
```
