/**
 * @license
 * Copyright The Closure Library Authors.
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * @fileoverview Base64 en/decoding. Not much to say here except that we
 * work with decoded values in arrays of bytes. By "byte" I mean a number
 * in [0, 255].
 */

import {contains, isEmptyOrWhitespace} from "./goog.string.internal";
import {assert} from "./goog.asserts";
import {isArrayLike} from "./goog";

/**
 * Default alphabet, shared between alphabets. Only 62 characters.
 * @private {string}
 */
const DEFAULT_ALPHABET_COMMON_ = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' +
  'abcdefghijklmnopqrstuvwxyz' +
  '0123456789';

/**
 * Alphabets for Base64 encoding
 * Alphabets with no padding character are for encoding without padding.
 * About the alphabets, please refer to RFC 4686.
 * https://tools.ietf.org/html/rfc4648
 * @enum {number}
 */
enum Alphabet {
  /** Section 4 "base64". */
  DEFAULT = 0,
  /** Section 4 "base64", omitting padding per Section 3.2. */
  NO_PADDING = 1,
  /** Section 5 "base64url". */
  WEBSAFE = 2,
  /** Like WEBSAFE, but with non-standard '.' padding character. */
  WEBSAFE_DOT_PADDING = 3,
  /** Section 5 "base64url", omitting padding per Section 3.2. */
  WEBSAFE_NO_PADDING = 4,
};


/**
 * Padding chars for Base64 encoding
 * @const {string}
 * @private
 */
const paddingChars_ = '=.';


/**
 * Check if a character is a padding character
 *
 * @param {string} char
 * @return {boolean}
 * @private
 */
function isPadding_(char: string): boolean {
  'use strict';
  return contains(paddingChars_, char);
};


// Static lookup maps, lazily populated by init_()

/**
 * For each `Alphabet`, maps from bytes to characters.
 *
 * @see https://jsperf.com/char-lookups
 * @type {!Object<!goog.crypt.base64.Alphabet, !Array<string>>}
 * @private
 */
let byteToCharMaps_: Record<Alphabet, Array<string>> = {};

/**
 * Maps characters to bytes.
 *
 * This map is used for all alphabets since, across alphabets, common chars
 * always map to the same byte.
 *
 * `null` indicates `init` has not yet been called.
 *
 * @type {?Object<string, number>}
 * @private
 */
let charToByteMap_: Record<string, number> | null = null;

/**
 * Base64-encode an array of bytes.
 *
 * @param {Array<number>|Uint8Array} input An array of bytes (numbers with
 *     value in [0, 255]) to encode.
 * @param {!goog.crypt.base64.Alphabet=} alphabet Base 64 alphabet to
 *     use in encoding. Alphabet.DEFAULT is used by default.
 * @return {string} The base64 encoded string.
 */
