// Package usercontacts is the server-side registry of external platforms a user may link a
// profile on, plus the parser that turns whatever the user types (a URL or a bare handle) into
// a normalised, stored handle. It is the source of truth; the Angular client keeps a matching
// SOCIAL_PLATFORMS registry for instant feedback only.
package usercontacts

import (
	"errors"
	"net/url"
	"regexp"
	"strings"

	"github.com/autowp/goautowp/schema"
)

// Parse errors. They are stable identifiers, not user-facing text; the caller maps them to a
// localised field-violation description.
var (
	ErrUnknownPlatform = errors.New("unknown platform")
	ErrEmpty           = errors.New("empty")
	ErrBadFormat       = errors.New("bad format")
	ErrTooLong         = errors.New("too long")
	ErrWrongPlatform   = errors.New("url belongs to a different platform")
	ErrNotAProfile     = errors.New("url is not a profile link")
)

// Platform describes one entry of the registry.
type Platform struct {
	ID   schema.UserContactPlatform
	Name string

	// hosts accepted in a URL, after a leading www./m./mobile./old./np. is stripped.
	hosts []string
	// bareRe is what a bare handle must match, after a leading '@' is stripped when stripAt.
	bareRe *regexp.Regexp
	// pathRes are tried in order against the URL path; submatch 1 is the raw handle/segment.
	pathRes []*regexp.Regexp
	// storedRe is what the final stored value must match, whichever way it was obtained.
	storedRe *regexp.Regexp
	// urlTemplate builds the public link; one %s is the stored value.
	urlTemplate string

	stripAt  bool
	keepCase bool
	// reserved handles that the broad path patterns would otherwise capture from a
	// non-profile URL (x.com/i/…, github.com/features, …). Checked case-insensitively.
	reserved map[string]bool
	// bareTransform optionally rewrites a bare handle before validation (YouTube: UC… → channel/UC…).
	bareTransform func(string) string
}

func set(values ...string) map[string]bool {
	out := make(map[string]bool, len(values))
	for _, value := range values {
		out[value] = true
	}

	return out
}

// CanonicalURL returns the public profile link for a stored username.
func (p Platform) CanonicalURL(username string) string {
	return strings.Replace(p.urlTemplate, "%s", username, 1)
}

func re(pattern string) *regexp.Regexp { return regexp.MustCompile(pattern) }

// youTubeBareTransform rewrites a bare channel id (UC…) into the path form the URL template and
// storedRe expect; an @handle is left untouched.
func youTubeBareTransform(handle string) string {
	if strings.HasPrefix(handle, "UC") {
		return "channel/" + handle
	}

	return handle
}

