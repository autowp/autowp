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
 * Message implementation for google.rpc.Status
 */
export class Status implements GrpcMessage {
  static id = 'google.rpc.Status';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Status();
    Status.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Status) {
    _instance.code = _instance.code || 0;
    _instance.message = _instance.message || '';
    _instance.details = _instance.details || [];
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Status, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.code = _reader.readInt32();
          break;
        case 2:
          _instance.message = _reader.readString();
          break;
        case 3:
          const messageInitializer3 = new googleProtobuf000.Any();
          _reader.readMessage(
            messageInitializer3,
            googleProtobuf000.Any.deserializeBinaryFromReader
          );
          (_instance.details = _instance.details || []).push(
            messageInitializer3
          );
          break;
        default:
          _reader.skipField();
      }
    }

    Status.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Status, _writer: BinaryWriter) {
    if (_instance.code) {
      _writer.writeInt32(1, _instance.code);
    }
    if (_instance.message) {
      _writer.writeString(2, _instance.message);
    }
    if (_instance.details && _instance.details.length) {
      _writer.writeRepeatedMessage(
        3,
        _instance.details as any,
        googleProtobuf000.Any.serializeBinaryToWriter
      );
    }
  }

  private _code: number;
  private _message: string;
  private _details?: googleProtobuf000.Any[];

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Status to deeply clone from
   */
  constructor(_value?: RecursivePartial<Status.AsObject>) {
    _value = _value || {};
    this.code = _value.code;
    this.message = _value.message;
    this.details = (_value.details || []).map(
      m => new googleProtobuf000.Any(m)
    );
    Status.refineValues(this);
  }
  get code(): number {
    return this._code;
  }
  set code(value: number) {
    this._code = value;
  }
  get message(): string {
    return this._message;
  }
  set message(value: string) {
    this._message = value;
  }
  get details(): googleProtobuf000.Any[] | undefined {
    return this._details;
  }
  set details(value: googleProtobuf000.Any[] | undefined) {
    this._details = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Status.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Status.AsObject {
    return {
      code: this.code,
      message: this.message,
      details: (this.details || []).map(m => m.toObject())
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
  ): Status.AsProtobufJSON {
    return {
      code: this.code,
      message: this.message,
      details: (this.details || []).map(m => m.toProtobufJSON(options))
    };
  }
}
export module Status {
  /**
   * Standard JavaScript object representation for Status
   */
  export interface AsObject {
    code: number;
    message: string;
    details?: googleProtobuf000.Any.AsObject[];
  }

  /**
   * Protobuf JSON representation for Status
   */
  export interface AsProtobufJSON {
    code: number;
    message: string;
    details: googleProtobuf000.Any.AsProtobufJSON[] | null;
  }
}
