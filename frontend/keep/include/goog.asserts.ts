/**
 * @license
 * Copyright The Closure Library Authors.
 * SPDX-License-Identifier: Apache-2.0
 */

import {DebugError} from "./goog.debug.Error";

/**
 * @fileoverview Utilities to check the preconditions, postconditions and
 * invariants runtime.
 *
 * Methods in this package are given special treatment by the compiler
 * for type-inference. For example, <code>goog.asserts.assert(foo)</code>
 * will make the compiler treat <code>foo</code> as non-nullable. Similarly,
 * <code>goog.asserts.assertNumber(foo)</code> informs the compiler about the
 * type of <code>foo</code>. Where applicable, such assertions are preferable to
 * casts by jsdoc with <code>@type</code>.
 *
 * The compiler has an option to disable asserts. So code like:
 * <code>
 * var x = goog.asserts.assert(foo());
 * goog.asserts.assert(bar());
 * </code>
 * will be transformed into:
 * <code>
 * var x = foo();
 * </code>
 * The compiler will leave in foo() (because its return value is used),
 * but it will remove bar() because it assumes it does not have side-effects.
 *
 * Additionally, note the compiler will consider the type to be "tightened" for
 * all statements <em>after</em> the assertion. For example:
 * <code>
 * const /** ?Object &#ast;/ value = foo();
 * goog.asserts.assert(value);
 * // "value" is of type {!Object} at this point.
 * </code>
 */


// NOTE: this needs to be exported directly and referenced via the exports
// object because unit tests stub it out.
/**
 * @define {boolean} Whether to strip out asserts or to leave them in.
 */
const ENABLE_ASSERTS = true; // goog.define('goog.asserts.ENABLE_ASSERTS', goog.DEBUG);


/**
 * Error object for failed assertions.
 * @param {string} messagePattern The pattern that was used to form message.
 * @param {!Array<*>} messageArgs The items to substitute into the pattern.
 * @constructor
 * @extends {DebugError}
 * @final
 */
export class AssertionError extends DebugError {
  public override name = 'AssertionError';

  constructor(public messagePattern: string, messageArgs: any[]) {
    super(subs(messagePattern, messageArgs));
  }
}

/**
 * The default error handler.
 * @param {!AssertionError} e The exception to be handled.
 * @return {void}
 */
export const DEFAULT_ERROR_HANDLER = function(e: any) {
  throw e;
};

/**
 * The handler responsible for throwing or logging assertion errors.
 * @type {function(!AssertionError)}
 */
let errorHandler_ = DEFAULT_ERROR_HANDLER;


/**
 * Does simple python-style string substitution.
 * subs("foo%s hot%s", "bar", "dog") becomes "foobar hotdog".
 * @param {string} pattern The string containing the pattern.
 * @param {!Array<*>} subs The items to substitute into the pattern.
 * @return {string} A copy of `str` in which each occurrence of
 *     `%s` has been replaced an argument from `var_args`.
 */
function subs(pattern: string, subs: any[]): string {
  const splitParts = pattern.split('%s');
  let returnString = '';

  // Replace up to the last split part. We are inserting in the
  // positions between split parts.
  const subLast = splitParts.length - 1;
  for (let i = 0; i < subLast; i++) {
    // keep unsupplied as '%s'
    const sub = (i < subs.length) ? subs[i] : '%s';
    returnString += splitParts[i] + sub;
  }
  return returnString + splitParts[subLast];
}


/**
 * Throws an exception with the given message and "Assertion failed" prefixed
 * onto it.
 * @param {string} defaultMessage The message to use if givenMessage is empty.
 * @param {?Array<*>} defaultArgs The substitution arguments for defaultMessage.
 * @param {string|undefined} givenMessage Message supplied by the caller.
 * @param {!Array<*>} givenArgs The substitution arguments for givenMessage.
 * @throws {AssertionError} When the value is not a number.
 */
function doAssertFailure(defaultMessage: string, defaultArgs: any[]|null, givenMessage: string|undefined, givenArgs: any[]): void {
  let message = 'Assertion failed';
  let args;
  if (givenMessage) {
    message += ': ' + givenMessage;
    args = givenArgs;
  } else if (defaultMessage) {
    message += ': ' + defaultMessage;
    args = defaultArgs;
  }
  // The '' + works around an Opera 10 bug in the unit tests. Without it,
  // a stack trace is added to var message above. With this, a stack trace is
  // not added until this line (it causes the extra garbage to be added after
  // the assertion message instead of in the middle of it).
  const e = new AssertionError('' + message, args || []);
  errorHandler_(e);
}



/**
 * Checks if the condition evaluates to true if ENABLE_ASSERTS is
 * true.
 * @template T
 * @param {T} condition The condition to check.
 * @param {string=} opt_message Error message in case of failure.
 * @param {...*} var_args The items to substitute into the failure message.
 * @return {T} The value of the condition.
 * @throws {AssertionError} When the condition evaluates to false.
 * @closurePrimitive {asserts.truthy}
 */
export function assert<T>(condition: T, opt_message?: string, ...var_args: any[]): T {
  if (ENABLE_ASSERTS && !condition) {
    doAssertFailure(
      '', null, opt_message, Array.prototype.slice.call(arguments, 2));
  }
  return condition;
};
