# REXTIAN SSO API 对接文档

## 概述

这是一套简化的 SSO API，方便你的其他门户系统快速接入统一身份认证。

## API 列表

### 1. 获取登录跳转 URL - GET /api/sso/login-url.php

**功能**：生成 OAuth 授权登录 URL，供其他门户直接跳转到登录页

**请求参数**：
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| client_id | string | 是 | 应用的 Client ID |
| redirect_uri | string | 是 | 授权成功后的回调地址 |
| scope | string | 否 | 授权范围，默认：openid profile email |
| state | string | 否 | 状态码，防止 CSRF，系统会自动生成 |

**返回示例**：
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "login_url": "https://sso.rextian.com/oauth/authorize.php?client_id=xxx&...",
        "state": "random_state_string",
        "app_name": "My Portal"
    }
}
```

---

### 2. 验证 Token - POST /api/sso/verify.php

**功能**：验证 access_token 并返回用户信息（支持多种传参方式）

**请求方式**：
- Header: `Authorization: Bearer <access_token>`
- 或 POST body: `{"access_token": "xxx"}`
- 或 GET query: `?access_token=xxx`

**返回示例**：
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "user": {
            "id": 1,
            "uid": "1",
            "username": "admin",
            "email": "admin@example.com",
            "phone": "13800138000",
            "display_name": "管理员",
            "avatar": null,
            "role": "admin",
            "is_admin": true,
            "mfa_enabled": false,
            "last_login_at": "2026-02-18 10:00:00",
            "created_at": "2026-01-01 00:00:00"
        },
        "app": {
            "id": 1,
            "name": "My Portal",
            "client_id": "xxx"
        },
        "token": {
            "scope": "openid profile email",
            "expires_at": "2026-02-18 11:00:00",
            "expires_in": 3600
        }
    },
    "timestamp": 1708236000
}
```

**错误码说明**：
| code | 说明 |
|------|------|
| 0 | 成功 |
| 40101 | 缺少 access_token |
| 40102 | access_token 无效 |
| 40103 | access_token 已过期 |
| 40301 | 用户已被禁用 |
| 40401 | 用户不存在 |

---

### 3. 刷新 Token - POST /api/sso/refresh.php

**功能**：使用 refresh_token 刷新新的 access_token

**请求参数**：
```json
{
    "refresh_token": "your_refresh_token_here",
    "client_id": "your_client_id",
    "client_secret": "your_client_secret"
}
```

**返回示例**：
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "access_token": "new_access_token",
        "refresh_token": "new_refresh_token",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

---

## 完整对接流程示例（PHP）

```php
<?php
// 你的门户系统配置
$ssoConfig = [
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'sso_base_url' => 'https://sso.rextian.com',
    'redirect_uri' => 'https://your-portal.com/callback.php'
];

// 1. 生成登录 URL 并跳转
function getLoginUrl() {
    global $ssoConfig;
    $url = $ssoConfig['sso_base_url'] . '/api/sso/login-url.php?' . http_build_query([
        'client_id' => $ssoConfig['client_id'],
        'redirect_uri' => $ssoConfig['redirect_uri']
    ]);
    $result = json_decode(file_get_contents($url), true);
    if ($result['code'] === 0) {
        $_SESSION['sso_state'] = $result['data']['state'];
        header('Location: ' . $result['data']['login_url']);
        exit;
    }
}

// 2. 回调处理（callback.php）
function handleCallback() {
    global $ssoConfig;
    
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    
    if (!$code || $state !== ($_SESSION['sso_state'] ?? '')) {
        die('授权失败');
    }
    
    // 用 code 换取 token
    $tokenUrl = $ssoConfig['sso_base_url'] . '/oauth/token.php';
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $ssoConfig['redirect_uri'],
            'client_id' => $ssoConfig['client_id'],
            'client_secret' => $ssoConfig['client_secret']
        ]),
        CURLOPT_RETURNTRANSFER => true
    ]);
    $tokenResult = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (isset($tokenResult['access_token'])) {
        // 保存 token
        $_SESSION['sso_access_token'] = $tokenResult['access_token'];
        $_SESSION['sso_refresh_token'] = $tokenResult['refresh_token'];
        
        // 获取用户信息
        $userInfo = verifyToken($tokenResult['access_token']);
        if ($userInfo) {
            $_SESSION['user'] = $userInfo;
            header('Location: /dashboard.php');
            exit;
        }
    }
}

// 3. 验证 token 并获取用户信息
function verifyToken($accessToken) {
    global $ssoConfig;
    $verifyUrl = $ssoConfig['sso_base_url'] . '/api/sso/verify.php';
    $ch = curl_init($verifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if ($result['code'] === 0) {
        return $result['data']['user'];
    }
    return null;
}
?>
```

## JavaScript 前端示例

```javascript
// 验证 token
async function verifyToken(accessToken) {
    const response = await fetch('/api/sso/verify.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ access_token: accessToken })
    });
    return await response.json();
}

// 使用示例
verifyToken('your_token_here').then(data => {
    if (data.code === 0) {
        console.log('用户信息:', data.data.user);
    }
});
```

---

## 注意事项

1. **CORS**：所有 API 都已开启跨域支持，可以直接从前端调用
2. **Token 有效期**：access_token 有效期 1 小时，过期后使用 refresh_token 刷新
3. **安全性**：client_secret 请妥善保管，不要暴露在前端
4. **state 参数**：建议使用，防止 CSRF 攻击
