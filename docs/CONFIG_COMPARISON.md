# ProcuChain Configuration Comparison: Local vs Heroku

**Generated:** November 16, 2025  
**Last Updated:** November 16, 2025 (Heroku v161)  
**Local Environment:** Herd (macOS/Windows)  
**Production Environment:** Heroku (procuchain app)

---

## Quick Summary

| Aspect           | Local Development            | Heroku Production           |
| ---------------- | ---------------------------- | --------------------------- |
| **Environment**  | local                        | production                  |
| **Debug Mode**   | ✅ Enabled                   | ❌ Disabled                 |
| **URL**          | https://procuchain.test      | https://www.procuchain.tech |
| **Database**     | Local MySQL (127.0.0.1:3307) | JawsDB MySQL (AWS RDS)      |
| **File Storage** | Local + On-chain             | Local + On-chain            |
| **Cache**        | Database                     | Database                    |
| **Queue**        | Redis (local)                | Redis Cloud                 |
| **Mail**         | Log driver (no sending)      | Resend API (live sending)   |
| **Monitoring**   | Sentry disabled              | Sentry + New Relic enabled  |
| **Log Level**    | debug                        | error                       |

---

## Detailed Configuration Comparison

### Application Settings

| Variable              | Local                   | Heroku                      | Notes                           |
| --------------------- | ----------------------- | --------------------------- | ------------------------------- |
| `APP_NAME`            | ProcuChain              | ProcuChain                  | ✅ Same                         |
| `APP_ENV`             | **local**               | **production**              | Environment indicator           |
| `APP_KEY`             | base64:Apdn...          | base64:C00U...              | ✅ Different (Rotated v161)     |
| `APP_DEBUG`           | **true**                | **false**                   | ⚠️ Debug disabled in production |
| `APP_URL`             | https://procuchain.test | https://www.procuchain.tech | Different domains               |
| `APP_LOCALE`          | en                      | en                          | ✅ Same                         |
| `APP_FALLBACK_LOCALE` | en                      | en                          | ✅ Same                         |
| `APP_FAKER_LOCALE`    | en_US                   | en_US                       | ✅ Same                         |

**Key Differences:**

- ⚠️ **Debug Mode**: Enabled locally for development, disabled on Heroku for security
- 🌐 **URL**: Local uses `.test` domain (Herd), Heroku uses live domain
- 🔧 **Environment**: Different environment affects service behavior
- 🔐 **APP_KEY**: Rotated for production security (v161) - environments now have unique encryption keys

---

### Database Configuration

| Variable          | Local      | Heroku                                                    | Notes                        |
| ----------------- | ---------- | --------------------------------------------------------- | ---------------------------- |
| `DB_CONNECTION`   | mysql      | mysql                                                     | ✅ Same                      |
| `DB_HOST`         | 127.0.0.1  | otmaa16c1i9nwrek.cbetxkdyhwsb.us-east-1.rds.amazonaws.com | Local vs AWS RDS             |
| `DB_PORT`         | **3307**   | **3306**                                                  | ⚠️ Non-standard port locally |
| `DB_DATABASE`     | procuchain | xkfyqxpzf5b8px0o                                          | Different databases          |
| `DB_USERNAME`     | procuchain | xje7x1nilxfvt9ih                                          | Different credentials        |
| `DB_PASSWORD`     | procuchain | i8mzfrk7l7g1543f                                          | Different credentials        |
| `DB_FOREIGN_KEYS` | true       | true                                                      | ✅ Same                      |

**Key Differences:**

- 🗄️ **Local MySQL**: Running on custom port 3307 (likely Docker)
- ☁️ **Heroku MySQL**: JawsDB add-on (AWS RDS us-east-1)
- 🔐 **Credentials**: Completely different between environments
- ⚠️ **Data Isolation**: Local and production databases are separate

**Additional Heroku Variable:**

- `JAWSDB_URL`: Full connection string for JawsDB

---

### Cache & Session Configuration

