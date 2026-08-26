# Copilot Credits Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Meter Accountant Copilot per tenant with monthly included credits + purchasable top-ups (never expire).

**Architecture:** Tenant balance/ledger; central `CopilotCreditPurchase` + ToyyibPay webhook (mirror ExtraSeatPurchase); enforce on `CopilotController::chat`; share via Inertia; Plan & Usage UI.

**Tech:** Laravel 12, Inertia React, Stancl tenancy, ToyyibPay.

## Tasks

1. Central: `plans.copilot_credits_monthly` + PlanSeeder 70/140/320
2. Tenant: `copilot_credit_balances` + `copilot_credit_ledger`
3. Central: `copilot_credit_purchases` + model
4. `CopilotCreditService` (ensure period, burn, grant purchase, snapshot)
5. Gate chat + Inertia share + UI (copilot widget + Plan.jsx)
6. Purchase + webhook routes
7. Unit/feature tests

See design: `docs/superpowers/specs/2026-08-17-copilot-credits-design.md`
