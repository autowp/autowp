create table picture_moder_vote_template (
  id      serial primary key,
  user_id int not null, -- references users (id),
  reason  varchar(80) not null,
  vote    int not null
);

create index picture_moder_vote_template_user_id on picture_moder_vote_template (user_id);
