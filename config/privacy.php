<?php

/*
 * Single source of truth for which version of the privacy policy users
 * are consenting to. Bumping this string is the trigger for re-prompting
 * users to re-accept on next login (a future feature) — keep edits
 * deliberate.
 */
return [
    'current_version' => env('PRIVACY_POLICY_VERSION', '2026-06-06'),

    /*
     * DPO inbox shown on the privacy page and used as the From: address
     * for data-export / erasure confirmations. Override per environment.
     */
    'dpo_email' => env('DPO_EMAIL', 'dpo@bukucloud.com'),

    /*
     * Legal entity name shown in the policy. Self-hosted deployments
     * override this so the policy reads as "X Sdn Bhd is the controller"
     * rather than BukuCloud — the deployment's own operator becomes the
     * data controller for their tenants.
     */
    'controller_name' => env('PRIVACY_CONTROLLER_NAME', 'BukuCloud Sdn Bhd'),
];
