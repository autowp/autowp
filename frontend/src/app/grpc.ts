import {BadRequest} from '@grpc/google/rpc/error-details.pb';
import {Status} from '@grpc/google/rpc/status.pb';
import {ErrorDetails} from '@grpc/spec.pb';
import {GrpcMetadata, GrpcStatusEvent} from '@ngx-grpc/common';
import {InvalidParams} from '@utils/invalid-params.pipe';
import {GRPC_STATUS_DETAILS_BIN_HEADER} from 'grpc-web-client/grpc-web-client';
import {StatusCode} from 'grpc-web-client/statuscode';
import {Observable, throwError} from 'rxjs';
import {base64ToUint8Array, stringToUint8Array} from 'uint8array-extras';

import FieldViolation = BadRequest.FieldViolation;

/**
 * For use inside an rxResource `stream()` when a route param or other precondition is missing -
 * makes that case indistinguishable from a genuine backend NOT_FOUND response.
 */
export const notFoundError = (): Observable<never> =>
  throwError(() => new GrpcStatusEvent(StatusCode.NOT_FOUND, 'Not found', new GrpcMetadata()));

/**
 * Detects a NOT_FOUND gRPC status, whether it's the raw event caught by a plain `catchError`
 * or wrapped in an `Error.cause` by rxResource's `encapsulateResourceError`.
 */
export const isNotFoundError = (error: unknown): boolean => {
  if (error instanceof GrpcStatusEvent) {
    return error.statusCode === StatusCode.NOT_FOUND;
  }
  return (
    error instanceof Error && error.cause instanceof GrpcStatusEvent && error.cause.statusCode === StatusCode.NOT_FOUND
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
    if (!result[fv.field]) {
      result[fv.field] = {};
    }
    result[fv.field][fv.description] = fv.description;
  });

  return result;
};
