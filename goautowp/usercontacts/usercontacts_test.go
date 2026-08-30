package usercontacts

import (
	"strings"
	"testing"

	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestParseAcceptsBareHandleAndURL(t *testing.T) {
	t.Parallel()

	cases := []struct {
		name     string
		platform schema.UserContactPlatform
		input    string
		want     string
	}{
		{"drive2 bare", schema.UserContactPlatformDrive2, "john-doe", "john-doe"},
		{"drive2 url", schema.UserContactPlatformDrive2, "https://www.drive2.ru/users/John-Doe/", "john-doe"},
		{"drive2 url no www", schema.UserContactPlatformDrive2, "drive2.ru/users/johndoe", "johndoe"},
		{"telegram strips @", schema.UserContactPlatformTelegram, "@durov", "durov"},
		{"telegram t.me url", schema.UserContactPlatformTelegram, "https://t.me/durov", "durov"},
		{"x bare", schema.UserContactPlatformX, "Jack", "jack"},
		{"x url with status", schema.UserContactPlatformX, "https://x.com/jack/status/20", "jack"},
		{"x twitter.com host", schema.UserContactPlatformX, "twitter.com/jack", "jack"},
		{
			"x legacy http twitter link",
			schema.UserContactPlatformX,
			"http://twitter.com/microbber",
			"microbber",
		},
		{"youtube handle bare", schema.UserContactPlatformYouTube, "@MrBeast", "@MrBeast"},
		{"youtube handle url", schema.UserContactPlatformYouTube, "https://www.youtube.com/@MrBeast", "@MrBeast"},
		{
			"youtube bare channel id",
			schema.UserContactPlatformYouTube,
			"UCX6OQ3DkcsbYNE6H8uQQuVA",
			"channel/UCX6OQ3DkcsbYNE6H8uQQuVA",
		},
		{
			"youtube channel url",
			schema.UserContactPlatformYouTube,
			"https://youtube.com/channel/UCX6OQ3DkcsbYNE6H8uQQuVA",
			"channel/UCX6OQ3DkcsbYNE6H8uQQuVA",
		},
		{"vk screen name", schema.UserContactPlatformVK, "vk.com/durov", "durov"},
		{"vk id form", schema.UserContactPlatformVK, "https://vk.com/id1", "id1"},
		{"reddit strips u/", schema.UserContactPlatformReddit, "reddit.com/user/spez", "spez"},
		{"reddit short u form", schema.UserContactPlatformReddit, "https://www.reddit.com/u/spez/", "spez"},
		{
			"linkedin in-path", schema.UserContactPlatformLinkedIn,
			"https://www.linkedin.com/in/williamhgates/", "williamhgates",
		},
		{
			"linkedin cc subdomain", schema.UserContactPlatformLinkedIn,
			"https://de.linkedin.com/in/williamhgates", "williamhgates",
		},
		{"github bare", schema.UserContactPlatformGitHub, "torvalds", "torvalds"},
		{
			"tiktok strips @ in url", schema.UserContactPlatformTikTok,
			"https://www.tiktok.com/@charlidamelio", "charlidamelio",
		},
		{"flickr keeps case", schema.UserContactPlatformFlickr, "https://www.flickr.com/photos/BigName/", "BigName"},
		{"500px old url", schema.UserContactPlatform500px, "https://500px.com/p/someone", "someone"},
		{"artstation url", schema.UserContactPlatformArtStation, "artstation.com/someartist", "someartist"},
		{"vsco url with trailing path", schema.UserContactPlatformVSCO, "https://vsco.co/someone/gallery", "someone"},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			got, err := Parse(tc.platform, tc.input)
			require.NoError(t, err)
			assert.Equal(t, tc.want, got)
		})
	}
}

func TestParseRejects(t *testing.T) {
	t.Parallel()

	cases := []struct {
		name     string
		platform schema.UserContactPlatform
		input    string
		wantErr  error
	}{
		{"wrong platform host", schema.UserContactPlatformX, "https://vk.com/jack", ErrWrongPlatform},
		{"instagram not a profile", schema.UserContactPlatformX, "https://x.com/i/flow/login", ErrNotAProfile},
		{
			"drive2 community not profile",
			schema.UserContactPlatformDrive2,
			"https://www.drive2.ru/c/bmw/",
			ErrNotAProfile,
		},
		{"telegram invite link", schema.UserContactPlatformTelegram, "https://t.me/+abcdefgh", ErrNotAProfile},
		{"telegram bad bare", schema.UserContactPlatformTelegram, "1234", ErrBadFormat},
		{"youtube ambiguous bare word", schema.UserContactPlatformYouTube, "somechannel", ErrBadFormat},
		{"unknown platform", schema.UserContactPlatform(999), "whatever", ErrUnknownPlatform},
		{"too long github", schema.UserContactPlatformGitHub, "x-" + strings.Repeat("a", 80), ErrBadFormat},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			_, err := Parse(tc.platform, tc.input)
			require.ErrorIs(t, err, tc.wantErr)
		})
	}
}

func TestDetect(t *testing.T) {
	t.Parallel()

	cases := []struct {
		name         string
		input        string
		wantPlatform schema.UserContactPlatform
		wantUsername string
	}{
		{"legacy twitter", "http://twitter.com/microbber", schema.UserContactPlatformX, "microbber"},
		{"vk id link", "https://vk.com/id12345", schema.UserContactPlatformVK, "id12345"},
		{"github", "https://github.com/torvalds", schema.UserContactPlatformGitHub, "torvalds"},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			platform, username, ok := Detect(tc.input)
			require.True(t, ok)
			assert.Equal(t, tc.wantPlatform, platform)
			assert.Equal(t, tc.wantUsername, username)
		})
	}
}

func TestDetectIgnoresBareHandlesAndUnknownHosts(t *testing.T) {
	t.Parallel()

	_, _, ok := Detect("microbber")
	assert.False(t, ok, "a bare handle is ambiguous and must not be attributed")

	_, _, ok = Detect("https://facebook.com/someone")
	assert.False(t, ok, "an unregistered platform must not match")
}

func TestParseEmptyIsNotAnError(t *testing.T) {
	t.Parallel()

	got, err := Parse(schema.UserContactPlatformGitHub, "   ")
	require.NoError(t, err)
	assert.Empty(t, got)
}

func TestCanonicalURL(t *testing.T) {
	t.Parallel()

	assert.Equal(t,
		"https://www.drive2.ru/users/johndoe/",
		Platforms[schema.UserContactPlatformDrive2].CanonicalURL("johndoe"),
	)
	assert.Equal(t,
		"https://www.youtube.com/channel/UC123",
		Platforms[schema.UserContactPlatformYouTube].CanonicalURL("channel/UC123"),
	)
	assert.Equal(t,
		"https://www.tiktok.com/@someone",
		Platforms[schema.UserContactPlatformTikTok].CanonicalURL("someone"),
	)
}

// TestRegistryComplete makes sure every enum value in the schema has a registry entry, so a
// contact stored with an unknown platform can never be rendered.
func TestRegistryComplete(t *testing.T) {
	t.Parallel()

	for id := schema.UserContactPlatform(1); id <= schema.UserContactPlatformVK; id++ {
		_, ok := Platforms[id]
		assert.Truef(t, ok, "platform %d missing from registry", id)
	}
}
