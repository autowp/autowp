import type {InvalidParams} from '@utils/invalid-params.pipe';
import type {Observable} from 'rxjs';

import {BadRequest} from '@grpc/google/rpc/error-details.pb';
import {Status} from '@grpc/google/rpc/status.pb';
import {ErrorDetails} from '@grpc/spec.pb';
import {GrpcMetadata, GrpcStatusEvent} from '@ngx-grpc/common';
import {GRPC_STATUS_DETAILS_BIN_HEADER} from 'grpc-web-client/grpc-web-client';
import {StatusCode} from 'grpc-web-client/statuscode';
import {throwError} from 'rxjs';
import {base64ToUint8Array, stringToUint8Array} from 'uint8array-extras';

import FieldViolation = BadRequest.FieldViolation;

/**
 * For use inside an rxResource `stream()` when a route param or other precondition is missing -
 * makes that case indistinguishable from a genuine backend NOT_FOUND response.
 */
export const notFoundError = (): Observable<never> =>
  // Throws the raw GrpcStatusEvent, not an Error wrapping it: real backend failures reach
  // catchError as a bare GrpcStatusEvent too (it's what @ngx-grpc's client itself rejects with),
  // and isNotFoundError()/other `instanceof GrpcStatusEvent` checks throughout the app rely on
  // both paths having the exact same shape.
  // eslint-disable-next-line rxjs-x/throw-error
  throwError(() => new GrpcStatusEvent(StatusCode.NOT_FOUND, 'Not found', new GrpcMetadata()));

/**
 * Detects a NOT_FOUND gRPC status, whether it's the raw event caught by a plain `catchError`
 * or wrapped in an `Error.cause` by rxResource's `encapsulateResourceError`.
 */
export const isNotFoundError = (error: unknown): boolean => {
  // GrpcStatusEvent.statusCode is typed as a bare `number` by @ngx-grpc/common (not our
  // StatusCode enum), so comparing it against StatusCode.NOT_FOUND is a real numeric-code
  // comparison, not a mismatched-enum bug.
  if (error instanceof GrpcStatusEvent) {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-enum-comparison
    return error.statusCode === StatusCode.NOT_FOUND;
  }
  return (
    error instanceof Error &&
    error.cause instanceof GrpcStatusEvent &&
    // eslint-disable-next-line @typescript-eslint/no-unsafe-enum-comparison
    error.cause.statusCode === StatusCode.NOT_FOUND
  );
};

export const extractFieldViolations = (response: GrpcStatusEvent): FieldViolation[] => {
  if (!(response instanceof GrpcStatusEvent)) {
    return [];
  }

  const statusEncoded = response.metadata.get(GRPC_STATUS_DETAILS_BIN_HEADER);
  if (!statusEncoded) {
    return [];
  }

  const statusDecoded = base64ToUint8Array(statusEncoded);
  const status = Status.deserializeBinary(statusDecoded);

  const fieldViolations: FieldViolation[] = [];
  if (status.details) {
    status.details.forEach((detail) => {
      const deserialized = ErrorDetails.deserializeBinary(detail.serializeBinary());
      if (deserialized.debugInfo) {
        deserialized.debugInfo.stackEntries.forEach((value) => {
          const fieldViolation = FieldViolation.deserializeBinary(stringToUint8Array(value));
          fieldViolations.push(fieldViolation);
        });
      }
    });
  }

  return fieldViolations;
};

export const fieldViolations2InvalidParams = (fvs: FieldViolation[]): InvalidParams => {
  const result: InvalidParams = {};

  fvs.forEach((fv) => {
    // Object.hasOwn(), not `!result[fv.field]`: without noUncheckedIndexedAccess, TS types a
    // Record's index access as always-present, so this reads as "always false" to the type
    // checker even though the key is genuinely absent until a field is seen for the first time.
    if (!Object.hasOwn(result, fv.field)) {
      result[fv.field] = {};
    }
    result[fv.field][fv.description] = fv.description;
  });

  return result;
};
