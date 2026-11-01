package compliance

import "context"

// RecordNameHitIfMatched records an exact name match for moderator review.
// A match does not block saving: a different person may have the same name.
func (s *Repository) RecordNameHitIfMatched(ctx context.Context, name string) error {
	objection, matched, err := s.FindExact(ctx, name)
	if err != nil || !matched {
		return err
	}

	return s.RecordHit(ctx, objection.ID)
}
