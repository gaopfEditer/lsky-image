# Lsky Pro Windows 运行指南

## 系统要求

根据项目要求，您需要安装以下软件：

### 必需软件
- **PHP >= 8.0.2** (推荐 PHP 8.1 或 8.2)
- **MySQL 5.7+** 或 **PostgreSQL 9.6+** 或 **SQLite 3.8.8+** 或 **SQL Server 2017+**
- **Node.js >= 16.0** (用于前端资源编译)
- **Composer** (PHP依赖管理工具)

### 必需的PHP扩展
- BCMath PHP 扩展
- Ctype PHP 扩展
- DOM PHP 拓展
- Fileinfo PHP 扩展
- JSON PHP 扩展
- Mbstring PHP 扩展
- OpenSSL PHP 扩展
- PDO PHP 扩展
- Tokenizer PHP 扩展
- XML PHP 扩展
- **Imagick 拓展** (重要！用于图片处理)
- exec、shell_exec 函数
- readlink、symlink 函数
- putenv、getenv 函数
- chmod、chown、fileperms 函数

## 安装步骤

### 1. 安装PHP环境

推荐使用以下方式之一：

#### 方式一：使用XAMPP (推荐新手)
1. 下载并安装 [XAMPP for Windows](https://www.apachefriends.org/download.html)
2. 确保选择PHP 8.0+版本
3. 安装后启动Apache和MySQL服务

#### 方式二：使用PHP官方版本
1. 从 [PHP官网](https://windows.php.net/download/) 下载PHP 8.0+
2. 解压到 `C:\php`
3. 将 `C:\php` 添加到系统PATH环境变量
4. 复制 `php.ini-development` 为 `php.ini`
5. 在php.ini中启用所需扩展

### 2. 安装Composer
1. 下载 [Composer Windows Installer](https://getcomposer.org/download/)
2. 运行安装程序，确保选择正确的PHP路径

### 3. 安装Node.js
1. 从 [Node.js官网](https://nodejs.org/) 下载LTS版本
2. 运行安装程序，确保勾选"Add to PATH"

### 4. 安装数据库
- **MySQL**: 如果使用XAMPP，MySQL已包含
- **SQLite**: 无需额外安装，PHP内置支持

### 5. 配置项目

#### 5.1 创建环境配置文件
在项目根目录创建 `.env` 文件：

```env
APP_NAME=LskyPro
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lsky_pro
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

THUMBNAIL_PATH=thumbnails
```

#### 5.2 安装PHP依赖
在项目根目录打开命令提示符或PowerShell，运行：

```bash
composer install
```

#### 5.3 安装Node.js依赖
```bash
npm install
```

#### 5.4 编译前端资源
```bash
npm run dev
```

#### 5.5 生成应用密钥
```bash
php artisan key:generate
```

#### 5.6 创建数据库
在MySQL中创建数据库：
```sql
CREATE DATABASE lsky_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 5.7 运行安装命令
```bash
php artisan lsky:install --connection=mysql --host=127.0.0.1 --port=3306 --database=lsky_pro --username=root --password=你的密码
```

### 6. 启动项目

#### 方式一：使用Laravel内置服务器
```bash
php artisan serve
```
访问：http://localhost:8000

#### 方式二：使用Apache/Nginx
1. 将项目目录设置为Web根目录
2. 确保 `public` 目录是文档根目录
3. 配置虚拟主机指向 `public` 目录

## 常见问题解决

### 1. Imagick扩展问题
如果遇到Imagick相关错误：
- 下载对应PHP版本的Imagick扩展
- 将 `php_imagick.dll` 放到PHP扩展目录
- 在php.ini中添加 `extension=imagick`

### 2. 权限问题
确保以下目录有写入权限：
- `storage/`
- `bootstrap/cache/`
- `public/thumbnails/`

### 3. 数据库连接问题
- 检查数据库服务是否启动
- 验证数据库连接参数
- 确保数据库用户有足够权限

### 4. 前端资源问题
如果页面样式或JS不工作：
```bash
npm run production
```

## 生产环境部署

### 1. 优化配置
```bash
# 生成优化文件
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 编译生产资源
npm run production
```

### 2. 环境配置
将 `.env` 中的 `APP_ENV` 改为 `production`，`APP_DEBUG` 改为 `false`

### 3. 设置Web服务器
配置Apache或Nginx指向 `public` 目录

## 项目结构说明

- `app/` - 应用核心代码
- `config/` - 配置文件
- `database/` - 数据库迁移和种子文件
- `public/` - Web根目录
- `resources/` - 视图、CSS、JS源文件
- `routes/` - 路由定义
- `storage/` - 存储目录（上传文件、缓存等）

## 技术支持

- 项目官网：https://www.lsky.pro
- 文档：https://docs.lsky.pro
- GitHub：https://github.com/lsky-org/lsky-pro
- 社区：https://github.com/lsky-org/lsky-pro/discussions

---

**注意**：根据README显示，开源版本已停止维护，建议考虑使用其他替代方案或购买商业版本。

