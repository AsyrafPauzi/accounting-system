# Deployment Documentation

This document explains how the Accounter (Malaysia Edition) application is deployed and maintained in production.

## 1. Architecture Overview
The application uses a modern cloud-native stack:
- **Platform**: AWS ECS Fargate (Serverless container orchestration).
- **Registry**: Amazon ECR (Docker image storage).
- **CI/CD**: GitHub Actions (Automated build and deployment).
- **Database**: Amazon RDS (MySQL 8.0).
- **Caching/Session**: Database-backed (on the central RDS instance).

## 2. Automated Deployment (GitHub Actions)
Deployments are automated whenever code is merged into the `main` branch.

### How it works:
1. **Trigger**: A push to the `main` branch.
2. **Build**: GitHub Actions builds the Docker image (compiles React assets and installs PHP dependencies).
3. **Push**: The image is pushed to Amazon ECR with a unique tag (the commit SHA).
4. **Deploy**:
    - The current ECS Task Definition is downloaded.
    - The container image is updated to the newly pushed tag.
    - A new revision of the Task Definition is created and deployed to the ECS Service.
    - ECS performs a rolling update (zero-downtime).

### Configuration (Required GitHub Secrets):
You must set these in `GitHub Repo Settings > Secrets and variables > Actions`:
- `AWS_ACCESS_KEY_ID`: IAM User Access Key.
- `AWS_SECRET_ACCESS_KEY`: IAM User Secret Key.
- `AWS_REGION`: e.g., `ap-southeast-5` (Malaysia).
- `ECR_REPOSITORY`: The ECR repository name.
- `ECS_CLUSTER`: The ECS cluster name.
- `ECS_SERVICE`: The ECS service name.
- `ECS_TASK_DEFINITION_NAME`: The family name of the task definition.
- `CONTAINER_NAME`: The name of the container in the task definition (usually `app`).

## 3. Database Migrations

Migrations are handled in two layers due to the multi-tenant architecture.

| Layer | Path | Command |
|-------|------|---------|
| Central (users, tenants, plans, OCR settings, …) | `database/migrations/` | `php artisan migrate --force` |
| Tenant (invoices, bills, GL, …) | `database/migrations/tenant/` | `php artisan tenants:migrate --force` |

### Automatic migrations on deploy (recommended)

Set **`RUN_MIGRATIONS=true`** on the ECS task definition for the app service.

On each container start, `docker/entrypoint.sh` runs (in order):

1. `php artisan migrate --force --isolated` — central schema

`tenants:migrate` is **not** run on every boot by default. With many tenant databases, iterating all tenants on each ECS task start adds minutes to boot time. Run tenant migrations via a one-off task instead (see below).

To opt back into boot-time tenant migrations (e.g. small installs), set:

```
RUN_TENANT_MIGRATE=1
```

**`db:seed` is not run on startup.** Seeders include demo/test accounts and must not run on every production deploy.

When there are no pending central migrations, the migrate step finishes in seconds and the container continues to nginx/supervisor as usual.

### Tenant migrations on deploy (recommended)

After deploying schema changes under `database/migrations/tenant/`, run a **one-off ECS task** (or ECS Exec) before or immediately after the rolling deploy:

```bash
php artisan tenants:migrate --force
```

Do **not** rely on every web container boot to migrate all tenants in production. Use `RUN_TENANT_MIGRATE=1` only for small/staging environments.

### ECS task definition

Add to the app container environment:

```
RUN_MIGRATIONS=true
```

You do **not** need `RUN_TENANT_MIGRATE=1` on the web service in production. Keep a one-off migrate task or ECS Exec for `tenants:migrate` and emergencies.

### First-time production database / seeding

Run once manually (ECS Exec or a management task), not on every deploy:

```bash
php artisan migrate --force
php artisan tenants:migrate --force
php artisan db:seed --force   # only on initial install, or when you intend to re-seed
```

After changing roles or plan permissions in code, prefer:

```bash
php artisan app:sync-roles-permissions
```

instead of full `db:seed`.

### Manual migration (fallback)

If `RUN_MIGRATIONS` is unset or a deploy shipped code before migrations ran:

```bash
php artisan migrate --force
php artisan tenants:migrate --force
```

**Always run central `migrate` before `tenants:migrate`.** Running only `tenants:migrate` leaves the central database behind (e.g. missing `users.theme_preference` or `ocr_settings`).

Check status:

```bash
php artisan migrate:status
```

### Scaling note

`tenants:migrate` runs against **every** tenant database on each container start. This is fine for a small tenant count. If you grow to many tenants or frequent autoscaling, consider:

- Startup: only `php artisan migrate --force --isolated` (set `RUN_MIGRATIONS` to run central only — customize entrypoint), and
- A single one-off task per deploy for `tenants:migrate`

## 4. Mail (Email Sending)

The app uses **Resend** (`resend.com`) as its production email transport. Resend's free tier gives 3,000 emails/month / 100/day, which covers all early-stage SaaS volume; paid tiers start at $20/month for 50k emails.

### What sends mail today

| Mailable | Trigger |
|---|---|
| Estimate emails | "Email" button on `/estimates` |
| Firm invitation (existing client) | When a firm invites an SME tenant they already manage |
| Email verification | New user registration |
| Password reset | Forgot-password flow |

All of those are queued via the standard Laravel mail queue, so a Resend outage delays delivery but does **not** block user requests.

