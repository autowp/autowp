alter table article add column html longtext not null;

update article set html=(select html from htmls where id = article.html_id);

alter table article drop constraint articles_fk2, drop column html_id;

drop table htmls;
