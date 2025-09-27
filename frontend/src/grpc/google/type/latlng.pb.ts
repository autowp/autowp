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
 * Message implementation for google.type.LatLng
 */
export class LatLng implements GrpcMessage {
  static id = 'google.type.LatLng';

  /**
   * Deserialize binary data to message
   * @param instance message instance
   */
  static deserializeBinary(bytes: ByteSource) {
    const instance = new LatLng();
    LatLng.deserializeBinaryFromReader(instance, new BinaryReader(bytes));
    return instance;
  }

  /**
   * Check all the properties and set default protobuf values if necessary
   * @param _instance message instance
   */
  static refineValues(_instance: LatLng) {
    _instance.latitude = _instance.latitude || 0;
    _instance.longitude = _instance.longitude || 0;
  }

  /**
   * Deserializes / reads binary message into message instance using provided binary reader
   * @param _instance message instance
   * @param _reader binary reader instance
   */
  static deserializeBinaryFromReader(_instance: LatLng, _reader: BinaryReader) {
    while (_reader.nextField()) {
      if (_reader.isEndGroup()) break;

      switch (_reader.getFieldNumber()) {
        case 1:
          _instance.latitude = _reader.readDouble();
          break;
        case 2:
          _instance.longitude = _reader.readDouble();
          break;
        default:
          _reader.skipField();
      }
    }

    LatLng.refineValues(_instance);
  }

  /**
   * Serializes a message to binary format using provided binary reader
   * @param _instance message instance
   * @param _writer binary writer instance
   */
  static serializeBinaryToWriter(_instance: LatLng, _writer: BinaryWriter) {
    if (_instance.latitude) {
      _writer.writeDouble(1, _instance.latitude);
    }
    if (_instance.longitude) {
      _writer.writeDouble(2, _instance.longitude);
    }
  }

  private _latitude: number;
  private _longitude: number;

  /**
   * Message constructor. Initializes the properties and applies default Protobuf values if necessary
   * @param _value initial values object or instance of LatLng to deeply clone from
   */
  constructor(_value?: RecursivePartial<LatLng.AsObject>) {
    _value = _value || {};
    this.latitude = _value.latitude;
    this.longitude = _value.longitude;
    LatLng.refineValues(this);
  }
  get latitude(): number {
    return this._latitude;
  }
  set latitude(value: number) {
    this._latitude = value;
  }
  get longitude(): number {
    return this._longitude;
  }
  set longitude(value: number) {
    this._longitude = value;
  }

  /**
   * Serialize message to binary data
   * @param instance message instance
   */
  serializeBinary() {
    const writer = new BinaryWriter();
    LatLng.serializeBinaryToWriter(this, writer);
    return writer.getResultBuffer();
  }

  /**
   * Cast message to standard JavaScript object (all non-primitive values are deeply cloned)
   */
  toObject(): LatLng.AsObject {
    return {
      latitude: this.latitude,
      longitude: this.longitude
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
  ): LatLng.AsProtobufJSON {
    return {
      latitude: this.latitude,
      longitude: this.longitude
    };
  }
}
export module LatLng {
  /**
   * Standard JavaScript object representation for LatLng
   */
  export interface AsObject {
    latitude: number;
    longitude: number;
  }

  /**
   * Protobuf JSON representation for LatLng
   */
  export interface AsProtobufJSON {
    latitude: number;
    longitude: number;
  }
}