### One-time provider setup

1. **Sign up at [resend.com](https://resend.com)** and verify your account email.
2. **Add and verify your sending subdomain** — `app.bukucloud.com`:
    - Resend → **Domains** → **Add Domain** → enter `app.bukucloud.com` (NOT the apex `bukucloud.com`; the marketing site lives on the apex and this keeps app-transactional mail cleanly scoped to the app).
    - Resend will show you 3 DNS records to publish on whatever DNS host owns `bukucloud.com` (Cloudflare, Route 53, etc.):

      | Type | Host | Value |
      |---|---|---|
      | TXT | `send.app.bukucloud.com` | `v=spf1 include:amazonses.com ~all` (Resend gives the exact value) |
      | TXT | `resend._domainkey.app.bukucloud.com` | (long DKIM key, copy from Resend) |
      | MX (optional but recommended) | `send.app.bukucloud.com` | `feedback-smtp.us-east-1.amazonses.com` priority 10 |

      Plus a DMARC record on `app.bukucloud.com` if you don't already inherit one from the apex:

      | Type | Host | Value |
      |---|---|---|
      | TXT | `_dmarc.app.bukucloud.com` | `v=DMARC1; p=none; rua=mailto:dmarc-reports@bukucloud.com` |

    - Wait 5–60 minutes for DNS propagation, then click **Verify** in the Resend dashboard.

3. **Generate an API key** at [resend.com/api-keys](https://resend.com/api-keys). Pick **Sending access** scope (not the broader admin scope).

4. **Add to ECS task definition environment variables**:

    ```
    MAIL_MAILER=resend
    RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxx
    MAIL_FROM_ADDRESS=no-reply@app.bukucloud.com
    MAIL_FROM_NAME=BukuCloud
    ```

    `MAIL_FROM_ADDRESS` must be on the verified domain or Resend will reject the send.

5. **Redeploy.** New ECS tasks will pick up the env vars on boot.

### Smoke test after switching

```bash
php artisan tinker
>>> Mail::raw('Resend smoke test', fn ($m) => $m->to('you@yourdomain.com')->subject('Test'));
```

Within ~30 seconds the email should arrive. If it doesn't:

- Check `storage/logs/laravel.log` for `Symfony\Component\Mailer\Exception\TransportException` — usually means the API key is wrong or the from-address domain isn't verified.
- Check the Resend dashboard → **Logs** for delivery status. Bounces, deferred, and delivered are all logged with full SMTP transcripts.

### Switching to a different provider later

Every transport listed in `config/mail.php` (`ses`, `postmark`, `smtp`, `log`) is already wired. Switching is a `.env` change plus the relevant credentials — no code changes:

| Switch to | Set | Plus credentials |
|---|---|---|
| AWS SES | `MAIL_MAILER=ses` | Requires `composer require aws/aws-sdk-php` and `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` |
| Postmark | `MAIL_MAILER=postmark` | `POSTMARK_API_KEY` |
| Generic SMTP (Brevo, Mailjet, etc.) | `MAIL_MAILER=smtp` | `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` |
| Local dev / silent | `MAIL_MAILER=log` | Nothing — emails go to `storage/logs/laravel.log` |

### Self-hosted instances

Self-hosted defaults to `MAIL_MAILER=log` (see `.env.example.self-hosted`). Each customer's IT/engineer picks their own transport during install — they typically point it at their company's existing SMTP relay. The engineer runbook covers this in the post-install configuration phase.

## 5. Manual Deployment (Fallback)
If GitHub Actions is unavailable, you can deploy manually from your local machine:

1. **Login to ECR**:
   ```bash
   aws ecr get-login-password --region <region> | docker login --username AWS --password-stdin <aws_account_id>.dkr.ecr.<region>.amazonaws.com
   ```
2. **Build and Tag**:
   ```bash
   docker build -t <repo_name> .
   docker tag <repo_name>:latest <aws_account_id>.dkr.ecr.<region>.amazonaws.com/<repo_name>:latest
   ```
3. **Push**:
   ```bash
   docker push <aws_account_id>.dkr.ecr.<region>.amazonaws.com/<repo_name>:latest
   ```
4. **Update Service**:
   ```bash
   aws ecs update-service --cluster <cluster_name> --service <service_name> --force-new-deployment --region <region>
   ```

Ensure `RUN_MIGRATIONS=true` on the service so new tasks apply migrations on boot.

## 6. Troubleshooting
- **Logs**: View application logs in **AWS CloudWatch** under the log group associated with the ECS Task.
- **Scheduled jobs** (subscription renewals, invoice reminders, recurring invoices): the ECS container runs Laravel's scheduler via Supervisor (`docker/supervisor.conf`, program `laravel-scheduler`). Check `storage/logs/scheduler.log` inside the task if renewals or reminders stop firing.
- **500 after deploy on central features** (profile theme, `/admin/ocr`): usually missing central migrations — run `php artisan migrate --force` and confirm pending migrations in `migrate:status`.
- **500 on tenant features** (bills, invoices): usually missing tenant migrations — run `php artisan tenants:migrate --force`.
- **Service Stability**: If a deployment hangs, check the ECS Service "Events" tab for errors (e.g., health check failures). Slow `tenants:migrate` on many DBs can delay health checks — see scaling note above.
- **Rollback**: If a deployment fails, you can manually update the ECS Service to use the previous known-good Task Definition revision.
