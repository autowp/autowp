## ADDED Requirements

### Requirement: Achievement Dictionary
The system SHALL maintain a fixed dictionary of achievements, each identified by a
unique, stable `code`. The dictionary SHALL NOT store user-facing display name or
description text — those are resolved client-side from `code`.

The initial dictionary SHALL contain 23 achievements: `pictures-contributor`,
`top-pictures-contributor`, four 5-tier ladders (`picture-inspector-*`,
`picture-buster-*`, `spec-master-*`, `commentator-*`, each
Bronze/Silver/Gold/Platinum/Diamond at 100/1,000/10,000/100,000/1,000,000), and
`veteran`.

#### Scenario: Looking up an achievement by code
- **WHEN** any part of the system needs an achievement's identity
- **THEN** it is referenced by its `code`, never by display text

### Requirement: Permanent, Idempotent Grants
The system SHALL record, per user, which achievements have been earned and when. Once
granted, an achievement SHALL NOT be revoked or removed, regardless of later changes to
the user's activity or counts.

Granting the same achievement to the same user more than once SHALL be a no-op: no
duplicate record, and no duplicate notification.

#### Scenario: Re-granting an already-earned achievement
- **WHEN** a grant is attempted for a user/achievement pair that already has a record
- **THEN** no new record is created and no notification is sent

#### Scenario: Underlying activity later decreases
- **WHEN** a user's activity count for an already-earned achievement's series later
  drops (e.g. a comment is deleted, a picture is un-accepted)
- **THEN** the previously granted achievement remains on the user's profile

### Requirement: Deleted Users Are Excluded From Grants
The system SHALL NOT grant or retain an achievement for a deleted user account.

#### Scenario: Grant attempted for a deleted user
- **WHEN** an achievement grant is attempted for a user whose account is marked deleted
- **THEN** no achievement record is created and no notification is sent

### Requirement: Tiered Achievement Progress Tracking
For each of the four 5-tier achievement series (Picture Inspector, Picture Buster, Spec
Master, Commentator), the system SHALL maintain a persistent, monotonically increasing
per-user counter reflecting lifetime activity toward that series, independent of later
changes to the underlying source data.

Each qualifying action SHALL increment the relevant user's counter by exactly one:
- A picture newly transitioning to accepted status increments the accepting moderator's
  Picture Inspector counter (only on that picture's first-ever acceptance).
- A picture being queued for removal increments the moderator's Picture Buster counter.
- A user setting a new or changed attribute/spec value increments that user's Spec
  Master counter (a no-op resubmission of an unchanged value SHALL NOT increment it).
- A user posting a comment increments that user's Commentator counter.

Whenever a user's counter for a series reaches or exceeds a tier's threshold, that tier
SHALL be granted (subject to the permanence and idempotency rules above), together with
every lower tier not yet granted.

#### Scenario: Crossing a tier threshold
- **WHEN** a qualifying action brings a user's series counter to exactly a tier's
  threshold (e.g. 100 for Bronze)
- **THEN** that tier is granted and a congratulation notification is sent

#### Scenario: Re-accepting an already-accepted picture
- **WHEN** a picture that was already accepted once is accepted again
- **THEN** the accepting moderator's Picture Inspector counter is not incremented

#### Scenario: Resubmitting an unchanged spec value
- **WHEN** a user submits the same value for an attribute/item pair they already hold a
  value for, with no actual change
- **THEN** that user's Spec Master counter is not incremented

### Requirement: Relative and Time-Based Achievements
The system SHALL periodically (at least daily) re-evaluate achievements that cannot be
determined at the moment of a single action:
- `top-pictures-contributor`, granted to any user currently among the top 10 by count of
  accepted uploaded pictures.
- `veteran`, granted to any user whose account was registered 10 or more years ago.

Once granted, these follow the same permanence rule as all other achievements — falling
out of the top 10 later SHALL NOT revoke `top-pictures-contributor`.

#### Scenario: Periodic re-evaluation grants new veterans
- **WHEN** the daily evaluation runs
- **THEN** every non-deleted user whose registration date is 10+ years in the past and
  who does not already hold the Veteran achievement receives it

### Requirement: Grant Notification
Whenever an achievement is newly granted to a user, the system SHALL send that user a
congratulation message through the personal messaging system, originated by the system
(not by another user).

#### Scenario: New achievement grant
- **WHEN** an achievement is granted to a user for the first time
- **THEN** that user receives a system-originated personal message about the grant

### Requirement: Public Profile Visibility
A user's earned achievements, and their progress toward the next unearned tier in any
series they have started, SHALL be visible on that user's public profile page to any
visitor, without requiring authentication.

A tiered series with no recorded activity for a user SHALL NOT be shown as "in progress"
on that user's profile.

#### Scenario: Anonymous visitor views a profile
- **WHEN** an unauthenticated visitor loads a user's profile page
- **THEN** that user's earned achievements and any in-progress tiers are visible

#### Scenario: Non-moderator's profile
- **WHEN** viewing the profile of a user who has never moderated a picture
- **THEN** no Picture Inspector or Picture Buster progress is shown for that user

### Requirement: Achievements Catalog Page
The system SHALL provide a public page listing every achievement in the dictionary, each
with its icon, display name, a description of how to earn it, and the current number of
users who have earned it.

#### Scenario: Viewing the catalog
- **WHEN** any visitor loads the achievements catalog page
- **THEN** all 23 achievements are listed with name, "how to earn" description, and an
  earned-by count (0 if nobody has earned it yet)

### Requirement: Historical Backfill
On deployment of this feature, the system SHALL retroactively grant achievements and
seed progress counters based on pre-existing historical data, using exact counts where
the underlying data provides them (spec value contributions, comments, registration
date, current accepted-picture totals) and a best-effort approximation from the
moderation event log where no exact historical record exists (moderator accept and
queue-for-removal actions).

The backfill SHALL NOT send grant notifications for retroactively granted achievements.

#### Scenario: Deploying the feature on an existing dataset
- **WHEN** the backfill runs against a database with pre-existing users, pictures,
  comments, and spec contributions
- **THEN** users who already qualify for an achievement receive it immediately, with no
  personal message sent for those retroactive grants