// Platforms is the registry, keyed by platform id.
var Platforms = map[schema.UserContactPlatform]Platform{
	schema.UserContactPlatformDrive2: {
		ID: schema.UserContactPlatformDrive2, Name: "drive2.ru",
		hosts:       []string{"drive2.ru"},
		bareRe:      re(`^[A-Za-z0-9](?:[A-Za-z0-9_-]{0,28}[A-Za-z0-9])?$`),
		pathRes:     []*regexp.Regexp{re(`^/users/([A-Za-z0-9][A-Za-z0-9_-]{0,29})/?$`)},
		storedRe:    re(`^[A-Za-z0-9][A-Za-z0-9_-]{0,29}$`),
		urlTemplate: "https://www.drive2.ru/users/%s/",
	},
	schema.UserContactPlatformDzen: {
		ID: schema.UserContactPlatformDzen, Name: "Дзен",
		hosts:       []string{"dzen.ru", "zen.yandex.ru"},
		bareRe:      re(`^[A-Za-z0-9._-]{2,50}$`),
		pathRes:     []*regexp.Regexp{re(`^/(id/[0-9a-f]{20,40})/?$`), re(`^/([A-Za-z0-9._-]{2,50})/?$`)},
		storedRe:    re(`^(id/[0-9a-f]{20,40}|[A-Za-z0-9._-]{2,50})$`),
		urlTemplate: "https://dzen.ru/%s",
	},
	schema.UserContactPlatformYouTube: {
		ID: schema.UserContactPlatformYouTube, Name: "YouTube",
		hosts:  []string{"youtube.com"},
		bareRe: re(`^(@[A-Za-z0-9._-]{3,30}|channel/UC[A-Za-z0-9_-]{22})$`),
		pathRes: []*regexp.Regexp{
			re(`^/(@[A-Za-z0-9._-]{3,30})/?$`),
			re(`^/(channel/UC[A-Za-z0-9_-]{22})/?$`),
			re(`^/(c/[A-Za-z0-9._-]{1,60})/?$`),
			re(`^/(user/[A-Za-z0-9._-]{1,60})/?$`),
		},
		storedRe: re(
			`^(@[A-Za-z0-9._-]{3,30}` +
				`|channel/UC[A-Za-z0-9_-]{22}` +
				`|c/[A-Za-z0-9._-]{1,60}` +
				`|user/[A-Za-z0-9._-]{1,60})$`,
		),
		urlTemplate:   "https://www.youtube.com/%s",
		keepCase:      true, // @handle routing is case-insensitive but channel ids are not
		bareTransform: youTubeBareTransform,
	},
	schema.UserContactPlatformTelegram: {
		ID: schema.UserContactPlatformTelegram, Name: "Telegram",
		hosts:       []string{"t.me", "telegram.me", "telegram.dog"},
		bareRe:      re(`^[A-Za-z][A-Za-z0-9_]{3,31}$`),
		pathRes:     []*regexp.Regexp{re(`^/([A-Za-z][A-Za-z0-9_]{3,31})/?$`)},
		storedRe:    re(`^[A-Za-z][A-Za-z0-9_]{3,31}$`),
		urlTemplate: "https://t.me/%s",
		stripAt:     true,
	},
	schema.UserContactPlatformX: {
		ID: schema.UserContactPlatformX, Name: "X",
		hosts:       []string{"x.com", "twitter.com"},
		bareRe:      re(`^[A-Za-z0-9_]{1,15}$`),
		pathRes:     []*regexp.Regexp{re(`^/([A-Za-z0-9_]{1,15})(?:/.*)?$`)},
		storedRe:    re(`^[A-Za-z0-9_]{1,15}$`),
		urlTemplate: "https://x.com/%s",
		stripAt:     true,
		reserved: set(
			"i", "home", "explore", "notifications", "messages", "settings",
			"search", "intent", "hashtag", "login", "share", "about", "tos", "privacy",
		),
	},
	schema.UserContactPlatformTikTok: {
		ID: schema.UserContactPlatformTikTok, Name: "TikTok",
		hosts:       []string{"tiktok.com"},
		bareRe:      re(`^[A-Za-z0-9._]{2,24}$`),
		pathRes:     []*regexp.Regexp{re(`^/@([A-Za-z0-9._]{2,24})/?$`)},
		storedRe:    re(`^[A-Za-z0-9._]{2,24}$`),
		urlTemplate: "https://www.tiktok.com/@%s",
		stripAt:     true,
	},
	schema.UserContactPlatformReddit: {
		ID: schema.UserContactPlatformReddit, Name: "Reddit",
		hosts:       []string{"reddit.com"},
		bareRe:      re(`^[A-Za-z0-9_-]{3,20}$`),
		pathRes:     []*regexp.Regexp{re(`^/(?:user|u)/([A-Za-z0-9_-]{3,20})/?$`)},
		storedRe:    re(`^[A-Za-z0-9_-]{3,20}$`),
		urlTemplate: "https://www.reddit.com/user/%s/",
		stripAt:     true,
	},
	schema.UserContactPlatformFlickr: {
		ID: schema.UserContactPlatformFlickr, Name: "Flickr",
		hosts:       []string{"flickr.com"},
		bareRe:      re(`^[A-Za-z0-9_@-]{2,50}$`),
		pathRes:     []*regexp.Regexp{re(`^/(?:photos|people)/([A-Za-z0-9_@-]{2,50})/?$`)},
		storedRe:    re(`^[A-Za-z0-9_@-]{2,50}$`),
		urlTemplate: "https://www.flickr.com/photos/%s/",
		keepCase:    true,
	},
	schema.UserContactPlatform500px: {
		ID: schema.UserContactPlatform500px, Name: "500px",
		hosts:       []string{"500px.com"},
		bareRe:      re(`^[A-Za-z0-9_-]{1,40}$`),
		pathRes:     []*regexp.Regexp{re(`^/(?:p/)?([A-Za-z0-9_-]{1,40})/?$`)},
		storedRe:    re(`^[A-Za-z0-9_-]{1,40}$`),
		urlTemplate: "https://500px.com/p/%s",
	},
	schema.UserContactPlatformBehance: {
		ID: schema.UserContactPlatformBehance, Name: "Behance",
		hosts:       []string{"behance.net"},
		bareRe:      re(`^[A-Za-z0-9_-]{1,40}$`),
		pathRes:     []*regexp.Regexp{re(`^/([A-Za-z0-9_-]{1,40})/?$`)},
		storedRe:    re(`^[A-Za-z0-9_-]{1,40}$`),
		urlTemplate: "https://www.behance.net/%s",
	},
	schema.UserContactPlatformVSCO: {
		ID: schema.UserContactPlatformVSCO, Name: "VSCO",
		hosts:       []string{"vsco.co"},
		bareRe:      re(`^[A-Za-z0-9_-]{1,40}$`),
		pathRes:     []*regexp.Regexp{re(`^/([A-Za-z0-9_-]{1,40})(?:/.*)?$`)},
		storedRe:    re(`^[A-Za-z0-9_-]{1,40}$`),
		urlTemplate: "https://vsco.co/%s",
	},
	schema.UserContactPlatformArtStation: {
		ID: schema.UserContactPlatformArtStation, Name: "ArtStation",
		hosts:       []string{"artstation.com"},
		bareRe:      re(`^[A-Za-z0-9_-]{1,40}$`),
		pathRes:     []*regexp.Regexp{re(`^/([A-Za-z0-9_-]{1,40})/?$`)},
		storedRe:    re(`^[A-Za-z0-9_-]{1,40}$`),
		urlTemplate: "https://www.artstation.com/%s",
	},
	schema.UserContactPlatformDeviantArt: {
		ID: schema.UserContactPlatformDeviantArt, Name: "DeviantArt",
		hosts:       []string{"deviantart.com"},
		bareRe:      re(`^[A-Za-z0-9_-]{2,40}$`),
		pathRes:     []*regexp.Regexp{re(`^/([A-Za-z0-9_-]{2,40})(?:/.*)?$`)},
		storedRe:    re(`^[A-Za-z0-9_-]{2,40}$`),
		urlTemplate: "https://www.deviantart.com/%s",
	},
	schema.UserContactPlatformLinkedIn: {
		ID: schema.UserContactPlatformLinkedIn, Name: "LinkedIn",
		hosts:       []string{"linkedin.com"},
		bareRe:      re(`^[A-Za-z0-9-]{3,100}$`),
		pathRes:     []*regexp.Regexp{re(`^/in/([A-Za-z0-9-]{3,100})/?$`)},
		storedRe:    re(`^[A-Za-z0-9-]{3,100}$`),
		urlTemplate: "https://www.linkedin.com/in/%s/",
	},
	schema.UserContactPlatformGitHub: {
		ID: schema.UserContactPlatformGitHub, Name: "GitHub",
		hosts:       []string{"github.com"},
		bareRe:      re(`^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$`),
		pathRes:     []*regexp.Regexp{re(`^/([A-Za-z0-9][A-Za-z0-9-]{0,38})/?$`)},
		storedRe:    re(`^[A-Za-z0-9][A-Za-z0-9-]{0,38}$`),
		urlTemplate: "https://github.com/%s",
		reserved: set(
			"features", "about", "pricing", "login", "join", "settings", "marketplace",
			"explore", "sponsors", "topics", "collections", "trending", "notifications",
			"new", "orgs", "team", "enterprise", "security", "contact",
		),
	},
	schema.UserContactPlatformVK: {
		ID: schema.UserContactPlatformVK, Name: "VK",
		hosts:       []string{"vk.com", "vk.ru"},
		bareRe:      re(`^(id[0-9]+|[A-Za-z0-9_.]{2,32})$`),
		pathRes:     []*regexp.Regexp{re(`^/(id[0-9]+|[A-Za-z0-9_.]{2,32})/?$`)},
		storedRe:    re(`^(id[0-9]+|[A-Za-z0-9_.]{2,32})$`),
		urlTemplate: "https://vk.com/%s",
	},
}

