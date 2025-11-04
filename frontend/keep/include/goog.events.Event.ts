/**
 * @license
 * Copyright The Closure Library Authors.
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * @fileoverview A base class for event objects.
 */


import {EventId} from "./goog.events.EventId";

/**
 * A base class for event objects, so that they can support preventDefault and
 * stopPropagation.
 *
 * @param {string|!goog.events.EventId} type Event Type.
 * @param {Object=} opt_target Reference to the object that is the target of
 *     this event. It has to implement the `EventTarget` interface
 *     declared at {@link http://developer.mozilla.org/en/DOM/EventTarget}.
 * @constructor
 */
export class Event {
  /**
   * Event type.
   * @type {string}
   */
  type: string;

  /**
   * TODO(tbreisacher): The type should probably be
   * EventTarget|goog.events.EventTarget.
   *
   * Target of the event.
   * @type {Object|undefined}
   */
  target: Object | undefined;

  /**
   * Object that had the listener attached.
   * @type {Object|undefined}
   */
  currentTarget: Object | undefined;

  /**
   * Whether to cancel the event in internal capture/bubble processing for IE.
   * @type {boolean}
   * @private
   */
  private propagationStopped_: boolean;

  /**
   * Whether the default action has been prevented.
   * This is a property to match the W3C specification at
   * {@link http://www.w3.org/TR/DOM-Level-3-Events/
   * #events-event-type-defaultPrevented}.
   * Must be treated as read-only outside the class.
   * @type {boolean}
   */
  defaultPrevented: boolean = false;

  constructor(type: string | EventId, opt_target: Object) {
    'use strict';
    this.type = type instanceof EventId ? String(type) : type;
    this.target = opt_target;
    this.currentTarget = this.target;
    this.propagationStopped_ = false;
    this.defaultPrevented = false;
  };

  /**
   * @return {boolean} true iff internal propagation has been stopped.
   */
  hasPropagationStopped(): boolean {
    'use strict';
    return this.propagationStopped_;
  };

  /**
   * Stops event propagation.
   * @return {void}
   */
  stopPropagation(): void {
    'use strict';
    this.propagationStopped_ = true;
  };


  /**
   * Prevents the default action, for example a link redirecting to a url.
   * @return {void}
   */
  preventDefault(): void {
    'use strict';
    this.defaultPrevented = true;
  };


  /**
   * Stops the propagation of the event. It is equivalent to
   * `e.stopPropagation()`, but can be used as the callback argument of
   * {@link goog.events.listen} without declaring another function.
   * @param {!goog.events.Event} e An event.
   * @return {void}
   */
  // stopPropagation(e: Event): void {
  //   'use strict';
  //   e.stopPropagation();
  // };


  /**
   * Prevents the default action. It is equivalent to
   * `e.preventDefault()`, but can be used as the callback argument of
   * {@link goog.events.listen} without declaring another function.
   * @param {!goog.events.Event} e An event.
   * @return {void}
   */
  // preventDefault(e: Event): void {
  //   'use strict';
  //   e.preventDefault();
  // };
}
