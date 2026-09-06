<?php
/**
 * Admin account profile, reusing shared profile module.
 */
$account_allowed_roles = ['admin', 'super admin'];
$account_page_title    = 'Admin Account Profile';
$account_heading       = 'Admin Account';
$account_description   = 'Maintain your administrator contact details and profile photo.';
$account_upload_prefix = 'admin_profile';

require_once __DIR__ . '/../../profile/employee-profile.php';