var hostPrefixes = []string{"www.", "m.", "mobile.", "old.", "np.", "de.", "uk.", "fr."}

// Detect attributes a profile URL to a platform. It works on URLs only — a bare handle is
// inherently ambiguous across platforms — and returns ok=false when raw is not a recognised
// profile URL. Used by the one-off backfill from the legacy users.url field.
func Detect(raw string) (schema.UserContactPlatform, string, bool) {
	if parseAsURL(strings.TrimSpace(raw)) == nil {
		return 0, "", false
	}

	for id := schema.UserContactPlatform(1); id <= schema.UserContactPlatformVK; id++ {
		if username, err := Parse(id, raw); err == nil && username != "" {
			return id, username, true
		}
	}

	return 0, "", false
}

// Parse turns raw user input for one platform into a normalised stored handle. An empty result
// with a nil error means "the field was left blank" — the caller should drop the contact.
func Parse(platformID schema.UserContactPlatform, raw string) (string, error) {
	platform, ok := Platforms[platformID]
	if !ok {
		return "", ErrUnknownPlatform
	}

	raw = strings.TrimSpace(raw)
	if raw == "" {
		return "", nil
	}

	username, err := platform.extract(raw)
	if err != nil {
		return "", err
	}

	if platform.reserved[strings.ToLower(username)] {
		return "", ErrNotAProfile
	}

	if !platform.keepCase {
		username = strings.ToLower(username)
	}

	if len(username) > schema.UserContactUsernameMaxLen {
		return "", ErrTooLong
	}

	if !platform.storedRe.MatchString(username) {
		return "", ErrBadFormat
	}

	return username, nil
}

