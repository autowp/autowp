ALTER TABLE article
  ADD constraint articles_fk foreign key (last_editor_id) references users (id),
  ADD  constraint articles_fk1 foreign key (author_id) references users (id);

ALTER TABLE voting_variant_vote
  ADD constraint voting_variant_votes_ibfk_1 foreign key (user_id) references users (id);

ALTER TABLE picture_moder_vote_template
  ADD constraint picture_moder_vote_template_user_id_fk foreign key (user_id) references users (id);

ALTER TABLE brand_alias
  ADD constraint brand_alias_item_id_fk  foreign key (item_id) references item (id);
