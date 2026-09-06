<?php
/**
 * Staff / Supervisor account profile leveraging the shared module.
 */
$account_allowed_roles = ['staff', 'staff supervisor', 'super admin'];
$account_page_title    = 'Team Account Profile';
$account_heading       = 'Team Account Profile';
$account_description   = 'Review and update your team account information.';
$account_upload_prefix = 'staff_profile';

require_once __DIR__ . '/../../profile/employee-profile.php';

