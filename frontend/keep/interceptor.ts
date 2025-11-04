/**
 * @fileoverview grpc-web client interceptors.
 *
 * The type of interceptors is determined by the response type of the RPC call.
 * gRPC-Web has two generated clients for one service:
 * FooServiceClient and FooServicePromiseClient. The response type of
 * FooServiceClient is ClientReadableStream for BOTH unary calls and server
 * streaming calls, so StreamInterceptor is expected to be used for intercepting
 * FooServiceClient calls. The response type of PromiseClient is Promise, so use
 * UnaryInterceptor for PromiseClients.
 */

import {UnaryResponseInternal} from "./unaryresponseinternal";

/**
 * Interceptor for RPC calls with response type `UnaryResponse`.
 * An example implementation of UnaryInterceptor
 * <pre>
 * TestUnaryInterceptor.prototype.intercept = function(request, invoker) {
 *   const newRequest = ...
 *   return invoker(newRequest).then((response) => {
 *     // Do something with response.getMetadata
 // Do something with response.getResponseMessage
 *     return response;
 *   });
 * };
 * </pre>
 * @interface
 */
export interface UnaryInterceptor {
  /**
   * @export
   * @abstract
   * @template REQUEST, RESPONSE
   * @param {!UnaryResponseInternal<REQUEST, RESPONSE>} request
   * @param {function(!UnaryResponseInternal<REQUEST,RESPONSE>):!Promise<!UnaryResponseInternal<RESPONSE>>}
   *     invoker
   * @return {!Promise<!UnaryResponseInternal<RESPONSE>>}
   */
  intercept<REQUEST, RESPONSE>(
    request: UnaryResponseInternal<REQUEST, RESPONSE>,
    invoker: (request: UnaryResponseInternal<REQUEST, RESPONSE>) => Promise<UnaryResponseInternal<REQUEST, RESPONSE>>
  ): Promise<UnaryResponseInternal<REQUEST, RESPONSE>>;

}


