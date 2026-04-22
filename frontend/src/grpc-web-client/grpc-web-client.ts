import {HttpClient, HttpErrorResponse, HttpHeaders} from '@angular/common/http';
import {inject, Injectable, Provider} from '@angular/core';
import {
  GrpcClient,
  GrpcClientFactory,
  GrpcDataEvent,
  GrpcEvent,
  GrpcMessage,
  GrpcMessageClass,
  GrpcMetadata,
  GrpcStatusEvent,
} from '@ngx-grpc/common';
import {GRPC_CLIENT_FACTORY} from '@ngx-grpc/core';
import {EMPTY, Observable, of, throwError} from 'rxjs';
import {catchError, switchMap} from 'rxjs/operators';
import {base64ToUint8Array, concatUint8Arrays, uint8ArrayToBase64} from 'uint8array-extras';

import {FrameType, GrpcWebStreamParser} from './grpcwebstreamparser';
import {Metadata} from './metadata';
import {RpcError} from './rpcerror';
import {fromHttpStatus, StatusCode} from './statuscode';
import {NG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS} from './tokens';

export const CONTENT_TYPE_HEADER = 'content-type';
export const GRPC_STATUS_HEADER = 'grpc-status';
export const GRPC_MESSAGE_HEADER = 'grpc-message';
export const GRPC_STATUS_DETAILS_BIN_HEADER = 'grpc-status-details-bin';

const EXCLUDED_RESPONSE_HEADERS = [CONTENT_TYPE_HEADER, GRPC_STATUS_HEADER, GRPC_MESSAGE_HEADER];

export interface NgGrpcWebClientRootOptions {
  settings?: NgGrpcWebClientSettings;
}

/**
 * Settings for the chosen implementation of GrpcClient
 */
export interface NgGrpcWebClientSettings {
  host: string;
  suppressCorsPreflight?: boolean;
  withCredentials?: boolean;
}

/**
 * GrpcClientFactory implementation based on grpc-web
 */
@Injectable({providedIn: 'root'})
export class NgGrpcWebClientFactory implements GrpcClientFactory<NgGrpcWebClientSettings> {
  readonly #httpClient = inject(HttpClient);
  readonly #defaultSettings: NgGrpcWebClientSettings | null = inject(NG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS, {
    optional: true,
  });

  createClient(serviceId: string, customSettings: NgGrpcWebClientSettings) {
    const settings = customSettings || this.#defaultSettings;

    if (!settings) {
      throw new Error(`grpc-web client factory: no settings provided for ${serviceId}`);
    }

    return new NgGrpcWebClient(settings, this.#httpClient);
  }
}

/**
 * GrpcClient implementation based on grpc-web
 */
export class NgGrpcWebClient implements GrpcClient<NgGrpcWebClientSettings> {
  readonly #parser = new GrpcWebStreamParser();
  readonly #withCredentials: boolean;

  constructor(
    private settings: NgGrpcWebClientSettings,
    private httpClient: HttpClient,
  ) {
    this.#withCredentials = settings.withCredentials ?? false;
  }

  getSettings(): NgGrpcWebClientSettings {
    return this.settings;
  }

