-- Document 1.4.0 document sort order; generated from Schema::documentSort(). Prefix rewritten by host migration runner.
alter table `fun_documents` add `sort_order` int not null default '0' after `applies_to`;
