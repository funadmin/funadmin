-- CMS 1.5.0 page attached form; generated from PageFormSchema.php.
alter table `fun_cms_content_revision` add `form_code` varchar(64) not null default '' after `robots`;
alter table `fun_cms_category_locale` add `form_code` varchar(64) not null default '' after `detail_template`;
