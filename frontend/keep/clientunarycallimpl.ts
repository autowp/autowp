/**
 * @fileoverview This class handles ClientReadableStream returned by unary
 * calls.
 */
import {ClientReadableStream} from './clientreadablestream';

/**
 * @implements {ClientReadableStream<RESPONSE>}
 * @template RESPONSE
 */
export class ClientUnaryCallImpl<RESPONSE> implements ClientReadableStream<RESPONSE> {
  /**
   * @param {!ClientReadableStream<RESPONSE>} stream
   */
  constructor(private stream: ClientReadableStream<RESPONSE>) {
  }

  /**
   * @override
   */
  on(eventType: string, callback: (input: any) => void): ClientReadableStream<RESPONSE> {
    if (eventType == 'data' || eventType == 'error') {
      // unary call responses and errors should be handled by the main
      // (err, resp) => ... callback
      return this;
    }
    return this.stream.on(eventType, callback);
  }

  /**
   * @override
   */
  removeListener(eventType: string, callback: Function) {
    return this.stream.removeListener(eventType, callback);
  }

  /**
   * @override
   */
  cancel(): void {
    this.stream.cancel();
  }
}
