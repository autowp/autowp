// Touch support detection function adapted (under MIT License)
// from code by Jeffrey Sambells - http://github.com/iamamused/
export function hasTouchSupport(): boolean {
  const el = document.createElement('div');
  const events = ['touchstart', 'touchmove', 'touchend'];
  const support: Record<string, boolean> = {};

  try {
    for (const eventName of events) {
      const onEventName = 'on' + eventName;
      let isSupported = onEventName in el;
      if (!isSupported) {
        el.setAttribute(onEventName, 'return;');
        isSupported = typeof (el as unknown as Record<string, unknown>)[onEventName] === 'function';
      }
      support[eventName] = isSupported;
    }
    return support['touchstart'] && support['touchend'] && support['touchmove'];
  } catch {
    return false;
  }
}
