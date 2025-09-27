/* tslint:disable */
/* eslint-disable */
// @ts-nocheck
//
// THIS IS A GENERATED FILE
// DO NOT MODIFY IT! YOUR CHANGES WILL BE LOST
import {
  GrpcMessage,
  RecursivePartial,
  ToProtobufJSONOptions
} from '@ngx-grpc/common';
import { BinaryReader, BinaryWriter, ByteSource } from 'google-protobuf';
import * as googleProtobuf000 from '@ngx-grpc/well-known-types';
/**
 * Message implementation for google.rpc.ErrorInfo
 */
export class ErrorInfo implements GrpcMessage {
  static id = 'google.rpc.ErrorInfo';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new ErrorInfo();
    ErrorInfo.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: ErrorInfo) {
    _instance.reason = _instance.reason || '';
    _instance.domain = _instance.domain || '';
    _instance.metadata = _instance.metadata || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: ErrorInfo,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.reason = _reader.readString();
          break;
        case 2:
          _instance.domain = _reader.readString();
          break;
        case 3:
          const msg_3 = {} as any;
          _reader.readMessage(
            msg_3,
            ErrorInfo.MetadataEntry.deserializeBinaryFromReader
          );
          _instance.metadata = _instance.metadata || {};
          _instance.metadata[msg_3.key] = msg_3.value;
          break;
        default:
          _reader.skipField();
      }
    }

    ErrorInfo.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: ErrorInfo, _writer: BinaryWriter) {
    if (_instance.reason) {
      _writer.writeString(1, _instance.reason);
    }
    if (_instance.domain) {
      _writer.writeString(2, _instance.domain);
    }
    if (!!_instance.metadata) {
      const keys_3 = Object.keys(_instance.metadata as any);

      if (keys_3.length) {
        const repeated_3 = keys_3
          .map(key => ({ key: key, value: (_instance.metadata as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          3,
          repeated_3,
          ErrorInfo.MetadataEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _reason: string;
  private _domain: string;
  private _metadata: { [prop: string]: string };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of ErrorInfo to deeply clone from
   */
  constructor(_value?: RecursivePartial<ErrorInfo.AsObject>) {
    _value = _value || {};
    this.reason = _value.reason;
    this.domain = _value.domain;
    (this.metadata = _value!.metadata
      ? Object.keys(_value!.metadata).reduce(
          (r, k) => ({ ...r, [k]: _value!.metadata![k] }),
          {}
        )
      : {}),
      ErrorInfo.refineValues(this);
  }
  get reason(): string {
    return this._reason;
  }
  set reason(value: string) {
    this._reason = value;
  }
  get domain(): string {
    return this._domain;
  }
  set domain(value: string) {
    this._domain = value;
  }
  get metadata(): { [prop: string]: string } {
    return this._metadata;
  }
  set metadata(value: { [prop: string]: string }) {
    this._metadata = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    ErrorInfo.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): ErrorInfo.AsObject {
    return {
      reason: this.reason,
      domain: this.domain,
      metadata: this.metadata
        ? Object.keys(this.metadata).reduce(
            (r, k) => ({ ...r, [k]: this.metadata![k] }),
            {}
          )
        : {}
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): ErrorInfo.AsProtobufJSON {
    return {
      reason: this.reason,
      domain: this.domain,
      metadata: this.metadata
        ? Object.keys(this.metadata).reduce(
            (r, k) => ({ ...r, [k]: this.metadata![k] }),
            {}
          )
        : {}
    };
  }
}
export module ErrorInfo {
  /**
   * Standard JavaScript object representation for ErrorInfo
   */
  export interface AsObject {
    reason: string;
    domain: string;
    metadata: { [prop: string]: string };
  }

  /**
   * Protobuf JSON representation for ErrorInfo
   */
  export interface AsProtobufJSON {
    reason: string;
    domain: string;
    metadata: { [prop: string]: string };
  }

  /**
   * Message implementation for google.rpc.ErrorInfo.MetadataEntry
   */
  export class MetadataEntry implements GrpcMessage {
    static id = 'google.rpc.ErrorInfo.MetadataEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new MetadataEntry();
      MetadataEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: MetadataEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || '';
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: MetadataEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = _reader.readString();
            break;
          default:
            _reader.skipField();
        }
      }

      MetadataEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: MetadataEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeString(2, _instance.value);
      }
    }

    private _key: string;
    private _value: string;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of MetadataEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<MetadataEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value;
      MetadataEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): string {
      return this._value;
    }
    set value(value: string) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      MetadataEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): MetadataEntry.AsObject {
      return {
        key: this.key,
        value: this.value
      };
    }

    /**
     * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
     */
    toJSON() {
      return this.toObject();
    }

    /**
     * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
     * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
     * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
     */
    toProtobufJSON(
      // @ts-ignore
      options?: ToProtobufJSONOptions
    ): MetadataEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value
      };
    }
  }
  export module MetadataEntry {
    /**
     * Standard JavaScript object representation for MetadataEntry
     */
    export interface AsObject {
      key: string;
      value: string;
    }

    /**
     * Protobuf JSON representation for MetadataEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: string;
    }
  }
}

/**
 * Message implementation for google.rpc.RetryInfo
 */
export class RetryInfo implements GrpcMessage {
  static id = 'google.rpc.RetryInfo';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new RetryInfo();
    RetryInfo.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: RetryInfo) {
    _instance.retryDelay = _instance.retryDelay || undefined;
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: RetryInfo,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.retryDelay = new googleProtobuf000.Duration();
          _reader.readMessage(
            _instance.retryDelay,
            googleProtobuf000.Duration.deserializeBinaryFromReader
          );
          break;
        default:
          _reader.skipField();
      }
    }

    RetryInfo.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: RetryInfo, _writer: BinaryWriter) {
    if (_instance.retryDelay) {
      _writer.writeMessage(
        1,
        _instance.retryDelay as any,
        googleProtobuf000.Duration.serializeBinaryToWriter
      );
    }
  }

  private _retryDelay?: googleProtobuf000.Duration;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of RetryInfo to deeply clone from
   */
  constructor(_value?: RecursivePartial<RetryInfo.AsObject>) {
    _value = _value || {};
    this.retryDelay = _value.retryDelay
      ? new googleProtobuf000.Duration(_value.retryDelay)
      : undefined;
    RetryInfo.refineValues(this);
  }
  get retryDelay(): googleProtobuf000.Duration | undefined {
    return this._retryDelay;
  }
  set retryDelay(value: googleProtobuf000.Duration | undefined) {
    this._retryDelay = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    RetryInfo.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): RetryInfo.AsObject {
    return {
      retryDelay: this.retryDelay ? this.retryDelay.toObject() : undefined
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): RetryInfo.AsProtobufJSON {
    return {
      retryDelay: this.retryDelay
        ? this.retryDelay.toProtobufJSON(options)
        : null
    };
  }
}
export module RetryInfo {
  /**
   * Standard JavaScript object representation for RetryInfo
   */
  export interface AsObject {
    retryDelay?: googleProtobuf000.Duration.AsObject;
  }

