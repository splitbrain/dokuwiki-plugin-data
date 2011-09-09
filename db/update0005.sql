alter table pages add entry default '';
drop index idx_page;
create index idx_page on pages(page);
create unique index idx_entry on pages(page,entry);