export function encodeByteArray(input: Array<number> | Uint8Array, alphabet?: Alphabet): string {
  'use strict';
  // Assert avoids runtime dependency on goog.isArrayLike, which helps reduce
  // size of jscompiler output, and which yields slight performance increase.
  assert(isArrayLike(input), 'encodeByteArray takes an array as a parameter');

  if (alphabet === undefined) {
    alphabet = Alphabet.DEFAULT;
  }
  init_();

  const byteToCharMap = byteToCharMaps_[alphabet];
  const output = new Array(Math.floor(input.length / 3));
  const paddingChar = byteToCharMap[64] || '';

  // Add all blocks for which we have four output bytes.
  let inputIdx = 0;
  let outputIdx = 0;
  for (; inputIdx < input.length - 2; inputIdx += 3) {
    const byte1 = input[inputIdx];
    const byte2 = input[inputIdx + 1];
    const byte3 = input[inputIdx + 2];

    const outChar1 = byteToCharMap[byte1 >> 2];
    const outChar2 = byteToCharMap[((byte1 & 0x03) << 4) | (byte2 >> 4)];
    const outChar3 = byteToCharMap[((byte2 & 0x0F) << 2) | (byte3 >> 6)];
    const outChar4 = byteToCharMap[byte3 & 0x3F];

    output[outputIdx++] = ((('' + outChar1) + outChar2) + outChar3) + outChar4;
  }

  // Add our trailing block, in which case we can skip computations relating to
  // byte3/outByte4.
  let byte2 = 0;
  let outChar3 = paddingChar;
  switch (input.length - inputIdx) {
    // @ts-ignore
    case 2:
      byte2 = input[inputIdx + 1];
      outChar3 = byteToCharMap[(byte2 & 0x0F) << 2] || paddingChar;
    // fall through.
    // @ts-ignore
    case 1:
      const byte1 = input[inputIdx];
      const outChar1 = byteToCharMap[byte1 >> 2];
      const outChar2 = byteToCharMap[((byte1 & 0x03) << 4) | (byte2 >> 4)];

      output[outputIdx] =
        ((('' + outChar1) + outChar2) + outChar3) + paddingChar;
    // fall through.
    // @ts-ignore
    default:
    // We've ended on a block, so we have no more bytes to encode.
  }

  return output.join('');
};


/**
 * Base64-decode a string to a Uint8Array.
 *
 * Note that Uint8Array is not supported on older browsers, e.g. IE < 10.
 * @see http://caniuse.com/uint8array
 *
 * In base-64 decoding, groups of four characters are converted into three
 * bytes.  If the encoder did not apply padding, the input length may not
 * be a multiple of 4.
 *
 * In this case, the last group will have fewer than 4 characters, and
 * padding will be inferred.  If the group has one or two characters, it decodes
 * to one byte.  If the group has three characters, it decodes to two bytes.
 *
 * TODO(sdh): We may want to consider renaming this to `decodeToUint8Array` for
 * consistency with `decodeToText`/`decodeToBinaryString`.
 *
 * @param {string} input Input to decode. Any whitespace is ignored, and the
 *     input maybe encoded with either supported alphabet (or a mix thereof).
 * @return {!Uint8Array} bytes representing the decoded value.
 */
export function decodeStringToUint8Array(input: string): Uint8Array {
  'use strict';
  var len = input.length;
  // Approximate the length of the array needed for output.
  // Our method varies according to the format of the input, which we can
  // consider in three categories:
  //   A) well-formed with proper padding
  //   B) well-formed without any padding
  //   C) not-well-formed, either with extra whitespace in the middle or with
  //      extra padding characters.
  //
  //  In the case of (A), (length * 3 / 4) will result in an integer number of
  //  bytes evenly divisible by 3, and we need only subtract bytes according to
  //  the padding observed.
  //
  //  In the case of (B), (length * 3 / 4) will result in a non-integer number
  //  of bytes, or not evenly divisible by 3. (If the result is evenly divisible
  //  by 3, it's well-formed with the proper amount of padding [0 padding]).
  //  This approximation can become exact by rounding down.
  //
  //  In the case of (C), the only way to get the length is to walk the full
  //  length of the string to consider each character. This is handled by
  //  tracking the number of bytes added to the array and using subarray to
  //  trim the array back down to size.
  var approxByteLength = len * 3 / 4;
  if (approxByteLength % 3) {
    // The string isn't complete, either because it didn't include padding, or
    // because it has extra white space.
    // In either case, we won't generate more bytes than are completely encoded,
    // so rounding down is appropriate to have a buffer at least as large as
    // output.
    approxByteLength = Math.floor(approxByteLength);
  } else if (isPadding_(input[len - 1])) {
    // The string has a round length, and has some padding.
    // Reduce the byte length according to the quantity of padding.
    if (isPadding_(input[len - 2])) {
      approxByteLength -= 2;
    } else {
      approxByteLength -= 1;
    }
  }
  var output = new Uint8Array(approxByteLength);
  var outLen = 0;

  function pushByte(b: number) {
    output[outLen++] = b;
  }

  decodeStringInternal_(input, pushByte);

  // Trim unused trailing bytes if necessary, this only happens if the input
  // included extra whitespace or extra padding that caused our estimate to be
  // too large (this is uncommon).
  //
  // It would be correct to simply always call subarray, but we avoid doing so
  // to avoid potential poor performance from chrome.
  // See https://bugs.chromium.org/p/v8/issues/detail?id=7161
  return outLen !== approxByteLength ? output.subarray(0, outLen) : output;
};


