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
- `AWS_REGION`: e.g., `ap-southeast-3` (Malaysia).
- `ECR_REPOSITORY`: The ECR repository name.
- `ECS_CLUSTER`: The ECS cluster name.
- `ECS_SERVICE`: The ECS service name.
- `ECS_TASK_DEFINITION_NAME`: The family name of the task definition.
- `CONTAINER_NAME`: The name of the container in the task definition (usually `app`).

## 3. Database Migrations
Migrations are handled in two layers due to the multi-tenant architecture.

### Central Database
Migrations for the central database (tenants, users, plans) are automatically run during the container startup if the environment variable `RUN_MIGRATIONS` is set to `true` in the ECS Task Definition.

### Tenant Databases
Tenant-specific migrations (invoices, GL, etc.) are **NOT** automatically run by the container startup to avoid performance issues during scaling.

To migrate tenant databases after a deployment:
1. Access the container shell (via AWS ECS Exec or a management task).
2. Run:
   ```bash
   php artisan tenants:migrate
   ```

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

## 5. Troubleshooting
- **Logs**: View application logs in **AWS CloudWatch** under the log group associated with the ECS Task.
- **Service Stability**: If a deployment hangs, check the ECS Service "Events" tab for errors (e.g., health check failures).
- **Rollback**: If a deployment fails, you can manually update the ECS Service to use the previous known-good Task Definition revision.
