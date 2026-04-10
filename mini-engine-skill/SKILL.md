---
name: mini-engine-skill
description: Mini Engine PHP MVC 框架的 conventions 與開發指引。當專案有 mini-engine.php、init.inc.php，或程式碼中使用 MiniEngine、MiniEngine_Table、MiniEngine_Controller 時自動載入。
user-invocable: false
metadata:
  author: openfunltd
  version: 0.1
---

# Mini Engine 開發指引

Mini Engine 是輕量 PHP MVC framework，核心是單一檔案 `mini-engine.php`。

## 命名規則

| 項目 | 規則 | 範例 |
|------|------|------|
| Controller 檔名 | `controllers/XxxController.php` | `ProductController.php` |
| Controller 類別 | URL `foo_bar` → `FooBarController` | `/user` → `UserController` |
| Action 方法 | `{name}Action()` | `showAction($id)` |
| View 路徑 | `views/{controller}/{action}.php` | `views/user/profile.php` |
| Model 檔名 | `models/Xxx.php`，繼承 `MiniEngine_Table` | `models/User.php` |
| 資料表名稱 | CamelCase → snake_case（自動） | `MeetingMember` → `meeting_member` |

## Gotchas

- `MINI_ENGINE_LIBRARY` 常數**必須在** `require mini-engine.php` 之前定義，否則觸發 CLI 模式
- `$this->json()`, `$this->redirect()`, `$this->notfound()`, `$this->noview()`, `$this->alert()` 都是拋 exception 實作的，呼叫後同一 method 的後續程式碼**不會執行**，所以要用 `return $this->json(...)` 的寫法
- Rowset 是 lazy 的 — `search()` 不執行 SQL，要到 `foreach`/`count()`/`first()`/`toArray()` 才查詢
- `search()` 第二個參數不傳時只 SELECT 主鍵，傳 `'*'` 才選全欄位。忘記傳 `'*'` 會讀不到資料
- `jsonb` 欄位自動 `json_encode`/`json_decode`，不要手動轉
- 重複主鍵 insert 拋出 `MiniEngine_Table_DuplicateException`，不是回傳 false
- SQL 參數：`:param` 是值（PDO prepare），`::param` 是識別符（自動 quote）。搞混會有 SQL injection
- Column 設定 `'lazy' => true` 時，預設查詢不選取該欄位，需傳 `'*'` flag 給 search
- `bulkInsert()` 累積到 500 筆才自動 commit，迴圈結束後必須呼叫 `bulkCommit()`
- CSRF token 欄位名稱慣例是 `sToken`，不是 `csrf_token`
- View 中 `$this` 是 `MiniEngine_Controller_ViewObject`，不是 Controller
- View 中不使用 `endif;`, `endforeach;`, `endfor;`, `endwhile;`, `endswitch;` 等替代語法，一律用 `{ }` 大括號
- `ENV=production` 時關閉 SQL log 和詳細錯誤輸出
- 變數命名一律用 snake_case（`$user_name`），不用 camelCase（`$userName`）

## 情境指引

根據你要做的事，讀取對應的 reference 檔案：

- **建立有頁面的功能**（Controller + View + partial）→ 讀取 `references/page.md`
- **建立 JSON API**（token 驗證、CORS、回傳格式）→ 讀取 `references/json-api.md`
- **處理表單**（POST、CSRF、FormGroup 驗證、錯誤顯示）→ 讀取 `references/form.md`
- **登入與權限驗證**（Session、init() 權限檢查）→ 讀取 `references/auth.md`
- **建立或操作 Model**（欄位定義、CRUD、關聯、批次操作）→ 讀取 `references/model.md`
- **建立 Library**（Session、Helper、Mail、業務邏輯抽離）→ 讀取 `references/library.md`