| Variable           | Local    | Heroku   | Notes             |
| ------------------ | -------- | -------- | ----------------- |
| `CACHE_DRIVER`     | database | database | ✅ Same           |
| `CACHE_STORE`      | database | database | ✅ Same           |
| `SESSION_DRIVER`   | database | database | ✅ Same           |
| `SESSION_LIFETIME` | 120      | 120      | ✅ Same (2 hours) |
| `SESSION_ENCRYPT`  | false    | false    | ✅ Same           |
| `SESSION_PATH`     | /        | /        | ✅ Same           |
| `SESSION_DOMAIN`   | null     | null     | ✅ Same           |

**Analysis:**

- ✅ **Consistent**: Both use database for cache and sessions
- 💡 **Why Database**: Avoids Redis memory limits on Heroku (30MB free tier)
- 📊 **Session Duration**: 2 hours for both environments

---

### Queue Configuration

| Variable           | Local         | Heroku                                                     | Notes           |
| ------------------ | ------------- | ---------------------------------------------------------- | --------------- |
| `QUEUE_CONNECTION` | redis         | redis                                                      | ✅ Same driver  |
| `REDIS_CLIENT`     | predis        | predis                                                     | ✅ Same         |
| `REDIS_HOST`       | **127.0.0.1** | **redis-12100.c323.us-east-1-2.ec2.redns.redis-cloud.com** | Local vs Cloud  |
| `REDIS_PORT`       | **6379**      | **12100**                                                  | Different ports |
| `REDIS_PASSWORD`   | **null**      | **kXgH8rQ3c2mOa7E0vu0oishIqc8WcH1a**                       | No auth locally |
| `REDIS_DB`         | (not set)     | 0                                                          | Heroku explicit |
| `REDIS_CACHE_DB`   | (not set)     | 0                                                          | Heroku explicit |

**Additional Heroku Variables:**

- `REDISCLOUD_URL`: Full connection string
- `REDIS_URL`: Full connection string

**Key Differences:**

- 🔴 **Local Redis**: Running on localhost without authentication
- ☁️ **Heroku Redis**: Redis Cloud add-on with authentication
- ⚡ **Purpose**: Used for queue processing (blockchain operations)
- 💾 **Cache**: Database used instead to save Redis memory

---

### File Storage Configuration

| Variable                      | Local     | Heroku        | Notes                      |
| ----------------------------- | --------- | ------------- | -------------------------- |
| `FILESYSTEM_DISK`             | **local** | **local**     | ✅ Same (on-chain storage) |
| `AWS_ACCESS_KEY_ID`           | (not set) | **(removed)** | No longer using S3         |
| `AWS_SECRET_ACCESS_KEY`       | (not set) | **(removed)** | No longer using S3         |
| `AWS_DEFAULT_REGION`          | (not set) | **(removed)** | No longer using S3         |
| `AWS_BUCKET`                  | (not set) | **(removed)** | No longer using S3         |
| `AWS_ENDPOINT`                | (not set) | **(removed)** | No longer using S3         |
| `AWS_USE_PATH_STYLE_ENDPOINT` | (not set) | **(removed)** | No longer using S3         |

**Key Changes:**

- ⛓️ **On-Chain Storage**: Both environments now use blockchain for file storage
- 📦 **No Cloud Storage**: DigitalOcean Spaces removed (as of v160)
- 💾 **Local Disk**: Used only for temporary application operations
- 🔗 **Blockchain Primary**: All procurement documents stored on MultiChain streams

**Note:** Files are stored directly on blockchain via MultiChain streams, eliminating the need for traditional cloud storage (S3/Spaces).

---

### Mail Configuration

| Variable            | Local                    | Heroku                   | Notes                |
| ------------------- | ------------------------ | ------------------------ | -------------------- |
| `MAIL_MAILER`       | **log**                  | **resend**               | ⚠️ Different mailers |
| `MAIL_FROM_ADDRESS` | no-reply@example.com | no-reply@example.com | ✅ Same              |
| `MAIL_FROM_NAME`    | ProcuChain               | ProcuChain               | ✅ Same              |
| `RESEND_API_KEY`    | re_dqXo9xdM...           | re_dqXo9xdM...           | ✅ Same API key      |

