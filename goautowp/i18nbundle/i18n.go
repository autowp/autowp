package i18nbundle

import (
	"embed"
	"encoding/json"
	"sync"

	"github.com/nicksnyder/go-i18n/v2/i18n"
	"golang.org/x/text/language"
)

//go:embed *.json
var LocaleFS embed.FS

type I18n struct {
	bundle          *i18n.Bundle
	tags            map[string]language.Tag
	tagsMutex       sync.Mutex
	localizers      map[string]*i18n.Localizer
	localizersMutex sync.Mutex
}

func New() (*I18n, error) {
	bundle := i18n.NewBundle(language.English)
	bundle.RegisterUnmarshalFunc("json", json.Unmarshal)

	files, err := LocaleFS.ReadDir(".")
	if err != nil {
		return nil, err
	}

	for _, file := range files {
		_, err = bundle.LoadMessageFileFS(LocaleFS, file.Name())
		if err != nil {
			return nil, err
		}
	}

	return &I18n{
		bundle:          bundle,
		localizers:      make(map[string]*i18n.Localizer),
		localizersMutex: sync.Mutex{},
		tags:            make(map[string]language.Tag),
		tagsMutex:       sync.Mutex{},
	}, nil
}

func (s *I18n) Localizer(lang string) *i18n.Localizer {
	s.localizersMutex.Lock()
	defer s.localizersMutex.Unlock()

	localizer, ok := s.localizers[lang]
	if !ok {
		localizer = i18n.NewLocalizer(s.bundle, lang)
		s.localizers[lang] = localizer
	}

	return localizer
}

func (s *I18n) Tag(lang string) language.Tag {
	s.tagsMutex.Lock()
	defer s.tagsMutex.Unlock()

	tag, ok := s.tags[lang]
	if !ok {
		tag = language.Make(lang)
		s.tags[lang] = tag
	}

	return tag
}
