/**
 * @fileoverview gRPC-Web UnaryResponse internal implementation.
 */

import {Metadata} from './metadata';
import {MethodDescriptor} from './methoddescriptor';
import {Status} from './status';

/**
 * @template REQUEST, RESPONSE
 * @final
 * @package
 */
export class UnaryResponseInternal<REQUEST, RESPONSE> {
  /**
   * @param {RESPONSE} responseMessage
   * @param {!MethodDescriptor<REQUEST, RESPONSE>} methodDescriptor
   * @param {!Metadata=} metadata
   * @param {?Status=} status
   */
  constructor(private responseMessage: RESPONSE, private methodDescriptor: MethodDescriptor<REQUEST, RESPONSE>, private metadata: Metadata = {}, private status: Status|null = null) {
  }

  /** @override */
  getResponseMessage(): RESPONSE {
    return this.responseMessage;
  }

  /** @override */
  getMetadata(): Metadata {
    return this.metadata;
  }

  /** @override */
  getMethodDescriptor(): MethodDescriptor<REQUEST, RESPONSE> {
    return this.methodDescriptor;
  }

  /** @override */
  getStatus(): Status|null {
    return this.status;
  }
}
