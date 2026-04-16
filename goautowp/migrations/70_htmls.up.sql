alter table article add column html longtext not null default '';

update article set html=(select html from htmls where id = article.html_id);

alter table article drop column html_id;

drop table htmls;
