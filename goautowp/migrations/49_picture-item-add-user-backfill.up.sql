UPDATE picture_item pi
SET add_user_id = sub.user_id
FROM (
  SELECT DISTINCT ON (lep.picture_id, lei.item_id)
    lep.picture_id,
    lei.item_id,
    le.user_id
  FROM log_event le
  JOIN log_event_picture lep ON lep.log_event_id = le.id
  JOIN log_event_item lei ON lei.log_event_id = le.id
  ORDER BY lep.picture_id, lei.item_id, le.created_at ASC
) sub
WHERE pi.picture_id = sub.picture_id
  AND pi.item_id = sub.item_id
  AND pi.add_user_id IS NULL;

UPDATE picture_item pi
SET add_user_id = p.owner_id
FROM picture p
WHERE pi.picture_id = p.id
  AND pi.add_user_id IS NULL
  AND p.owner_id IS NOT NULL;
