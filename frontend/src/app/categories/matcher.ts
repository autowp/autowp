import {UrlSegment} from '@angular/router';

export function categoriesPathMatcher(url: UrlSegment[]) {
  if (url.length <= 0) {
    return null;
  }

  const category = url[0];
  const consumed: UrlSegment[] = [category];
  const path: string[] = [];
  for (const segment of url.slice(1)) {
    if (segment.path === 'pictures') {
      break;
    }
    if (segment.path === 'gallery') {
      break;
    }
    consumed.push(segment);
    path.push(segment.path);
  }

  return {
    consumed,
    posParams: {
      category,
      path: new UrlSegment(path.join('/'), {}),
    },
  };
}
