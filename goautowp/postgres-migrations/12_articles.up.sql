create table article (
  id                     serial primary key,
  name                   varchar(100)                        null,
  catname                varchar(100)                        null unique,
  last_editor_id         int                                 null,
  last_edit_date         timestamp with time zone                           null,
  add_date               timestamp with time zone default CURRENT_TIMESTAMP not null,
  author_id              int                                 null,
  enabled                boolean default false               not null,
  first_enabled_datetime timestamp with time zone            null,
  description            varchar(255)                        not null,
  preview_width          int                                 null,
  preview_height         int                                 null,
  preview_filename       varchar(50)                         null,
  ratio                  float                               not null,
  html                   text                                not null
);

create index article_author_id on article (author_id);
create index article_first_enabled_datetime on article (first_enabled_datetime);
create index article_last_editor_id on article (last_editor_id);

-- constraint articles_fk foreign key (last_editor_id) references users (id),
-- constraint articles_fk1 foreign key (author_id) references users (id)
