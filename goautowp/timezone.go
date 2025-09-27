package goautowp

import (
	"os"
	"runtime"
	"strings"
	"sync"
	"time"

	"github.com/sirupsen/logrus"
)

var TimeZones = sync.OnceValue(func() []string {
	zoneDirs := map[string]string{
		"android":   "/system/usr/share/zoneinfo/",
		"darwin":    "/usr/share/zoneinfo/",
		"dragonfly": "/usr/share/zoneinfo/",
		"freebsd":   "/usr/share/zoneinfo/",
		"linux":     "/usr/share/zoneinfo/",
		"netbsd":    "/usr/share/zoneinfo/",
		"openbsd":   "/usr/share/zoneinfo/",
		"solaris":   "/usr/share/lib/zoneinfo/",
	}

	var result []string

	// Reads the Directory corresponding to the OS
	dirFile, _ := os.ReadDir(zoneDirs[runtime.GOOS])
	for _, i := range dirFile {
		// Checks if starts with Capital Letter
		if i.Name() == (strings.ToUpper(i.Name()[:1]) + i.Name()[1:]) {
			if i.IsDir() {
				// Recursive read if directory
				subFiles, err := os.ReadDir(zoneDirs[runtime.GOOS] + i.Name())
				if err != nil {
					logrus.Fatal(err)
				}

				for _, s := range subFiles {
					// Appends the path to timeZones var
					result = append(result, i.Name()+"/"+s.Name())
				}
			}
			// Appends the path to timeZones var
			result = append(result, i.Name())
		}
	}
	// Loop over timezones and Check Validity, Delete entry if invalid.
	// Range function doesnt work with changing length.
	for i := 0; i < len(result); i++ {
		_, err := time.LoadLocation(result[i])
		if err != nil {
			result = append(result[:i], result[i+1:]...)

			continue
		}
	}

	return result
})
