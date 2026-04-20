insert into article (id, html, name, catname, last_editor_id, last_edit_date, add_date, author_id,
    enabled, first_enabled_datetime, description, preview_width, preview_height, preview_filename, ratio)
values (1, 'Test html', 'Test article', 'test-article', null, null, now(), null, true, now(), 'Test description', 100, 100, 'test.jpg', 0);

insert into voting (id, name, multivariant, begin_date, end_date, votes, text)
values (1, 'Test vote', false, NOW(),  NOW() + INTERVAL '1 year', 0, 'Voting text');

insert into voting_variant(id, voting_id, name, votes, position, text)
values (1, 1, 'First variant', 0, 1, 'First variant text'),
       (2, 1, 'Second variant', 0, 2, 'Second variant text');

insert into article ( id, html, name, catname, last_editor_id, last_edit_date, add_date, author_id,
  enabled, first_enabled_datetime, description, preview_width, preview_height, preview_filename, ratio)
values (1, 'Test html', 'Test article', 'test-article', null, null, now(), null, true, now(), 'Test description', 100, 100, 'test.jpg', 0);
