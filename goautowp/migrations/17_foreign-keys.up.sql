ALTER TABLE article
  ADD constraint articles_fk foreign key (last_editor_id) references users (id),
  ADD  constraint articles_fk1 foreign key (author_id) references users (id);

ALTER TABLE voting_variant_vote
  ADD constraint voting_variant_votes_ibfk_1 foreign key (user_id) references users (id);

ALTER TABLE picture_moder_vote_template
  ADD constraint picture_moder_vote_template_user_id_fk foreign key (user_id) references users (id);

ALTER TABLE brand_alias
  ADD constraint brand_alias_item_id_fk foreign key (item_id) references item (id);

alter table transaction
  add constraint transaction_users_id_fk foreign key (user_id) references users (id);

create index transaction_user_id_index on transaction (user_id);

create index user_user_preferences_to_user_id_index on user_user_preferences (to_user_id);

alter table user_user_preferences
  add constraint user_user_preferences_users_id_fk foreign key (user_id) references users;

alter table user_user_preferences
  add constraint user_user_preferences_users_id_fk_2 foreign key (to_user_id) references users;

alter table vehicle_type
  add constraint vehicle_type_vehicle_type_id_fk foreign key (parent_id) references vehicle_type;