  /**
   * Protobuf JSON representation for RetryInfo
   */
  export interface AsProtobufJSON {
    retryDelay: googleProtobuf000.Duration.AsProtobufJSON | null;
  }
}

/**
 * Message implementation for google.rpc.DebugInfo
 */
export class DebugInfo implements GrpcMessage {
  static id = 'google.rpc.DebugInfo';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new DebugInfo();
    DebugInfo.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: DebugInfo) {
    _instance.stackEntries = _instance.stackEntries || [];
    _instance.detail = _instance.detail || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: DebugInfo,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          (_instance.stackEntries = _instance.stackEntries || []).push(
            _reader.readString()
          );
          break;
        case 2:
          _instance.detail = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    DebugInfo.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: DebugInfo, _writer: BinaryWriter) {
    if (_instance.stackEntries && _instance.stackEntries.length) {
      _writer.writeRepeatedString(1, _instance.stackEntries);
    }
    if (_instance.detail) {
      _writer.writeString(2, _instance.detail);
    }
  }

  private _stackEntries: string[];
  private _detail: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of DebugInfo to deeply clone from
   */
  constructor(_value?: RecursivePartial<DebugInfo.AsObject>) {
    _value = _value || {};
    this.stackEntries = (_value.stackEntries || []).slice();
    this.detail = _value.detail;
    DebugInfo.refineValues(this);
  }
  get stackEntries(): string[] {
    return this._stackEntries;
  }
  set stackEntries(value: string[]) {
    this._stackEntries = value;
  }
  get detail(): string {
    return this._detail;
  }
  set detail(value: string) {
    this._detail = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    DebugInfo.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): DebugInfo.AsObject {
    return {
      stackEntries: (this.stackEntries || []).slice(),
      detail: this.detail
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): DebugInfo.AsProtobufJSON {
    return {
      stackEntries: (this.stackEntries || []).slice(),
      detail: this.detail
    };
  }
}
export module DebugInfo {
  /**
   * Standard JavaScript object representation for DebugInfo
   */
  export interface AsObject {
    stackEntries: string[];
    detail: string;
  }

  /**
   * Protobuf JSON representation for DebugInfo
   */
  export interface AsProtobufJSON {
    stackEntries: string[];
    detail: string;
  }
}

/**
 * Message implementation for google.rpc.QuotaFailure
 */
export class QuotaFailure implements GrpcMessage {
  static id = 'google.rpc.QuotaFailure';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new QuotaFailure();
    QuotaFailure.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: QuotaFailure) {
    _instance.violations = _instance.violations || [];
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: QuotaFailure,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const messageInitializer1 = new QuotaFailure.Violation();
          _reader.readMessage(
            messageInitializer1,
            QuotaFailure.Violation.deserializeBinaryFromReader
          );
          (_instance.violations = _instance.violations || []).push(
            messageInitializer1
          );
          break;
        default:
          _reader.skipField();
      }
    }

    QuotaFailure.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: QuotaFailure,
    _writer: BinaryWriter
  ) {
    if (_instance.violations && _instance.violations.length) {
      _writer.writeRepeatedMessage(
        1,
        _instance.violations as any,
        QuotaFailure.Violation.serializeBinaryToWriter
      );
    }
  }

  private _violations?: QuotaFailure.Violation[];

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of QuotaFailure to deeply clone from
   */
  constructor(_value?: RecursivePartial<QuotaFailure.AsObject>) {
    _value = _value || {};
    this.violations = (_value.violations || []).map(
      m => new QuotaFailure.Violation(m)
    );
    QuotaFailure.refineValues(this);
  }
  get violations(): QuotaFailure.Violation[] | undefined {
    return this._violations;
  }
  set violations(value: QuotaFailure.Violation[] | undefined) {
    this._violations = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    QuotaFailure.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): QuotaFailure.AsObject {
    return {
      violations: (this.violations || []).map(m => m.toObject())
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): QuotaFailure.AsProtobufJSON {
    return {
      violations: (this.violations || []).map(m => m.toProtobufJSON(options))
    };
  }
}
export module QuotaFailure {
  /**
   * Standard JavaScript object representation for QuotaFailure
   */
  export interface AsObject {
    violations?: QuotaFailure.Violation.AsObject[];
  }

