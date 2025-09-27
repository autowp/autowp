import {BadRequest} from '@grpc/google/rpc/error-details.pb';
import {Status} from '@grpc/google/rpc/status.pb';
import {ErrorDetails} from '@grpc/spec.pb';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {InvalidParams} from '@utils/invalid-params.pipe';

import FieldViolation = BadRequest.FieldViolation;

const stringToUint8Array = (str: string): Uint8Array => {
  const buf = new ArrayBuffer(str.length);
  const bufView = new Uint8Array(buf);
  for (let i = 0; i < str.length; i++) {
    bufView[i] = str.charCodeAt(i);
  }
  return bufView;
};

export const extractFieldViolations = (response: GrpcStatusEvent): FieldViolation[] => {
  if (!(response instanceof GrpcStatusEvent)) {
    return [];
  }

  const statusEncoded = response.metadata.get('grpc-status-details-bin');
  if (!statusEncoded) {
    return [];
  }

  const statusDecoded = stringToUint8Array(atob(statusEncoded));
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
