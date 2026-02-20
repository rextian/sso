# REXTIAN SSO - 统一身份认证系统

这是一个功能完整、安全可靠的开源统一身份认证（SSO）系统，支持OAuth 2.0、RADIUS认证、多因素认证等功能。

## ✨ 功能特性

### 核心功能
- **OAuth 2.0 协议**：完整的OAuth 2.0授权流程
- **用户管理**：用户注册、审核、禁用/启用
- **应用接入**：管理接入的OAuth应用
- **授权管理**：查看和取消用户的应用授权
- **审计日志**：完整的操作审计记录

### 安全特性
- **双因素认证（MFA）**：支持TOTP双因素认证
- **邮箱验证**：注册流程中的邮箱验证码
- **密码加密**：使用password_hash安全加密
- **CSRF防护**：跨站请求伪造防护
- **速率限制**：防止暴力破解和滥用
- **会话管理**：安全的会话处理

### 认证方式
- **账号密码登录**：传统的用户名密码认证
- **第三方登录**：支持微信、飞书、GitHub、钉钉、企业微信
- **RADIUS认证**：支持网络设备的RADIUS认证
- **SSO简化API**：提供简化的单点登录接口

### 用户自助服务
- **个人门户**：用户自助服务门户
- **登录历史**：查看自己的登录记录
- **RADIUS会话**：查看RADIUS认证会话
- **授权应用管理**：管理自己授权的应用
- **安全设置**：修改密码、管理MFA

## 🚀 快速开始

### 环境要求
- PHP 7.4+ 或 PHP 8.0+
- MySQL 5.7+ 或 MariaDB 10.2+
- Apache 或 Nginx Web服务器
- 支持PDO和PDO_MySQL扩展

### 安装步骤

1. **克隆项目**
```bash
git clone https://github.com/rextian/sso.git
cd sso
```

2. **配置数据库**
```bash
# 创建数据库
mysql -u root -p
CREATE DATABASE rextian_sso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 导入初始数据
mysql -u root -p sso < install/8085_2026-02-19_00-40-33_mysql_data_gdWQI.sql
```

3. **配置文件**
```bash
# 复制配置文件模板
cp config.example.php config.php

# 编辑配置文件，填写数据库信息
vim config.php
```

4. **配置Web服务器**

