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
 * Message implementation for google.type.Date
 */
export class Date implements GrpcMessage {
  static id = 'google.type.Date';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new Date();
    Date.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: Date) {
    _instance.year = _instance.year || 0;
    _instance.month = _instance.month || 0;
    _instance.day = _instance.day || 0;
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: Date, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.year = _reader.readInt32();
          break;
        case 2:
          _instance.month = _reader.readInt32();
          break;
        case 3:
          _instance.day = _reader.readInt32();
          break;
        default:
          _reader.skipField();
      }
    }

    Date.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: Date, _writer: BinaryWriter) {
    if (_instance.year) {
      _writer.writeInt32(1, _instance.year);
    }
    if (_instance.month) {
      _writer.writeInt32(2, _instance.month);
    }
    if (_instance.day) {
      _writer.writeInt32(3, _instance.day);
    }
  }

  private _year: number;
  private _month: number;
  private _day: number;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of Date to deeply clone from
   */
  constructor(_value?: RecursivePartial<Date.AsObject>) {
    _value = _value || {};
    this.year = _value.year;
    this.month = _value.month;
    this.day = _value.day;
    Date.refineValues(this);
  }
  get year(): number {
    return this._year;
  }
  set year(value: number) {
    this._year = value;
  }
  get month(): number {
    return this._month;
  }
  set month(value: number) {
    this._month = value;
  }
  get day(): number {
    return this._day;
  }
  set day(value: number) {
    this._day = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    Date.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): Date.AsObject {
    return {
      year: this.year,
      month: this.month,
      day: this.day
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
  ): Date.AsProtobufJSON {
    return {
      year: this.year,
      month: this.month,
      day: this.day
    };
  }
}
export module Date {
  /**
   * Standard JavaScript object representation for Date
   */
  export interface AsObject {
    year: number;
    month: number;
    day: number;
  }

  /**
   * Protobuf JSON representation for Date
   */
  export interface AsProtobufJSON {
    year: number;
    month: number;
    day: number;
  }
}