/**
 * @param {string} input Input to decode.
 * @param {function(number):void} pushByte result accumulator.
 * @private
 */
function decodeStringInternal_(input: string, pushByte: (byte: number) => void) {
  'use strict';
  init_();

  var nextCharIndex = 0;

  /**
   * @param {number} default_val Used for end-of-input.
   * @return {number} The next 6-bit value, or the default for end-of-input.
   */
  function getByte(default_val: number) {
    while (nextCharIndex < input.length) {
      var ch = input.charAt(nextCharIndex++);
      var b = charToByteMap_![ch];
      if (b != null) {
        return b;  // Common case: decoded the char.
      }
      if (!isEmptyOrWhitespace(ch)) {
        throw new Error('Unknown base64 encoding at char: ' + ch);
      }
      // We encountered whitespace: loop around to the next input char.
    }
    return default_val;  // No more input remaining.
  }

  while (true) {
    var byte1 = getByte(-1);
    var byte2 = getByte(0);
    var byte3 = getByte(64);
    var byte4 = getByte(64);

    // The common case is that all four bytes are present, so if we have byte4
    // we can skip over the truncated input special case handling.
    if (byte4 === 64) {
      if (byte1 === -1) {
        return;  // Terminal case: no input left to decode.
      }
      // Here we know an intermediate number of bytes are missing.
      // The defaults for byte2, byte3 and byte4 apply the inferred padding
      // rules per the public API documentation. i.e: 1 byte
      // missing should yield 2 bytes of output, but 2 or 3 missing bytes yield
      // a single byte of output. (Recall that 64 corresponds the padding char).
    }

    var outByte1 = (byte1 << 2) | (byte2 >> 4);
    pushByte(outByte1);

    if (byte3 != 64) {
      var outByte2 = ((byte2 << 4) & 0xF0) | (byte3 >> 2);
      pushByte(outByte2);

      if (byte4 != 64) {
        var outByte3 = ((byte3 << 6) & 0xC0) | byte4;
        pushByte(outByte3);
      }
    }
  }
};


/**
 * Lazy static initialization function. Called before
 * accessing any of the static map variables.
 * @private
 */
function init_() {
  'use strict';
  if (charToByteMap_) {
    return;
  }
  charToByteMap_ = {};

  // We want quick mappings back and forth, so we precompute encoding maps.

  /** @type {!Array<string>} */
  var commonChars = DEFAULT_ALPHABET_COMMON_.split('');
  var specialChars = [
    '+/=',  // DEFAULT
    '+/',   // NO_PADDING
    '-_=',  // WEBSAFE
    '-_.',  // WEBSAFE_DOT_PADDING
    '-_',   // WEBSAFE_NO_PADDING
  ];

  for (let i = 0; i < 5; i++) {
    // `i` is each value of the `goog.crypt.base64.Alphabet` enum
    var chars = commonChars.concat(specialChars[i].split(''));

    // Sets byte-to-char map
    // @ts-ignore
    byteToCharMaps_[i] = chars;

    // Sets char-to-byte map
    for (var j = 0; j < chars.length; j++) {
      var char = chars[j];

      var existingByte = charToByteMap_[char];
      if (existingByte === undefined) {
        charToByteMap_[char] = j;
      } else {
        assert(existingByte === j);
      }
    }
  }
}
