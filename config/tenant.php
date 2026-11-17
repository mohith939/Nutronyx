<?php
declare(strict_types=1);

/**
 * NUTRONYX — Multi-Tenant Client Website Isolation Configuration
 *
 * When integrating this Admin Core application into a new client's website:
 * 
 * 1. SINGLE-TENANT / CLIENT WEBSITE ISOLATION (Recommended for dedicated client deployments):
 *    Uncomment or define NUTRONYX_TENANT_ID below, OR set TENANT_WEBSITE_ID in your .env file.
 *    - The store dropdown switcher is COMPLETELY HIDDEN from sidebar, header, and governance.
 *    - The client can NEVER see other clients' stores, brands, or backend details.
 *    - All database queries (orders, products, customers, payments, inventory, promotions)
 *      are 100% strictly scoped and locked to this website ID.
 *
 * 2. MULTI-STORE AGENCY / SAAS MODE (Default when undefined):
 *    Leave NUTRONYX_TENANT_ID undefined or commented out.
 *    - Store access is dynamically governed by each user's assigned allowed_stores.
 *    - A client staff user assigned to only 1 store will NOT see any store dropdown or other client stores.
 *    - Only a platform Super Admin with multi-store access will see the switcher for their stores.
 */

// To lock this admin deployment to a specific client website, define its ID below:
// define('NUTRONYX_TENANT_ID', 'web_nutronyx_d2c');
