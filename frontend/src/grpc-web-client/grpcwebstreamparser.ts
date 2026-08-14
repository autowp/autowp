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

export enum FrameType {
  DATA = 0x00, // expecting a data frame
  TRAILER = 0x80, // expecting a trailer frame
}

enum State {
  INIT = 0, // expecting the next frame byte
  LENGTH = 1, // expecting 4 bytes of length
  MESSAGE = 2, // expecting more message bytes
  INVALID = 3,
}

interface Message {
  buffer: null | Uint8Array;
  frameType: FrameType;
}

export class GrpcWebStreamParser {
  #errorMessage: null | string = null;
  #result: Message[] = [];
  #streamPos = 0;
  #state: State = State.INIT;
  #frame: FrameType = 0;
  #length = 0;
  #countLengthBytes = 0;
  #messageBuffer: null | Uint8Array = null;
  #countMessageBytes = 0;

  parse(inputBytes: Uint8Array): Message[] | null {
    let pos = 0;

    while (pos < inputBytes.length) {
      switch (this.#state) {
        case State.INIT: {
          const res = this.processFrameByte(inputBytes[pos]);
          if (!res) {
            this.error_(inputBytes, pos, 'invalid frame byte');
          }
          break;
        }
        case State.INVALID: {
          this.error_(inputBytes, pos, 'stream already broken');
          break;
        }
        case State.LENGTH: {
          this.processLengthByte(inputBytes[pos]);
          break;
        }
        case State.MESSAGE: {
          this.processMessageByte(inputBytes[pos]);
          break;
        }
        default: {
          throw new Error('unexpected parser state: ' + String(this.#state));
        }
      }

      this.#streamPos++;
      pos++;
    }

    const msgs = this.#result;
    this.#result = [];
    return msgs.length > 0 ? msgs : null;
  }

  // b is typed as a plain byte, not FrameType: it's an arbitrary byte read off the wire
  // (inputBytes[pos] in parse() below), not guaranteed to actually be one of FrameType's two
  // values - typing it FrameType would make TS treat the frame-type check below as tautological
  // once DATA is ruled out, when it's a real validity check against malformed/corrupt input.
  private processFrameByte(b: number): boolean {
    // b is a raw wire byte, not our FrameType enum - comparing it against a known enum constant
    // is legitimate here.
    // eslint-disable-next-line @typescript-eslint/no-unsafe-enum-comparison
    if (b === FrameType.DATA) {
      this.#frame = FrameType.DATA;
      // eslint-disable-next-line @typescript-eslint/no-unsafe-enum-comparison
    } else if (b === FrameType.TRAILER) {
      this.#frame = FrameType.TRAILER;
    } else {
      return false;
    }

    this.#state = State.LENGTH;
    this.#length = 0;
    this.#countLengthBytes = 0;

    return true;
  }

  private processLengthByte(b: number) {
    this.#countLengthBytes++;
    this.#length = (this.#length << 8) + b;

    if (this.#countLengthBytes == 4) {
      // no more length byte
      this.#state = State.MESSAGE;
      this.#countMessageBytes = 0;
      this.#messageBuffer = new Uint8Array(this.#length);

      if (this.#length == 0) {
        // empty message
        this.finishMessage();
      }
    }
  }

  private processMessageByte(b: number) {
    if (!this.#messageBuffer) {
      throw new Error('messageBuffer is not initialized');
    }

    this.#messageBuffer[this.#countMessageBytes++] = b;
    if (this.#countMessageBytes == this.#length) {
      this.finishMessage();
    }
  }

  private finishMessage() {
    const message: Message = {
      frameType: this.#frame,
      buffer: this.#messageBuffer,
    };
    this.#result.push(message);
    this.#state = State.INIT;
  }

  private error_(inputBytes: Uint8Array, pos: number, errorMsg: string) {
    this.#state = State.INVALID;
    this.#errorMessage =
      'The stream is broken @' +
      this.#streamPos +
      '/' +
      pos +
      '. ' +
      'Error: ' +
      errorMsg +
      '. ' +
      'With input:\n' +
      String(inputBytes);
    throw new Error(this.#errorMessage);
  }
}
