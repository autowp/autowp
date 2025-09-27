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
export enum Scheme {
  UNKNOWN = 0,
  HTTP = 1,
  HTTPS = 2,
  WS = 3,
  WSS = 4
}
/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Swagger
 */
export class Swagger implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Swagger';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Swagger();
    Swagger.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Swagger) {
    _instance.swagger = _instance.swagger || '';
    _instance.info = _instance.info || undefined;
    _instance.host = _instance.host || '';
    _instance.basePath = _instance.basePath || '';
    _instance.schemes = _instance.schemes || [];
    _instance.consumes = _instance.consumes || [];
    _instance.produces = _instance.produces || [];
    _instance.responses = _instance.responses || {};
    _instance.securityDefinitions = _instance.securityDefinitions || undefined;
    _instance.security = _instance.security || [];
    _instance.tags = _instance.tags || [];
    _instance.externalDocs = _instance.externalDocs || undefined;
    _instance.extensions = _instance.extensions || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: Swagger,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.swagger = _reader.readString();
          break;
        case 2:
          _instance.info = new Info();
          _reader.readMessage(_instance.info, Info.deserializeBinaryFromReader);
          break;
        case 3:
          _instance.host = _reader.readString();
          break;
        case 4:
          _instance.basePath = _reader.readString();
          break;
        case 5:
          (_instance.schemes = _instance.schemes || []).push(
            ...(_reader.readPackedEnum() || [])
          );
          break;
        case 6:
          (_instance.consumes = _instance.consumes || []).push(
            _reader.readString()
          );
          break;
        case 7:
          (_instance.produces = _instance.produces || []).push(
            _reader.readString()
          );
          break;
        case 10:
          const msg_10 = {} as any;
          _reader.readMessage(
            msg_10,
            Swagger.ResponsesEntry.deserializeBinaryFromReader
          );
          _instance.responses = _instance.responses || {};
          _instance.responses[msg_10.key] = msg_10.value;
          break;
        case 11:
          _instance.securityDefinitions = new SecurityDefinitions();
          _reader.readMessage(
            _instance.securityDefinitions,
            SecurityDefinitions.deserializeBinaryFromReader
          );
          break;
        case 12:
          const messageInitializer12 = new SecurityRequirement();
          _reader.readMessage(
            messageInitializer12,
            SecurityRequirement.deserializeBinaryFromReader
          );
          (_instance.security = _instance.security || []).push(
            messageInitializer12
          );
          break;
        case 13:
          const messageInitializer13 = new Tag();
          _reader.readMessage(
            messageInitializer13,
            Tag.deserializeBinaryFromReader
          );
          (_instance.tags = _instance.tags || []).push(messageInitializer13);
          break;
        case 14:
          _instance.externalDocs = new ExternalDocumentation();
          _reader.readMessage(
            _instance.externalDocs,
            ExternalDocumentation.deserializeBinaryFromReader
          );
          break;
        case 15:
          const msg_15 = {} as any;
          _reader.readMessage(
            msg_15,
            Swagger.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_15.key] = msg_15.value;
          break;
        default:
          _reader.skipField();
      }
    }

    Swagger.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Swagger, _writer: BinaryWriter) {
    if (_instance.swagger) {
      _writer.writeString(1, _instance.swagger);
    }
    if (_instance.info) {
      _writer.writeMessage(
        2,
        _instance.info as any,
        Info.serializeBinaryToWriter
      );
    }
    if (_instance.host) {
      _writer.writeString(3, _instance.host);
    }
    if (_instance.basePath) {
      _writer.writeString(4, _instance.basePath);
    }
    if (_instance.schemes && _instance.schemes.length) {
      _writer.writePackedEnum(5, _instance.schemes);
    }
    if (_instance.consumes && _instance.consumes.length) {
      _writer.writeRepeatedString(6, _instance.consumes);
    }
    if (_instance.produces && _instance.produces.length) {
      _writer.writeRepeatedString(7, _instance.produces);
    }
    if (!!_instance.responses) {
      const keys_10 = Object.keys(_instance.responses as any);

      if (keys_10.length) {
        const repeated_10 = keys_10
          .map(key => ({ key: key, value: (_instance.responses as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          10,
          repeated_10,
          Swagger.ResponsesEntry.serializeBinaryToWriter
        );
      }
    }
    if (_instance.securityDefinitions) {
      _writer.writeMessage(
        11,
        _instance.securityDefinitions as any,
        SecurityDefinitions.serializeBinaryToWriter
      );
    }
    if (_instance.security && _instance.security.length) {
      _writer.writeRepeatedMessage(
        12,
        _instance.security as any,
        SecurityRequirement.serializeBinaryToWriter
      );
    }
    if (_instance.tags && _instance.tags.length) {
      _writer.writeRepeatedMessage(
        13,
        _instance.tags as any,
        Tag.serializeBinaryToWriter
      );
    }
    if (_instance.externalDocs) {
      _writer.writeMessage(
        14,
        _instance.externalDocs as any,
        ExternalDocumentation.serializeBinaryToWriter
      );
    }
    if (!!_instance.extensions) {
      const keys_15 = Object.keys(_instance.extensions as any);

      if (keys_15.length) {
        const repeated_15 = keys_15
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          15,
          repeated_15,
          Swagger.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _swagger: string;
  private _info?: Info;
  private _host: string;
  private _basePath: string;
  private _schemes: Scheme[];
  private _consumes: string[];
  private _produces: string[];
  private _responses: { [prop: string]: Response };
  private _securityDefinitions?: SecurityDefinitions;
  private _security?: SecurityRequirement[];
  private _tags?: Tag[];
  private _externalDocs?: ExternalDocumentation;
  private _extensions: { [prop: string]: googleProtobuf000.Value };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Swagger to deeply clone from
   */
  constructor(_value?: RecursivePartial<Swagger.AsObject>) {
    _value = _value || {};
    this.swagger = _value.swagger;
    this.info = _value.info ? new Info(_value.info) : undefined;
    this.host = _value.host;
    this.basePath = _value.basePath;
    this.schemes = (_value.schemes || []).slice();
    this.consumes = (_value.consumes || []).slice();
    this.produces = (_value.produces || []).slice();
    (this.responses = _value!.responses
      ? Object.keys(_value!.responses).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.responses![k]
              ? new Response(_value!.responses![k])
              : undefined
          }),
          {}
        )
      : {}),
      (this.securityDefinitions = _value.securityDefinitions
        ? new SecurityDefinitions(_value.securityDefinitions)
        : undefined);
    this.security = (_value.security || []).map(
      m => new SecurityRequirement(m)
    );
    this.tags = (_value.tags || []).map(m => new Tag(m));
    this.externalDocs = _value.externalDocs
      ? new ExternalDocumentation(_value.externalDocs)
      : undefined;
    (this.extensions = _value!.extensions
      ? Object.keys(_value!.extensions).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.extensions![k]
              ? new googleProtobuf000.Value(_value!.extensions![k])
              : undefined
          }),
          {}
        )
      : {}),
      Swagger.refineValues(this);
  }
  get swagger(): string {
    return this._swagger;
  }
  set swagger(value: string) {
    this._swagger = value;
  }
  get info(): Info | undefined {
    return this._info;
  }
  set info(value: Info | undefined) {
    this._info = value;
  }
  get host(): string {
    return this._host;
  }
  set host(value: string) {
    this._host = value;
  }
  get basePath(): string {
    return this._basePath;
  }
  set basePath(value: string) {
    this._basePath = value;
  }
  get schemes(): Scheme[] {
    return this._schemes;
  }
  set schemes(value: Scheme[]) {
    this._schemes = value;
  }
  get consumes(): string[] {
    return this._consumes;
  }
  set consumes(value: string[]) {
    this._consumes = value;
  }
  get produces(): string[] {
    return this._produces;
  }
  set produces(value: string[]) {
    this._produces = value;
  }
  get responses(): { [prop: string]: Response } {
    return this._responses;
  }
  set responses(value: { [prop: string]: Response }) {
    this._responses = value;
  }
  get securityDefinitions(): SecurityDefinitions | undefined {
    return this._securityDefinitions;
  }
  set securityDefinitions(value: SecurityDefinitions | undefined) {
    this._securityDefinitions = value;
  }
  get security(): SecurityRequirement[] | undefined {
    return this._security;
  }
  set security(value: SecurityRequirement[] | undefined) {
    this._security = value;
  }
  get tags(): Tag[] | undefined {
    return this._tags;
  }
  set tags(value: Tag[] | undefined) {
    this._tags = value;
  }
  get externalDocs(): ExternalDocumentation | undefined {
    return this._externalDocs;
  }
  set externalDocs(value: ExternalDocumentation | undefined) {
    this._externalDocs = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Swagger.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Swagger.AsObject {
    return {
      swagger: this.swagger,
      info: this.info ? this.info.toObject() : undefined,
      host: this.host,
      basePath: this.basePath,
      schemes: (this.schemes || []).slice(),
      consumes: (this.consumes || []).slice(),
      produces: (this.produces || []).slice(),
      responses: this.responses
        ? Object.keys(this.responses).reduce(
            (r, k) => ({
              ...r,
              [k]: this.responses![k]
                ? this.responses![k].toObject()
                : undefined
            }),
            {}
          )
        : {},
      securityDefinitions: this.securityDefinitions
        ? this.securityDefinitions.toObject()
        : undefined,
      security: (this.security || []).map(m => m.toObject()),
      tags: (this.tags || []).map(m => m.toObject()),
      externalDocs: this.externalDocs
        ? this.externalDocs.toObject()
        : undefined,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
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
  ): Swagger.AsProtobufJSON {
    return {
      swagger: this.swagger,
      info: this.info ? this.info.toProtobufJSON(options) : null,
      host: this.host,
      basePath: this.basePath,
      schemes: (this.schemes || []).map(v => Scheme[v]),
      consumes: (this.consumes || []).slice(),
      produces: (this.produces || []).slice(),
      responses: this.responses
        ? Object.keys(this.responses).reduce(
            (r, k) => ({
              ...r,
              [k]: this.responses![k] ? this.responses![k].toJSON() : null
            }),
            {}
          )
        : {},
      securityDefinitions: this.securityDefinitions
        ? this.securityDefinitions.toProtobufJSON(options)
        : null,
      security: (this.security || []).map(m => m.toProtobufJSON(options)),
      tags: (this.tags || []).map(m => m.toProtobufJSON(options)),
      externalDocs: this.externalDocs
        ? this.externalDocs.toProtobufJSON(options)
        : null,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module Swagger {
  /**
   * Standard JavaScript object representation for Swagger
   */
  export interface AsObject {
    swagger: string;
    info?: Info.AsObject;
    host: string;
    basePath: string;
    schemes: Scheme[];
    consumes: string[];
    produces: string[];
    responses: { [prop: string]: Response };
    securityDefinitions?: SecurityDefinitions.AsObject;
    security?: SecurityRequirement.AsObject[];
    tags?: Tag.AsObject[];
    externalDocs?: ExternalDocumentation.AsObject;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Protobuf JSON representation for Swagger
   */
  export interface AsProtobufJSON {
    swagger: string;
    info: Info.AsProtobufJSON | null;
    host: string;
    basePath: string;
    schemes: string[];
    consumes: string[];
    produces: string[];
    responses: { [prop: string]: Response };
    securityDefinitions: SecurityDefinitions.AsProtobufJSON | null;
    security: SecurityRequirement.AsProtobufJSON[] | null;
    tags: Tag.AsProtobufJSON[] | null;
    externalDocs: ExternalDocumentation.AsProtobufJSON | null;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Swagger.ResponsesEntry
   */
  export class ResponsesEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Swagger.ResponsesEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ResponsesEntry();
      ResponsesEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ResponsesEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ResponsesEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new Response();
            _reader.readMessage(
              _instance.value,
              Response.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ResponsesEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ResponsesEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          Response.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: Response;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ResponsesEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ResponsesEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value ? new Response(_value.value) : undefined;
      ResponsesEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): Response | undefined {
      return this._value;
    }
    set value(value: Response | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ResponsesEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ResponsesEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ResponsesEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ResponsesEntry {
    /**
     * Standard JavaScript object representation for ResponsesEntry
     */
    export interface AsObject {
      key: string;
      value?: Response.AsObject;
    }

    /**
     * Protobuf JSON representation for ResponsesEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: Response.AsProtobufJSON | null;
    }
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Swagger.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Swagger.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Operation
 */
export class Operation implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Operation';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Operation();
    Operation.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Operation) {
    _instance.tags = _instance.tags || [];
    _instance.summary = _instance.summary || '';
    _instance.description = _instance.description || '';
    _instance.externalDocs = _instance.externalDocs || undefined;
    _instance.operationId = _instance.operationId || '';
    _instance.consumes = _instance.consumes || [];
    _instance.produces = _instance.produces || [];
    _instance.responses = _instance.responses || {};
    _instance.schemes = _instance.schemes || [];
    _instance.deprecated = _instance.deprecated || false;
    _instance.security = _instance.security || [];
    _instance.extensions = _instance.extensions || {};
    _instance.parameters = _instance.parameters || undefined;
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: Operation,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          (_instance.tags = _instance.tags || []).push(_reader.readString());
          break;
        case 2:
          _instance.summary = _reader.readString();
          break;
        case 3:
          _instance.description = _reader.readString();
          break;
        case 4:
          _instance.externalDocs = new ExternalDocumentation();
          _reader.readMessage(
            _instance.externalDocs,
            ExternalDocumentation.deserializeBinaryFromReader
          );
          break;
        case 5:
          _instance.operationId = _reader.readString();
          break;
        case 6:
          (_instance.consumes = _instance.consumes || []).push(
            _reader.readString()
          );
          break;
        case 7:
          (_instance.produces = _instance.produces || []).push(
            _reader.readString()
          );
          break;
        case 9:
          const msg_9 = {} as any;
          _reader.readMessage(
            msg_9,
            Operation.ResponsesEntry.deserializeBinaryFromReader
          );
          _instance.responses = _instance.responses || {};
          _instance.responses[msg_9.key] = msg_9.value;
          break;
        case 10:
          (_instance.schemes = _instance.schemes || []).push(
            ...(_reader.readPackedEnum() || [])
          );
          break;
        case 11:
          _instance.deprecated = _reader.readBool();
          break;
        case 12:
          const messageInitializer12 = new SecurityRequirement();
          _reader.readMessage(
            messageInitializer12,
            SecurityRequirement.deserializeBinaryFromReader
          );
          (_instance.security = _instance.security || []).push(
            messageInitializer12
          );
          break;
        case 13:
          const msg_13 = {} as any;
          _reader.readMessage(
            msg_13,
            Operation.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_13.key] = msg_13.value;
          break;
        case 14:
          _instance.parameters = new Parameters();
          _reader.readMessage(
            _instance.parameters,
            Parameters.deserializeBinaryFromReader
          );
          break;
        default:
          _reader.skipField();
      }
    }

    Operation.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Operation, _writer: BinaryWriter) {
    if (_instance.tags && _instance.tags.length) {
      _writer.writeRepeatedString(1, _instance.tags);
    }
    if (_instance.summary) {
      _writer.writeString(2, _instance.summary);
    }
    if (_instance.description) {
      _writer.writeString(3, _instance.description);
    }
    if (_instance.externalDocs) {
      _writer.writeMessage(
        4,
        _instance.externalDocs as any,
        ExternalDocumentation.serializeBinaryToWriter
      );
    }
    if (_instance.operationId) {
      _writer.writeString(5, _instance.operationId);
    }
    if (_instance.consumes && _instance.consumes.length) {
      _writer.writeRepeatedString(6, _instance.consumes);
    }
    if (_instance.produces && _instance.produces.length) {
      _writer.writeRepeatedString(7, _instance.produces);
    }
    if (!!_instance.responses) {
      const keys_9 = Object.keys(_instance.responses as any);

      if (keys_9.length) {
        const repeated_9 = keys_9
          .map(key => ({ key: key, value: (_instance.responses as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          9,
          repeated_9,
          Operation.ResponsesEntry.serializeBinaryToWriter
        );
      }
    }
    if (_instance.schemes && _instance.schemes.length) {
      _writer.writePackedEnum(10, _instance.schemes);
    }
    if (_instance.deprecated) {
      _writer.writeBool(11, _instance.deprecated);
    }
    if (_instance.security && _instance.security.length) {
      _writer.writeRepeatedMessage(
        12,
        _instance.security as any,
        SecurityRequirement.serializeBinaryToWriter
      );
    }
    if (!!_instance.extensions) {
      const keys_13 = Object.keys(_instance.extensions as any);

      if (keys_13.length) {
        const repeated_13 = keys_13
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          13,
          repeated_13,
          Operation.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
    if (_instance.parameters) {
      _writer.writeMessage(
        14,
        _instance.parameters as any,
        Parameters.serializeBinaryToWriter
      );
    }
  }

  private _tags: string[];
  private _summary: string;
  private _description: string;
  private _externalDocs?: ExternalDocumentation;
  private _operationId: string;
  private _consumes: string[];
  private _produces: string[];
  private _responses: { [prop: string]: Response };
  private _schemes: Scheme[];
  private _deprecated: boolean;
  private _security?: SecurityRequirement[];
  private _extensions: { [prop: string]: googleProtobuf000.Value };
  private _parameters?: Parameters;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Operation to deeply clone from
   */
  constructor(_value?: RecursivePartial<Operation.AsObject>) {
    _value = _value || {};
    this.tags = (_value.tags || []).slice();
    this.summary = _value.summary;
    this.description = _value.description;
    this.externalDocs = _value.externalDocs
      ? new ExternalDocumentation(_value.externalDocs)
      : undefined;
    this.operationId = _value.operationId;
    this.consumes = (_value.consumes || []).slice();
    this.produces = (_value.produces || []).slice();
    (this.responses = _value!.responses
      ? Object.keys(_value!.responses).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.responses![k]
              ? new Response(_value!.responses![k])
              : undefined
          }),
          {}
        )
      : {}),
      (this.schemes = (_value.schemes || []).slice());
    this.deprecated = _value.deprecated;
    this.security = (_value.security || []).map(
      m => new SecurityRequirement(m)
    );
    (this.extensions = _value!.extensions
      ? Object.keys(_value!.extensions).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.extensions![k]
              ? new googleProtobuf000.Value(_value!.extensions![k])
              : undefined
          }),
          {}
        )
      : {}),
      (this.parameters = _value.parameters
        ? new Parameters(_value.parameters)
        : undefined);
    Operation.refineValues(this);
  }
  get tags(): string[] {
    return this._tags;
  }
  set tags(value: string[]) {
    this._tags = value;
  }
  get summary(): string {
    return this._summary;
  }
  set summary(value: string) {
    this._summary = value;
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get externalDocs(): ExternalDocumentation | undefined {
    return this._externalDocs;
  }
  set externalDocs(value: ExternalDocumentation | undefined) {
    this._externalDocs = value;
  }
  get operationId(): string {
    return this._operationId;
  }
  set operationId(value: string) {
    this._operationId = value;
  }
  get consumes(): string[] {
    return this._consumes;
  }
  set consumes(value: string[]) {
    this._consumes = value;
  }
  get produces(): string[] {
    return this._produces;
  }
  set produces(value: string[]) {
    this._produces = value;
  }
  get responses(): { [prop: string]: Response } {
    return this._responses;
  }
  set responses(value: { [prop: string]: Response }) {
    this._responses = value;
  }
  get schemes(): Scheme[] {
    return this._schemes;
  }
  set schemes(value: Scheme[]) {
    this._schemes = value;
  }
  get deprecated(): boolean {
    return this._deprecated;
  }
  set deprecated(value: boolean) {
    this._deprecated = value;
  }
  get security(): SecurityRequirement[] | undefined {
    return this._security;
  }
  set security(value: SecurityRequirement[] | undefined) {
    this._security = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }
  get parameters(): Parameters | undefined {
    return this._parameters;
  }
  set parameters(value: Parameters | undefined) {
    this._parameters = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Operation.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Operation.AsObject {
    return {
      tags: (this.tags || []).slice(),
      summary: this.summary,
      description: this.description,
      externalDocs: this.externalDocs
        ? this.externalDocs.toObject()
        : undefined,
      operationId: this.operationId,
      consumes: (this.consumes || []).slice(),
      produces: (this.produces || []).slice(),
      responses: this.responses
        ? Object.keys(this.responses).reduce(
            (r, k) => ({
              ...r,
              [k]: this.responses![k]
                ? this.responses![k].toObject()
                : undefined
            }),
            {}
          )
        : {},
      schemes: (this.schemes || []).slice(),
      deprecated: this.deprecated,
      security: (this.security || []).map(m => m.toObject()),
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
            {}
          )
        : {},
      parameters: this.parameters ? this.parameters.toObject() : undefined
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
  ): Operation.AsProtobufJSON {
    return {
      tags: (this.tags || []).slice(),
      summary: this.summary,
      description: this.description,
      externalDocs: this.externalDocs
        ? this.externalDocs.toProtobufJSON(options)
        : null,
      operationId: this.operationId,
      consumes: (this.consumes || []).slice(),
      produces: (this.produces || []).slice(),
      responses: this.responses
        ? Object.keys(this.responses).reduce(
            (r, k) => ({
              ...r,
              [k]: this.responses![k] ? this.responses![k].toJSON() : null
            }),
            {}
          )
        : {},
      schemes: (this.schemes || []).map(v => Scheme[v]),
      deprecated: this.deprecated,
      security: (this.security || []).map(m => m.toProtobufJSON(options)),
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {},
      parameters: this.parameters
        ? this.parameters.toProtobufJSON(options)
        : null
    };
  }
}
export module Operation {
  /**
   * Standard JavaScript object representation for Operation
   */
  export interface AsObject {
    tags: string[];
    summary: string;
    description: string;
    externalDocs?: ExternalDocumentation.AsObject;
    operationId: string;
    consumes: string[];
    produces: string[];
    responses: { [prop: string]: Response };
    schemes: Scheme[];
    deprecated: boolean;
    security?: SecurityRequirement.AsObject[];
    extensions: { [prop: string]: googleProtobuf000.Value };
    parameters?: Parameters.AsObject;
  }

  /**
   * Protobuf JSON representation for Operation
   */
  export interface AsProtobufJSON {
    tags: string[];
    summary: string;
    description: string;
    externalDocs: ExternalDocumentation.AsProtobufJSON | null;
    operationId: string;
    consumes: string[];
    produces: string[];
    responses: { [prop: string]: Response };
    schemes: string[];
    deprecated: boolean;
    security: SecurityRequirement.AsProtobufJSON[] | null;
    extensions: { [prop: string]: googleProtobuf000.Value };
    parameters: Parameters.AsProtobufJSON | null;
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Operation.ResponsesEntry
   */
  export class ResponsesEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Operation.ResponsesEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ResponsesEntry();
      ResponsesEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ResponsesEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ResponsesEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new Response();
            _reader.readMessage(
              _instance.value,
              Response.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ResponsesEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ResponsesEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          Response.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: Response;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ResponsesEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ResponsesEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value ? new Response(_value.value) : undefined;
      ResponsesEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): Response | undefined {
      return this._value;
    }
    set value(value: Response | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ResponsesEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ResponsesEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ResponsesEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ResponsesEntry {
    /**
     * Standard JavaScript object representation for ResponsesEntry
     */
    export interface AsObject {
      key: string;
      value?: Response.AsObject;
    }

    /**
     * Protobuf JSON representation for ResponsesEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: Response.AsProtobufJSON | null;
    }
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Operation.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Operation.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Parameters
 */
export class Parameters implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Parameters';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Parameters();
    Parameters.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Parameters) {
    _instance.headers = _instance.headers || [];
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: Parameters,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const messageInitializer1 = new HeaderParameter();
          _reader.readMessage(
            messageInitializer1,
            HeaderParameter.deserializeBinaryFromReader
          );
          (_instance.headers = _instance.headers || []).push(
            messageInitializer1
          );
          break;
        default:
          _reader.skipField();
      }
    }

    Parameters.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Parameters, _writer: BinaryWriter) {
    if (_instance.headers && _instance.headers.length) {
      _writer.writeRepeatedMessage(
        1,
        _instance.headers as any,
        HeaderParameter.serializeBinaryToWriter
      );
    }
  }

  private _headers?: HeaderParameter[];

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Parameters to deeply clone from
   */
  constructor(_value?: RecursivePartial<Parameters.AsObject>) {
    _value = _value || {};
    this.headers = (_value.headers || []).map(m => new HeaderParameter(m));
    Parameters.refineValues(this);
  }
  get headers(): HeaderParameter[] | undefined {
    return this._headers;
  }
  set headers(value: HeaderParameter[] | undefined) {
    this._headers = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Parameters.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Parameters.AsObject {
    return {
      headers: (this.headers || []).map(m => m.toObject())
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
  ): Parameters.AsProtobufJSON {
    return {
      headers: (this.headers || []).map(m => m.toProtobufJSON(options))
    };
  }
}
export module Parameters {
  /**
   * Standard JavaScript object representation for Parameters
   */
  export interface AsObject {
    headers?: HeaderParameter.AsObject[];
  }

  /**
   * Protobuf JSON representation for Parameters
   */
  export interface AsProtobufJSON {
    headers: HeaderParameter.AsProtobufJSON[] | null;
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.HeaderParameter
 */
export class HeaderParameter implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.HeaderParameter';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new HeaderParameter();
    HeaderParameter.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: HeaderParameter) {
    _instance.name = _instance.name || '';
    _instance.description = _instance.description || '';
    _instance.type = _instance.type || 0;
    _instance.format = _instance.format || '';
    _instance.required = _instance.required || false;
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: HeaderParameter,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.name = _reader.readString();
          break;
        case 2:
          _instance.description = _reader.readString();
          break;
        case 3:
          _instance.type = _reader.readEnum();
          break;
        case 4:
          _instance.format = _reader.readString();
          break;
        case 5:
          _instance.required = _reader.readBool();
          break;
        default:
          _reader.skipField();
      }
    }

    HeaderParameter.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: HeaderParameter,
    _writer: BinaryWriter
  ) {
    if (_instance.name) {
      _writer.writeString(1, _instance.name);
    }
    if (_instance.description) {
      _writer.writeString(2, _instance.description);
    }
    if (_instance.type) {
      _writer.writeEnum(3, _instance.type);
    }
    if (_instance.format) {
      _writer.writeString(4, _instance.format);
    }
    if (_instance.required) {
      _writer.writeBool(5, _instance.required);
    }
  }

  private _name: string;
  private _description: string;
  private _type: HeaderParameter.Type;
  private _format: string;
  private _required: boolean;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of HeaderParameter to deeply clone from
   */
  constructor(_value?: RecursivePartial<HeaderParameter.AsObject>) {
    _value = _value || {};
    this.name = _value.name;
    this.description = _value.description;
    this.type = _value.type;
    this.format = _value.format;
    this.required = _value.required;
    HeaderParameter.refineValues(this);
  }
  get name(): string {
    return this._name;
  }
  set name(value: string) {
    this._name = value;
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get type(): HeaderParameter.Type {
    return this._type;
  }
  set type(value: HeaderParameter.Type) {
    this._type = value;
  }
  get format(): string {
    return this._format;
  }
  set format(value: string) {
    this._format = value;
  }
  get required(): boolean {
    return this._required;
  }
  set required(value: boolean) {
    this._required = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    HeaderParameter.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): HeaderParameter.AsObject {
    return {
      name: this.name,
      description: this.description,
      type: this.type,
      format: this.format,
      required: this.required
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
  ): HeaderParameter.AsProtobufJSON {
    return {
      name: this.name,
      description: this.description,
      type:
        HeaderParameter.Type[
          this.type === null || this.type === undefined ? 0 : this.type
        ],
      format: this.format,
      required: this.required
    };
  }
}
export module HeaderParameter {
  /**
   * Standard JavaScript object representation for HeaderParameter
   */
  export interface AsObject {
    name: string;
    description: string;
    type: HeaderParameter.Type;
    format: string;
    required: boolean;
  }

  /**
   * Protobuf JSON representation for HeaderParameter
   */
  export interface AsProtobufJSON {
    name: string;
    description: string;
    type: string;
    format: string;
    required: boolean;
  }
  export enum Type {
    UNKNOWN = 0,
    STRING = 1,
    NUMBER = 2,
    INTEGER = 3,
    BOOLEAN = 4
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Header
 */
export class Header implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Header';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Header();
    Header.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Header) {
    _instance.description = _instance.description || '';
    _instance.type = _instance.type || '';
    _instance.format = _instance.format || '';
    _instance.pbDefault = _instance.pbDefault || '';
    _instance.pattern = _instance.pattern || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Header, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.description = _reader.readString();
          break;
        case 2:
          _instance.type = _reader.readString();
          break;
        case 3:
          _instance.format = _reader.readString();
          break;
        case 6:
          _instance.pbDefault = _reader.readString();
          break;
        case 13:
          _instance.pattern = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    Header.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Header, _writer: BinaryWriter) {
    if (_instance.description) {
      _writer.writeString(1, _instance.description);
    }
    if (_instance.type) {
      _writer.writeString(2, _instance.type);
    }
    if (_instance.format) {
      _writer.writeString(3, _instance.format);
    }
    if (_instance.pbDefault) {
      _writer.writeString(6, _instance.pbDefault);
    }
    if (_instance.pattern) {
      _writer.writeString(13, _instance.pattern);
    }
  }

  private _description: string;
  private _type: string;
  private _format: string;
  private _pbDefault: string;
  private _pattern: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Header to deeply clone from
   */
  constructor(_value?: RecursivePartial<Header.AsObject>) {
    _value = _value || {};
    this.description = _value.description;
    this.type = _value.type;
    this.format = _value.format;
    this.pbDefault = _value.pbDefault;
    this.pattern = _value.pattern;
    Header.refineValues(this);
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get type(): string {
    return this._type;
  }
  set type(value: string) {
    this._type = value;
  }
  get format(): string {
    return this._format;
  }
  set format(value: string) {
    this._format = value;
  }
  get pbDefault(): string {
    return this._pbDefault;
  }
  set pbDefault(value: string) {
    this._pbDefault = value;
  }
  get pattern(): string {
    return this._pattern;
  }
  set pattern(value: string) {
    this._pattern = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Header.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Header.AsObject {
    return {
      description: this.description,
      type: this.type,
      format: this.format,
      pbDefault: this.pbDefault,
      pattern: this.pattern
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
  ): Header.AsProtobufJSON {
    return {
      description: this.description,
      type: this.type,
      format: this.format,
      pbDefault: this.pbDefault,
      pattern: this.pattern
    };
  }
}
export module Header {
  /**
   * Standard JavaScript object representation for Header
   */
  export interface AsObject {
    description: string;
    type: string;
    format: string;
    pbDefault: string;
    pattern: string;
  }

  /**
   * Protobuf JSON representation for Header
   */
  export interface AsProtobufJSON {
    description: string;
    type: string;
    format: string;
    pbDefault: string;
    pattern: string;
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Response
 */
export class Response implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Response';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Response();
    Response.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Response) {
    _instance.description = _instance.description || '';
    _instance.schema = _instance.schema || undefined;
    _instance.headers = _instance.headers || {};
    _instance.examples = _instance.examples || {};
    _instance.extensions = _instance.extensions || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: Response,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.description = _reader.readString();
          break;
        case 2:
          _instance.schema = new Schema();
          _reader.readMessage(
            _instance.schema,
            Schema.deserializeBinaryFromReader
          );
          break;
        case 3:
          const msg_3 = {} as any;
          _reader.readMessage(
            msg_3,
            Response.HeadersEntry.deserializeBinaryFromReader
          );
          _instance.headers = _instance.headers || {};
          _instance.headers[msg_3.key] = msg_3.value;
          break;
        case 4:
          const msg_4 = {} as any;
          _reader.readMessage(
            msg_4,
            Response.ExamplesEntry.deserializeBinaryFromReader
          );
          _instance.examples = _instance.examples || {};
          _instance.examples[msg_4.key] = msg_4.value;
          break;
        case 5:
          const msg_5 = {} as any;
          _reader.readMessage(
            msg_5,
            Response.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_5.key] = msg_5.value;
          break;
        default:
          _reader.skipField();
      }
    }

    Response.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Response, _writer: BinaryWriter) {
    if (_instance.description) {
      _writer.writeString(1, _instance.description);
    }
    if (_instance.schema) {
      _writer.writeMessage(
        2,
        _instance.schema as any,
        Schema.serializeBinaryToWriter
      );
    }
    if (!!_instance.headers) {
      const keys_3 = Object.keys(_instance.headers as any);

      if (keys_3.length) {
        const repeated_3 = keys_3
          .map(key => ({ key: key, value: (_instance.headers as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          3,
          repeated_3,
          Response.HeadersEntry.serializeBinaryToWriter
        );
      }
    }
    if (!!_instance.examples) {
      const keys_4 = Object.keys(_instance.examples as any);

      if (keys_4.length) {
        const repeated_4 = keys_4
          .map(key => ({ key: key, value: (_instance.examples as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          4,
          repeated_4,
          Response.ExamplesEntry.serializeBinaryToWriter
        );
      }
    }
    if (!!_instance.extensions) {
      const keys_5 = Object.keys(_instance.extensions as any);

      if (keys_5.length) {
        const repeated_5 = keys_5
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          5,
          repeated_5,
          Response.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _description: string;
  private _schema?: Schema;
  private _headers: { [prop: string]: Header };
  private _examples: { [prop: string]: string };
  private _extensions: { [prop: string]: googleProtobuf000.Value };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Response to deeply clone from
   */
  constructor(_value?: RecursivePartial<Response.AsObject>) {
    _value = _value || {};
    this.description = _value.description;
    this.schema = _value.schema ? new Schema(_value.schema) : undefined;
    (this.headers = _value!.headers
      ? Object.keys(_value!.headers).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.headers![k]
              ? new Header(_value!.headers![k])
              : undefined
          }),
          {}
        )
      : {}),
      (this.examples = _value!.examples
        ? Object.keys(_value!.examples).reduce(
            (r, k) => ({ ...r, [k]: _value!.examples![k] }),
            {}
          )
        : {}),
      (this.extensions = _value!.extensions
        ? Object.keys(_value!.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: _value!.extensions![k]
                ? new googleProtobuf000.Value(_value!.extensions![k])
                : undefined
            }),
            {}
          )
        : {}),
      Response.refineValues(this);
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get schema(): Schema | undefined {
    return this._schema;
  }
  set schema(value: Schema | undefined) {
    this._schema = value;
  }
  get headers(): { [prop: string]: Header } {
    return this._headers;
  }
  set headers(value: { [prop: string]: Header }) {
    this._headers = value;
  }
  get examples(): { [prop: string]: string } {
    return this._examples;
  }
  set examples(value: { [prop: string]: string }) {
    this._examples = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Response.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Response.AsObject {
    return {
      description: this.description,
      schema: this.schema ? this.schema.toObject() : undefined,
      headers: this.headers
        ? Object.keys(this.headers).reduce(
            (r, k) => ({
              ...r,
              [k]: this.headers![k] ? this.headers![k].toObject() : undefined
            }),
            {}
          )
        : {},
      examples: this.examples
        ? Object.keys(this.examples).reduce(
            (r, k) => ({ ...r, [k]: this.examples![k] }),
            {}
          )
        : {},
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
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
  ): Response.AsProtobufJSON {
    return {
      description: this.description,
      schema: this.schema ? this.schema.toProtobufJSON(options) : null,
      headers: this.headers
        ? Object.keys(this.headers).reduce(
            (r, k) => ({
              ...r,
              [k]: this.headers![k] ? this.headers![k].toJSON() : null
            }),
            {}
          )
        : {},
      examples: this.examples
        ? Object.keys(this.examples).reduce(
            (r, k) => ({ ...r, [k]: this.examples![k] }),
            {}
          )
        : {},
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module Response {
  /**
   * Standard JavaScript object representation for Response
   */
  export interface AsObject {
    description: string;
    schema?: Schema.AsObject;
    headers: { [prop: string]: Header };
    examples: { [prop: string]: string };
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Protobuf JSON representation for Response
   */
  export interface AsProtobufJSON {
    description: string;
    schema: Schema.AsProtobufJSON | null;
    headers: { [prop: string]: Header };
    examples: { [prop: string]: string };
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Response.HeadersEntry
   */
  export class HeadersEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Response.HeadersEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new HeadersEntry();
      HeadersEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: HeadersEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: HeadersEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new Header();
            _reader.readMessage(
              _instance.value,
              Header.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      HeadersEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: HeadersEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          Header.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: Header;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of HeadersEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<HeadersEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value ? new Header(_value.value) : undefined;
      HeadersEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): Header | undefined {
      return this._value;
    }
    set value(value: Header | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      HeadersEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): HeadersEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): HeadersEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module HeadersEntry {
    /**
     * Standard JavaScript object representation for HeadersEntry
     */
    export interface AsObject {
      key: string;
      value?: Header.AsObject;
    }

    /**
     * Protobuf JSON representation for HeadersEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: Header.AsProtobufJSON | null;
    }
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Response.ExamplesEntry
   */
  export class ExamplesEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Response.ExamplesEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExamplesEntry();
      ExamplesEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExamplesEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || '';
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExamplesEntry,
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

      ExamplesEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExamplesEntry,
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
     * @param _value initial values object or instance of ExamplesEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExamplesEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value;
      ExamplesEntry.refineValues(this);
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
      ExamplesEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExamplesEntry.AsObject {
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
    ): ExamplesEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value
      };
    }
  }
  export module ExamplesEntry {
    /**
     * Standard JavaScript object representation for ExamplesEntry
     */
    export interface AsObject {
      key: string;
      value: string;
    }

    /**
     * Protobuf JSON representation for ExamplesEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: string;
    }
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Response.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Response.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Info
 */
export class Info implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Info';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Info();
    Info.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Info) {
    _instance.title = _instance.title || '';
    _instance.description = _instance.description || '';
    _instance.termsOfService = _instance.termsOfService || '';
    _instance.contact = _instance.contact || undefined;
    _instance.license = _instance.license || undefined;
    _instance.version = _instance.version || '';
    _instance.extensions = _instance.extensions || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Info, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.title = _reader.readString();
          break;
        case 2:
          _instance.description = _reader.readString();
          break;
        case 3:
          _instance.termsOfService = _reader.readString();
          break;
        case 4:
          _instance.contact = new Contact();
          _reader.readMessage(
            _instance.contact,
            Contact.deserializeBinaryFromReader
          );
          break;
        case 5:
          _instance.license = new License();
          _reader.readMessage(
            _instance.license,
            License.deserializeBinaryFromReader
          );
          break;
        case 6:
          _instance.version = _reader.readString();
          break;
        case 7:
          const msg_7 = {} as any;
          _reader.readMessage(
            msg_7,
            Info.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_7.key] = msg_7.value;
          break;
        default:
          _reader.skipField();
      }
    }

    Info.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Info, _writer: BinaryWriter) {
    if (_instance.title) {
      _writer.writeString(1, _instance.title);
    }
    if (_instance.description) {
      _writer.writeString(2, _instance.description);
    }
    if (_instance.termsOfService) {
      _writer.writeString(3, _instance.termsOfService);
    }
    if (_instance.contact) {
      _writer.writeMessage(
        4,
        _instance.contact as any,
        Contact.serializeBinaryToWriter
      );
    }
    if (_instance.license) {
      _writer.writeMessage(
        5,
        _instance.license as any,
        License.serializeBinaryToWriter
      );
    }
    if (_instance.version) {
      _writer.writeString(6, _instance.version);
    }
    if (!!_instance.extensions) {
      const keys_7 = Object.keys(_instance.extensions as any);

      if (keys_7.length) {
        const repeated_7 = keys_7
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          7,
          repeated_7,
          Info.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _title: string;
  private _description: string;
  private _termsOfService: string;
  private _contact?: Contact;
  private _license?: License;
  private _version: string;
  private _extensions: { [prop: string]: googleProtobuf000.Value };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Info to deeply clone from
   */
  constructor(_value?: RecursivePartial<Info.AsObject>) {
    _value = _value || {};
    this.title = _value.title;
    this.description = _value.description;
    this.termsOfService = _value.termsOfService;
    this.contact = _value.contact ? new Contact(_value.contact) : undefined;
    this.license = _value.license ? new License(_value.license) : undefined;
    this.version = _value.version;
    (this.extensions = _value!.extensions
      ? Object.keys(_value!.extensions).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.extensions![k]
              ? new googleProtobuf000.Value(_value!.extensions![k])
              : undefined
          }),
          {}
        )
      : {}),
      Info.refineValues(this);
  }
  get title(): string {
    return this._title;
  }
  set title(value: string) {
    this._title = value;
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get termsOfService(): string {
    return this._termsOfService;
  }
  set termsOfService(value: string) {
    this._termsOfService = value;
  }
  get contact(): Contact | undefined {
    return this._contact;
  }
  set contact(value: Contact | undefined) {
    this._contact = value;
  }
  get license(): License | undefined {
    return this._license;
  }
  set license(value: License | undefined) {
    this._license = value;
  }
  get version(): string {
    return this._version;
  }
  set version(value: string) {
    this._version = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Info.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Info.AsObject {
    return {
      title: this.title,
      description: this.description,
      termsOfService: this.termsOfService,
      contact: this.contact ? this.contact.toObject() : undefined,
      license: this.license ? this.license.toObject() : undefined,
      version: this.version,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
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
  ): Info.AsProtobufJSON {
    return {
      title: this.title,
      description: this.description,
      termsOfService: this.termsOfService,
      contact: this.contact ? this.contact.toProtobufJSON(options) : null,
      license: this.license ? this.license.toProtobufJSON(options) : null,
      version: this.version,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module Info {
  /**
   * Standard JavaScript object representation for Info
   */
  export interface AsObject {
    title: string;
    description: string;
    termsOfService: string;
    contact?: Contact.AsObject;
    license?: License.AsObject;
    version: string;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Protobuf JSON representation for Info
   */
  export interface AsProtobufJSON {
    title: string;
    description: string;
    termsOfService: string;
    contact: Contact.AsProtobufJSON | null;
    license: License.AsProtobufJSON | null;
    version: string;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Info.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.Info.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Contact
 */
export class Contact implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Contact';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Contact();
    Contact.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Contact) {
    _instance.name = _instance.name || '';
    _instance.url = _instance.url || '';
    _instance.email = _instance.email || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: Contact,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.name = _reader.readString();
          break;
        case 2:
          _instance.url = _reader.readString();
          break;
        case 3:
          _instance.email = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    Contact.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Contact, _writer: BinaryWriter) {
    if (_instance.name) {
      _writer.writeString(1, _instance.name);
    }
    if (_instance.url) {
      _writer.writeString(2, _instance.url);
    }
    if (_instance.email) {
      _writer.writeString(3, _instance.email);
    }
  }

  private _name: string;
  private _url: string;
  private _email: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Contact to deeply clone from
   */
  constructor(_value?: RecursivePartial<Contact.AsObject>) {
    _value = _value || {};
    this.name = _value.name;
    this.url = _value.url;
    this.email = _value.email;
    Contact.refineValues(this);
  }
  get name(): string {
    return this._name;
  }
  set name(value: string) {
    this._name = value;
  }
  get url(): string {
    return this._url;
  }
  set url(value: string) {
    this._url = value;
  }
  get email(): string {
    return this._email;
  }
  set email(value: string) {
    this._email = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Contact.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Contact.AsObject {
    return {
      name: this.name,
      url: this.url,
      email: this.email
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
  ): Contact.AsProtobufJSON {
    return {
      name: this.name,
      url: this.url,
      email: this.email
    };
  }
}
export module Contact {
  /**
   * Standard JavaScript object representation for Contact
   */
  export interface AsObject {
    name: string;
    url: string;
    email: string;
  }

  /**
   * Protobuf JSON representation for Contact
   */
  export interface AsProtobufJSON {
    name: string;
    url: string;
    email: string;
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.License
 */
export class License implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.License';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new License();
    License.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: License) {
    _instance.name = _instance.name || '';
    _instance.url = _instance.url || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: License,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.name = _reader.readString();
          break;
        case 2:
          _instance.url = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    License.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: License, _writer: BinaryWriter) {
    if (_instance.name) {
      _writer.writeString(1, _instance.name);
    }
    if (_instance.url) {
      _writer.writeString(2, _instance.url);
    }
  }

  private _name: string;
  private _url: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of License to deeply clone from
   */
  constructor(_value?: RecursivePartial<License.AsObject>) {
    _value = _value || {};
    this.name = _value.name;
    this.url = _value.url;
    License.refineValues(this);
  }
  get name(): string {
    return this._name;
  }
  set name(value: string) {
    this._name = value;
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
    License.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): License.AsObject {
    return {
      name: this.name,
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
  ): License.AsProtobufJSON {
    return {
      name: this.name,
      url: this.url
    };
  }
}
export module License {
  /**
   * Standard JavaScript object representation for License
   */
  export interface AsObject {
    name: string;
    url: string;
  }

  /**
   * Protobuf JSON representation for License
   */
  export interface AsProtobufJSON {
    name: string;
    url: string;
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.ExternalDocumentation
 */
export class ExternalDocumentation implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.ExternalDocumentation';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new ExternalDocumentation();
    ExternalDocumentation.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: ExternalDocumentation) {
    _instance.description = _instance.description || '';
    _instance.url = _instance.url || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: ExternalDocumentation,
    _reader: BinaryReader
  ) {
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

    ExternalDocumentation.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: ExternalDocumentation,
    _writer: BinaryWriter
  ) {
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
   * @param _value initial values object or instance of ExternalDocumentation to deeply clone from
   */
  constructor(_value?: RecursivePartial<ExternalDocumentation.AsObject>) {
    _value = _value || {};
    this.description = _value.description;
    this.url = _value.url;
    ExternalDocumentation.refineValues(this);
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
    ExternalDocumentation.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): ExternalDocumentation.AsObject {
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
  ): ExternalDocumentation.AsProtobufJSON {
    return {
      description: this.description,
      url: this.url
    };
  }
}
export module ExternalDocumentation {
  /**
   * Standard JavaScript object representation for ExternalDocumentation
   */
  export interface AsObject {
    description: string;
    url: string;
  }

  /**
   * Protobuf JSON representation for ExternalDocumentation
   */
  export interface AsProtobufJSON {
    description: string;
    url: string;
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Schema
 */
export class Schema implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Schema';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Schema();
    Schema.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Schema) {
    _instance.jsonSchema = _instance.jsonSchema || undefined;
    _instance.discriminator = _instance.discriminator || '';
    _instance.readOnly = _instance.readOnly || false;
    _instance.externalDocs = _instance.externalDocs || undefined;
    _instance.example = _instance.example || '';
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Schema, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.jsonSchema = new JSONSchema();
          _reader.readMessage(
            _instance.jsonSchema,
            JSONSchema.deserializeBinaryFromReader
          );
          break;
        case 2:
          _instance.discriminator = _reader.readString();
          break;
        case 3:
          _instance.readOnly = _reader.readBool();
          break;
        case 5:
          _instance.externalDocs = new ExternalDocumentation();
          _reader.readMessage(
            _instance.externalDocs,
            ExternalDocumentation.deserializeBinaryFromReader
          );
          break;
        case 6:
          _instance.example = _reader.readString();
          break;
        default:
          _reader.skipField();
      }
    }

    Schema.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Schema, _writer: BinaryWriter) {
    if (_instance.jsonSchema) {
      _writer.writeMessage(
        1,
        _instance.jsonSchema as any,
        JSONSchema.serializeBinaryToWriter
      );
    }
    if (_instance.discriminator) {
      _writer.writeString(2, _instance.discriminator);
    }
    if (_instance.readOnly) {
      _writer.writeBool(3, _instance.readOnly);
    }
    if (_instance.externalDocs) {
      _writer.writeMessage(
        5,
        _instance.externalDocs as any,
        ExternalDocumentation.serializeBinaryToWriter
      );
    }
    if (_instance.example) {
      _writer.writeString(6, _instance.example);
    }
  }

  private _jsonSchema?: JSONSchema;
  private _discriminator: string;
  private _readOnly: boolean;
  private _externalDocs?: ExternalDocumentation;
  private _example: string;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Schema to deeply clone from
   */
  constructor(_value?: RecursivePartial<Schema.AsObject>) {
    _value = _value || {};
    this.jsonSchema = _value.jsonSchema
      ? new JSONSchema(_value.jsonSchema)
      : undefined;
    this.discriminator = _value.discriminator;
    this.readOnly = _value.readOnly;
    this.externalDocs = _value.externalDocs
      ? new ExternalDocumentation(_value.externalDocs)
      : undefined;
    this.example = _value.example;
    Schema.refineValues(this);
  }
  get jsonSchema(): JSONSchema | undefined {
    return this._jsonSchema;
  }
  set jsonSchema(value: JSONSchema | undefined) {
    this._jsonSchema = value;
  }
  get discriminator(): string {
    return this._discriminator;
  }
  set discriminator(value: string) {
    this._discriminator = value;
  }
  get readOnly(): boolean {
    return this._readOnly;
  }
  set readOnly(value: boolean) {
    this._readOnly = value;
  }
  get externalDocs(): ExternalDocumentation | undefined {
    return this._externalDocs;
  }
  set externalDocs(value: ExternalDocumentation | undefined) {
    this._externalDocs = value;
  }
  get example(): string {
    return this._example;
  }
  set example(value: string) {
    this._example = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Schema.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Schema.AsObject {
    return {
      jsonSchema: this.jsonSchema ? this.jsonSchema.toObject() : undefined,
      discriminator: this.discriminator,
      readOnly: this.readOnly,
      externalDocs: this.externalDocs
        ? this.externalDocs.toObject()
        : undefined,
      example: this.example
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
  ): Schema.AsProtobufJSON {
    return {
      jsonSchema: this.jsonSchema
        ? this.jsonSchema.toProtobufJSON(options)
        : null,
      discriminator: this.discriminator,
      readOnly: this.readOnly,
      externalDocs: this.externalDocs
        ? this.externalDocs.toProtobufJSON(options)
        : null,
      example: this.example
    };
  }
}
export module Schema {
  /**
   * Standard JavaScript object representation for Schema
   */
  export interface AsObject {
    jsonSchema?: JSONSchema.AsObject;
    discriminator: string;
    readOnly: boolean;
    externalDocs?: ExternalDocumentation.AsObject;
    example: string;
  }

  /**
   * Protobuf JSON representation for Schema
   */
  export interface AsProtobufJSON {
    jsonSchema: JSONSchema.AsProtobufJSON | null;
    discriminator: string;
    readOnly: boolean;
    externalDocs: ExternalDocumentation.AsProtobufJSON | null;
    example: string;
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.EnumSchema
 */
export class EnumSchema implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.EnumSchema';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new EnumSchema();
    EnumSchema.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: EnumSchema) {
    _instance.description = _instance.description || '';
    _instance.pbDefault = _instance.pbDefault || '';
    _instance.title = _instance.title || '';
    _instance.required = _instance.required || false;
    _instance.readOnly = _instance.readOnly || false;
    _instance.externalDocs = _instance.externalDocs || undefined;
    _instance.example = _instance.example || '';
    _instance.ref = _instance.ref || '';
    _instance.extensions = _instance.extensions || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: EnumSchema,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.description = _reader.readString();
          break;
        case 2:
          _instance.pbDefault = _reader.readString();
          break;
        case 3:
          _instance.title = _reader.readString();
          break;
        case 4:
          _instance.required = _reader.readBool();
          break;
        case 5:
          _instance.readOnly = _reader.readBool();
          break;
        case 6:
          _instance.externalDocs = new ExternalDocumentation();
          _reader.readMessage(
            _instance.externalDocs,
            ExternalDocumentation.deserializeBinaryFromReader
          );
          break;
        case 7:
          _instance.example = _reader.readString();
          break;
        case 8:
          _instance.ref = _reader.readString();
          break;
        case 9:
          const msg_9 = {} as any;
          _reader.readMessage(
            msg_9,
            EnumSchema.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_9.key] = msg_9.value;
          break;
        default:
          _reader.skipField();
      }
    }

    EnumSchema.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: EnumSchema, _writer: BinaryWriter) {
    if (_instance.description) {
      _writer.writeString(1, _instance.description);
    }
    if (_instance.pbDefault) {
      _writer.writeString(2, _instance.pbDefault);
    }
    if (_instance.title) {
      _writer.writeString(3, _instance.title);
    }
    if (_instance.required) {
      _writer.writeBool(4, _instance.required);
    }
    if (_instance.readOnly) {
      _writer.writeBool(5, _instance.readOnly);
    }
    if (_instance.externalDocs) {
      _writer.writeMessage(
        6,
        _instance.externalDocs as any,
        ExternalDocumentation.serializeBinaryToWriter
      );
    }
    if (_instance.example) {
      _writer.writeString(7, _instance.example);
    }
    if (_instance.ref) {
      _writer.writeString(8, _instance.ref);
    }
    if (!!_instance.extensions) {
      const keys_9 = Object.keys(_instance.extensions as any);

      if (keys_9.length) {
        const repeated_9 = keys_9
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          9,
          repeated_9,
          EnumSchema.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _description: string;
  private _pbDefault: string;
  private _title: string;
  private _required: boolean;
  private _readOnly: boolean;
  private _externalDocs?: ExternalDocumentation;
  private _example: string;
  private _ref: string;
  private _extensions: { [prop: string]: googleProtobuf000.Value };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of EnumSchema to deeply clone from
   */
  constructor(_value?: RecursivePartial<EnumSchema.AsObject>) {
    _value = _value || {};
    this.description = _value.description;
    this.pbDefault = _value.pbDefault;
    this.title = _value.title;
    this.required = _value.required;
    this.readOnly = _value.readOnly;
    this.externalDocs = _value.externalDocs
      ? new ExternalDocumentation(_value.externalDocs)
      : undefined;
    this.example = _value.example;
    this.ref = _value.ref;
    (this.extensions = _value!.extensions
      ? Object.keys(_value!.extensions).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.extensions![k]
              ? new googleProtobuf000.Value(_value!.extensions![k])
              : undefined
          }),
          {}
        )
      : {}),
      EnumSchema.refineValues(this);
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get pbDefault(): string {
    return this._pbDefault;
  }
  set pbDefault(value: string) {
    this._pbDefault = value;
  }
  get title(): string {
    return this._title;
  }
  set title(value: string) {
    this._title = value;
  }
  get required(): boolean {
    return this._required;
  }
  set required(value: boolean) {
    this._required = value;
  }
  get readOnly(): boolean {
    return this._readOnly;
  }
  set readOnly(value: boolean) {
    this._readOnly = value;
  }
  get externalDocs(): ExternalDocumentation | undefined {
    return this._externalDocs;
  }
  set externalDocs(value: ExternalDocumentation | undefined) {
    this._externalDocs = value;
  }
  get example(): string {
    return this._example;
  }
  set example(value: string) {
    this._example = value;
  }
  get ref(): string {
    return this._ref;
  }
  set ref(value: string) {
    this._ref = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    EnumSchema.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): EnumSchema.AsObject {
    return {
      description: this.description,
      pbDefault: this.pbDefault,
      title: this.title,
      required: this.required,
      readOnly: this.readOnly,
      externalDocs: this.externalDocs
        ? this.externalDocs.toObject()
        : undefined,
      example: this.example,
      ref: this.ref,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
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
  ): EnumSchema.AsProtobufJSON {
    return {
      description: this.description,
      pbDefault: this.pbDefault,
      title: this.title,
      required: this.required,
      readOnly: this.readOnly,
      externalDocs: this.externalDocs
        ? this.externalDocs.toProtobufJSON(options)
        : null,
      example: this.example,
      ref: this.ref,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module EnumSchema {
  /**
   * Standard JavaScript object representation for EnumSchema
   */
  export interface AsObject {
    description: string;
    pbDefault: string;
    title: string;
    required: boolean;
    readOnly: boolean;
    externalDocs?: ExternalDocumentation.AsObject;
    example: string;
    ref: string;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Protobuf JSON representation for EnumSchema
   */
  export interface AsProtobufJSON {
    description: string;
    pbDefault: string;
    title: string;
    required: boolean;
    readOnly: boolean;
    externalDocs: ExternalDocumentation.AsProtobufJSON | null;
    example: string;
    ref: string;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.EnumSchema.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.EnumSchema.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.JSONSchema
 */
export class JSONSchema implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.JSONSchema';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new JSONSchema();
    JSONSchema.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: JSONSchema) {
    _instance.ref = _instance.ref || '';
    _instance.title = _instance.title || '';
    _instance.description = _instance.description || '';
    _instance.pbDefault = _instance.pbDefault || '';
    _instance.readOnly = _instance.readOnly || false;
    _instance.example = _instance.example || '';
    _instance.multipleOf = _instance.multipleOf || 0;
    _instance.maximum = _instance.maximum || 0;
    _instance.exclusiveMaximum = _instance.exclusiveMaximum || false;
    _instance.minimum = _instance.minimum || 0;
    _instance.exclusiveMinimum = _instance.exclusiveMinimum || false;
    _instance.maxLength = _instance.maxLength || '0';
    _instance.minLength = _instance.minLength || '0';
    _instance.pattern = _instance.pattern || '';
    _instance.maxItems = _instance.maxItems || '0';
    _instance.minItems = _instance.minItems || '0';
    _instance.uniqueItems = _instance.uniqueItems || false;
    _instance.maxProperties = _instance.maxProperties || '0';
    _instance.minProperties = _instance.minProperties || '0';
    _instance.required = _instance.required || [];
    _instance.array = _instance.array || [];
    _instance.type = _instance.type || [];
    _instance.format = _instance.format || '';
    _instance.enum = _instance.enum || [];
    _instance.fieldConfiguration = _instance.fieldConfiguration || undefined;
    _instance.extensions = _instance.extensions || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: JSONSchema,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 3:
          _instance.ref = _reader.readString();
          break;
        case 5:
          _instance.title = _reader.readString();
          break;
        case 6:
          _instance.description = _reader.readString();
          break;
        case 7:
          _instance.pbDefault = _reader.readString();
          break;
        case 8:
          _instance.readOnly = _reader.readBool();
          break;
        case 9:
          _instance.example = _reader.readString();
          break;
        case 10:
          _instance.multipleOf = _reader.readDouble();
          break;
        case 11:
          _instance.maximum = _reader.readDouble();
          break;
        case 12:
          _instance.exclusiveMaximum = _reader.readBool();
          break;
        case 13:
          _instance.minimum = _reader.readDouble();
          break;
        case 14:
          _instance.exclusiveMinimum = _reader.readBool();
          break;
        case 15:
          _instance.maxLength = _reader.readUint64String();
          break;
        case 16:
          _instance.minLength = _reader.readUint64String();
          break;
        case 17:
          _instance.pattern = _reader.readString();
          break;
        case 20:
          _instance.maxItems = _reader.readUint64String();
          break;
        case 21:
          _instance.minItems = _reader.readUint64String();
          break;
        case 22:
          _instance.uniqueItems = _reader.readBool();
          break;
        case 24:
          _instance.maxProperties = _reader.readUint64String();
          break;
        case 25:
          _instance.minProperties = _reader.readUint64String();
          break;
        case 26:
          (_instance.required = _instance.required || []).push(
            _reader.readString()
          );
          break;
        case 34:
          (_instance.array = _instance.array || []).push(_reader.readString());
          break;
        case 35:
          (_instance.type = _instance.type || []).push(
            ...(_reader.readPackedEnum() || [])
          );
          break;
        case 36:
          _instance.format = _reader.readString();
          break;
        case 46:
          (_instance.enum = _instance.enum || []).push(_reader.readString());
          break;
        case 1001:
          _instance.fieldConfiguration = new JSONSchema.FieldConfiguration();
          _reader.readMessage(
            _instance.fieldConfiguration,
            JSONSchema.FieldConfiguration.deserializeBinaryFromReader
          );
          break;
        case 48:
          const msg_48 = {} as any;
          _reader.readMessage(
            msg_48,
            JSONSchema.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_48.key] = msg_48.value;
          break;
        default:
          _reader.skipField();
      }
    }

    JSONSchema.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: JSONSchema, _writer: BinaryWriter) {
    if (_instance.ref) {
      _writer.writeString(3, _instance.ref);
    }
    if (_instance.title) {
      _writer.writeString(5, _instance.title);
    }
    if (_instance.description) {
      _writer.writeString(6, _instance.description);
    }
    if (_instance.pbDefault) {
      _writer.writeString(7, _instance.pbDefault);
    }
    if (_instance.readOnly) {
      _writer.writeBool(8, _instance.readOnly);
    }
    if (_instance.example) {
      _writer.writeString(9, _instance.example);
    }
    if (_instance.multipleOf) {
      _writer.writeDouble(10, _instance.multipleOf);
    }
    if (_instance.maximum) {
      _writer.writeDouble(11, _instance.maximum);
    }
    if (_instance.exclusiveMaximum) {
      _writer.writeBool(12, _instance.exclusiveMaximum);
    }
    if (_instance.minimum) {
      _writer.writeDouble(13, _instance.minimum);
    }
    if (_instance.exclusiveMinimum) {
      _writer.writeBool(14, _instance.exclusiveMinimum);
    }
    if (_instance.maxLength) {
      _writer.writeUint64String(15, _instance.maxLength);
    }
    if (_instance.minLength) {
      _writer.writeUint64String(16, _instance.minLength);
    }
    if (_instance.pattern) {
      _writer.writeString(17, _instance.pattern);
    }
    if (_instance.maxItems) {
      _writer.writeUint64String(20, _instance.maxItems);
    }
    if (_instance.minItems) {
      _writer.writeUint64String(21, _instance.minItems);
    }
    if (_instance.uniqueItems) {
      _writer.writeBool(22, _instance.uniqueItems);
    }
    if (_instance.maxProperties) {
      _writer.writeUint64String(24, _instance.maxProperties);
    }
    if (_instance.minProperties) {
      _writer.writeUint64String(25, _instance.minProperties);
    }
    if (_instance.required && _instance.required.length) {
      _writer.writeRepeatedString(26, _instance.required);
    }
    if (_instance.array && _instance.array.length) {
      _writer.writeRepeatedString(34, _instance.array);
    }
    if (_instance.type && _instance.type.length) {
      _writer.writePackedEnum(35, _instance.type);
    }
    if (_instance.format) {
      _writer.writeString(36, _instance.format);
    }
    if (_instance.enum && _instance.enum.length) {
      _writer.writeRepeatedString(46, _instance.enum);
    }
    if (_instance.fieldConfiguration) {
      _writer.writeMessage(
        1001,
        _instance.fieldConfiguration as any,
        JSONSchema.FieldConfiguration.serializeBinaryToWriter
      );
    }
    if (!!_instance.extensions) {
      const keys_48 = Object.keys(_instance.extensions as any);

      if (keys_48.length) {
        const repeated_48 = keys_48
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          48,
          repeated_48,
          JSONSchema.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _ref: string;
  private _title: string;
  private _description: string;
  private _pbDefault: string;
  private _readOnly: boolean;
  private _example: string;
  private _multipleOf: number;
  private _maximum: number;
  private _exclusiveMaximum: boolean;
  private _minimum: number;
  private _exclusiveMinimum: boolean;
  private _maxLength: string;
  private _minLength: string;
  private _pattern: string;
  private _maxItems: string;
  private _minItems: string;
  private _uniqueItems: boolean;
  private _maxProperties: string;
  private _minProperties: string;
  private _required: string[];
  private _array: string[];
  private _type: JSONSchema.JSONSchemaSimpleTypes[];
  private _format: string;
  private _enum: string[];
  private _fieldConfiguration?: JSONSchema.FieldConfiguration;
  private _extensions: { [prop: string]: googleProtobuf000.Value };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of JSONSchema to deeply clone from
   */
  constructor(_value?: RecursivePartial<JSONSchema.AsObject>) {
    _value = _value || {};
    this.ref = _value.ref;
    this.title = _value.title;
    this.description = _value.description;
    this.pbDefault = _value.pbDefault;
    this.readOnly = _value.readOnly;
    this.example = _value.example;
    this.multipleOf = _value.multipleOf;
    this.maximum = _value.maximum;
    this.exclusiveMaximum = _value.exclusiveMaximum;
    this.minimum = _value.minimum;
    this.exclusiveMinimum = _value.exclusiveMinimum;
    this.maxLength = _value.maxLength;
    this.minLength = _value.minLength;
    this.pattern = _value.pattern;
    this.maxItems = _value.maxItems;
    this.minItems = _value.minItems;
    this.uniqueItems = _value.uniqueItems;
    this.maxProperties = _value.maxProperties;
    this.minProperties = _value.minProperties;
    this.required = (_value.required || []).slice();
    this.array = (_value.array || []).slice();
    this.type = (_value.type || []).slice();
    this.format = _value.format;
    this.enum = (_value.enum || []).slice();
    this.fieldConfiguration = _value.fieldConfiguration
      ? new JSONSchema.FieldConfiguration(_value.fieldConfiguration)
      : undefined;
    (this.extensions = _value!.extensions
      ? Object.keys(_value!.extensions).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.extensions![k]
              ? new googleProtobuf000.Value(_value!.extensions![k])
              : undefined
          }),
          {}
        )
      : {}),
      JSONSchema.refineValues(this);
  }
  get ref(): string {
    return this._ref;
  }
  set ref(value: string) {
    this._ref = value;
  }
  get title(): string {
    return this._title;
  }
  set title(value: string) {
    this._title = value;
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get pbDefault(): string {
    return this._pbDefault;
  }
  set pbDefault(value: string) {
    this._pbDefault = value;
  }
  get readOnly(): boolean {
    return this._readOnly;
  }
  set readOnly(value: boolean) {
    this._readOnly = value;
  }
  get example(): string {
    return this._example;
  }
  set example(value: string) {
    this._example = value;
  }
  get multipleOf(): number {
    return this._multipleOf;
  }
  set multipleOf(value: number) {
    this._multipleOf = value;
  }
  get maximum(): number {
    return this._maximum;
  }
  set maximum(value: number) {
    this._maximum = value;
  }
  get exclusiveMaximum(): boolean {
    return this._exclusiveMaximum;
  }
  set exclusiveMaximum(value: boolean) {
    this._exclusiveMaximum = value;
  }
  get minimum(): number {
    return this._minimum;
  }
  set minimum(value: number) {
    this._minimum = value;
  }
  get exclusiveMinimum(): boolean {
    return this._exclusiveMinimum;
  }
  set exclusiveMinimum(value: boolean) {
    this._exclusiveMinimum = value;
  }
  get maxLength(): string {
    return this._maxLength;
  }
  set maxLength(value: string) {
    this._maxLength = value;
  }
  get minLength(): string {
    return this._minLength;
  }
  set minLength(value: string) {
    this._minLength = value;
  }
  get pattern(): string {
    return this._pattern;
  }
  set pattern(value: string) {
    this._pattern = value;
  }
  get maxItems(): string {
    return this._maxItems;
  }
  set maxItems(value: string) {
    this._maxItems = value;
  }
  get minItems(): string {
    return this._minItems;
  }
  set minItems(value: string) {
    this._minItems = value;
  }
  get uniqueItems(): boolean {
    return this._uniqueItems;
  }
  set uniqueItems(value: boolean) {
    this._uniqueItems = value;
  }
  get maxProperties(): string {
    return this._maxProperties;
  }
  set maxProperties(value: string) {
    this._maxProperties = value;
  }
  get minProperties(): string {
    return this._minProperties;
  }
  set minProperties(value: string) {
    this._minProperties = value;
  }
  get required(): string[] {
    return this._required;
  }
  set required(value: string[]) {
    this._required = value;
  }
  get array(): string[] {
    return this._array;
  }
  set array(value: string[]) {
    this._array = value;
  }
  get type(): JSONSchema.JSONSchemaSimpleTypes[] {
    return this._type;
  }
  set type(value: JSONSchema.JSONSchemaSimpleTypes[]) {
    this._type = value;
  }
  get format(): string {
    return this._format;
  }
  set format(value: string) {
    this._format = value;
  }
  get enum(): string[] {
    return this._enum;
  }
  set enum(value: string[]) {
    this._enum = value;
  }
  get fieldConfiguration(): JSONSchema.FieldConfiguration | undefined {
    return this._fieldConfiguration;
  }
  set fieldConfiguration(value: JSONSchema.FieldConfiguration | undefined) {
    this._fieldConfiguration = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    JSONSchema.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): JSONSchema.AsObject {
    return {
      ref: this.ref,
      title: this.title,
      description: this.description,
      pbDefault: this.pbDefault,
      readOnly: this.readOnly,
      example: this.example,
      multipleOf: this.multipleOf,
      maximum: this.maximum,
      exclusiveMaximum: this.exclusiveMaximum,
      minimum: this.minimum,
      exclusiveMinimum: this.exclusiveMinimum,
      maxLength: this.maxLength,
      minLength: this.minLength,
      pattern: this.pattern,
      maxItems: this.maxItems,
      minItems: this.minItems,
      uniqueItems: this.uniqueItems,
      maxProperties: this.maxProperties,
      minProperties: this.minProperties,
      required: (this.required || []).slice(),
      array: (this.array || []).slice(),
      type: (this.type || []).slice(),
      format: this.format,
      enum: (this.enum || []).slice(),
      fieldConfiguration: this.fieldConfiguration
        ? this.fieldConfiguration.toObject()
        : undefined,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
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
  ): JSONSchema.AsProtobufJSON {
    return {
      ref: this.ref,
      title: this.title,
      description: this.description,
      pbDefault: this.pbDefault,
      readOnly: this.readOnly,
      example: this.example,
      multipleOf: this.multipleOf,
      maximum: this.maximum,
      exclusiveMaximum: this.exclusiveMaximum,
      minimum: this.minimum,
      exclusiveMinimum: this.exclusiveMinimum,
      maxLength: this.maxLength,
      minLength: this.minLength,
      pattern: this.pattern,
      maxItems: this.maxItems,
      minItems: this.minItems,
      uniqueItems: this.uniqueItems,
      maxProperties: this.maxProperties,
      minProperties: this.minProperties,
      required: (this.required || []).slice(),
      array: (this.array || []).slice(),
      type: (this.type || []).map(v => JSONSchema.JSONSchemaSimpleTypes[v]),
      format: this.format,
      enum: (this.enum || []).slice(),
      fieldConfiguration: this.fieldConfiguration
        ? this.fieldConfiguration.toProtobufJSON(options)
        : null,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module JSONSchema {
  /**
   * Standard JavaScript object representation for JSONSchema
   */
  export interface AsObject {
    ref: string;
    title: string;
    description: string;
    pbDefault: string;
    readOnly: boolean;
    example: string;
    multipleOf: number;
    maximum: number;
    exclusiveMaximum: boolean;
    minimum: number;
    exclusiveMinimum: boolean;
    maxLength: string;
    minLength: string;
    pattern: string;
    maxItems: string;
    minItems: string;
    uniqueItems: boolean;
    maxProperties: string;
    minProperties: string;
    required: string[];
    array: string[];
    type: JSONSchema.JSONSchemaSimpleTypes[];
    format: string;
    enum: string[];
    fieldConfiguration?: JSONSchema.FieldConfiguration.AsObject;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Protobuf JSON representation for JSONSchema
   */
  export interface AsProtobufJSON {
    ref: string;
    title: string;
    description: string;
    pbDefault: string;
    readOnly: boolean;
    example: string;
    multipleOf: number;
    maximum: number;
    exclusiveMaximum: boolean;
    minimum: number;
    exclusiveMinimum: boolean;
    maxLength: string;
    minLength: string;
    pattern: string;
    maxItems: string;
    minItems: string;
    uniqueItems: boolean;
    maxProperties: string;
    minProperties: string;
    required: string[];
    array: string[];
    type: string[];
    format: string;
    enum: string[];
    fieldConfiguration: JSONSchema.FieldConfiguration.AsProtobufJSON | null;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }
  export enum JSONSchemaSimpleTypes {
    UNKNOWN = 0,
    ARRAY = 1,
    BOOLEAN = 2,
    INTEGER = 3,
    NULL = 4,
    NUMBER = 5,
    OBJECT = 6,
    STRING = 7
  }
  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.JSONSchema.FieldConfiguration
   */
  export class FieldConfiguration implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.JSONSchema.FieldConfiguration';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new FieldConfiguration();
      FieldConfiguration.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: FieldConfiguration) {
      _instance.pathParamName = _instance.pathParamName || '';
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: FieldConfiguration,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 47:
            _instance.pathParamName = _reader.readString();
            break;
          default:
            _reader.skipField();
        }
      }

      FieldConfiguration.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: FieldConfiguration,
      _writer: BinaryWriter
    ) {
      if (_instance.pathParamName) {
        _writer.writeString(47, _instance.pathParamName);
      }
    }

    private _pathParamName: string;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of FieldConfiguration to deeply clone from
     */
    constructor(_value?: RecursivePartial<FieldConfiguration.AsObject>) {
      _value = _value || {};
      this.pathParamName = _value.pathParamName;
      FieldConfiguration.refineValues(this);
    }
    get pathParamName(): string {
      return this._pathParamName;
    }
    set pathParamName(value: string) {
      this._pathParamName = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      FieldConfiguration.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): FieldConfiguration.AsObject {
      return {
        pathParamName: this.pathParamName
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
    ): FieldConfiguration.AsProtobufJSON {
      return {
        pathParamName: this.pathParamName
      };
    }
  }
  export module FieldConfiguration {
    /**
     * Standard JavaScript object representation for FieldConfiguration
     */
    export interface AsObject {
      pathParamName: string;
    }

    /**
     * Protobuf JSON representation for FieldConfiguration
     */
    export interface AsProtobufJSON {
      pathParamName: string;
    }
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.JSONSchema.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.JSONSchema.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Tag
 */
export class Tag implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Tag';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Tag();
    Tag.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Tag) {
    _instance.name = _instance.name || '';
    _instance.description = _instance.description || '';
    _instance.externalDocs = _instance.externalDocs || undefined;
    _instance.extensions = _instance.extensions || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Tag, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.name = _reader.readString();
          break;
        case 2:
          _instance.description = _reader.readString();
          break;
        case 3:
          _instance.externalDocs = new ExternalDocumentation();
          _reader.readMessage(
            _instance.externalDocs,
            ExternalDocumentation.deserializeBinaryFromReader
          );
          break;
        case 4:
          const msg_4 = {} as any;
          _reader.readMessage(
            msg_4,
            Tag.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_4.key] = msg_4.value;
          break;
        default:
          _reader.skipField();
      }
    }

    Tag.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Tag, _writer: BinaryWriter) {
    if (_instance.name) {
      _writer.writeString(1, _instance.name);
    }
    if (_instance.description) {
      _writer.writeString(2, _instance.description);
    }
    if (_instance.externalDocs) {
      _writer.writeMessage(
        3,
        _instance.externalDocs as any,
        ExternalDocumentation.serializeBinaryToWriter
      );
    }
    if (!!_instance.extensions) {
      const keys_4 = Object.keys(_instance.extensions as any);

      if (keys_4.length) {
        const repeated_4 = keys_4
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          4,
          repeated_4,
          Tag.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _name: string;
  private _description: string;
  private _externalDocs?: ExternalDocumentation;
  private _extensions: { [prop: string]: googleProtobuf000.Value };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Tag to deeply clone from
   */
  constructor(_value?: RecursivePartial<Tag.AsObject>) {
    _value = _value || {};
    this.name = _value.name;
    this.description = _value.description;
    this.externalDocs = _value.externalDocs
      ? new ExternalDocumentation(_value.externalDocs)
      : undefined;
    (this.extensions = _value!.extensions
      ? Object.keys(_value!.extensions).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.extensions![k]
              ? new googleProtobuf000.Value(_value!.extensions![k])
              : undefined
          }),
          {}
        )
      : {}),
      Tag.refineValues(this);
  }
  get name(): string {
    return this._name;
  }
  set name(value: string) {
    this._name = value;
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get externalDocs(): ExternalDocumentation | undefined {
    return this._externalDocs;
  }
  set externalDocs(value: ExternalDocumentation | undefined) {
    this._externalDocs = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Tag.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Tag.AsObject {
    return {
      name: this.name,
      description: this.description,
      externalDocs: this.externalDocs
        ? this.externalDocs.toObject()
        : undefined,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
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
  ): Tag.AsProtobufJSON {
    return {
      name: this.name,
      description: this.description,
      externalDocs: this.externalDocs
        ? this.externalDocs.toProtobufJSON(options)
        : null,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module Tag {
  /**
   * Standard JavaScript object representation for Tag
   */
  export interface AsObject {
    name: string;
    description: string;
    externalDocs?: ExternalDocumentation.AsObject;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Protobuf JSON representation for Tag
   */
  export interface AsProtobufJSON {
    name: string;
    description: string;
    externalDocs: ExternalDocumentation.AsProtobufJSON | null;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Tag.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id = 'grpc.gateway.protoc_gen_openapiv2.options.Tag.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.SecurityDefinitions
 */
export class SecurityDefinitions implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.SecurityDefinitions';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new SecurityDefinitions();
    SecurityDefinitions.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: SecurityDefinitions) {
    _instance.security = _instance.security || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: SecurityDefinitions,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const msg_1 = {} as any;
          _reader.readMessage(
            msg_1,
            SecurityDefinitions.SecurityEntry.deserializeBinaryFromReader
          );
          _instance.security = _instance.security || {};
          _instance.security[msg_1.key] = msg_1.value;
          break;
        default:
          _reader.skipField();
      }
    }

    SecurityDefinitions.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: SecurityDefinitions,
    _writer: BinaryWriter
  ) {
    if (!!_instance.security) {
      const keys_1 = Object.keys(_instance.security as any);

      if (keys_1.length) {
        const repeated_1 = keys_1
          .map(key => ({ key: key, value: (_instance.security as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          1,
          repeated_1,
          SecurityDefinitions.SecurityEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _security: { [prop: string]: SecurityScheme };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of SecurityDefinitions to deeply clone from
   */
  constructor(_value?: RecursivePartial<SecurityDefinitions.AsObject>) {
    _value = _value || {};
    (this.security = _value!.security
      ? Object.keys(_value!.security).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.security![k]
              ? new SecurityScheme(_value!.security![k])
              : undefined
          }),
          {}
        )
      : {}),
      SecurityDefinitions.refineValues(this);
  }
  get security(): { [prop: string]: SecurityScheme } {
    return this._security;
  }
  set security(value: { [prop: string]: SecurityScheme }) {
    this._security = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    SecurityDefinitions.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): SecurityDefinitions.AsObject {
    return {
      security: this.security
        ? Object.keys(this.security).reduce(
            (r, k) => ({
              ...r,
              [k]: this.security![k] ? this.security![k].toObject() : undefined
            }),
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
  ): SecurityDefinitions.AsProtobufJSON {
    return {
      security: this.security
        ? Object.keys(this.security).reduce(
            (r, k) => ({
              ...r,
              [k]: this.security![k] ? this.security![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module SecurityDefinitions {
  /**
   * Standard JavaScript object representation for SecurityDefinitions
   */
  export interface AsObject {
    security: { [prop: string]: SecurityScheme };
  }

  /**
   * Protobuf JSON representation for SecurityDefinitions
   */
  export interface AsProtobufJSON {
    security: { [prop: string]: SecurityScheme };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.SecurityDefinitions.SecurityEntry
   */
  export class SecurityEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.SecurityDefinitions.SecurityEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new SecurityEntry();
      SecurityEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: SecurityEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: SecurityEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new SecurityScheme();
            _reader.readMessage(
              _instance.value,
              SecurityScheme.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      SecurityEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: SecurityEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          SecurityScheme.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: SecurityScheme;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of SecurityEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<SecurityEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value ? new SecurityScheme(_value.value) : undefined;
      SecurityEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): SecurityScheme | undefined {
      return this._value;
    }
    set value(value: SecurityScheme | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      SecurityEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): SecurityEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): SecurityEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module SecurityEntry {
    /**
     * Standard JavaScript object representation for SecurityEntry
     */
    export interface AsObject {
      key: string;
      value?: SecurityScheme.AsObject;
    }

    /**
     * Protobuf JSON representation for SecurityEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: SecurityScheme.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.SecurityScheme
 */
export class SecurityScheme implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.SecurityScheme';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new SecurityScheme();
    SecurityScheme.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: SecurityScheme) {
    _instance.type = _instance.type || 0;
    _instance.description = _instance.description || '';
    _instance.name = _instance.name || '';
    _instance.in = _instance.in || 0;
    _instance.flow = _instance.flow || 0;
    _instance.authorizationUrl = _instance.authorizationUrl || '';
    _instance.tokenUrl = _instance.tokenUrl || '';
    _instance.scopes = _instance.scopes || undefined;
    _instance.extensions = _instance.extensions || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: SecurityScheme,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.type = _reader.readEnum();
          break;
        case 2:
          _instance.description = _reader.readString();
          break;
        case 3:
          _instance.name = _reader.readString();
          break;
        case 4:
          _instance.in = _reader.readEnum();
          break;
        case 5:
          _instance.flow = _reader.readEnum();
          break;
        case 6:
          _instance.authorizationUrl = _reader.readString();
          break;
        case 7:
          _instance.tokenUrl = _reader.readString();
          break;
        case 8:
          _instance.scopes = new Scopes();
          _reader.readMessage(
            _instance.scopes,
            Scopes.deserializeBinaryFromReader
          );
          break;
        case 9:
          const msg_9 = {} as any;
          _reader.readMessage(
            msg_9,
            SecurityScheme.ExtensionsEntry.deserializeBinaryFromReader
          );
          _instance.extensions = _instance.extensions || {};
          _instance.extensions[msg_9.key] = msg_9.value;
          break;
        default:
          _reader.skipField();
      }
    }

    SecurityScheme.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: SecurityScheme,
    _writer: BinaryWriter
  ) {
    if (_instance.type) {
      _writer.writeEnum(1, _instance.type);
    }
    if (_instance.description) {
      _writer.writeString(2, _instance.description);
    }
    if (_instance.name) {
      _writer.writeString(3, _instance.name);
    }
    if (_instance.in) {
      _writer.writeEnum(4, _instance.in);
    }
    if (_instance.flow) {
      _writer.writeEnum(5, _instance.flow);
    }
    if (_instance.authorizationUrl) {
      _writer.writeString(6, _instance.authorizationUrl);
    }
    if (_instance.tokenUrl) {
      _writer.writeString(7, _instance.tokenUrl);
    }
    if (_instance.scopes) {
      _writer.writeMessage(
        8,
        _instance.scopes as any,
        Scopes.serializeBinaryToWriter
      );
    }
    if (!!_instance.extensions) {
      const keys_9 = Object.keys(_instance.extensions as any);

      if (keys_9.length) {
        const repeated_9 = keys_9
          .map(key => ({ key: key, value: (_instance.extensions as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          9,
          repeated_9,
          SecurityScheme.ExtensionsEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _type: SecurityScheme.Type;
  private _description: string;
  private _name: string;
  private _in: SecurityScheme.In;
  private _flow: SecurityScheme.Flow;
  private _authorizationUrl: string;
  private _tokenUrl: string;
  private _scopes?: Scopes;
  private _extensions: { [prop: string]: googleProtobuf000.Value };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of SecurityScheme to deeply clone from
   */
  constructor(_value?: RecursivePartial<SecurityScheme.AsObject>) {
    _value = _value || {};
    this.type = _value.type;
    this.description = _value.description;
    this.name = _value.name;
    this.in = _value.in;
    this.flow = _value.flow;
    this.authorizationUrl = _value.authorizationUrl;
    this.tokenUrl = _value.tokenUrl;
    this.scopes = _value.scopes ? new Scopes(_value.scopes) : undefined;
    (this.extensions = _value!.extensions
      ? Object.keys(_value!.extensions).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.extensions![k]
              ? new googleProtobuf000.Value(_value!.extensions![k])
              : undefined
          }),
          {}
        )
      : {}),
      SecurityScheme.refineValues(this);
  }
  get type(): SecurityScheme.Type {
    return this._type;
  }
  set type(value: SecurityScheme.Type) {
    this._type = value;
  }
  get description(): string {
    return this._description;
  }
  set description(value: string) {
    this._description = value;
  }
  get name(): string {
    return this._name;
  }
  set name(value: string) {
    this._name = value;
  }
  get in(): SecurityScheme.In {
    return this._in;
  }
  set in(value: SecurityScheme.In) {
    this._in = value;
  }
  get flow(): SecurityScheme.Flow {
    return this._flow;
  }
  set flow(value: SecurityScheme.Flow) {
    this._flow = value;
  }
  get authorizationUrl(): string {
    return this._authorizationUrl;
  }
  set authorizationUrl(value: string) {
    this._authorizationUrl = value;
  }
  get tokenUrl(): string {
    return this._tokenUrl;
  }
  set tokenUrl(value: string) {
    this._tokenUrl = value;
  }
  get scopes(): Scopes | undefined {
    return this._scopes;
  }
  set scopes(value: Scopes | undefined) {
    this._scopes = value;
  }
  get extensions(): { [prop: string]: googleProtobuf000.Value } {
    return this._extensions;
  }
  set extensions(value: { [prop: string]: googleProtobuf000.Value }) {
    this._extensions = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    SecurityScheme.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): SecurityScheme.AsObject {
    return {
      type: this.type,
      description: this.description,
      name: this.name,
      in: this.in,
      flow: this.flow,
      authorizationUrl: this.authorizationUrl,
      tokenUrl: this.tokenUrl,
      scopes: this.scopes ? this.scopes.toObject() : undefined,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k]
                ? this.extensions![k].toObject()
                : undefined
            }),
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
  ): SecurityScheme.AsProtobufJSON {
    return {
      type:
        SecurityScheme.Type[
          this.type === null || this.type === undefined ? 0 : this.type
        ],
      description: this.description,
      name: this.name,
      in:
        SecurityScheme.In[
          this.in === null || this.in === undefined ? 0 : this.in
        ],
      flow:
        SecurityScheme.Flow[
          this.flow === null || this.flow === undefined ? 0 : this.flow
        ],
      authorizationUrl: this.authorizationUrl,
      tokenUrl: this.tokenUrl,
      scopes: this.scopes ? this.scopes.toProtobufJSON(options) : null,
      extensions: this.extensions
        ? Object.keys(this.extensions).reduce(
            (r, k) => ({
              ...r,
              [k]: this.extensions![k] ? this.extensions![k].toJSON() : null
            }),
            {}
          )
        : {}
    };
  }
}
export module SecurityScheme {
  /**
   * Standard JavaScript object representation for SecurityScheme
   */
  export interface AsObject {
    type: SecurityScheme.Type;
    description: string;
    name: string;
    in: SecurityScheme.In;
    flow: SecurityScheme.Flow;
    authorizationUrl: string;
    tokenUrl: string;
    scopes?: Scopes.AsObject;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }

  /**
   * Protobuf JSON representation for SecurityScheme
   */
  export interface AsProtobufJSON {
    type: string;
    description: string;
    name: string;
    in: string;
    flow: string;
    authorizationUrl: string;
    tokenUrl: string;
    scopes: Scopes.AsProtobufJSON | null;
    extensions: { [prop: string]: googleProtobuf000.Value };
  }
  export enum Type {
    TYPE_INVALID = 0,
    TYPE_BASIC = 1,
    TYPE_API_KEY = 2,
    TYPE_OAUTH2 = 3
  }
  export enum In {
    IN_INVALID = 0,
    IN_QUERY = 1,
    IN_HEADER = 2
  }
  export enum Flow {
    FLOW_INVALID = 0,
    FLOW_IMPLICIT = 1,
    FLOW_PASSWORD = 2,
    FLOW_APPLICATION = 3,
    FLOW_ACCESS_CODE = 4
  }
  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.SecurityScheme.ExtensionsEntry
   */
  export class ExtensionsEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.SecurityScheme.ExtensionsEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ExtensionsEntry();
      ExtensionsEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ExtensionsEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ExtensionsEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new googleProtobuf000.Value();
            _reader.readMessage(
              _instance.value,
              googleProtobuf000.Value.deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      ExtensionsEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ExtensionsEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          googleProtobuf000.Value.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: googleProtobuf000.Value;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of ExtensionsEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ExtensionsEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new googleProtobuf000.Value(_value.value)
        : undefined;
      ExtensionsEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): googleProtobuf000.Value | undefined {
      return this._value;
    }
    set value(value: googleProtobuf000.Value | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      ExtensionsEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ExtensionsEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): ExtensionsEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module ExtensionsEntry {
    /**
     * Standard JavaScript object representation for ExtensionsEntry
     */
    export interface AsObject {
      key: string;
      value?: googleProtobuf000.Value.AsObject;
    }

    /**
     * Protobuf JSON representation for ExtensionsEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: googleProtobuf000.Value.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.SecurityRequirement
 */
export class SecurityRequirement implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.SecurityRequirement';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new SecurityRequirement();
    SecurityRequirement.deserializeBinaryFromReader(
      instance,
      new BinaryReader(bytes)
    );
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: SecurityRequirement) {
    _instance.securityRequirement = _instance.securityRequirement || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(
    _instance: SecurityRequirement,
    _reader: BinaryReader
  ) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const msg_1 = {} as any;
          _reader.readMessage(
            msg_1,
            SecurityRequirement.SecurityRequirementEntry
              .deserializeBinaryFromReader
          );
          _instance.securityRequirement = _instance.securityRequirement || {};
          _instance.securityRequirement[msg_1.key] = msg_1.value;
          break;
        default:
          _reader.skipField();
      }
    }

    SecurityRequirement.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(
    _instance: SecurityRequirement,
    _writer: BinaryWriter
  ) {
    if (!!_instance.securityRequirement) {
      const keys_1 = Object.keys(_instance.securityRequirement as any);

      if (keys_1.length) {
        const repeated_1 = keys_1
          .map(key => ({
            key: key,
            value: (_instance.securityRequirement as any)[key]
          }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          1,
          repeated_1,
          SecurityRequirement.SecurityRequirementEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _securityRequirement: {
    [prop: string]: SecurityRequirement.SecurityRequirementValue;
  };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of SecurityRequirement to deeply clone from
   */
  constructor(_value?: RecursivePartial<SecurityRequirement.AsObject>) {
    _value = _value || {};
    (this.securityRequirement = _value!.securityRequirement
      ? Object.keys(_value!.securityRequirement).reduce(
          (r, k) => ({
            ...r,
            [k]: _value!.securityRequirement![k]
              ? new SecurityRequirement.SecurityRequirementValue(
                  _value!.securityRequirement![k]
                )
              : undefined
          }),
          {}
        )
      : {}),
      SecurityRequirement.refineValues(this);
  }
  get securityRequirement(): {
    [prop: string]: SecurityRequirement.SecurityRequirementValue;
  } {
    return this._securityRequirement;
  }
  set securityRequirement(value: {
    [prop: string]: SecurityRequirement.SecurityRequirementValue;
  }) {
    this._securityRequirement = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    SecurityRequirement.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): SecurityRequirement.AsObject {
    return {
      securityRequirement: this.securityRequirement
        ? Object.keys(this.securityRequirement).reduce(
            (r, k) => ({
              ...r,
              [k]: this.securityRequirement![k]
                ? this.securityRequirement![k].toObject()
                : undefined
            }),
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
  ): SecurityRequirement.AsProtobufJSON {
    return {
      securityRequirement: this.securityRequirement
        ? Object.keys(this.securityRequirement).reduce(
            (r, k) => ({
              ...r,
              [k]: this.securityRequirement![k]
                ? this.securityRequirement![k].toJSON()
                : null
            }),
            {}
          )
        : {}
    };
  }
}
export module SecurityRequirement {
  /**
   * Standard JavaScript object representation for SecurityRequirement
   */
  export interface AsObject {
    securityRequirement: {
      [prop: string]: SecurityRequirement.SecurityRequirementValue;
    };
  }

  /**
   * Protobuf JSON representation for SecurityRequirement
   */
  export interface AsProtobufJSON {
    securityRequirement: {
      [prop: string]: SecurityRequirement.SecurityRequirementValue;
    };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.SecurityRequirement.SecurityRequirementValue
   */
  export class SecurityRequirementValue implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.SecurityRequirement.SecurityRequirementValue';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new SecurityRequirementValue();
      SecurityRequirementValue.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: SecurityRequirementValue) {
      _instance.scope = _instance.scope || [];
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: SecurityRequirementValue,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            (_instance.scope = _instance.scope || []).push(
              _reader.readString()
            );
            break;
          default:
            _reader.skipField();
        }
      }

      SecurityRequirementValue.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: SecurityRequirementValue,
      _writer: BinaryWriter
    ) {
      if (_instance.scope && _instance.scope.length) {
        _writer.writeRepeatedString(1, _instance.scope);
      }
    }

    private _scope: string[];

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of SecurityRequirementValue to deeply clone from
     */
    constructor(_value?: RecursivePartial<SecurityRequirementValue.AsObject>) {
      _value = _value || {};
      this.scope = (_value.scope || []).slice();
      SecurityRequirementValue.refineValues(this);
    }
    get scope(): string[] {
      return this._scope;
    }
    set scope(value: string[]) {
      this._scope = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      SecurityRequirementValue.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): SecurityRequirementValue.AsObject {
      return {
        scope: (this.scope || []).slice()
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
    ): SecurityRequirementValue.AsProtobufJSON {
      return {
        scope: (this.scope || []).slice()
      };
    }
  }
  export module SecurityRequirementValue {
    /**
     * Standard JavaScript object representation for SecurityRequirementValue
     */
    export interface AsObject {
      scope: string[];
    }

    /**
     * Protobuf JSON representation for SecurityRequirementValue
     */
    export interface AsProtobufJSON {
      scope: string[];
    }
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.SecurityRequirement.SecurityRequirementEntry
   */
  export class SecurityRequirementEntry implements GrpcMessage {
    static id =
      'grpc.gateway.protoc_gen_openapiv2.options.SecurityRequirement.SecurityRequirementEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new SecurityRequirementEntry();
      SecurityRequirementEntry.deserializeBinaryFromReader(
        instance,
        new BinaryReader(bytes)
      );
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: SecurityRequirementEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || undefined;
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: SecurityRequirementEntry,
      _reader: BinaryReader
    ) {
      while (_reader.nextField()) {
        if (_reader.isEndGroup()) break;

        switch (_reader.getFieldNumber()) {
          case 1:
            _instance.key = _reader.readString();
            break;
          case 2:
            _instance.value = new SecurityRequirement.SecurityRequirementValue();
            _reader.readMessage(
              _instance.value,
              SecurityRequirement.SecurityRequirementValue
                .deserializeBinaryFromReader
            );
            break;
          default:
            _reader.skipField();
        }
      }

      SecurityRequirementEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: SecurityRequirementEntry,
      _writer: BinaryWriter
    ) {
      if (_instance.key) {
        _writer.writeString(1, _instance.key);
      }
      if (_instance.value) {
        _writer.writeMessage(
          2,
          _instance.value as any,
          SecurityRequirement.SecurityRequirementValue.serializeBinaryToWriter
        );
      }
    }

    private _key: string;
    private _value?: SecurityRequirement.SecurityRequirementValue;

    /**
     * Message constructor. Initializes the properties and applies default Protobuf values if necessary
     * @param _value initial values object or instance of SecurityRequirementEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<SecurityRequirementEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value
        ? new SecurityRequirement.SecurityRequirementValue(_value.value)
        : undefined;
      SecurityRequirementEntry.refineValues(this);
    }
    get key(): string {
      return this._key;
    }
    set key(value: string) {
      this._key = value;
    }
    get value(): SecurityRequirement.SecurityRequirementValue | undefined {
      return this._value;
    }
    set value(value: SecurityRequirement.SecurityRequirementValue | undefined) {
      this._value = value;
    }

    /**
     * Serialize message to binary data
     * @param instance message instance
     */
    serializeBinary() {
      const writer = new BinaryWriter();
      SecurityRequirementEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): SecurityRequirementEntry.AsObject {
      return {
        key: this.key,
        value: this.value ? this.value.toObject() : undefined
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
    ): SecurityRequirementEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value ? this.value.toProtobufJSON(options) : null
      };
    }
  }
  export module SecurityRequirementEntry {
    /**
     * Standard JavaScript object representation for SecurityRequirementEntry
     */
    export interface AsObject {
      key: string;
      value?: SecurityRequirement.SecurityRequirementValue.AsObject;
    }

    /**
     * Protobuf JSON representation for SecurityRequirementEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: SecurityRequirement.SecurityRequirementValue.AsProtobufJSON | null;
    }
  }
}

/**
 * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Scopes
 */
export class Scopes implements GrpcMessage {
  static id = 'grpc.gateway.protoc_gen_openapiv2.options.Scopes';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Scopes();
    Scopes.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Scopes) {
    _instance.scope = _instance.scope || {};
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Scopes, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          const msg_1 = {} as any;
          _reader.readMessage(
            msg_1,
            Scopes.ScopeEntry.deserializeBinaryFromReader
          );
          _instance.scope = _instance.scope || {};
          _instance.scope[msg_1.key] = msg_1.value;
          break;
        default:
          _reader.skipField();
      }
    }

    Scopes.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Scopes, _writer: BinaryWriter) {
    if (!!_instance.scope) {
      const keys_1 = Object.keys(_instance.scope as any);

      if (keys_1.length) {
        const repeated_1 = keys_1
          .map(key => ({ key: key, value: (_instance.scope as any)[key] }))
          .reduce((r, v) => [...r, v], [] as any[]);

        _writer.writeRepeatedMessage(
          1,
          repeated_1,
          Scopes.ScopeEntry.serializeBinaryToWriter
        );
      }
    }
  }

  private _scope: { [prop: string]: string };

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Scopes to deeply clone from
   */
  constructor(_value?: RecursivePartial<Scopes.AsObject>) {
    _value = _value || {};
    (this.scope = _value!.scope
      ? Object.keys(_value!.scope).reduce(
          (r, k) => ({ ...r, [k]: _value!.scope![k] }),
          {}
        )
      : {}),
      Scopes.refineValues(this);
  }
  get scope(): { [prop: string]: string } {
    return this._scope;
  }
  set scope(value: { [prop: string]: string }) {
    this._scope = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Scopes.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Scopes.AsObject {
    return {
      scope: this.scope
        ? Object.keys(this.scope).reduce(
            (r, k) => ({ ...r, [k]: this.scope![k] }),
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
  ): Scopes.AsProtobufJSON {
    return {
      scope: this.scope
        ? Object.keys(this.scope).reduce(
            (r, k) => ({ ...r, [k]: this.scope![k] }),
            {}
          )
        : {}
    };
  }
}
export module Scopes {
  /**
   * Standard JavaScript object representation for Scopes
   */
  export interface AsObject {
    scope: { [prop: string]: string };
  }

  /**
   * Protobuf JSON representation for Scopes
   */
  export interface AsProtobufJSON {
    scope: { [prop: string]: string };
  }

  /**
   * Message implementation for grpc.gateway.protoc_gen_openapiv2.options.Scopes.ScopeEntry
   */
  export class ScopeEntry implements GrpcMessage {
    static id = 'grpc.gateway.protoc_gen_openapiv2.options.Scopes.ScopeEntry';

    /**
     * Deserialize binary data to message
     * @param instance message instance
     */
    static deserializeBinary(bytes: ByteSource) {
      const instance = new ScopeEntry();
      ScopeEntry.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
      return instance;
    }

    /**
     * Check all the properties and set default protobuf values if necessary
     * @param _instance message instance
     */
    static refineValues(_instance: ScopeEntry) {
      _instance.key = _instance.key || '';
      _instance.value = _instance.value || '';
    }

    /**
     * Deserializes / reads binary message into message instance using provided binary reader
     * @param _instance message instance
     * @param _reader binary reader instance
     */
    static deserializeBinaryFromReader(
      _instance: ScopeEntry,
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

      ScopeEntry.refineValues(_instance);
    }

    /**
     * Serializes a message to binary format using provided binary reader
     * @param _instance message instance
     * @param _writer binary writer instance
     */
    static serializeBinaryToWriter(
      _instance: ScopeEntry,
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
     * @param _value initial values object or instance of ScopeEntry to deeply clone from
     */
    constructor(_value?: RecursivePartial<ScopeEntry.AsObject>) {
      _value = _value || {};
      this.key = _value.key;
      this.value = _value.value;
      ScopeEntry.refineValues(this);
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
      ScopeEntry.serializeBinaryToWriter(this, writer);
      return writer.getResultBuffer();
    }

    /**
     * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
     */
    toObject(): ScopeEntry.AsObject {
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
    ): ScopeEntry.AsProtobufJSON {
      return {
        key: this.key,
        value: this.value
      };
    }
  }
  export module ScopeEntry {
    /**
     * Standard JavaScript object representation for ScopeEntry
     */
    export interface AsObject {
      key: string;
      value: string;
    }

    /**
     * Protobuf JSON representation for ScopeEntry
     */
    export interface AsProtobufJSON {
      key: string;
      value: string;
    }
  }
}