  /**
   * Protobuf JSON representation for QuotaFailure
   */
  export interface AsProtobufJSON {
    violations: QuotaFailure.Violation.AsProtobufJSON[] | null;
  }

  /**
   * Message implementation for google.rpc.QuotaFailure.Violation
   */
  export class Violation implements GrpcMessage {
    static id = 'google.rpc.QuotaFailure.Violation';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new Violation();
      Violation.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: Violation) {
      _instance.subject = _instance.subject || '';
      _instance.description = _instance.description || '';
      _instance.apiService = _instance.apiService || '';
      _instance.quotaMetric = _instance.quotaMetric || '';
      _instance.quotaId = _instance.quotaId || '';
      _instance.quotaDimensions = _instance.quotaDimensions || {};
      _instance.quotaValue = _instance.quotaValue || '0';
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: Violation,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.subject = _reader.readString();
            break;
          case 2:
            _instance.description = _reader.readString();
            break;
          case 3:
            _instance.apiService = _reader.readString();
            break;
          case 4:
            _instance.quotaMetric = _reader.readString();
            break;
          case 5:
            _instance.quotaId = _reader.readString();
            break;
          case 6:
            const msg_6 = {} as any;
            _reader.readMessage(
              msg_6,
              QuotaFailure.Violation.QuotaDimensionsEntry
                .deserializeBinaryFromReader
            );
            _instance.quotaDimensions = _instance.quotaDimensions || {};
            _instance.quotaDimensions[msg_6.key] = msg_6.value;
            break;
          case 7:
            _instance.quotaValue = _reader.readInt64String();
            break;
          case 8:
            _instance.futureQuotaValue = _reader.readInt64String();
            break;
          default:
            _reader.skipField();
        }
      }

      Violation.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: Violation,
      _writer: BinaryWriter
    ) {
      if (_instance.subject) {
        _writer.writeString(1, _instance.subject);
      }
      if (_instance.description) {
        _writer.writeString(2, _instance.description);
      }
      if (_instance.apiService) {
        _writer.writeString(3, _instance.apiService);
      }
      if (_instance.quotaMetric) {
        _writer.writeString(4, _instance.quotaMetric);
      }
      if (_instance.quotaId) {
        _writer.writeString(5, _instance.quotaId);
      }
      if (!!_instance.quotaDimensions) {
        const keys_6 = Object.keys(_instance.quotaDimensions as any);

        if (keys_6.length) {
          const repeated_6 = keys_6
            .map(key => ({
              key: key,
              value: (_instance.quotaDimensions as any)[key]
            }))
            .reduce((r, v) => [...r, v], [] as any[]);

          _writer.writeRepeatedMessage(
            6,
            repeated_6,
            QuotaFailure.Violation.QuotaDimensionsEntry.serializeBinaryToWriter
          );
        }
      }
      if (_instance.quotaValue) {
        _writer.writeInt64String(7, _instance.quotaValue);
      }
      if (
        _instance.futureQuotaValue !== undefined &&
        _instance.futureQuotaValue !== null
      ) {
        _writer.writeInt64String(8, _instance.futureQuotaValue);
      }
    }

    private _subject: string;
    private _description: string;
    private _apiService: string;
    private _quotaMetric: string;
    private _quotaId: string;
    private _quotaDimensions: { [prop: string]: string };
    private _quotaValue: string;
    private _futureQuotaValue?: string;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of Violation to deeply clone from
     */
    constructor(_value?: RecursivePartial<Violation.AsObject>) {
      _value = _value || {};
      this.subject = _value.subject;
      this.description = _value.description;
      this.apiService = _value.apiService;
      this.quotaMetric = _value.quotaMetric;
      this.quotaId = _value.quotaId;
      (this.quotaDimensions = _value!.quotaDimensions
        ? Object.keys(_value!.quotaDimensions).reduce(
            (r, k) => ({ ...r, [k]: _value!.quotaDimensions![k] }),
            {}
          )
        : {}),
        (this.quotaValue = _value.quotaValue);
      this.futureQuotaValue = _value.futureQuotaValue;
      Violation.refineValues(this);
    }
    get subject(): string {
      return this._subject;
    }
    set subject(value: string) {
      this._subject = value;
    }
    get description(): string {
      return this._description;
    }
    set description(value: string) {
      this._description = value;
    }
    get apiService(): string {
      return this._apiService;
    }
    set apiService(value: string) {
      this._apiService = value;
    }
    get quotaMetric(): string {
      return this._quotaMetric;
    }
    set quotaMetric(value: string) {
      this._quotaMetric = value;
    }
    get quotaId(): string {
      return this._quotaId;
    }
    set quotaId(value: string) {
      this._quotaId = value;
    }
    get quotaDimensions(): { [prop: string]: string } {
      return this._quotaDimensions;
    }
    set quotaDimensions(value: { [prop: string]: string }) {
      this._quotaDimensions = value;
    }
    get quotaValue(): string {
      return this._quotaValue;
    }
    set quotaValue(value: string) {
      this._quotaValue = value;
    }
    get futureQuotaValue(): string | undefined {
      return this._futureQuotaValue;
    }
    set futureQuotaValue(value?: string) {
      this._futureQuotaValue = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      Violation.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): Violation.AsObject {
      return {
        subject: this.subject,
        description: this.description,
        apiService: this.apiService,
        quotaMetric: this.quotaMetric,
        quotaId: this.quotaId,
        quotaDimensions: this.quotaDimensions
          ? Object.keys(this.quotaDimensions).reduce(
              (r, k) => ({ ...r, [k]: this.quotaDimensions![k] }),
              {}
            )
          : {},
        quotaValue: this.quotaValue,
        futureQuotaValue: this.futureQuotaValue
      };
    }

    /**
     * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
     */
    toJSON() {
      return this.toObject();
    }

    /**
     * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
     * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
     * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
     */
    toProtobufJSON(
      // @ts-ignore
      options?: ToProtobufJSONOptions
    ): Violation.AsProtobufJSON {
      return {
        subject: this.subject,
        description: this.description,
        apiService: this.apiService,
        quotaMetric: this.quotaMetric,
        quotaId: this.quotaId,
        quotaDimensions: this.quotaDimensions
          ? Object.keys(this.quotaDimensions).reduce(
              (r, k) => ({ ...r, [k]: this.quotaDimensions![k] }),
              {}
            )
          : {},
        quotaValue: this.quotaValue,
        futureQuotaValue:
          this.futureQuotaValue === null || this.futureQuotaValue === undefined
            ? null
            : this.futureQuotaValue
      };
    }
  }
  export module Violation {
    /**
     * Standard JavaScript object representation for Violation
     */
    export interface AsObject {
      subject: string;
      description: string;
      apiService: string;
      quotaMetric: string;
      quotaId: string;
      quotaDimensions: { [prop: string]: string };
      quotaValue: string;
      futureQuotaValue?: string;
    }

    /**
     * Protobuf JSON representation for Violation
     */
    export interface AsProtobufJSON {
      subject: string;
      description: string;
      apiService: string;
      quotaMetric: string;
      quotaId: string;
      quotaDimensions: { [prop: string]: string };
      quotaValue: string;
      futureQuotaValue: string | null;
    }

    /**
     * Message implementation for google.rpc.QuotaFailure.Violation.QuotaDimensionsEntry
     */
    export class QuotaDimensionsEntry implements GrpcMessage {
      static id = 'google.rpc.QuotaFailure.Violation.QuotaDimensionsEntry';

      /**
       * Deserialize binary data to message
       * @param instance message instance
       */
      static deserializeBinary(bytes: ByteSource) {
        const instance = new QuotaDimensionsEntry();
        QuotaDimensionsEntry.deserializeBinaryFromReader(
          instance,
          new BinaryReader(bytes)
        );
        return instance;
      }

      /**
       * Check all the properties and set default protobuf values if necessary
       * @param _instance message instance
       */
      static refineValues(_instance: QuotaDimensionsEntry) {
        _instance.key = _instance.key || '';
        _instance.value = _instance.value || '';
      }

      /**
       * Deserializes / reads binary message into message instance using provided binary reader
       * @param _instance message instance
       * @param _reader binary reader instance
       */
      static deserializeBinaryFromReader(
        _instance: QuotaDimensionsEntry,
        _reader: BinaryReader
      ) {
        while (_reader.nextField()) {
          if (_reader.isEndGroup()) break;

          switch (_reader.getFieldNumber()) {
            case 1:
              _instance.key = _reader.readString();
              break;
            case 2:
              _instance.value = _reader.readString();
              break;
            default:
              _reader.skipField();
          }
        }

        QuotaDimensionsEntry.refineValues(_instance);
      }

      /**
       * Serializes a message to binary format using provided binary reader
       * @param _instance message instance
       * @param _writer binary writer instance
       */
      static serializeBinaryToWriter(
        _instance: QuotaDimensionsEntry,
        _writer: BinaryWriter
      ) {
        if (_instance.key) {
          _writer.writeString(1, _instance.key);
        }
        if (_instance.value) {
          _writer.writeString(2, _instance.value);
        }
      }

      private _key: string;
      private _value: string;

      /**
       * Message constructor. Initializes the properties and applies default Protobuf values if necessary
       * @param _value initial values object or instance of QuotaDimensionsEntry to deeply clone from
       */
      constructor(_value?: RecursivePartial<QuotaDimensionsEntry.AsObject>) {
        _value = _value || {};
        this.key = _value.key;
        this.value = _value.value;
        QuotaDimensionsEntry.refineValues(this);
      }
      get key(): string {
        return this._key;
      }
      set key(value: string) {
        this._key = value;
      }
      get value(): string {
        return this._value;
      }
      set value(value: string) {
        this._value = value;
      }

      /**
       * Serialize message to binary data
       * @param instance message instance
       */
      serializeBinary() {
        const writer = new BinaryWriter();
        QuotaDimensionsEntry.serializeBinaryToWriter(this, writer);
        return writer.getResultBuffer();
      }

      /**
       * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
       */
      toObject(): QuotaDimensionsEntry.AsObject {
        return {
          key: this.key,
          value: this.value
        };
      }

      /**
       * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
       */
      toJSON() {
        return this.toObject();
      }

      /**
       * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
       * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
       * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
       */
      toProtobufJSON(
        // @ts-ignore
        options?: ToProtobufJSONOptions
      ): QuotaDimensionsEntry.AsProtobufJSON {
        return {
          key: this.key,
          value: this.value
        };
      }
    }
    export module QuotaDimensionsEntry {
      /**
       * Standard JavaScript object representation for QuotaDimensionsEntry
       */
      export interface AsObject {
        key: string;
        value: string;
      }

      /**
       * Protobuf JSON representation for QuotaDimensionsEntry
       */
      export interface AsProtobufJSON {
        key: string;
        value: string;
      }
    }
  }
}

