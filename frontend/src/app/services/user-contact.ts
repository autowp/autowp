import {UserContactPlatform} from '@grpc/spec.pb';

/**
 * Client-side mirror of the server's usercontacts.Platforms registry. Used for instant feedback
 * in the profile editor and to render the public icon row. The Go parser stays authoritative on
 * save.
 */
export interface SocialPlatform {
  bareRe: RegExp;
  bareTransform?: (handle: string) => string;
  /** a bootstrap-icons class, or a custom mask icon under src/assets/social/svg/<icon>.svg */
  bi?: string;
  hosts: string[];
  id: UserContactPlatform;
  keepCase?: boolean;
  name: string;
  pathRes: RegExp[];
  reserved?: Set<string>;
  stripAt?: boolean;
  svg?: string;
  /** %s is the stored handle */
  urlTemplate: string;
}

const P = UserContactPlatform;

export const SOCIAL_PLATFORMS: readonly SocialPlatform[] = [
  {
    id: P.USER_CONTACT_PLATFORM_DRIVE2,
    name: 'drive2.ru',
    svg: 'drive2',
    urlTemplate: 'https://www.drive2.ru/users/%s/',
    hosts: ['drive2.ru'],
    bareRe: /^[A-Za-z0-9](?:[A-Za-z0-9_-]{0,28}[A-Za-z0-9])?$/,
    pathRes: [/^\/users\/([A-Za-z0-9][A-Za-z0-9_-]{0,29})\/?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_DZEN,
    name: 'Дзен',
    svg: 'dzen',
    urlTemplate: 'https://dzen.ru/%s',
    hosts: ['dzen.ru', 'zen.yandex.ru'],
    bareRe: /^[A-Za-z0-9._-]{2,50}$/,
    pathRes: [/^\/(id\/[0-9a-f]{20,40})\/?$/, /^\/([A-Za-z0-9._-]{2,50})\/?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_YOUTUBE,
    name: 'YouTube',
    bi: 'bi-youtube',
    urlTemplate: 'https://www.youtube.com/%s',
    hosts: ['youtube.com'],
    bareRe: /^(@[A-Za-z0-9._-]{3,30}|channel\/UC[A-Za-z0-9_-]{22})$/,
    pathRes: [
      /^\/(@[A-Za-z0-9._-]{3,30})\/?$/,
      /^\/(channel\/UC[A-Za-z0-9_-]{22})\/?$/,
      /^\/(c\/[A-Za-z0-9._-]{1,60})\/?$/,
      /^\/(user\/[A-Za-z0-9._-]{1,60})\/?$/,
    ],
    keepCase: true,
    bareTransform: (h) => (h.startsWith('UC') ? 'channel/' + h : h),
  },
  {
    id: P.USER_CONTACT_PLATFORM_TELEGRAM,
    name: 'Telegram',
    bi: 'bi-telegram',
    urlTemplate: 'https://t.me/%s',
    hosts: ['t.me', 'telegram.me', 'telegram.dog'],
    bareRe: /^[A-Za-z]\w{3,31}$/,
    pathRes: [/^\/([A-Za-z]\w{3,31})\/?$/],
    stripAt: true,
  },
  {
    id: P.USER_CONTACT_PLATFORM_X,
    name: 'X',
    bi: 'bi-twitter-x',
    urlTemplate: 'https://x.com/%s',
    hosts: ['x.com', 'twitter.com'],
    bareRe: /^\w{1,15}$/,
    pathRes: [/^\/(\w{1,15})(?:\/.*)?$/],
    stripAt: true,
    reserved: new Set([
      'about',
      'explore',
      'hashtag',
      'home',
      'i',
      'intent',
      'login',
      'messages',
      'notifications',
      'privacy',
      'search',
      'settings',
      'share',
      'tos',
    ]),
  },
  {
    id: P.USER_CONTACT_PLATFORM_TIKTOK,
    name: 'TikTok',
    bi: 'bi-tiktok',
    urlTemplate: 'https://www.tiktok.com/@%s',
    hosts: ['tiktok.com'],
    bareRe: /^[A-Za-z0-9._]{2,24}$/,
    pathRes: [/^\/@([A-Za-z0-9._]{2,24})\/?$/],
    stripAt: true,
  },
  {
    id: P.USER_CONTACT_PLATFORM_REDDIT,
    name: 'Reddit',
    bi: 'bi-reddit',
    urlTemplate: 'https://www.reddit.com/user/%s/',
    hosts: ['reddit.com'],
    bareRe: /^[A-Za-z0-9_-]{3,20}$/,
    pathRes: [/^\/(?:user|u)\/([A-Za-z0-9_-]{3,20})\/?$/],
    stripAt: true,
  },
  {
    id: P.USER_CONTACT_PLATFORM_FLICKR,
    name: 'Flickr',
    svg: 'flickr',
    urlTemplate: 'https://www.flickr.com/photos/%s/',
    hosts: ['flickr.com'],
    bareRe: /^[A-Za-z0-9_@-]{2,50}$/,
    pathRes: [/^\/(?:photos|people)\/([A-Za-z0-9_@-]{2,50})\/?$/],
    keepCase: true,
  },
  {
    id: P.USER_CONTACT_PLATFORM_500PX,
    name: '500px',
    svg: '500px',
    urlTemplate: 'https://500px.com/p/%s',
    hosts: ['500px.com'],
    bareRe: /^[A-Za-z0-9_-]{1,40}$/,
    pathRes: [/^\/(?:p\/)?([A-Za-z0-9_-]{1,40})\/?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_BEHANCE,
    name: 'Behance',
    bi: 'bi-behance',
    urlTemplate: 'https://www.behance.net/%s',
    hosts: ['behance.net'],
    bareRe: /^[A-Za-z0-9_-]{1,40}$/,
    pathRes: [/^\/([A-Za-z0-9_-]{1,40})\/?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_VSCO,
    name: 'VSCO',
    svg: 'vsco',
    urlTemplate: 'https://vsco.co/%s',
    hosts: ['vsco.co'],
    bareRe: /^[A-Za-z0-9_-]{1,40}$/,
    pathRes: [/^\/([A-Za-z0-9_-]{1,40})(?:\/.*)?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_ARTSTATION,
    name: 'ArtStation',
    svg: 'artstation',
    urlTemplate: 'https://www.artstation.com/%s',
    hosts: ['artstation.com'],
    bareRe: /^[A-Za-z0-9_-]{1,40}$/,
    pathRes: [/^\/([A-Za-z0-9_-]{1,40})\/?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_DEVIANTART,
    name: 'DeviantArt',
    svg: 'deviantart',
    urlTemplate: 'https://www.deviantart.com/%s',
    hosts: ['deviantart.com'],
    bareRe: /^[A-Za-z0-9_-]{2,40}$/,
    pathRes: [/^\/([A-Za-z0-9_-]{2,40})(?:\/.*)?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_LINKEDIN,
    name: 'LinkedIn',
    bi: 'bi-linkedin',
    urlTemplate: 'https://www.linkedin.com/in/%s/',
    hosts: ['linkedin.com'],
    bareRe: /^[A-Za-z0-9-]{3,100}$/,
    pathRes: [/^\/in\/([A-Za-z0-9-]{3,100})\/?$/],
  },
  {
    id: P.USER_CONTACT_PLATFORM_GITHUB,
    name: 'GitHub',
    bi: 'bi-github',
    urlTemplate: 'https://github.com/%s',
    hosts: ['github.com'],
    bareRe: /^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$/,
    pathRes: [/^\/([A-Za-z0-9][A-Za-z0-9-]{0,38})\/?$/],
    reserved: new Set([
      'about',
      'collections',
      'contact',
      'enterprise',
      'explore',
      'features',
      'join',
      'login',
      'marketplace',
      'new',
      'notifications',
      'orgs',
      'pricing',
      'security',
      'settings',
      'sponsors',
      'team',
      'topics',
      'trending',
    ]),
  },
  {
    id: P.USER_CONTACT_PLATFORM_VK,
    name: 'VK',
    svg: 'vk',
    urlTemplate: 'https://vk.com/%s',
    hosts: ['vk.com', 'vk.ru'],
    bareRe: /^(id\d+|[A-Za-z0-9_.]{2,32})$/,
    pathRes: [/^\/(id\d+|[A-Za-z0-9_.]{2,32})\/?$/],
  },
];

const BY_ID = new Map<UserContactPlatform, SocialPlatform>(SOCIAL_PLATFORMS.map((p) => [p.id, p]));

export function socialPlatform(id: UserContactPlatform): SocialPlatform | undefined {
  return BY_ID.get(id);
}

export function socialContactUrl(id: UserContactPlatform, username: string): string {
  const platform = BY_ID.get(id);
  return platform ? platform.urlTemplate.replace('%s', username) : '';
}

const HOST_PREFIXES = ['www.', 'm.', 'mobile.', 'old.', 'np.', 'de.', 'uk.', 'fr.'];
const HOST_HEAD = /^[A-Za-z0-9.-]+\.[A-Za-z]{2,}(\/|$|\?|#)/;

function asUrl(raw: string): null | URL {
  let candidate = raw;
  if (!candidate.includes('://')) {
    if (candidate.startsWith('//')) {
      candidate = 'https:' + candidate;
    } else if (candidate.startsWith('www.') || HOST_HEAD.test(candidate)) {
      candidate = 'https://' + candidate;
    } else {
      return null;
    }
  }
  try {
    const url = new URL(candidate);
    return url.hostname ? url : null;
  } catch {
    return null;
  }
}

function stripHostPrefix(host: string): string {
  for (const prefix of HOST_PREFIXES) {
    if (host.startsWith(prefix)) {
      return host.slice(prefix.length);
    }
  }
  return host;
}

export type ParseContactResult = {error: 'bad-format' | 'not-a-profile' | 'wrong-platform'} | {username: string};

function extractFromUrl(platform: SocialPlatform, url: URL): ParseContactResult {
  const host = stripHostPrefix(url.hostname.toLowerCase());
  if (!platform.hosts.includes(host)) {
    return {error: 'wrong-platform'};
  }
  const match = platform.pathRes.map((re) => re.exec(url.pathname)).find((m) => m !== null);

  return match ? {username: match[1]} : {error: 'not-a-profile'};
}

function extractFromHandle(platform: SocialPlatform, trimmed: string): ParseContactResult {
  let handle = trimmed;
  if (platform.stripAt && handle.startsWith('@')) {
    handle = handle.slice(1);
  }
  if (platform.bareTransform) {
    handle = platform.bareTransform(handle);
  }

  return platform.bareRe.test(handle) ? {username: handle} : {error: 'bad-format'};
}

/** Mirror of usercontacts.Parse for one platform. */
export function parseContact(id: UserContactPlatform, raw: string): ParseContactResult {
  const platform = BY_ID.get(id);
  if (!platform) {
    return {error: 'bad-format'};
  }

  const trimmed = raw.trim();
  if (!trimmed) {
    return {username: ''};
  }

  const url = asUrl(trimmed);
  const extracted = url ? extractFromUrl(platform, url) : extractFromHandle(platform, trimmed);
  if ('error' in extracted) {
    return extracted;
  }

  let {username} = extracted;
  if (platform.reserved?.has(username.toLowerCase())) {
    return {error: 'not-a-profile'};
  }
  if (!platform.keepCase) {
    username = username.toLowerCase();
  }

  return username.length > 64 ? {error: 'bad-format'} : {username};
}

/** URL-only platform detection for the "just paste a link" flow. */
export function detectContact(raw: string): null | {platform: UserContactPlatform; username: string} {
  if (!asUrl(raw.trim())) {
    return null;
  }
  for (const platform of SOCIAL_PLATFORMS) {
    const result = parseContact(platform.id, raw);
    if ('username' in result && result.username) {
      return {platform: platform.id, username: result.username};
    }
  }
  return null;
}