**Additional Heroku Variable:**

- `MAIL_SUPPORT_EMAIL`: admin@example.com

**Key Differences:**

- 📧 **Local**: Emails logged to `storage/logs/laravel.log` (not sent)
- 📨 **Heroku**: Emails sent via Resend API (live delivery)
- 🔑 **API Key**: Shared between environments (both could send if local mailer changed)

---

### MultiChain Blockchain Configuration

| Variable                        | Local                                        | Heroku                                       | Notes                 |
| ------------------------------- | -------------------------------------------- | -------------------------------------------- | --------------------- |
| `MULTICHAIN_CHAIN_NAME`         | procuchain                                   | procuchain                                   | ✅ Same (Fixed v158)  |
| `MULTICHAIN_RPC_HOST`           | **159.65.12.99**                             | **159.65.12.99**                             | ✅ Same (shared node) |
| `MULTICHAIN_RPC_PORT`           | **6486**                                     | **6486**                                     | ✅ Same               |
| `MULTICHAIN_RPC_USERNAME`       | multichainrpc                                | multichainrpc                                | ✅ Same               |
| `MULTICHAIN_RPC_PASSWORD`       | EnMtPc5rdZhfPhRJKsAkBzRRpNLURVMRbFqfEqrxFaQg | EnMtPc5rdZhfPhRJKsAkBzRRpNLURVMRbFqfEqrxFaQg | ✅ Same               |
| `MULTICHAIN_USE_SSL`            | false                                        | false                                        | ✅ Same               |
| `MULTICHAIN_VERIFY_SSL`         | false                                        | false                                        | ✅ Same               |
| `MULTICHAIN_CONNECTION_TIMEOUT` | 30                                           | 30                                           | ✅ Same               |
| `MULTICHAIN_MAX_RETRIES`        | 3                                            | 3                                            | ✅ Same               |

**Additional Heroku Variable:**

- `MULTICHAIN_MASTER_PORT`: 7447 (not in local)

**Key Findings:**

- ✅ **Shared Blockchain Node**: Both environments connect to the same MultiChain node (159.65.12.99)
- ✅ **Variable Naming**: Both use `MULTICHAIN_CHAIN_NAME=procuchain` (Fixed v158-v159)
- 🔗 **Same Network**: Local and production share the same blockchain data
- 🔐 **Same Credentials**: Both use the same RPC authentication
- ⛓️ **On-Chain Storage**: All files stored directly on blockchain streams

---

### Logging & Monitoring

| Variable                   | Local     | Heroku    | Notes                  |
| -------------------------- | --------- | --------- | ---------------------- |
| `LOG_CHANNEL`              | stack     | stack     | ✅ Same                |
| `LOG_STACK`                | single    | single    | ✅ Same                |
| `LOG_LEVEL`                | **debug** | **error** | ⚠️ Different verbosity |
| `LOG_DEPRECATIONS_CHANNEL` | null      | null      | ✅ Same                |

**Heroku-Only Variables:**

- `LOGDNA_KEY`: 3520cf89dff619d757bccf4761b6cf0f (LogDNA integration)
- `NEW_RELIC_LICENSE_KEY`: e52a30c1c500061b325f1686a76d2ead94c8NRAL
- `NEW_RELIC_LOG`: stdout

**Key Differences:**

- 📊 **Log Level**: Debug locally (verbose), Error only on Heroku (minimal)
- 📈 **APM**: New Relic enabled on Heroku for performance monitoring
- 📝 **Log Aggregation**: LogDNA on Heroku for centralized logging
- 🔍 **Local Logs**: File-based in `storage/logs/`

---

### Error Tracking (Sentry)

