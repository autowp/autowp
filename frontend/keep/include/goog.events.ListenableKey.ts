/**
 * @license
 * Copyright The Closure Library Authors.
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * @fileoverview An interface that describes a single registered listener.
 */



import {Listenable} from "./goog.events.Listenable";

/**
 * An interface that describes a single registered listener.
 * @interface
 */
export class ListenableKey {
  /**
   * Counter used to create a unique key
   * @type {number}
   * @private
   */
  private static counter_ = 0;

  /**
   * Reserves a key to be used for ListenableKey#key field.
   * @return {number} A number to be used to fill ListenableKey#key
   *     field.
   */
  static reserveKey(): number {
    'use strict';
    return ++ListenableKey.counter_;
  };


  /**
   * The source event target.
   * @type {?Object|?goog.events.Listenable}
   */
  src: Object|Listenable;


  /**
   * The event type the listener is listening to.
   * @type {string}
   */
  type: string;


  /**
   * The listener function.
   * @type {function(?):?|{handleEvent:function(?):?}|null}
   */
  listener: (e: any) => any|{handleEvent:(e: any) => any}|null;


  /**
   * Whether the listener works on capture phase.
   * @type {boolean}
   */
  capture: boolean = false;


  /**
   * The 'this' object for the listener function's scope.
   * @type {?Object|undefined}
   */
  handler: Object|undefined;


  /**
   * A globally unique number to identify the key.
   * @type {number}
   */
  key: number = 0;
};