/**
 * Message implementation for google.rpc.PreconditionFailure
 */
export class PreconditionFailure implements GrpcMessage {
  static id = 'google.rpc.PreconditionFailure';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new PreconditionFailure();
    PreconditionFailure.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: PreconditionFailure) {
    _instance.violations = _instance.violations || [];
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: PreconditionFailure,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const messageInitializer1 = new PreconditionFailure.Violation();
          _reader.readMessage(
            messageInitializer1,
            PreconditionFailure.Violation.deserializeBinaryFromReader
          );
          (_instance.violations = _instance.violations || []).push(
            messageInitializer1
          );
          break;
        default:
          _reader.skipField();
      }
    }

    PreconditionFailure.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: PreconditionFailure,
    _writer: BinaryWriter
  ) {
    if (_instance.violations && _instance.violations.length) {
      _writer.writeRepeatedMessage(
        1,
        _instance.violations as any,
        PreconditionFailure.Violation.serializeBinaryToWriter
      );
    }
  }

  private _violations?: PreconditionFailure.Violation[];

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of PreconditionFailure to deeply clone from
   */
  constructor(_value?: RecursivePartial<PreconditionFailure.AsObject>) {
    _value = _value || {};
    this.violations = (_value.violations || []).map(
      m => new PreconditionFailure.Violation(m)
    );
    PreconditionFailure.refineValues(this);
  }
  get violations(): PreconditionFailure.Violation[] | undefined {
    return this._violations;
  }
  set violations(value: PreconditionFailure.Violation[] | undefined) {
    this._violations = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    PreconditionFailure.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): PreconditionFailure.AsObject {
    return {
      violations: (this.violations || []).map(m => m.toObject())
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): PreconditionFailure.AsProtobufJSON {
    return {
      violations: (this.violations || []).map(m => m.toProtobufJSON(options))
    };
  }
}
export module PreconditionFailure {
  /**
   * Standard JavaScript object representation for PreconditionFailure
   */
  export interface AsObject {
    violations?: PreconditionFailure.Violation.AsObject[];
  }

