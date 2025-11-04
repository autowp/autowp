/**
 * @license
 * Copyright The Closure Library Authors.
 * SPDX-License-Identifier: Apache-2.0
 */



/**
 * A templated class that is used when registering for events. Typical usage:
 *
 *    /** @type {goog.events.EventId<MyEventObj>} *\
 *    var myEventId = new goog.events.EventId(
 *        goog.events.getUniqueId(('someEvent'));
 *
 *    // No need to cast or declare here since the compiler knows the
 *    // correct type of 'evt' (MyEventObj).
 *    something.listen(myEventId, function(evt) {});
 *
 * @param {string} eventId
 * @template T
 * @constructor
 * @struct
 * @final
 */
export class EventId<T> {
  public id: string;

  constructor(eventId: string) {
    'use strict';
    /** @const */ this.id = eventId;
  };

  /**
   * @override
   * @return {string}
   */
  toString() {
    'use strict';
    return this.id;
  };
}

