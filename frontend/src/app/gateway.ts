import {HttpErrorResponse} from '@angular/common/http';
import {ProtobufAny} from '@rest/model/protobufAny';
import {RpcStatus} from '@rest/model/rpcStatus';
import {InvalidParams} from '@utils/invalid-params.pipe';

export interface BadRequest extends ProtobufAny {
  fieldViolations?: {
    description: string;
    field: string;
    localizedMessage: null | string;
    reason: string;
  }[];
}

export const invalidParamsFromError = (error: HttpErrorResponse): InvalidParams | undefined => {
  if (error.status !== 400) {
    return undefined;
  }

  const status = error.error as RpcStatus;

  const badRequest: BadRequest | undefined = status.details.find(
    (o) => o['@type'] === 'type.googleapis.com/google.rpc.BadRequest',
  );
  if (!badRequest || !badRequest.fieldViolations) {
    return undefined;
  }

  return badRequest2InvalidParams(badRequest);
};

const badRequest2InvalidParams = (badRequest: BadRequest): InvalidParams => {
  const result: InvalidParams = {};

  badRequest.fieldViolations?.forEach((fv) => {
    if (!result[fv.field]) {
      result[fv.field] = {};
    }
    result[fv.field][fv.description] = fv.description;
  });

  return result;
};