// extract pulls the handle from a URL, or validates a bare handle, without normalising case.
func (p Platform) extract(raw string) (string, error) {
	parsed := parseAsURL(raw)
	if parsed == nil {
		handle := raw
		if p.stripAt {
			handle = strings.TrimPrefix(handle, "@")
		}

		if p.bareTransform != nil {
			handle = p.bareTransform(handle)
		}

		if !p.bareRe.MatchString(handle) {
			return "", ErrBadFormat
		}

		return handle, nil
	}

	host := parsed.Hostname()
	for _, pfx := range hostPrefixes {
		host = strings.TrimPrefix(host, pfx)
	}

	if !contains(p.hosts, host) {
		return "", ErrWrongPlatform
	}

	for _, rx := range p.pathRes {
		if match := rx.FindStringSubmatch(parsed.EscapedPath()); match != nil {
			return match[1], nil
		}
	}

	return "", ErrNotAProfile
}

// parseAsURL returns a parsed URL when raw looks like one (has a scheme, or a bare host), else
// nil so the caller falls back to treating raw as a bare handle.
func parseAsURL(raw string) *url.URL {
	candidate := raw

	switch {
	case strings.Contains(candidate, "://"):
	case strings.HasPrefix(candidate, "//"):
		candidate = "https:" + candidate
	case looksLikeHost(candidate):
		candidate = "https://" + candidate
	default:
		return nil
	}

	parsed, err := url.Parse(candidate)
	if err != nil || parsed.Hostname() == "" {
		return nil
	}

	return parsed
}

var hostHead = regexp.MustCompile(`^[A-Za-z0-9.-]+\.[A-Za-z]{2,}(/|$|\?|#)`)

func looksLikeHost(candidate string) bool {
	return strings.HasPrefix(candidate, "www.") || hostHead.MatchString(candidate)
}

func contains(haystack []string, needle string) bool {
	for _, item := range haystack {
		if item == needle {
			return true
		}
	}

	return false
}
