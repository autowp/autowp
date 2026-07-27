## ADDED Requirements

### Requirement: Perceptual Hash Indexing
Whenever a picture with an image becomes indexable, the system SHALL asynchronously
compute a 256-bit PDQ perceptual hash of its image and record it, keyed by picture ID.
Indexing SHALL be idempotent per picture: a picture is only enqueued for hashing while
it has no recorded hash.

#### Scenario: A newly uploaded picture is indexed
- **WHEN** a picture with an image is queued for indexing and does not yet have a
  recorded hash
- **THEN** its perceptual hash is computed and recorded

#### Scenario: An already-hashed picture is not re-queued
- **WHEN** the system looks for pictures to enqueue for hashing
- **THEN** pictures that already have a recorded hash are excluded

### Requirement: Duplicate Candidate Detection
After a picture's hash is recorded, the system SHALL compute the Hamming distance
between its hash and every other recorded picture's hash, and SHALL record a candidate
duplicate relationship, in both directions, for every pair whose distance is within the
configured match threshold. Recomputing a pair's distance (e.g. after re-indexing)
SHALL update the existing relationship's distance rather than create a duplicate one.

#### Scenario: Two pictures within the match threshold
- **WHEN** two pictures' hashes have a Hamming distance within the configured threshold
- **THEN** a candidate duplicate relationship is recorded for both
  (picture A → picture B) and (picture B → picture A), with the same distance

#### Scenario: Two pictures outside the match threshold
- **WHEN** two pictures' hashes have a Hamming distance greater than the configured
  threshold
- **THEN** no candidate duplicate relationship is recorded for that pair

### Requirement: Moderator Dismissal Is Permanent
The system SHALL let a moderator mark a candidate duplicate relationship as dismissed
(not actually a duplicate). Once dismissed, that relationship SHALL remain dismissed
even if the underlying hashes or hashing algorithm later change, until a moderator
explicitly reverses the dismissal.

#### Scenario: A dismissed pair is re-indexed
- **WHEN** a picture in a dismissed candidate-duplicate pair is re-hashed and the pair's
  distance is recomputed
- **THEN** the pair's recorded distance is updated but its dismissed status is
  unchanged

#### Scenario: Underlying hashing algorithm changes
- **WHEN** the perceptual hashing algorithm used for indexing changes
- **THEN** previously dismissed candidate-duplicate relationships remain dismissed
