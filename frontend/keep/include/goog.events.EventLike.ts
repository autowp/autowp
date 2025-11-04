/**
 * @license
 * Copyright The Closure Library Authors.
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * @fileoverview A typedef for event like objects that are dispatchable via the
 * goog.events.dispatchEvent function.
 */

import {EventId} from "./goog.events.EventId";
import {Event} from "./goog.events.Event";

/**
 * A typedef for event like objects that are dispatchable via the
 * goog.events.dispatchEvent function. strings are treated as the type for a
 * goog.events.Event. Objects are treated as an extension of a new
 * goog.events.Event with the type property of the object being used as the type
 * of the Event.
 * @typedef {string|Object|goog.events.Event|goog.events.EventId}
 */
export type EventLike = string|Object|Event|EventId;
