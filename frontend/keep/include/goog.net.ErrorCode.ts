/**
 * @license
 * Copyright The Closure Library Authors.
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * @fileoverview Error codes shared between goog.net.IframeIo and
 * goog.net.XhrIo.
 */


/**
 * Error codes
 * @enum {number}
 */
export enum ErrorCode {

  /**
   * There is no error condition.
   */
  NO_ERROR = 0,

  /**
   * The most common error from iframeio, unfortunately, is that the browser
   * responded with an error page that is classed as a different domain. The
   * situations, are when a browser error page  is shown -- 404, access denied,
   * DNS failure, connection reset etc.)
   *
   */
  ACCESS_DENIED = 1,

  /**
   * Currently the only case where file not found will be caused is when the
   * code is running on the local file system and a non-IE browser makes a
   * request to a file that doesn't exist.
   */
  FILE_NOT_FOUND = 2,

  /**
   * If Firefox shows a browser error page, such as a connection reset by
   * server or access denied, then it will fail silently without the error or
   * load handlers firing.
   */
  FF_SILENT_ERROR = 3,

  /**
   * Custom error provided by the client through the error check hook.
   */
  CUSTOM_ERROR = 4,

  /**
   * Exception was thrown while processing the request.
   */
  EXCEPTION = 5,

  /**
   * The Http response returned a non-successful http status code.
   */
  HTTP_ERROR = 6,

  /**
   * The request was aborted.
   */
  ABORT = 7,

  /**
   * The request timed out.
   */
  TIMEOUT = 8,

  /**
   * The resource is not available offline.
   */
  OFFLINE = 9,
};


/**
 * Returns a friendly error message for an error code. These messages are for
 * debugging and are not localized.
 * @param {goog.net.ErrorCode} errorCode An error code.
 * @return {string} A message for debugging.
 */
export function getDebugMessage(errorCode: ErrorCode) {
  'use strict';
  switch (errorCode) {
    case ErrorCode.NO_ERROR:
      return 'No Error';

    case ErrorCode.ACCESS_DENIED:
      return 'Access denied to content document';

    case ErrorCode.FILE_NOT_FOUND:
      return 'File not found';

    case ErrorCode.FF_SILENT_ERROR:
      return 'Firefox silently errored';

    case ErrorCode.CUSTOM_ERROR:
      return 'Application custom error';

    case ErrorCode.EXCEPTION:
      return 'An exception occurred';

    case ErrorCode.HTTP_ERROR:
      return 'Http response at 400 or 500 level';

    case ErrorCode.ABORT:
      return 'Request was aborted';

    case ErrorCode.TIMEOUT:
      return 'Request timed out';

    case ErrorCode.OFFLINE:
      return 'The resource is not available offline';

    default:
      return 'Unrecognized error code';
  }
}
