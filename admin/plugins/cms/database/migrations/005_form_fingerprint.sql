-- CMS 1.4.1 form submission fingerprint; generated from FormFingerprintSchema.php.
alter table `fun_cms_form_submission` add `fingerprint` char(64) not null default '' after `member_id`;
alter table `fun_cms_form_submission` add index `cms_form_submission_fingerprint_idx`(`site_id`, `form_id`, `fingerprint`, `created_at`);
