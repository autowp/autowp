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

/**
 * Message implementation for google.api.Http
 */
export class Http implements GrpcMessage {
  static id = 'google.api.Http';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Http();
    Http.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Http) {
    _instance.rules = _instance.rules || [];
    _instance.fullyDecodeReservedExpansion =
      _instance.fullyDecodeReservedExpansion || false;
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Http, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const messageInitializer1 = new HttpRule();
          _reader.readMessage(
            messageInitializer1,
            HttpRule.deserializeBinaryFromReader
          );
          (_instance.rules = _instance.rules || []).push(messageInitializer1);
          break;
        case 2:
          _instance.fullyDecodeReservedExpansion = _reader.readBool();
          break;
        default:
          _reader.skipField();
      }
    }

    Http.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Http, _writer: BinaryWriter) {
    if (_instance.rules && _instance.rules.length) {
      _writer.writeRepeatedMessage(
        1,
        _instance.rules as any,
        HttpRule.serializeBinaryToWriter
      );
    }
    if (_instance.fullyDecodeReservedExpansion) {
      _writer.writeBool(2, _instance.fullyDecodeReservedExpansion);
    }
  }

  private _rules?: HttpRule[];
  private _fullyDecodeReservedExpansion: boolean;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Http to deeply clone from
   */
  constructor(_value?: RecursivePartial<Http.AsObject>) {
    _value = _value || {};
    this.rules = (_value.rules || []).map(m => new HttpRule(m));
    this.fullyDecodeReservedExpansion = _value.fullyDecodeReservedExpansion;
    Http.refineValues(this);
  }
  get rules(): HttpRule[] | undefined {
    return this._rules;
  }
  set rules(value: HttpRule[] | undefined) {
    this._rules = value;
  }
  get fullyDecodeReservedExpansion(): boolean {
    return this._fullyDecodeReservedExpansion;
  }
  set fullyDecodeReservedExpansion(value: boolean) {
    this._fullyDecodeReservedExpansion = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Http.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Http.AsObject {
    return {
      rules: (this.rules || []).map(m => m.toObject()),
      fullyDecodeReservedExpansion: this.fullyDecodeReservedExpansion
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
  ): Http.AsProtobufJSON {
    return {
      rules: (this.rules || []).map(m => m.toProtobufJSON(options)),
      fullyDecodeReservedExpansion: this.fullyDecodeReservedExpansion
    };
  }
}
export module Http {
  /**
   * Standard JavaScript object representation for Http
   */
  export interface AsObject {
    rules?: HttpRule.AsObject[];
    fullyDecodeReservedExpansion: boolean;
  }

  /**
   * Protobuf JSON representation for Http
   */
  export interface AsProtobufJSON {
    rules: HttpRule.AsProtobufJSON[] | null;
    fullyDecodeReservedExpansion: boolean;
  }
}

/**
 * Message implementation for google.api.HttpRule
 */
export class HttpRule implements GrpcMessage {
  static id = 'google.api.HttpRule';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new HttpRule();
    HttpRule.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: HttpRule) {
    _instance.selector = _instance.selector || '';

    _instance.body = _instance.body || '';
    _instance.responseBody = _instance.responseBody || '';
    _instance.additionalBindings = _instance.additionalBindings || [];
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: HttpRule,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.selector = _reader.readString();
          break;
        case 2:
          _instance.get = _reader.readString();
          break;
        case 3:
          _instance.put = _reader.readString();
          break;
        case 4:
          _instance.post = _reader.readString();
          break;
        case 5:
          _instance.delete = _reader.readString();
          break;
        case 6:
          _instance.patch = _reader.readString();
          break;
        case 8:
          _instance.custom = new CustomHttpPattern();
          _reader.readMessage(
            _instance.custom,
            CustomHttpPattern.deserializeBinaryFromReader
          );
          break;
        case 7:
          _instance.body = _reader.readString();
          break;
        case 12:
          _instance.responseBody = _reader.readString();
          break;
        case 11:
          const messageInitializer11 = new HttpRule();
          _reader.readMessage(
            messageInitializer11,
            HttpRule.deserializeBinaryFromReader
          );
          (_instance.additionalBindings =
            _instance.additionalBindings || []).push(messageInitializer11);
          break;
        default:
          _reader.skipField();
      }
    }

    HttpRule.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: HttpRule, _writer: BinaryWriter) {
    if (_instance.selector) {
      _writer.writeString(1, _instance.selector);
    }
    if (_instance.get || _instance.get === '') {
      _writer.writeString(2, _instance.get);
    }
    if (_instance.put || _instance.put === '') {
      _writer.writeString(3, _instance.put);
    }
    if (_instance.post || _instance.post === '') {
      _writer.writeString(4, _instance.post);
    }
    if (_instance.delete || _instance.delete === '') {
      _writer.writeString(5, _instance.delete);
    }
    if (_instance.patch || _instance.patch === '') {
      _writer.writeString(6, _instance.patch);
    }
    if (_instance.custom) {
      _writer.writeMessage(
        8,
        _instance.custom as any,
        CustomHttpPattern.serializeBinaryToWriter
      );
    }
    if (_instance.body) {
      _writer.writeString(7, _instance.body);
    }
    if (_instance.responseBody) {
      _writer.writeString(12, _instance.responseBody);
    }
    if (_instance.additionalBindings && _instance.additionalBindings.length) {
      _writer.writeRepeatedMessage(
        11,
        _instance.additionalBindings as any,
        HttpRule.serializeBinaryToWriter
      );
    }
  }

  private _selector: string;
  private _get: string;
  private _put: string;
  private _post: string;
  private _delete: string;
  private _patch: string;
  private _custom?: CustomHttpPattern;
  private _body: string;
  private _responseBody: string;
  private _additionalBindings?: HttpRule[];

  private _pattern: HttpRule.PatternCase = HttpRule.PatternCase.none;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of HttpRule to deeply clone from
   */
  constructor(_value?: RecursivePartial<HttpRule.AsObject>) {
    _value = _value || {};
    this.selector = _value.selector;
    this.get = _value.get;
    this.put = _value.put;
    this.post = _value.post;
    this.delete = _value.delete;
    this.patch = _value.patch;
    this.custom = _value.custom
      ? new CustomHttpPattern(_value.custom)
      : undefined;
    this.body = _value.body;
    this.responseBody = _value.responseBody;
    this.additionalBindings = (_value.additionalBindings || []).map(
      m => new HttpRule(m)
    );
    HttpRule.refineValues(this);
  }
  get selector(): string {
    return this._selector;
  }
  set selector(value: string) {
    this._selector = value;
  }
  get get(): string {
    return this._get;
  }
  set get(value: string) {
    if (value !== undefined && value !== null) {
      this._put = this._post = this._delete = this._patch = this._custom = undefined;
      this._pattern = HttpRule.PatternCase.get;
    }
    this._get = value;
  }
  get put(): string {
    return this._put;
  }
  set put(value: string) {
    if (value !== undefined && value !== null) {
      this._get = this._post = this._delete = this._patch = this._custom = undefined;
      this._pattern = HttpRule.PatternCase.put;
    }
    this._put = value;
  }
  get post(): string {
    return this._post;
  }
  set post(value: string) {
    if (value !== undefined && value !== null) {
      this._get = this._put = this._delete = this._patch = this._custom = undefined;
      this._pattern = HttpRule.PatternCase.post;
    }
    this._post = value;
  }
  get delete(): string {
    return this._delete;
  }
  set delete(value: string) {
    if (value !== undefined && value !== null) {
      this._get = this._put = this._post = this._patch = this._custom = undefined;
      this._pattern = HttpRule.PatternCase.delete;
    }
    this._delete = value;
  }
  get patch(): string {
    return this._patch;
  }
  set patch(value: string) {
    if (value !== undefined && value !== null) {
      this._get = this._put = this._post = this._delete = this._custom = undefined;
      this._pattern = HttpRule.PatternCase.patch;
    }
    this._patch = value;
  }
  get custom(): CustomHttpPattern | undefined {
    return this._custom;
  }
  set custom(value: CustomHttpPattern | undefined) {
    if (value !== undefined && value !== null) {
      this._get = this._put = this._post = this._delete = this._patch = undefined;
      this._pattern = HttpRule.PatternCase.custom;
    }
    this._custom = value;
  }
  get body(): string {
    return this._body;
  }
  set body(value: string) {
    this._body = value;
  }
  get responseBody(): string {
    return this._responseBody;
  }
  set responseBody(value: string) {
    this._responseBody = value;
  }
  get additionalBindings(): HttpRule[] | undefined {
    return this._additionalBindings;
  }
  set additionalBindings(value: HttpRule[] | undefined) {
    this._additionalBindings = value;
  }
  get pattern() {
    return this._pattern;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    HttpRule.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): HttpRule.AsObject {
    return {
      selector: this.selector,
      get: this.get,
      put: this.put,
      post: this.post,
      delete: this.delete,
      patch: this.patch,
      custom: this.custom ? this.custom.toObject() : undefined,
      body: this.body,
      responseBody: this.responseBody,
      additionalBindings: (this.additionalBindings || []).map(m => m.toObject())
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
  ): HttpRule.AsProtobufJSON {
    return {
      selector: this.selector,
      get: this.get === null || this.get === undefined ? null : this.get,
      put: this.put === null || this.put === undefined ? null : this.put,
      post: this.post === null || this.post === undefined ? null : this.post,
      delete:
        this.delete === null || this.delete === undefined ? null : this.delete,
      patch:
        this.patch === null || this.patch === undefined ? null : this.patch,
      custom: this.custom ? this.custom.toProtobufJSON(options) : null,
      body: this.body,
      responseBody: this.responseBody,
      additionalBindings: (this.additionalBindings || []).map(m =>
        m.toProtobufJSON(options)
      )
    };
  }
}
export module HttpRule {
  /**
   * Standard JavaScript object representation for HttpRule
   */
  export interface AsObject {
    selector: string;
    get: string;
    put: string;
    post: string;
    delete: string;
    patch: string;
    custom?: CustomHttpPattern.AsObject;
    body: string;
    responseBody: string;
    additionalBindings?: HttpRule.AsObject[];
  }

