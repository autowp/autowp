// Touch support detection function adapted (under MIT License)
// from code by Jeffrey Sambells - http://github.com/iamamused/
export function hasTouchSupport() {
  const el = document.createElement('div'),
    events = ['touchstart', 'touchmove', 'touchend'],
    support = {};

  try {
    for (let i = 0; i < events.length; i++) {
      let eventName = events[i];
      eventName = 'on' + eventName;
      let isSupported = eventName in el;
      if (!isSupported) {
        el.setAttribute(eventName, 'return;');
        isSupported = typeof el[eventName] == 'function';
      }
      support[events[i]] = isSupported;
    }
    return support.touchstart && support.touchend && support.touchmove;
  } catch (err) {
    return false;
  }
}
