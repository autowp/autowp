package hosts

import (
	"errors"
	"fmt"
	"net/url"

	"github.com/autowp/goautowp/config"
)

var errHostForLanguageNotFound = errors.New("host for language not found")

type Manager struct {
	languages map[string]config.LanguageConfig
}

func NewManager(languages map[string]config.LanguageConfig) *Manager {
	return &Manager{
		languages: languages,
	}
}

func (s *Manager) URIByLanguage(lang string) (*url.URL, error) {
	langConfig, ok := s.languages[lang]

	if !ok {
		return nil, fmt.Errorf("%w: `%s`", errHostForLanguageNotFound, lang)
	}

	return url.Parse("https://" + langConfig.Hostname)
}

func (s *Manager) TimezoneByLanguage(lang string) (string, error) {
	langConfig, ok := s.languages[lang]

	if !ok {
		return "", fmt.Errorf("%w: `%s`", errHostForLanguageNotFound, lang)
	}

	return langConfig.Timezone, nil
}
