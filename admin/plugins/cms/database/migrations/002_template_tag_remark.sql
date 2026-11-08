-- CMS 1.3.1 template tag remark; generated from TemplateTagRemarkSchema.php.
alter table `fun_cms_template_tag` add `remark` varchar(255) not null default '' after `name`;