  public unary<Q extends GrpcMessage, S extends GrpcMessage>(
    path: string,
    req: Q,
    metadata: GrpcMetadata,
    reqclss: GrpcMessageClass<Q>,
    resclss: GrpcMessageClass<S>,
  ): Observable<GrpcEvent<S>> {
    const headers = new HttpHeaders({
      ...metadata.toObject(),
      'Content-Type': 'application/grpc-web-text',
      'Accept': 'application/grpc-web-text',
      'X-User-Agent': 'grpc-web-javascript/0.1',
      'X-Grpc-Web': '1',
    });
    let requestTimeout: number | undefined = undefined;

    if (headers.has('deadline')) {
      const deadline = Number(headers.get('deadline')); // in ms
      const currentTime = new Date().getTime();
      let timeout = Math.ceil(deadline - currentTime);
      headers.delete('deadline');
      if (timeout === Infinity) {
        // grpc-timeout header defaults to infinity if not set.
        timeout = 0;
      }
      if (timeout > 0) {
        headers.set('grpc-timeout', timeout + 'm');
        // Also set timeout on the xhr request to terminate the HTTP request
        // if the server doesn't respond within the deadline. We use 110% of
        // grpc-timeout for this to allow the server to terminate the connection
        // with DEADLINE_EXCEEDED rather than terminating it in the Browser, but
        // at least 1 second in case the user is on a high-latency network.
        requestTimeout = Math.max(1000, Math.ceil(timeout * 1.1));
      }
    }

    const serialized = req.serializeBinary();
    const payload = this.encodeRequest_(serialized);
    const url = this.settings.host + path;

    return this.httpClient
      .request('POST', url, {
        withCredentials: this.#withCredentials,
        headers,
        timeout: requestTimeout,
        body: uint8ArrayToBase64(payload),
        observe: 'response',
        responseType: 'text',
      })
      .pipe(
        // eslint-disable-next-line sonarjs/cognitive-complexity
        switchMap((response): Observable<GrpcEvent<S>> => {
          let contentType = response.headers.get('Content-Type');
          if (!contentType) {
            return of(this.handleError_(new RpcError(StatusCode.UNKNOWN, 'No Content-type received.')));
          }
          contentType = contentType.toLowerCase();

          if (!contentType.startsWith('application/grpc-web-text')) {
            return of(this.handleError_(new RpcError(StatusCode.UNKNOWN, 'Unknown Content-type received.')));
          }

          let grpcStatusCode;
          let grpcStatusMessage = '';
          const initialMetadata: Metadata = {};

          // Get response headers with lower case keys.
          const responseHeaders: Record<string, string> = {};
          for (const key of response.headers.keys()) {
            const value = response.headers.get(key);
            if (value !== null) {
              const lcKey = key.toLowerCase();
              responseHeaders[lcKey] = value;
              if (!EXCLUDED_RESPONSE_HEADERS.includes(lcKey)) {
                initialMetadata[lcKey] = value;
              }
            }
          }

          const events: GrpcEvent<S>[] = [];

          // Check whethere there are grpc specific response headers
          if (GRPC_STATUS_HEADER in responseHeaders) {
            grpcStatusCode = Number(responseHeaders[GRPC_STATUS_HEADER]);
            if (GRPC_MESSAGE_HEADER in responseHeaders) {
              grpcStatusMessage = responseHeaders[GRPC_MESSAGE_HEADER];
            }
            if (grpcStatusCode !== StatusCode.OK) {
              return of(this.handleError_(new RpcError(grpcStatusCode, grpcStatusMessage || '', initialMetadata)));
            }
          }

          // Ensure responseText is not null
          const newData = response.body ?? '';
          if (newData.length == 0) {
            return of(this.handleError_(new RpcError(StatusCode.UNKNOWN, 'No data received.')));
          }

          const byteSource = decodeGrpcWebTextResponse(newData);

          let messages;
          try {
            messages = this.#parser.parse(byteSource);
          } catch (error) {
            return of(
              this.handleError_(
                new RpcError(StatusCode.UNKNOWN, `Error in parsing response body with parser: ${error}`),
              ),
            );
          }

          if (!messages || messages.length <= 0) {
            return of(this.handleError_(new RpcError(StatusCode.UNKNOWN, 'Error in parsing response body')));
          }

          for (const message of messages) {
            switch (message.frameType) {
              case FrameType.DATA: {
                const data = message.buffer;
                if (!data) {
                  return of(this.handleError_(new RpcError(StatusCode.UNKNOWN, 'No data in frame')));
                }

                let result: S;
                try {
                  result = resclss.deserializeBinary(data);
                } catch (err) {
                  console.error('Error when deserializing response data from ', url, ' error: ', err);

                  return of(
                    this.handleError_(
                      new RpcError(
                        StatusCode.INTERNAL,
                        `Error when deserializing response data from ${url}; error: ${err}`,
                      ),
                    ),
                  );
                }
                events.push(new GrpcDataEvent(result));
                break;
              }
              case FrameType.TRAILER:
                if (message.buffer && message.buffer.length > 0) {
                  let trailerString = '';
                  for (const char of message.buffer) {
                    trailerString += String.fromCharCode(char);
                  }
                  const trailers = this.parseHttp1Headers_(trailerString);
                  let grpcStatusCode = StatusCode.OK;
                  let grpcStatusMessage = '';
                  if (GRPC_STATUS_HEADER in trailers) {
                    grpcStatusCode = /** @type {!StatusCode} */ Number(trailers[GRPC_STATUS_HEADER]);
                    delete trailers[GRPC_STATUS_HEADER];
                  }
                  if (GRPC_MESSAGE_HEADER in trailers) {
                    grpcStatusMessage = trailers[GRPC_MESSAGE_HEADER];
                    delete trailers[GRPC_MESSAGE_HEADER];
                  }
                  events.push(this.handleError_(new RpcError(grpcStatusCode, grpcStatusMessage, trailers)));
                }
                // events.push(new GrpcStatusEvent(status.code, status.details, new GrpcMetadata(status.metadata));
                break;
            }
          }
          return of(...events);
        }),
        catchError((error: unknown) => {
          if (error instanceof HttpErrorResponse) {
            console.error(error);

            const xhrStatusCode = error.status;
            const grpcStatusCode = fromHttpStatus(xhrStatusCode);
            if (grpcStatusCode == StatusCode.ABORTED) {
              return EMPTY;
            }
            let errorMessage = 'Http response at 400 or 500 level';
            if (xhrStatusCode != -1) {
              errorMessage += ', http status code: ' + xhrStatusCode;
            }

            return of(this.handleError_(new RpcError(grpcStatusCode, errorMessage)));
          }

          return throwError(() => error);
        }),
      );
  }