  /**
   * Protobuf JSON representation for HttpRule
   */
  export interface AsProtobufJSON {
    selector: string;
    get: string | null;
    put: string | null;
    post: string | null;
    delete: string | null;
    patch: string | null;
    custom: CustomHttpPattern.AsProtobufJSON | null;
    body: string;
    responseBody: string;
    additionalBindings: HttpRule.AsProtobufJSON[] | null;
  }
  export enum PatternCase {
    none = 0,
    get = 1,
    put = 2,
    post = 3,
    delete = 4,
    patch = 5,
    custom = 6
  }
}

/**
 * Message implementation for google.api.CustomHttpPattern
 */
export class CustomHttpPattern implements GrpcMessage {
  static id = 'google.api.CustomHttpPattern';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new CustomHttpPattern();
    CustomHttpPattern.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: CustomHttpPattern) {
    _instance.kind = _instance.kind || '';
    _instance.path = _instance.path || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: CustomHttpPattern,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.kind = _reader.readString();
          break;
        case 2:
          _instance.path = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    CustomHttpPattern.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: CustomHttpPattern,
    _writer: BinaryWriter
  ) {
    if (_instance.kind) {
      _writer.writeString(1, _instance.kind);
    }
    if (_instance.path) {
      _writer.writeString(2, _instance.path);
    }
  }

  private _kind: string;
  private _path: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of CustomHttpPattern to deeply clone from
   */
  constructor(_value?: RecursivePartial<CustomHttpPattern.AsObject>) {
    _value = _value || {};
    this.kind = _value.kind;
    this.path = _value.path;
    CustomHttpPattern.refineValues(this);
  }
  get kind(): string {
    return this._kind;
  }
  set kind(value: string) {
    this._kind = value;
  }
  get path(): string {
    return this._path;
  }
  set path(value: string) {
    this._path = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    CustomHttpPattern.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): CustomHttpPattern.AsObject {
    return {
      kind: this.kind,
      path: this.path
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
  ): CustomHttpPattern.AsProtobufJSON {
    return {
      kind: this.kind,
      path: this.path
    };
  }
}
export module CustomHttpPattern {
  /**
   * Standard JavaScript object representation for CustomHttpPattern
   */
  export interface AsObject {
    kind: string;
    path: string;
  }

  /**
   * Protobuf JSON representation for CustomHttpPattern
   */
  export interface AsProtobufJSON {
    kind: string;
    path: string;
  }
}
