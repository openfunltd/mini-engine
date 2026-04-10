<?php

/**
 * Mini Engine Skills Installer
 *
 * Interactive installer that downloads mini-engine-skill from GitHub
 * and installs to AI agent skill directories.
 *
 * Usage: php skills-installer.php
 */

$github_base = 'https://raw.githubusercontent.com/openfunltd/mini-engine/main/mini-engine-skill';
$files = [
    'SKILL.md',
    'references/auth.md',
    'references/form.md',
    'references/json-api.md',
    'references/library.md',
    'references/model.md',
    'references/page.md',
];

// Step 1: 選擇安裝位置
echo "\n";
echo "=== Mini Engine Skills 安裝工具 ===\n";
echo "\n";
echo "請選擇安裝位置：\n";
echo "\n";
echo "  1) 家目錄 (" . getenv('HOME') . ")\n";
echo "     所有專案都能使用，AI agent 會在任何專案中自動載入這些 skills。\n";
echo "\n";
echo "  2) 目前工作目錄 (" . getcwd() . ")\n";
echo "     僅限此專案使用，適合不同專案需要不同 skill 設定的情況。\n";
echo "\n";

$base_dir = null;
while ($base_dir === null) {
    echo "請輸入選項 [1/2]: ";
    $choice = trim(fgets(STDIN));
    if ($choice === '1') {
        $base_dir = getenv('HOME');
    } elseif ($choice === '2') {
        $base_dir = getcwd();
    } else {
        echo "輸入無效，請輸入 1 或 2。\n";
    }
}

echo "\n";
echo "安裝位置：{$base_dir}\n";

// Step 2: 選擇要安裝到哪些目錄（複選）
$options = [
    1 => '.claude/skills',
    2 => '.copilot/skills',
    3 => '.codex/skills',
    4 => '.gemini/skills',
    5 => '.cursor/skills',
];

echo "\n";
echo "請選擇要安裝到哪些 AI agent 目錄？\n";
echo "（輸入編號，以逗號分隔，例如 1,2,3 或輸入 all 全選）\n";
echo "\n";
foreach ($options as $num => $dir) {
    echo "  {$num}) {$dir}\n";
}
echo "\n";

$selected = [];
while (empty($selected)) {
    echo "請輸入選項: ";
    $input = trim(fgets(STDIN));
    if (strtolower($input) === 'all') {
        $selected = array_keys($options);
    } else {
        $nums = array_map('trim', explode(',', $input));
        foreach ($nums as $n) {
            $n = intval($n);
            if (isset($options[$n])) {
                $selected[] = $n;
            }
        }
        $selected = array_unique($selected);
    }
    if (empty($selected)) {
        echo "輸入無效，請重新選擇。\n";
    }
}

// Step 3: 從 GitHub 下載並安裝
echo "\n";
echo "正在從 GitHub 下載 mini-engine-skill...\n";

$failed = false;
foreach ($selected as $num) {
    $dest_dir = $base_dir . '/' . $options[$num] . '/mini-engine-skill';

    foreach ($files as $file) {
        $url = $github_base . '/' . $file;
        $dest_path = $dest_dir . '/' . $file;
        $dest_parent = dirname($dest_path);

        if (!is_dir($dest_parent)) {
            mkdir($dest_parent, 0755, true);
        }

        $content = file_get_contents($url);
        if ($content === false) {
            fwrite(STDERR, "下載失敗：{$url}\n");
            $failed = true;
            continue;
        }

        file_put_contents($dest_path, $content);
    }

    echo "已安裝到 {$dest_dir}\n";
}

if ($failed) {
    echo "\n部分檔案下載失敗，請確認網路連線後重試。\n";
} else {
    echo "\n安裝完成！\n";
}