**Apache配置示例：**
```apache
<VirtualHost *:80>
    ServerName sso.yourdomain.com
    DocumentRoot /path/to/sso
    
    <Directory /path/to/sso>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx配置示例：**
```nginx
server {
    listen 80;
    server_name sso.yourdomain.com;
    root /path/to/sso;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

5. **设置文件权限**
```bash
chmod -R 755 /path/to/sso
chmod -R 777 /path/to/sso/uploads
chmod -R 777 /path/to/sso/data
```

6. **访问系统**
打开浏览器访问 `http://sso.yourdomain.com`

默认管理员账号：
- 用户名：`admin`
- 密码：`admin123`

⚠️ **重要**：首次登录后请立即修改管理员密码！

默认MFA需要自己开启。

## 📁 项目结构

```
sso/
├── api/                    # API接口目录
│   ├── apps/              # 应用管理API
│   ├── audit-logs/        # 审计日志API
│   ├── auth/              # 认证相关API
│   ├── authorizations/    # 授权管理API
│   ├── dashboard/         # 仪表盘API
│   ├── me/                # 当前用户API
│   ├── radius/            # RADIUS相关API
│   ├── settings/          # 设置API
│   ├── sso/               # SSO简化API
│   └── users/             # 用户管理API
├── auth/                  # 第三方登录回调
├── includes/              # 核心类库
│   ├── auth.php          # 认证类
│   ├── db.php            # 数据库类
│   ├── csrf.php          # CSRF防护
│   ├── audit.php         # 审计日志
│   ├── email_helper.php  # 邮件发送
│   ├── sms_helper.php    # 短信发送
│   └── totp.php          # MFA TOTP
├── install/               # 安装和迁移脚本
├── oauth/                 # OAuth 2.0核心流程
├── uploads/               # 上传文件目录
├── data/                  # 数据目录
├── admin_dashboard.php    # 管理后台仪表盘
├── user.php              # 用户管理
├── oauth.php             # 应用接入管理
├── authorizations.php     # 授权管理
├── auditlog.php          # 审计日志
├── settings.php          # 系统设置
├── portal.php            # 用户自助门户
├── login.php             # 登录页面
├── register.php          # 注册页面
└── config.php            # 配置文件
```

## 🔧 配置说明

### 基础配置 (`config.php`)

```php
<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'rextian_sso');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// 站点配置
define('SITE_URL', 'https://sso.yourdomain.com');
define('SITE_NAME', 'REXTIAN ID');

// Session配置
define('SESSION_NAME', 'REXTIAN_SESSID');
define('SESSION_LIFETIME', 86400);

// 安全配置
define('COOKIE_SECURE', false); // 生产环境设为true
define('COOKIE_HTTPONLY', true);
```

### 系统设置

登录后台管理后，可以在"系统设置"中配置：

1. **基础信息**：站点名称、主域名
2. **短信服务**：阿里云、腾讯云、京东云短信配置
3. **邮件服务**：SMTP邮件服务器配置
4. **安全策略**：密码强度、登录限制、会话超时
5. **第三方登录**：微信、飞书、GitHub、钉钉、企业微信
6. **RADIUS认证**：RADIUS服务器和客户端配置

## 🔐 OAuth 2.0 使用

### 接入流程

1. **创建应用**：在管理后台创建OAuth应用，获取Client ID和Client Secret
2. **配置回调**：设置授权回调URL
3. **实现授权**：按照OAuth 2.0流程实现授权

### API端点

- **授权端点**：`/oauth/authorize.php`
- **Token端点**：`/oauth/token.php`
- **用户信息**：`/oauth/userinfo.php`

### 授权码流程示例

```php
// 1. 重定向到授权页面
$authUrl = 'https://sso.yourdomain.com/oauth/authorize.php?' . http_build_query([
    'client_id' => 'your_client_id',
    'redirect_uri' => 'https://yourapp.com/callback',
    'response_type' => 'code',
    'scope' => 'basic profile email',
    'state' => bin2hex(random_bytes(16))
]);
header('Location: ' . $authUrl);

// 2. 回调处理，获取access_token
if (isset($_GET['code'])) {
    $ch = curl_init('https://sso.yourdomain.com/oauth/token.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'authorization_code',
        'client_id' => 'your_client_id',
        'client_secret' => 'your_client_secret',
        'redirect_uri' => 'https://yourapp.com/callback',
        'code' => $_GET['code']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    $accessToken = $response['access_token'];
}

// 3. 使用access_token获取用户信息
$ch = curl_init('https://sso.yourdomain.com/oauth/userinfo.php');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userInfo = json_decode(curl_exec($ch), true);
```

## 🌐 RADIUS 认证

### 配置RADIUS

1. 在系统设置中启用RADIUS认证
2. 配置RADIUS服务器信息（IP、端口、密钥）
3. 添加RADIUS客户端（NAS设备）

### RADIUS API

- **认证API**：`/api/radius/auth.php`
- **记账API**：`/api/radius/acct.php`
- **客户端管理**：`/api/radius/clients.php`

## 📊 审计日志

系统记录以下操作的审计日志：
- 用户登录/登出
- 用户注册/审核
- 密码修改/重置
- MFA启用/禁用
- 应用授权/取消授权
- 系统设置变更

所有日志包含：操作时间、操作用户、IP地址、操作状态、追踪ID。

## 🔒 安全建议

1. **使用HTTPS**：生产环境必须使用HTTPS
2. **强密码策略**：启用密码强度检查
3. **启用MFA**：建议管理员启用双因素认证
4. **定期备份**：定期备份数据库
5. **限制访问**：使用防火墙限制管理后台访问
6. **监控日志**：定期检查审计日志
7. **更新系统**：及时更新系统和依赖

## 🤝 贡献

欢迎提交Issue和Pull Request！

### 开发规范
- 遵循PSR编码规范
- 保持代码简洁和可读性
- 添加必要的注释
- 提交前进行测试

## 📄 许可证

本项目采用 MIT 许可证开源。详见 [LICENSE](LICENSE) 文件。

## 🆘 支持

如有问题，请：
1. 查看 [Wiki](https://github.com/rextian/sso/wiki)
2. 提交 [Issue](https://github.com/rextian/sso/issues)
3. 联系维护者

## 🙏 致谢

感谢以下开源项目和库：
- Tailwind CSS
- Remix Icon
- PHPMailer
- 和所有贡献者

## 📜 版权声明

Copyright © 2026 REXTIAN. All rights reserved.

本项目采用 MIT 许可证开源，使用需保留所有版权。

---

**REXTIAN SSO** - 让身份认证更简单、更安全！
