# Laravel 项目启动方式详解

## 🎯 三种主要启动方式

### 方式一：Laravel Artisan 服务器（开发推荐）

```bash
php artisan serve
# 或指定端口
php artisan serve --port=8000
# 或指定主机和端口
php artisan serve --host=127.0.0.1 --port=8000
```

**优点：**
- ✅ 简单方便，一条命令启动
- ✅ 自动处理路由重写
- ✅ 适合开发环境
- ✅ Laravel官方推荐

**缺点：**
- ❌ 性能较低，不适合生产环境
- ❌ 单线程处理请求

**访问地址：** http://localhost:8000

---

### 方式二：PHP 内置服务器（纯PHP命令）

```bash
# 基本用法
php -S localhost:8000 -t public

# 指定文档根目录
php -S 127.0.0.1:8000 -t public

# 带路由文件（处理Laravel路由）
php -S localhost:8000 -t public public/index.php
```

**优点：**
- ✅ 纯PHP命令，不需要Laravel
- ✅ 轻量级，启动快
- ✅ 适合快速测试

**缺点：**
- ❌ 需要手动处理路由重写
- ❌ 性能较低
- ❌ 不适合生产环境

**访问地址：** http://localhost:8000

**注意：** 这种方式需要配置路由重写，否则Laravel路由可能无法正常工作。

---

### 方式三：Apache/Nginx（生产环境推荐）

#### Apache 配置

**虚拟主机配置：**
```apache
<VirtualHost *:80>
    ServerName lsky.local
    DocumentRoot "D:/frontend/main/php/lsky-image/public"
    
    <Directory "D:/frontend/main/php/lsky-image/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**.htaccess 文件（Laravel已包含）：**
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

#### Nginx 配置

```nginx
server {
    listen 80;
    server_name lsky.local;
    root /path/to/lsky-image/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**优点：**
- ✅ 性能高，适合生产环境
- ✅ 支持并发处理
- ✅ 功能强大，支持HTTPS、负载均衡等

**缺点：**
- ❌ 配置相对复杂
- ❌ 需要安装和配置Web服务器

---

## 🔍 详细对比

| 特性 | artisan serve | php -S | Apache/Nginx |
|------|---------------|--------|--------------|
| **易用性** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ |
| **性能** | ⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| **开发环境** | ✅ 推荐 | ✅ 可用 | ⚠️ 过度 |
| **生产环境** | ❌ 不推荐 | ❌ 不推荐 | ✅ 必须 |
| **路由支持** | ✅ 自动 | ⚠️ 需配置 | ✅ 完整 |
| **HTTPS支持** | ⚠️ 有限 | ❌ 不支持 | ✅ 完整 |

---

## 💡 实际使用建议

### 开发环境

**推荐：使用 `php artisan serve`**
```bash
php artisan serve
```

**原因：**
- 最简单，开箱即用
- Laravel自动处理所有路由
- 适合快速开发和测试

### 快速测试

**可选：使用 `php -S`**
```bash
cd public
php -S localhost:8000
```

**注意：** 这种方式需要确保路由重写正常工作。

### 生产环境

**必须：使用 Apache 或 Nginx**

**XAMPP用户：**
1. 启动Apache服务
2. 配置虚拟主机指向 `public` 目录
3. 访问配置的域名

---

## 🚀 针对您的项目

### 当前情况

您使用的是 **XAMPP**，所以有以下选择：

#### 选择1：继续使用 artisan serve（推荐开发）

```bash
"C:\Program Files\xampp8.1\php\php.exe" artisan serve
```

#### 选择2：使用XAMPP的Apache

1. **启动XAMPP控制面板**
2. **启动Apache服务**
3. **配置虚拟主机**

编辑 `C:\xampp\apache\conf\extra\httpd-vhosts.conf`：
```apache
<VirtualHost *:80>
    ServerName lsky.local
    DocumentRoot "D:/frontend/main/php/lsky-image/public"
    
    <Directory "D:/frontend/main/php/lsky-image/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

4. **编辑 hosts 文件**
   - 路径：`C:\Windows\System32\drivers\etc\hosts`
   - 添加：`127.0.0.1 lsky.local`

5. **访问：** http://lsky.local

#### 选择3：使用PHP内置服务器

```bash
cd public
"C:\Program Files\xampp8.1\php\php.exe" -S localhost:8000
```

**注意：** 这种方式可能无法正确处理Laravel路由，不推荐。

---

## 📝 总结

### 开发环境
```bash
# 最简单的方式
php artisan serve
```

### 生产环境
```bash
# 必须使用Apache或Nginx
# 配置虚拟主机指向 public 目录
```

### 快速测试
```bash
# 可以使用PHP内置服务器
php -S localhost:8000 -t public
```

**结论：** 
- ✅ **开发时**：`php artisan serve` 最简单
- ✅ **生产时**：必须使用 Apache/Nginx
- ⚠️ **纯PHP命令**：可以，但不推荐，因为路由处理可能有问题






