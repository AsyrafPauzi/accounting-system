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
2. `php artisan tenants:migrate --force --isolated` — all tenant databases

`--isolated` uses a cache lock so that during a rolling deploy only one task applies pending migrations; other tasks skip quickly if migrations are already running or complete.

**`db:seed` is not run on startup.** Seeders include demo/test accounts and must not run on every production deploy.

When there are no pending migrations, both commands finish in seconds and the container continues to nginx/supervisor as usual.

### ECS task definition

Add to the app container environment:

```
RUN_MIGRATIONS=true
```

You do **not** need a separate one-off migration task after each deploy if this is set. Keep a one-off task or ECS Exec only for emergencies or debugging.

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

## 4. Manual Deployment (Fallback)
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

## 5. Troubleshooting
- **Logs**: View application logs in **AWS CloudWatch** under the log group associated with the ECS Task.
- **500 after deploy on central features** (profile theme, `/admin/ocr`): usually missing central migrations — run `php artisan migrate --force` and confirm pending migrations in `migrate:status`.
- **500 on tenant features** (bills, invoices): usually missing tenant migrations — run `php artisan tenants:migrate --force`.
- **Service Stability**: If a deployment hangs, check the ECS Service "Events" tab for errors (e.g., health check failures). Slow `tenants:migrate` on many DBs can delay health checks — see scaling note above.
- **Rollback**: If a deployment fails, you can manually update the ECS Service to use the previous known-good Task Definition revision.
