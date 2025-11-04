/**
 * @fileoverview grpc-web request/response metadata.
 *
 * Request and response headers will be included in the Metadata.
 */

/**
 * @typedef {!Object<string,string>}
 */
export type Metadata = Record<string, string>;