| Variable                    | Local               | Heroku                                           | Notes               |
| --------------------------- | ------------------- | ------------------------------------------------ | ------------------- |
| `SENTRY_LARAVEL_DSN`        | **(commented out)** | **https://3ad9bddf9f1a4fa125d3cdde6fb4c82f@...** | ⚠️ Disabled locally |
| `SENTRY_TRACES_SAMPLE_RATE` | 1.0                 | 0.1                                              | Different sampling  |
| `SENTRY_ENABLE_LOGS`        | (not set)           | true                                             | Heroku explicit     |
| `SENTRY_LOG_LEVEL`          | (not set)           | error                                            | Heroku explicit     |

**Key Differences:**

- 🚫 **Local**: Sentry disabled (DSN commented out)
- ✅ **Heroku**: Sentry fully enabled for error tracking
- 📊 **Sampling**: 100% locally (if enabled), 10% on Heroku (cost optimization)
- 🔍 **Purpose**: Production error monitoring and alerting

---

### WebPush Notifications (VAPID)

| Variable            | Local                              | Heroku                             | Notes   |
| ------------------- | ---------------------------------- | ---------------------------------- | ------- |
| `VAPID_PUBLIC_KEY`  | VAPID_PUBLIC_KEY_TRUNCATED                   | VAPID_PUBLIC_KEY_TRUNCATED                   | ✅ Same |
| `VAPID_PRIVATE_KEY` | VAPID_PRIVATE_KEY_TRUNCATED                   | VAPID_PRIVATE_KEY_TRUNCATED                   | ✅ Same |
| `VAPID_SUBJECT`     | mailto:admin@example.com | mailto:admin@example.com | ✅ Same |

**Analysis:**

- ✅ **Shared Keys**: Same VAPID keys used in both environments
- 🔔 **Browser Push**: Enabled in both environments
- 🔑 **Key Security**: Private key should be kept secret

---

### Broadcasting

| Variable               | Local | Heroku | Notes   |
| ---------------------- | ----- | ------ | ------- |
| `BROADCAST_CONNECTION` | log   | log    | ✅ Same |

**Analysis:**

- Both environments use log driver for broadcasting (no real-time websockets)

---

## Critical Issues & Recommendations

### 🔴 Critical Issues

1. **MultiChain Variable Name Mismatch** ✅ **RESOLVED**
    - **Fixed**: Added `MULTICHAIN_CHAIN_NAME` to Heroku (v158)
    - **Fixed**: Removed legacy `CHAIN_NAME` from Heroku (v159)
    - **Status**: Both environments now use `MULTICHAIN_CHAIN_NAME=procuchain`

2. **Shared Blockchain Node**
    - Both environments connect to the same blockchain node
    - **Risk**: Local development writes to production blockchain
    - **Recommendation**: Use separate blockchain networks for dev/prod

3. **Shared APP_KEY** ✅ **RESOLVED**
    - **Fixed**: Generated and set new APP_KEY for Heroku (v161)
    - **Status**: Environments now have unique encryption keys
    - **Impact**: All production users logged out (expected behavior)

### ⚠️ Important Considerations

4. **Missing Local S3 Credentials**
    - Local uses filesystem, Heroku uses DigitalOcean Spaces
    - **Impact**: File storage behavior differs between environments
    - **Recommendation**: Add optional S3 config to local `.env` for testing

5. **Mail Driver Difference**
    - Local logs emails, Heroku sends them
    - **Impact**: Email testing requires checking logs locally
    - **Good Practice**: Keep this difference for safety

6. **Debug Mode**
    - Local has debug enabled, Heroku disabled
    - **Status**: ✅ This is correct and expected

7. **Sentry Sampling Rate**
    - Local: 100% (if enabled)
    - Heroku: 10%
    - **Impact**: Some errors might not be captured on Heroku
    - **Recommendation**: Consider increasing to 25-50% for better coverage

### ✅ Good Configurations

8. **Database Isolation**
    - Separate databases prevent data corruption
    - ✅ Good practice

9. **Redis Usage**
    - Both use Redis only for queues, not cache
    - ✅ Good optimization for free tier limits

10. **Log Levels**
    - Debug locally, Error on Heroku
    - ✅ Appropriate for each environment

---

## Environment Variables Missing on Heroku

Variables in local `.env` but **NOT** on Heroku:

