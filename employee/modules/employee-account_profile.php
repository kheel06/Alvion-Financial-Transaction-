<?php
/**
 * Employee self-service account profile
 * Reuses the shared profile page used by other roles.
 */
$account_allowed_roles   = ['employee'];
$account_page_title      = 'My HR Profile';
$account_heading         = 'My HR Profile';
$account_description     = 'Update your personal information, contact details, and profile photo.';
$account_upload_prefix   = 'employee_profile';

require_once __DIR__ . '/../../profile/employee-profile.php';

