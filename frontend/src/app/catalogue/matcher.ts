import {UrlSegment} from '@angular/router';

// eslint-disable-next-line sonarjs/cognitive-complexity
export function cataloguePathMatcher(url: UrlSegment[]) {
  if (url.length <= 0) {
    return null;
  }

  const consumed: UrlSegment[] = [];
  const path: string[] = [];
  let i = 0;
  for (; i < url.length; i++) {
    const segment = url[i].path;

    if (segment === 'pictures') {
      break;
    }
    if (segment === 'gallery') {
      break;
    }
    if (segment === 'exact') {
      break;
    }
    if (segment === 'sport') {
      break;
    }
    if (segment === 'tuning') {
      break;
    }
    if (segment === 'specifications') {
      break;
    }
    consumed.push(url[i]);
    path.push(segment);
  }

  let type = 'default';
  if (i < url.length) {
    const segment = url[i].path;
    switch (segment) {
      case 'sport':
      case 'tuning':
        type = segment;
        consumed.push(url[i]);
        break;
    }
  }

  return {
    consumed,
    posParams: {
      path: new UrlSegment(path.join('/'), {}),
      type: new UrlSegment(type, {}),
    },
  };
}