  /**
   * Protobuf JSON representation for PreconditionFailure
   */
  export interface AsProtobufJSON {
    violations: PreconditionFailure.Violation.AsProtobufJSON[] | null;
  }

  /**
   * Message implementation for google.rpc.PreconditionFailure.Violation
   */
  export class Violation implements GrpcMessage {
    static id = 'google.rpc.PreconditionFailure.Violation';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new Violation();
      Violation.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: Violation) {
      _instance.type = _instance.type || '';
      _instance.subject = _instance.subject || '';
      _instance.description = _instance.description || '';
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: Violation,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.type = _reader.readString();
            break;
          case 2:
            _instance.subject = _reader.readString();
            break;
          case 3:
            _instance.description = _reader.readString();
            break;
          default:
            _reader.skipField();
        }
      }

      Violation.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: Violation,
      _writer: BinaryWriter
    ) {
      if (_instance.type) {
        _writer.writeString(1, _instance.type);
      }
      if (_instance.subject) {
        _writer.writeString(2, _instance.subject);
      }
      if (_instance.description) {
        _writer.writeString(3, _instance.description);
      }
    }

    private _type: string;
    private _subject: string;
    private _description: string;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of Violation to deeply clone from
     */
    constructor(_value?: RecursivePartial<Violation.AsObject>) {
      _value = _value || {};
      this.type = _value.type;
      this.subject = _value.subject;
      this.description = _value.description;
      Violation.refineValues(this);
    }
    get type(): string {
      return this._type;
    }
    set type(value: string) {
      this._type = value;
    }
    get subject(): string {
      return this._subject;
    }
    set subject(value: string) {
      this._subject = value;
    }
    get description(): string {
      return this._description;
    }
    set description(value: string) {
      this._description = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      Violation.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): Violation.AsObject {
      return {
        type: this.type,
        subject: this.subject,
        description: this.description
      };
    }

    /**
     * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
     */
    toJSON() {
      return this.toObject();
    }

    /**
     * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
     * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
     * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
     */
    toProtobufJSON(
      // @ts-ignore
      options?: ToProtobufJSONOptions
    ): Violation.AsProtobufJSON {
      return {
        type: this.type,
        subject: this.subject,
        description: this.description
      };
    }
  }
  export module Violation {
    /**
     * Standard JavaScript object representation for Violation
     */
    export interface AsObject {
      type: string;
      subject: string;
      description: string;
    }

    /**
     * Protobuf JSON representation for Violation
     */
    export interface AsProtobufJSON {
      type: string;
      subject: string;
      description: string;
    }
  }
}

/**
 * Message implementation for google.rpc.BadRequest
 */
