create table brand_alias (
  name varchar(255) not null primary key,
  item_id int not null -- references item (id)
);

create index brand_alias_item_id on brand_alias (item_id);