  private parseHttp1Headers_(str: string): Record<string, string> {
    const chunks = str.trim().split('\r\n');
    const headers: Record<string, string> = {};
    for (const chunk of chunks) {
      const pos = chunk.indexOf(':');
      headers[chunk.substring(0, pos).trim()] = chunk.substring(pos + 1).trim();
    }
    return headers;
  }

  private handleError_(error: RpcError): GrpcStatusEvent {
    return new GrpcStatusEvent(error.code, decodeURIComponent(error.message || ''), new GrpcMetadata(error.metadata));
  }

  public serverStream = () => {
    throw new Error('Server streaming not supported');
  };

  public clientStream = () => {
    throw new Error('Client streaming not supported');
  };

  public bidiStream = () => {
    throw new Error('Bidirectional streaming not supported');
  };

  /**
   * Encode the grpc-web request
   *
   * @private
   * @param {!Uint8Array} serialized The serialized proto payload
   * @return {!Uint8Array} The application/grpc-web padded request
   */
  private encodeRequest_(serialized: Uint8Array): Uint8Array {
    let len = serialized.length;
    const bytesArray = [0, 0, 0, 0];
    const payload = new Uint8Array(5 + len);
    for (let i = 3; i >= 0; i--) {
      bytesArray[i] = len % 256;
      len = len >>> 8;
    }
    payload.set(new Uint8Array(bytesArray), 1);
    payload.set(serialized, 5);
    return payload;
  }
}

export function provideGrpcWebClient(options?: NgGrpcWebClientRootOptions): Provider[] {
  const providers: Provider[] = [{provide: GRPC_CLIENT_FACTORY, useClass: NgGrpcWebClientFactory}];

  if (options?.settings) {
    providers.push({provide: NG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS, useValue: options.settings});
  }

  return providers;
}

function decodeGrpcWebTextResponse(input: string): Uint8Array<ArrayBuffer> {
  const eqIndex = input.lastIndexOf('=');
  if (eqIndex > 0) {
    const data = input.substring(0, eqIndex + 1);
    const trailer = input.substring(eqIndex + 1);
    return concatUint8Arrays([base64ToUint8Array(data), base64ToUint8Array(trailer)]);
  }

  return base64ToUint8Array(input);
}
