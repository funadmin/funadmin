-- Document 1.4.0 reader feedback; generated from Schema::feedback(). Prefix rewritten by host migration runner.
create table `fun_document_feedback` (`id` bigint unsigned not null auto_increment primary key, `document_id` bigint unsigned not null, `revision_no` int unsigned not null default '0', `helpful` tinyint(1) not null, `reasons` json not null, `comment` varchar(500) not null default '', `voter` char(64) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `fun_document_feedback` add unique `document_feedback_document_id_voter_unique`(`document_id`, `voter`);
alter table `fun_document_feedback` add index `document_feedback_document_id_helpful_index`(`document_id`, `helpful`);
alter table `fun_document_feedback` add index `document_feedback_updated_at_index`(`updated_at`);
