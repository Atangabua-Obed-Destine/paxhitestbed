# Production Environment Configuration Fix Guide

## Problem
`Access denied for user 'root'@'127.0.0.1' (using password: NO)` 

This error means Laravel is using default database credentials instead of your production `.env` file values.

## Root Cause
1. **Configuration is cached** - Laravel cached the old config before you updated `.env`
2. **`.env` file permissions** - Server can't read the `.env` file
3. **`.env` syntax errors** - Invalid formatting in `.env` file
4. **Wrong `.env` location** - File not in the correct directory

---

## Fix Steps (Run on Production Server)

### Step 1: SSH into Your Server
Connect via SSH or use your hosting control panel's terminal:
```bash
ssh u833971835@paxhi.org
cd /home/u833971835/domains/paxhi.org/public_html
```

### Step 2: Clear ALL Laravel Caches
```bash
# Clear configuration cache (MOST IMPORTANT!)
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear

# Clear application cache
php artisan cache:clear

# Clear compiled classes
php artisan clear-compiled

# Optimize for production (recompiles everything)
php artisan optimize
```

### Step 3: Verify `.env` File Exists and Has Correct Values
```bash
# Check if .env exists
ls -la .env

# View .env content (check database credentials)
cat .env | grep DB_

# Expected output should show YOUR production database credentials:
# DB_CONNECTION=mysql
# DB_HOST=localhost  (or your DB host)
# DB_DATABASE=u833971835_paxhitest  (your actual DB name)
# DB_USERNAME=u833971835_paxhiadmin  (your actual DB user)
# DB_PASSWORD=YourActualPassword  (your actual DB password)
```

### Step 4: Fix `.env` File Permissions
```bash
# Set correct permissions
chmod 644 .env
chown u833971835:u833971835 .env
```

### Step 5: Update `.env` with Production Database Credentials

Your production `.env` should look like this:

```env
APP_NAME="PAXHI"
APP_ENV=production  # CHANGE FROM 'local' to 'production'
APP_KEY=base64:pHmoQLhkRaYV67M7u4PyHIA21veDqCcrdTMvyIUJnHw=
APP_DEBUG=false  # CHANGE FROM 'true' to 'false' for security
APP_URL=https://paxhi.org  # CHANGE to your actual domain

LOG_CHANNEL=stack

# PRODUCTION DATABASE CREDENTIALS (Update these!)
DB_CONNECTION=mysql
DB_HOST=localhost  # Usually 'localhost' for shared hosting
DB_PORT=3306
DB_DATABASE=u833971835_paxhitest  # Your actual database name from cPanel
DB_USERNAME=u833971835_paxhiadmin  # Your actual database username
DB_PASSWORD=YOUR_ACTUAL_DB_PASSWORD  # Your actual database password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# MAIL SETTINGS (already correct)
MAIL_DRIVER="smtp"
MAIL_HOST="smtp.titan.email"
MAIL_PORT="465"
MAIL_USERNAME="info@paxhi.org"
MAIL_PASSWORD="info@paxhi.org"
MAIL_ENCRYPTION="ssl"

# ... rest of your settings ...

PAYMENT_GATEWAY="flutterwave"
FLW_PUBLIC_KEY="FLWPUBK_TEST-d7333cca88a015c8e7a3e35a03486c52-X"
FLW_SECRET_KEY="FLWSECK_TEST-5bdcb21f2ac1f684cd68c3202e37e880-X"
FLW_SECRET_HASH="FLWSECK_TESTa4036884222d"
```

### Step 6: Get Your Production Database Credentials

If you don't know your production database credentials:

**Via cPanel:**
1. Log into cPanel at your hosting provider
2. Go to **MySQL Databases**
3. Check:
   - **Database Name** (usually `u833971835_something`)
   - **Database User** (usually `u833971835_something`)
   - **Database Host** (usually `localhost`)
4. If you don't have a user or password:
   - Create a new MySQL user
   - Assign it to your database
   - Note down the password
   - Update `.env` file

### Step 7: Test Database Connection

Create a test script to verify database connection:

```bash
# Create test file
nano test-db.php
```

Add this content:
```php
<?php
require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_DATABASE'];
$user = $_ENV['DB_USERNAME'];
$pass = $_ENV['DB_PASSWORD'];

echo "Attempting to connect to:\n";
echo "Host: $host\n";
echo "Database: $db\n";
echo "Username: $user\n";
echo "Password: " . (empty($pass) ? "(empty)" : "(set)") . "\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✅ SUCCESS! Database connection established.\n";
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
```

Run the test:
```bash
php test-db.php
```

### Step 8: Final Cache Clear and Optimize
```bash
# Clear all caches again
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Optimize for production
php artisan config:cache  # Cache config for performance
php artisan route:cache   # Cache routes for performance
php artisan view:cache    # Cache views for performance
```

### Step 9: Set Correct File Permissions

```bash
# Set storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Set ownership (replace with your actual user)
chown -R u833971835:u833971835 storage
chown -R u833971835:u833971835 bootstrap/cache
```

---

## Common Issues and Solutions

### Issue 1: `.env` File Not Being Read
**Solution:** Make sure `.env` is in the **root directory** (same level as `artisan`)
```bash
pwd  # Should show: /home/u833971835/domains/paxhi.org/public_html
ls -la .env  # Should exist here
```

### Issue 2: Configuration Still Cached
**Solution:** Completely remove cache files
```bash
rm -rf bootstrap/cache/*.php
php artisan config:clear
```

### Issue 3: Storage Logs Folder Permission Denied
**Solution:** Fix storage permissions
```bash
chmod -R 775 storage/logs
chown -R u833971835:u833971835 storage/logs
```

### Issue 4: Database Password Has Special Characters
**Solution:** Wrap password in quotes in `.env`
```env
DB_PASSWORD="P@ssw0rd#123"  # Use quotes if special chars
```

### Issue 5: Using Wrong Database Host
**Solution:** For shared hosting, usually use `localhost`
```env
DB_HOST=localhost  # NOT 127.0.0.1 on some shared hosts
```

---

## Verification Checklist

After applying fixes, verify:

- [ ] `.env` file exists in root directory
- [ ] Database credentials are correct for production
- [ ] `APP_ENV=production` (not `local`)
- [ ] `APP_DEBUG=false` (for security)
- [ ] `APP_URL` matches your domain
- [ ] Configuration cache cleared (`php artisan config:clear`)
- [ ] Storage permissions are 775
- [ ] Can connect to database (`php test-db.php`)
- [ ] Website loads without database errors
- [ ] Check logs: `tail -f storage/logs/laravel.log`

---

## Important Notes

1. **Never commit `.env` to Git** - It contains sensitive credentials
2. **Always use `config:clear` after changing `.env`** - Otherwise changes won't apply
3. **Use `APP_DEBUG=false` in production** - Prevents exposing sensitive information
4. **Keep separate `.env` files** - One for local development, one for production

---

## Quick Command Summary

```bash
# Complete fix sequence
cd /home/u833971835/domains/paxhi.org/public_html
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
chmod -R 775 storage bootstrap/cache
```

---

## Still Having Issues?

If the problem persists:

1. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Check PHP error logs:**
   ```bash
   tail -f /var/log/php_errors.log  # Location varies by host
   ```

3. **Enable debug temporarily to see detailed error:**
   ```env
   APP_DEBUG=true  # ONLY for troubleshooting, set back to false!
   ```

4. **Contact your hosting provider** - They can verify:
   - Database credentials
   - PHP version compatibility
   - Server permissions
   - Firewall/security settings