export class BadRequest implements GrpcMessage {
  static id = 'google.rpc.BadRequest';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new BadRequest();
    BadRequest.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: BadRequest) {
    _instance.fieldViolations = _instance.fieldViolations || [];
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: BadRequest,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const messageInitializer1 = new BadRequest.FieldViolation();
          _reader.readMessage(
            messageInitializer1,
            BadRequest.FieldViolation.deserializeBinaryFromReader
          );
          (_instance.fieldViolations = _instance.fieldViolations || []).push(
            messageInitializer1
          );
          break;
        default:
          _reader.skipField();
      }
    }

    BadRequest.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: BadRequest, _writer: BinaryWriter) {
    if (_instance.fieldViolations && _instance.fieldViolations.length) {
      _writer.writeRepeatedMessage(
        1,
        _instance.fieldViolations as any,
        BadRequest.FieldViolation.serializeBinaryToWriter
      );
    }
  }

  private _fieldViolations?: BadRequest.FieldViolation[];

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of BadRequest to deeply clone from
   */
  constructor(_value?: RecursivePartial<BadRequest.AsObject>) {
    _value = _value || {};
    this.fieldViolations = (_value.fieldViolations || []).map(
      m => new BadRequest.FieldViolation(m)
    );
    BadRequest.refineValues(this);
  }
  get fieldViolations(): BadRequest.FieldViolation[] | undefined {
    return this._fieldViolations;
  }
  set fieldViolations(value: BadRequest.FieldViolation[] | undefined) {
    this._fieldViolations = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    BadRequest.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): BadRequest.AsObject {
    return {
      fieldViolations: (this.fieldViolations || []).map(m => m.toObject())
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): BadRequest.AsProtobufJSON {
    return {
      fieldViolations: (this.fieldViolations || []).map(m =>
        m.toProtobufJSON(options)
      )
    };
  }
}
export module BadRequest {
  /**
   * Standard JavaScript object representation for BadRequest
   */
  export interface AsObject {
    fieldViolations?: BadRequest.FieldViolation.AsObject[];
  }

  /**
   * Protobuf JSON representation for BadRequest
   */
  export interface AsProtobufJSON {
    fieldViolations: BadRequest.FieldViolation.AsProtobufJSON[] | null;
  }

  /**
   * Message implementation for google.rpc.BadRequest.FieldViolation
   */
  export class FieldViolation implements GrpcMessage {
    static id = 'google.rpc.BadRequest.FieldViolation';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new FieldViolation();
      FieldViolation.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: FieldViolation) {
      _instance.field = _instance.field || '';
      _instance.description = _instance.description || '';
      _instance.reason = _instance.reason || '';
      _instance.localizedMessage = _instance.localizedMessage || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: FieldViolation,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.field = _reader.readString();
            break;
          case 2:
            _instance.description = _reader.readString();
            break;
          case 3:
            _instance.reason = _reader.readString();
            break;
          case 4:
            _instance.localizedMessage = new LocalizedMessage();
            _reader.readMessage(
              _instance.localizedMessage,
              LocalizedMessage.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      FieldViolation.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: FieldViolation,
      _writer: BinaryWriter
    ) {
      if (_instance.field) {
        _writer.writeString(1, _instance.field);
      }
      if (_instance.description) {
        _writer.writeString(2, _instance.description);
      }
      if (_instance.reason) {
        _writer.writeString(3, _instance.reason);
      }
      if (_instance.localizedMessage) {
        _writer.writeMessage(
          4,
          _instance.localizedMessage as any,
          LocalizedMessage.serializeBinaryToWriter
        );
      }
    }

    private _field: string;
    private _description: string;
    private _reason: string;
    private _localizedMessage?: LocalizedMessage;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of FieldViolation to deeply clone from
     */
    constructor(_value?: RecursivePartial<FieldViolation.AsObject>) {
      _value = _value || {};
      this.field = _value.field;
      this.description = _value.description;
      this.reason = _value.reason;
      this.localizedMessage = _value.localizedMessage
        ? new LocalizedMessage(_value.localizedMessage)
        : undefined;
      FieldViolation.refineValues(this);
    }
    get field(): string {
      return this._field;
    }
    set field(value: string) {
      this._field = value;
    }
    get description(): string {
      return this._description;
    }
    set description(value: string) {
      this._description = value;
    }
    get reason(): string {
      return this._reason;
    }
    set reason(value: string) {
      this._reason = value;
    }
    get localizedMessage(): LocalizedMessage | undefined {
      return this._localizedMessage;
    }
    set localizedMessage(value: LocalizedMessage | undefined) {
      this._localizedMessage = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      FieldViolation.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): FieldViolation.AsObject {
      return {
        field: this.field,
        description: this.description,
        reason: this.reason,
        localizedMessage: this.localizedMessage
          ? this.localizedMessage.toObject()
          : undefined
      };
    }

    /**
     * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
     */
    toJSON() {
      return this.toObject();
    }

    /**
     * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
     * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
     * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
     */
    toProtobufJSON(
      // @ts-ignore
      options?: ToProtobufJSONOptions
    ): FieldViolation.AsProtobufJSON {
      return {
        field: this.field,
        description: this.description,
        reason: this.reason,
        localizedMessage: this.localizedMessage
          ? this.localizedMessage.toProtobufJSON(options)
          : null
      };
    }
  }
  export module FieldViolation {
    /**
     * Standard JavaScript object representation for FieldViolation
     */
    export interface AsObject {
      field: string;
      description: string;
      reason: string;
      localizedMessage?: LocalizedMessage.AsObject;
    }

