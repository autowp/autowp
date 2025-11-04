/**
 * @fileoverview Description of this file.
 *
 * A templated class that is used to address gRPC Web requests.
 */

import {Metadata} from './metadata';
import {MethodType} from './methodtype';
import {Status} from './status';
import {UnaryResponseInternal} from './unaryresponseinternal';

/**
 * @final
 * @template REQUEST, RESPONSE
 * @unrestricted
 */
export class MethodDescriptor<REQUEST, RESPONSE> {
  /**
   * @param {string} name
   * @param {?MethodType} methodType
   * @param {function(new: REQUEST, ...)} requestType
   * @param {function(new: RESPONSE, ...)} responseType
   * @param {function(REQUEST): ?} requestSerializeFn
   * @param {function(?): RESPONSE} responseDeserializeFn
   */
  constructor(public name: string, public methodType: MethodType, public requestType: any, public responseType: any, public requestSerializeFn: any, public responseDeserializeFn: any) {
  }

  /**
   * @override
   * @param {RESPONSE} responseMessage
   * @param {!Metadata=} metadata
   * @param {?Status=} status
   * @return {!UnaryResponseInternal<REQUEST, RESPONSE>}
   */
  createUnaryResponse(responseMessage: RESPONSE, metadata: Metadata = {}, status: Status | null = null): UnaryResponseInternal<REQUEST, RESPONSE> {
    return new UnaryResponseInternal(responseMessage, this, metadata, status);
  }

  /**
   * @override
   * @export
   */
  getName() {
    return this.name;
  }

  /**
   * @override
   */
  getMethodType() {
    return this.methodType;
  }

  /**
   * @override
   * @return {function(new: RESPONSE, ...)}
   */
  getResponseMessageCtor() {
    return this.responseType;
  }

  /**
   * @override
   * @return {function(new: REQUEST, ...)}
   */
  getRequestMessageCtor() {
    return this.requestType;
  }

  /** @override */
  getResponseDeserializeFn() {
    return this.responseDeserializeFn;
  }

  /** @override */
  getRequestSerializeFn() {
    return this.requestSerializeFn;
  }
}
