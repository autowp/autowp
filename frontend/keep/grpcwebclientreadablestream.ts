/**
 *
 * Copyright 2018 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
/**
 * @fileoverview gRPC web client Readable Stream
 *
 * This class is being returned after a gRPC streaming call has been
 * started. This class provides functionality for user to operates on
 * the stream, e.g. set onData callback, etc.
 *
 * This wraps the underlying goog.net.streams.NodeReadableStream
 *
 * @author stanleycheung@google.com (Stanley Cheung)
 */

import {ClientReadableStream} from './clientreadablestream';
import {FrameType, GrpcWebStreamParser} from './grpcwebstreamparser';
import {RpcError} from './rpcerror';
import {Status} from './status';
import {fromHttpStatus, StatusCode} from './statuscode';
import {Metadata} from "./metadata";
import {decodeStringToUint8Array} from "./include/goog.crypt.base64";
import {startsWith} from "./include/goog.string.internal";
import {ErrorCode, getDebugMessage} from "./include/goog.net.ErrorCode";
import {EventType} from "./include/goog.net.EventType";
import {listen} from "./include/goog.events";

const XhrIo = goog.require('goog.net.XhrIo');

export const GRPC_STATUS = 'grpc-status';
export const GRPC_STATUS_MESSAGE = 'grpc-message';

/** @type {!Array<string>} */
const EXCLUDED_RESPONSE_HEADERS = ['content-type', GRPC_STATUS, GRPC_STATUS_MESSAGE];

/**
 * A stream that the client can read from. Used for calls that are streaming
 * from the server side.
 * @template RESPONSE
 * @implements {ClientReadableStream}
 * @final
 * @unrestricted
 */
export class GrpcWebClientReadableStream<RESPONSE> implements ClientReadableStream<RESPONSE> {
  /**
   * @private
   * @type {function(?):!RESPONSE|null} The deserialize function for the proto
   */
  private responseDeserializeFn_: null | (() => RESPONSE) = null;

  /**
   * @const
   * @private
   * @type {!Array<function(!RESPONSE)>} The list of data callbacks
   */
  private readonly onDataCallbacks_: Function[] = [];

  /**
   * @const
   * @private
   * @type {!Array<function(!Status)>} The list of status callbacks
   */
  private readonly onStatusCallbacks_: Function[] = [];

  /**
   * @const
   * @private
   * @type {!Array<function(!Metadata)>} The list of metadata callbacks
   */
  private readonly onMetadataCallbacks_: Function[] = [];

  /**
   * @const
   * @private
   * @type {!Array<function(!RpcError)>} The list of error callbacks
   */
  private readonly onErrorCallbacks_: Function[] = [];

  /**
   * @const
   * @private
   * @type {!Array<function(...):?>} The list of stream end callbacks
   */
  private readonly onEndCallbacks_: Function[] = [];

  /**
   * @private
   * @type {boolean} Whether the stream has been aborted
   */
  private aborted_ = false;

  /**
   * @private
   * @type {number} The stream parser position
   */
  private pos_ = 0;

  /**
   * @private
   * @type {!GrpcWebStreamParser} The grpc-web stream parser
   * @const
   */
  private readonly parser_ = new GrpcWebStreamParser();