1. ~~`MULTICHAIN_CHAIN_NAME`~~ - ✅ **Added** (v158)
2. `SENTRY_LARAVEL_DSN` - Present but different variable format
3. `SENTRY_TRACES_SAMPLE_RATE` - Present but different value (0.1 vs 1.0)

---

## Environment Variables Only on Heroku

Variables on Heroku but **NOT** locally:

1. ~~**AWS/S3 Configuration** (DigitalOcean Spaces)~~ - ✅ **Removed** (v160)
    - ~~`AWS_ACCESS_KEY_ID`~~
    - ~~`AWS_SECRET_ACCESS_KEY`~~
    - ~~`AWS_DEFAULT_REGION`~~
    - ~~`AWS_BUCKET`~~
    - ~~`AWS_ENDPOINT`~~
    - ~~`AWS_USE_PATH_STYLE_ENDPOINT`~~

2. **Redis Cloud Configuration**
    - `REDISCLOUD_URL`
    - `REDIS_URL`
    - `REDIS_CACHE_DB`
    - `REDIS_DB`

3. **JawsDB MySQL**
    - `JAWSDB_URL`

4. **Monitoring Tools**
    - `LOGDNA_KEY`
    - `NEW_RELIC_LICENSE_KEY`
    - `NEW_RELIC_LOG`

5. **Additional Configuration**
    - `MULTICHAIN_MASTER_PORT`
    - `MAIL_SUPPORT_EMAIL`
    - `SENTRY_ENABLE_LOGS`
    - `SENTRY_LOG_LEVEL`

---

## Action Items

### High Priority

- [x] **Fix MultiChain variable name inconsistency** ✅ **COMPLETED**
    - Added `MULTICHAIN_CHAIN_NAME` to Heroku (v158)
    - Removed legacy `CHAIN_NAME` from Heroku (v159)
    - Both environments now use consistent variable naming

- [x] **Remove unused S3/Spaces configuration** ✅ **COMPLETED**
    - Removed all AWS/S3 variables from Heroku (v160)
    - Application now uses on-chain storage exclusively
        - [x] **Consider separate blockchain networks** ✅ **IN PROGRESS**
            - Create separate MultiChain node for development (see `scripts/install_procuchain_dev.sh`)
            - Update local `.env` to use dev node values printed by the script
            - Keep production node isolated

- [x] **Rotate APP_KEY for production** ✅ **COMPLETED**
    - Generated new APP_KEY for Heroku (v161)
    - Updated Heroku config
    - All users logged out (expected)

### Medium Priority

- [x] ~~**Add S3 config to local environment (optional)**~~ - **N/A** (Using on-chain storage)

- [ ] **Increase Sentry sampling rate**
    - Consider 25-50% instead of 10%
    - Better error coverage

- [ ] **Document environment differences**
    - Update `.env.example` with comments
    - Add to deployment documentation

### Low Priority

- [ ] **Standardize Redis configuration**
    - Add explicit `REDIS_DB` and `REDIS_CACHE_DB` to local
    - Consistency between environments

---

## Quick Reference: Starting Services

### Local Development

```bash
# Start Laravel server
php artisan serve

# Start queue worker
php artisan queue:work

# Start Vite dev server
npm run dev

# Or use concurrent
composer run dev
```

### Heroku (Automatic)

```
web: php artisan inertia:start-ssr & heroku-php-apache2 public/
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

---

## Conclusion

The configuration is well-structured with appropriate differences between development and production. Recent improvements include:

**✅ Resolved Issues:**

1. **MultiChain variable name** - Now consistent across environments (v158-v159)
2. **Cloud storage removed** - Using on-chain storage exclusively (v160)
3. **APP_KEY rotation** - Production now has unique encryption key (v161)

**⚠️ Remaining Considerations:**

1. **Shared blockchain node** - Local and production share the same blockchain (consider separate networks)

The application is now production-ready with proper on-chain file storage implementation.

---

**Document Maintained By:** DevOps Team  
**Next Review:** After addressing critical issues
