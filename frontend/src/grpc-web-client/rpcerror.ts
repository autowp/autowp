/**
 *
 * Copyright 2021 Google LLC
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

import type {Metadata} from './metadata';
import type {StatusCode} from './statuscode';

import {statusCodeName} from './statuscode';

/**
 * gRPC-Web Error object, contains the {@link StatusCode}, a string message
 * and {@link Metadata} contained in the error response.
 */
export class RpcError extends Error {
  /**
   * @param {!StatusCode} code
   * @param {string} message
   * @param {!Metadata=} metadata
   */
  constructor(
    public code: StatusCode,
    message: string,
    public metadata: Metadata = {},
  ) {
    super(message);
  }

  override toString() {
    const status = statusCodeName(this.code) || String(this.code);
    let out = `RpcError(${status})`;
    if (this.message) {
      out += ': ' + this.message;
    }
    return out;
  }
}
