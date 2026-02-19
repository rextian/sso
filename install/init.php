<?php
/**
 * REXTIAN SSO - 数据库初始化脚本
 * 执行方式: php install/init.php 或通过浏览器访问 (生产环境请删除或限制访问)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';

// 检查是否已初始化（避免重复执行覆盖管理员）
$checkOnly = isset($argv[1]) && $argv[1] === '--check';

try {
    $pdo = getDb();
    if (!$pdo) {
        die("错误: 无法连接数据库，请检查 config.php 中的 DB_HOST、DB_NAME、DB_USER、DB_PASS 配置。\n");
    }

    echo "数据库连接成功。\n";

    // 读取并执行 init.sql
    $sqlFile = __DIR__ . '/init.sql';
    if (!file_exists($sqlFile)) {
        die("错误: 找不到 init.sql 文件。\n");
    }

    $sql = file_get_contents($sqlFile);
    // 移除行首 -- 注释
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    // 按分号分割，过滤空语句
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($s) { return strlen($s) > 5; }
    );

    foreach ($statements as $stmt) {
        if (empty(trim($stmt))) continue;
        try {
            $pdo->exec($stmt);
            echo "执行成功: " . substr($stmt, 0, 50) . "...\n";
        } catch (PDOException $e) {
            // 忽略 "table already exists" 等
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    // 插入初始管理员（仅当不存在时）
    $adminExists = $pdo->query("SELECT 1 FROM users WHERE username = 'admin' LIMIT 1")->fetch();
    if (!$adminExists) {
        $passwordHash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
        $stmt->execute(['admin', 'admin@rextian.com', 'Administrator', $passwordHash]);
        echo "已创建初始管理员: username=admin, password=Admin@123 (请首次登录后修改)\n";
    } else {
        echo "管理员账号已存在，跳过创建。\n";
    }

    // 插入 settings 默认配置（仅当不存在时）
    $settings = [
        'site_name' => 'REXTIAN ID',
        'site_url' => 'https://sso.rextian.com',
    ];
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    echo "已写入 settings 默认配置。\n";

    echo "\n初始化完成。\n";

} catch (PDOException $e) {
    die("数据库错误: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("错误: " . $e->getMessage() . "\n");
}