    /**
     * Protobuf JSON representation for FieldViolation
     */
    export interface AsProtobufJSON {
      field: string;
      description: string;
      reason: string;
      localizedMessage: LocalizedMessage.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for google.rpc.RequestInfo
 */
export class RequestInfo implements GrpcMessage {
  static id = 'google.rpc.RequestInfo';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new RequestInfo();
    RequestInfo.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: RequestInfo) {
    _instance.requestId = _instance.requestId || '';
    _instance.servingData = _instance.servingData || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: RequestInfo,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.requestId = _reader.readString();
          break;
        case 2:
          _instance.servingData = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    RequestInfo.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: RequestInfo,
    _writer: BinaryWriter
  ) {
    if (_instance.requestId) {
      _writer.writeString(1, _instance.requestId);
    }
    if (_instance.servingData) {
      _writer.writeString(2, _instance.servingData);
    }
  }

  private _requestId: string;
  private _servingData: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of RequestInfo to deeply clone from
   */
  constructor(_value?: RecursivePartial<RequestInfo.AsObject>) {
    _value = _value || {};
    this.requestId = _value.requestId;
    this.servingData = _value.servingData;
    RequestInfo.refineValues(this);
  }
  get requestId(): string {
    return this._requestId;
  }
  set requestId(value: string) {
    this._requestId = value;
  }
  get servingData(): string {
    return this._servingData;
  }
  set servingData(value: string) {
    this._servingData = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    RequestInfo.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): RequestInfo.AsObject {
    return {
      requestId: this.requestId,
      servingData: this.servingData
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): RequestInfo.AsProtobufJSON {
    return {
      requestId: this.requestId,
      servingData: this.servingData
    };
  }
}
export module RequestInfo {
  /**
   * Standard JavaScript object representation for RequestInfo
   */
  export interface AsObject {
    requestId: string;
    servingData: string;
  }

  /**
   * Protobuf JSON representation for RequestInfo
   */
  export interface AsProtobufJSON {
    requestId: string;
    servingData: string;
  }
}

/**
 * Message implementation for google.rpc.ResourceInfo
 */
export class ResourceInfo implements GrpcMessage {
  static id = 'google.rpc.ResourceInfo';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new ResourceInfo();
    ResourceInfo.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: ResourceInfo) {
    _instance.resourceType = _instance.resourceType || '';
    _instance.resourceName = _instance.resourceName || '';
    _instance.owner = _instance.owner || '';
    _instance.description = _instance.description || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: ResourceInfo,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.resourceType = _reader.readString();
          break;
        case 2:
          _instance.resourceName = _reader.readString();
          break;
        case 3:
          _instance.owner = _reader.readString();
          break;
        case 4:
          _instance.description = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    ResourceInfo.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: ResourceInfo,
    _writer: BinaryWriter
  ) {
    if (_instance.resourceType) {
      _writer.writeString(1, _instance.resourceType);
    }
    if (_instance.resourceName) {
      _writer.writeString(2, _instance.resourceName);
    }
    if (_instance.owner) {
      _writer.writeString(3, _instance.owner);
    }
    if (_instance.description) {
      _writer.writeString(4, _instance.description);
    }
  }

  private _resourceType: string;
  private _resourceName: string;
  private _owner: string;
  private _description: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of ResourceInfo to deeply clone from
   */
  constructor(_value?: RecursivePartial<ResourceInfo.AsObject>) {
    _value = _value || {};
    this.resourceType = _value.resourceType;
    this.resourceName = _value.resourceName;
    this.owner = _value.owner;
    this.description = _value.description;
    ResourceInfo.refineValues(this);
  }
  get resourceType(): string {
    return this._resourceType;
  }
  set resourceType(value: string) {
    this._resourceType = value;
  }
  get resourceName(): string {
    return this._resourceName;
  }
  set resourceName(value: string) {
    this._resourceName = value;
  }
  get owner(): string {
    return this._owner;
  }
  set owner(value: string) {
    this._owner = value;
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    ResourceInfo.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): ResourceInfo.AsObject {
    return {
      resourceType: this.resourceType,
      resourceName: this.resourceName,
      owner: this.owner,
      description: this.description
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): ResourceInfo.AsProtobufJSON {
    return {
      resourceType: this.resourceType,
      resourceName: this.resourceName,
      owner: this.owner,
      description: this.description
    };
  }
}
export module ResourceInfo {
  /**
   * Standard JavaScript object representation for ResourceInfo
   */
  export interface AsObject {
    resourceType: string;
    resourceName: string;
    owner: string;
    description: string;
  }

  /**
   * Protobuf JSON representation for ResourceInfo
   */
  export interface AsProtobufJSON {
    resourceType: string;
    resourceName: string;
    owner: string;
    description: string;
  }
}

/**
 * Message implementation for google.rpc.Help
 */
export class Help implements GrpcMessage {
  static id = 'google.rpc.Help';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Help();
    Help.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Help) {
    _instance.links = _instance.links || [];
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Help, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const messageInitializer1 = new Help.Link();
          _reader.readMessage(
            messageInitializer1,
            Help.Link.deserializeBinaryFromReader
          );
          (_instance.links = _instance.links || []).push(messageInitializer1);
          break;
        default:
          _reader.skipField();
      }
    }

