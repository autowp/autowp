import {Timestamp} from '@ngx-grpc/well-known-types';

/**
 * Converts a Timestamp to a Date from its seconds/nanos fields directly, rather than by calling
 * its .toDate() method. A resource value seeded from TransferState during SSR hydration is a
 * plain JSON-shaped object (Angular round-trips resource values through toJSON()/JSON.parse for
 * hydration), not a real Timestamp class instance - .toDate() doesn't exist on it even though
 * seconds/nanos (present on both shapes) do.
 */
export function timestampToDate(ts: Timestamp | Timestamp.AsObject | undefined): Date | undefined {
  if (!ts) {
    return undefined;
  }

  return new Date(Number(ts.seconds) * 1000 + Math.round(ts.nanos / 1e6));
}
