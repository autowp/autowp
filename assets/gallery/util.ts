import * as $ from 'jquery';

/**
 * ------------------------------------------------------------------------
 * Private TransitionEnd Helpers
 * ------------------------------------------------------------------------
 */

let transition: any = false;

const MAX_UID = 1000000;
const MILLISECONDS_MULTIPLIER = 1000;

// Shoutout AngusCroll (https://goo.gl/pxwQGp)
function toType(obj: any) {
    return {}.toString
        .call(obj)
        .match(/\s([a-z]+)/i)[1]
        .toLowerCase();
}

function getSpecialTransitionEndEvent(): any {
    return {
        bindType: transition.end,
        delegateType: transition.end,
        handle(event: any) {
            if ($(event.target).is(this)) {
                return event.handleObj.handler.apply(this, arguments); // eslint-disable-line prefer-rest-params
            }
            return undefined; // eslint-disable-line no-undefined
        }
    };
}

function transitionEndTest() {
    return {
        end: 'transitionend'
    };
}

function setTransitionEndSupport() {
    transition = transitionEndTest();

    $.fn.emulateTransitionEnd = function(duration: any) {
        let called = false;

        $(this).one(Util.TRANSITION_END, () => {
            called = true;
        });

        setTimeout(() => {
            if (!called) {
                Util.triggerTransitionEnd(this);
            }
        }, duration);

        return this;
    };

    if (Util.supportsTransitionEnd()) {
        ($ as any).event.special[Util.TRANSITION_END] = getSpecialTransitionEndEvent();
    }
}

/**
 * --------------------------------------------------------------------------
 * Public Util Api
 * --------------------------------------------------------------------------
 */

export class Util {
    static TRANSITION_END: 'bsTransitionEnd';

    static getUID(prefix: any) {
        do {
            // eslint-disable-next-line no-bitwise
            prefix += ~~(Math.random() * MAX_UID); // "~~" acts like a faster Math.floor() here
        } while (document.getElementById(prefix));
        return prefix;
    }

    static getSelectorFromElement(element: any) {
        let selector = element.getAttribute('data-target');
        if (!selector || selector === '#') {
            selector = element.getAttribute('href') || '';
        }

        try {
            const $selector = $(document).find(selector);
            return $selector.length > 0 ? selector : null;
        } catch (err) {
            return null;
        }
    }

    static getTransitionDurationFromElement(element: any) {
        if (!element) {
            return 0;
        }

        // Get transition-duration of the element
        let transitionDuration = $(element).css('transition-duration');
        const floatTransitionDuration = parseFloat(transitionDuration);

        // Return 0 if element or transition duration is not found
        if (!floatTransitionDuration) {
            return 0;
        }

        // If multiple durations are defined, take the first
        transitionDuration = transitionDuration.split(',')[0];

        return parseFloat(transitionDuration) * MILLISECONDS_MULTIPLIER;
    }

    static reflow(element: any) {
        return element.offsetHeight;
    }

    static triggerTransitionEnd(element: any) {
        $(element).trigger(transition.end);
    }

    static supportsTransitionEnd() {
        return Boolean(transition);
    }

    static isElement(obj: any) {
        return (obj[0] || obj).nodeType;
    }

    static typeCheckConfig(componentName: any, config: any, configTypes: any) {
        for (const property in configTypes) {
            if (Object.prototype.hasOwnProperty.call(configTypes, property)) {
                const expectedTypes = configTypes[property];
                const value = config[property];
                const valueType =
                    value && Util.isElement(value) ? 'element' : toType(value);

                if (!new RegExp(expectedTypes).test(valueType)) {
                    throw new Error(
                        `${componentName.toUpperCase()}: ` +
                            `Option "${property}" provided type "${valueType}" ` +
                            `but expected type "${expectedTypes}".`
                    );
                }
            }
        }
    }
}

setTransitionEndSupport();