    Help.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Help, _writer: BinaryWriter) {
    if (_instance.links && _instance.links.length) {
      _writer.writeRepeatedMessage(
        1,
        _instance.links as any,
        Help.Link.serializeBinaryToWriter
      );
    }
  }

  private _links?: Help.Link[];

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Help to deeply clone from
   */
  constructor(_value?: RecursivePartial<Help.AsObject>) {
    _value = _value || {};
    this.links = (_value.links || []).map(m => new Help.Link(m));
    Help.refineValues(this);
  }
  get links(): Help.Link[] | undefined {
    return this._links;
  }
  set links(value: Help.Link[] | undefined) {
    this._links = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Help.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Help.AsObject {
    return {
      links: (this.links || []).map(m => m.toObject())
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): Help.AsProtobufJSON {
    return {
      links: (this.links || []).map(m => m.toProtobufJSON(options))
    };
  }
}
export module Help {
  /**
   * Standard JavaScript object representation for Help
   */
  export interface AsObject {
    links?: Help.Link.AsObject[];
  }

  /**
   * Protobuf JSON representation for Help
   */
  export interface AsProtobufJSON {
    links: Help.Link.AsProtobufJSON[] | null;
  }

  /**
   * Message implementation for google.rpc.Help.Link
   */
  export class Link implements GrpcMessage {
    static id = 'google.rpc.Help.Link';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new Link();
      Link.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: Link) {
      _instance.description = _instance.description || '';
      _instance.url = _instance.url || '';
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(_instance: Link, _reader: BinaryReader) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.description = _reader.readString();
            break;
          case 2:
            _instance.url = _reader.readString();
            break;
          default:
            _reader.skipField();
        }
      }

      Link.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(_instance: Link, _writer: BinaryWriter) {
      if (_instance.description) {
        _writer.writeString(1, _instance.description);
      }
      if (_instance.url) {
        _writer.writeString(2, _instance.url);
      }
    }

    private _description: string;
    private _url: string;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of Link to deeply clone from
     */
    constructor(_value?: RecursivePartial<Link.AsObject>) {
      _value = _value || {};
      this.description = _value.description;
      this.url = _value.url;
      Link.refineValues(this);
    }
    get description(): string {
      return this._description;
    }
    set description(value: string) {
      this._description = value;
    }
    get url(): string {
      return this._url;
    }
    set url(value: string) {
      this._url = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      Link.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): Link.AsObject {
      return {
        description: this.description,
        url: this.url
      };
    }

    /**
     * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
     */
    toJSON() {
      return this.toObject();
    }

    /**
     * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
     * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
     * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
     */
    toProtobufJSON(
      // @ts-ignore
      options?: ToProtobufJSONOptions
    ): Link.AsProtobufJSON {
      return {
        description: this.description,
        url: this.url
      };
    }
  }
  export module Link {
    /**
     * Standard JavaScript object representation for Link
     */
    export interface AsObject {
      description: string;
      url: string;
    }

    /**
     * Protobuf JSON representation for Link
     */
    export interface AsProtobufJSON {
      description: string;
      url: string;
    }
  }
}

/**
 * Message implementation for google.rpc.LocalizedMessage
 */
export class LocalizedMessage implements GrpcMessage {
  static id = 'google.rpc.LocalizedMessage';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new LocalizedMessage();
    LocalizedMessage.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: LocalizedMessage) {
    _instance.locale = _instance.locale || '';
    _instance.message = _instance.message || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: LocalizedMessage,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.locale = _reader.readString();
          break;
        case 2:
          _instance.message = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    LocalizedMessage.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: LocalizedMessage,
    _writer: BinaryWriter
  ) {
    if (_instance.locale) {
      _writer.writeString(1, _instance.locale);
    }
    if (_instance.message) {
      _writer.writeString(2, _instance.message);
    }
  }

  private _locale: string;
  private _message: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of LocalizedMessage to deeply clone from
   */
  constructor(_value?: RecursivePartial<LocalizedMessage.AsObject>) {
    _value = _value || {};
    this.locale = _value.locale;
    this.message = _value.message;
    LocalizedMessage.refineValues(this);
  }
  get locale(): string {
    return this._locale;
  }
  set locale(value: string) {
    this._locale = value;
  }
  get message(): string {
    return this._message;
  }
  set message(value: string) {
    this._message = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    LocalizedMessage.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): LocalizedMessage.AsObject {
    return {
      locale: this.locale,
      message: this.message
    };
  }

  /**
   * Convenience method to support JSON.stringify(message), replicates the structure of toObject()
   */
  toJSON() {
    return this.toObject();
  }

  /**
   * Cast message to JSON using protobuf JSON notation: https://developers.google.com/protocol-buffers/docs/proto3#json
   * Attention: output differs from toObject() e.g. enums are represented as names and not as numbers, Timestamp is an ISO Date string format etc.
   * If the message itself or some of descendant messages is google.protobuf.Any, you MUST provide a message pool as options. If not, the messagePool is not required
   */
  toProtobufJSON(
    // @ts-ignore
    options?: ToProtobufJSONOptions
  ): LocalizedMessage.AsProtobufJSON {
    return {
      locale: this.locale,
      message: this.message
    };
  }
}
export module LocalizedMessage {
  /**
   * Standard JavaScript object representation for LocalizedMessage
   */
  export interface AsObject {
    locale: string;
    message: string;
  }

  /**
   * Protobuf JSON representation for LocalizedMessage
   */
  export interface AsProtobufJSON {
    locale: string;
    message: string;
  }
}