  constructor() {
    const self = this;
    listen(this.xhr_, EventType.READY_STATE_CHANGE, function (e) {
      let contentType = self.xhr_.getStreamingResponseHeader('Content-Type');
      if (!contentType) return;
      contentType = contentType.toLowerCase();

      if (!startsWith(contentType, 'application/grpc-web-text')) {
        self.handleError_(new RpcError(StatusCode.UNKNOWN, 'Unknown Content-type received.'));
        return;
      }
      // Ensure responseText is not null
      const responseText = self.xhr_.getResponseText() || '';
      const newPos = responseText.length - (responseText.length % 4);
      const newData = responseText.substring(self.pos_, newPos - self.pos_);
      if (newData.length == 0) return;
      self.pos_ = newPos;
      let byteSource = decodeStringToUint8Array(newData);

      let messages = null;
      try {
        messages = self.parser_.parse(byteSource);
      } catch (err) {
        self.handleError_(new RpcError(StatusCode.UNKNOWN, 'Error in parsing response body'));
      }
      if (messages) {
        for (let i = 0; i < messages.length; i++) {
          if (FrameType.DATA in messages[i]) {
            const data = messages[i][FrameType.DATA];
            if (data) {
              let isResponseDeserialized = false;
              let response;
              try {
                response = self.responseDeserializeFn_(data);
                isResponseDeserialized = true;
              } catch (err) {
                self.handleError_(
                  new RpcError(
                    StatusCode.INTERNAL,
                    `Error when deserializing response data; error: ${err}` + `, response: ${response}`,
                  ),
                );
              }
              if (isResponseDeserialized) {
                for (let i = 0; i < self.onDataCallbacks_.length; i++) {
                  self.onDataCallbacks_[i](response);
                }
              }
            }
          }
          if (FrameType.TRAILER in messages[i]) {
            if (messages[i][FrameType.TRAILER].length > 0) {
              let trailerString = '';
              for (let pos = 0; pos < messages[i][FrameType.TRAILER].length; pos++) {
                trailerString += String.fromCharCode(messages[i][FrameType.TRAILER][pos]);
              }
              const trailers = self.parseHttp1Headers_(trailerString);
              let grpcStatusCode = StatusCode.OK;
              let grpcStatusMessage = '';
              if (GRPC_STATUS in trailers) {
                grpcStatusCode = /** @type {!StatusCode} */ (Number(trailers[GRPC_STATUS]));
                delete trailers[GRPC_STATUS];
              }
              if (GRPC_STATUS_MESSAGE in trailers) {
                grpcStatusMessage = trailers[GRPC_STATUS_MESSAGE];
                delete trailers[GRPC_STATUS_MESSAGE];
              }
              self.handleError_(new RpcError(grpcStatusCode, grpcStatusMessage, trailers));
            }
          }
        }
      }
    });

    listen(this.xhr_, EventType.COMPLETE, function (e) {
      const lastErrorCode = self.xhr_.getLastErrorCode();
      let grpcStatusCode = StatusCode.UNKNOWN;
      let grpcStatusMessage = '';
      const initialMetadata = /** @type {!Metadata} */ ({});

      // Get response headers with lower case keys.
      const rawResponseHeaders = self.xhr_.getResponseHeaders();
      const responseHeaders = {};
      for (const key in rawResponseHeaders) {
        if (rawResponseHeaders.hasOwnProperty(key)) {
          responseHeaders[key.toLowerCase()] = rawResponseHeaders[key];
        }
      }

      Object.keys(responseHeaders).forEach((header_) => {
        if (!EXCLUDED_RESPONSE_HEADERS.includes(header_)) {
          initialMetadata[header_] = responseHeaders[header_];
        }
      });

      for (let i = 0; i < self.onMetadataCallbacks_.length; i++) {
        self.onMetadataCallbacks_[i](initialMetadata);
      }

      // There's an XHR level error
      let xhrStatusCode = -1;
      if (lastErrorCode != ErrorCode.NO_ERROR) {
        switch (lastErrorCode) {
          case ErrorCode.ABORT:
            grpcStatusCode = StatusCode.ABORTED;
            break;
          case ErrorCode.HTTP_ERROR:
            xhrStatusCode = self.xhr_.getStatus();
            grpcStatusCode = fromHttpStatus(xhrStatusCode);
            break;
          case ErrorCode.TIMEOUT:
            grpcStatusCode = StatusCode.DEADLINE_EXCEEDED;
            break;
          default:
            grpcStatusCode = StatusCode.UNAVAILABLE;
        }
        if (grpcStatusCode == StatusCode.ABORTED && self.aborted_) {
          return;
        }
        let errorMessage = getDebugMessage(lastErrorCode);
        if (xhrStatusCode != -1) {
          errorMessage += ', http status code: ' + xhrStatusCode;
        }

        self.handleError_(new RpcError(grpcStatusCode, errorMessage));
        return;
      }

      let errorEmitted = false;

      // Check whethere there are grpc specific response headers
      if (GRPC_STATUS in responseHeaders) {
        grpcStatusCode = /** @type {!StatusCode} */ (Number(responseHeaders[GRPC_STATUS]));
        if (GRPC_STATUS_MESSAGE in responseHeaders) {
          grpcStatusMessage = responseHeaders[GRPC_STATUS_MESSAGE];
        }
        if (grpcStatusCode != StatusCode.OK) {
          self.handleError_(new RpcError(grpcStatusCode, grpcStatusMessage || '', responseHeaders));
          errorEmitted = true;
        }
      }

      if (!errorEmitted) {
        for (let i = 0; i < self.onEndCallbacks_.length; i++) {
          self.onEndCallbacks_[i]();
        }
      }
    });
  }

  /**
   * @override
   * @export
   */
  on(eventType: string, callback: (input: any) => void): ClientReadableStream<RESPONSE> {
    // TODO(stanleycheung): change eventType to @enum type
    if (eventType == 'data') {
      this.onDataCallbacks_.push(callback);
    } else if (eventType == 'status') {
      this.onStatusCallbacks_.push(callback);
    } else if (eventType == 'metadata') {
      this.onMetadataCallbacks_.push(callback);
    } else if (eventType == 'end') {
      this.onEndCallbacks_.push(callback);
    } else if (eventType == 'error') {
      this.onErrorCallbacks_.push(callback);
    }
    return this;
  }

  /**
   * Parse HTTP headers
   *
   * @private
   * @param {string} str The raw http header string
   * @return {!Object} The header:value pairs
   */
  parseHttp1Headers_(str: string) : Object {
    const chunks = str.trim().split('\r\n');
    const headers = {};
    for (let i = 0; i < chunks.length; i++) {
      const pos = chunks[i].indexOf(':');
      headers[chunks[i].substring(0, pos).trim()] = chunks[i].substring(pos + 1).trim();
    }
    return headers;
  }

  /**
   * A central place to handle errors
   *
   * @private
   * @param {!RpcError} error The error object
   */
  private handleError_(error: RpcError) {
    if (error.code != StatusCode.OK) {
      let r = new RpcError(error.code, decodeURIComponent(error.message || ''), error.metadata);
      for (let i = 0; i < this.onErrorCallbacks_.length; i++) {
        this.onErrorCallbacks_[i](r);
      }
    }
    for (let i = 0; i < this.onStatusCallbacks_.length; i++) {
      this.onStatusCallbacks_[i]({
        code: error.code,
        details: decodeURIComponent(error.message || ''),
        metadata: error.metadata,
      });
    }
  }
}
