// Package logging is the process-wide logger, backed by log/slog.
//
// It exposes a small printf-style surface (Infof, Errorf, Warn, ...) that mirrors the subset of
// logrus this codebase used, so existing call sites move over with a mechanical rename. New code
// should prefer slog.Default() or the structured helper With directly.
package logging

import (
	"errors"
	"fmt"
	"log/slog"
	"os"
	"strings"
)

var errUnknownLevel = errors.New("unknown log level")

// level is the shared level knob; SetLevel writes it, the installed handler reads it on every
// record, so a change takes effect immediately and everywhere.
var level = new(slog.LevelVar)

// _ wires slog.Default() to a text handler on stderr filtered by level, at package
// initialisation, before any call site can log.
var _ = install()

func install() bool {
	level.Set(slog.LevelInfo)
	slog.SetDefault(slog.New(slog.NewTextHandler(os.Stderr, &slog.HandlerOptions{Level: level})))

	return true
}

// SetLevel parses a logrus-style level name (trace, debug, info, warn/warning, error, fatal,
// panic) and applies it to the default logger. An unknown name is returned as an error and the
// level is left unchanged.
func SetLevel(name string) error {
	parsed, err := parseLevel(name)
	if err != nil {
		return err
	}

	level.Set(parsed)

	return nil
}

func parseLevel(name string) (slog.Level, error) {
	switch strings.ToLower(strings.TrimSpace(name)) {
	case "trace", "debug":
		return slog.LevelDebug, nil
	case "", "info":
		return slog.LevelInfo, nil
	case "warn", "warning":
		return slog.LevelWarn, nil
	case "error", "fatal", "panic":
		return slog.LevelError, nil
	default:
		return slog.LevelInfo, fmt.Errorf("%w: %q", errUnknownLevel, name)
	}
}

// With returns a logger that carries the given key/value attributes, for the few call sites that
// need structured context. Mirrors logrus.WithFields.
func With(args ...any) *slog.Logger {
	return slog.Default().With(args...)
}

func emit(severity slog.Level, args ...any) {
	if severity < level.Level() {
		return
	}

	dispatch(severity, fmt.Sprint(args...))
}

func emitf(severity slog.Level, format string, args ...any) {
	if severity < level.Level() {
		return
	}

	dispatch(severity, fmt.Sprintf(format, args...))
}

// dispatch forwards to the context-free slog package helpers so this package never fabricates a
// context.Background() (which contextcheck would flag at every call site).
func dispatch(severity slog.Level, msg string) {
	switch severity {
	case slog.LevelDebug:
		slog.Debug(msg)
	case slog.LevelInfo:
		slog.Info(msg)
	case slog.LevelWarn:
		slog.Warn(msg)
	case slog.LevelError:
		slog.Error(msg)
	default:
		slog.Info(msg)
	}
}

// Debug logs at debug level; args are joined with fmt.Sprint.
func Debug(args ...any) { emit(slog.LevelDebug, args...) }

// Debugf logs a formatted message at debug level.
func Debugf(format string, args ...any) { emitf(slog.LevelDebug, format, args...) }

// Info logs at info level; args are joined with fmt.Sprint.
func Info(args ...any) { emit(slog.LevelInfo, args...) }

// Infof logs a formatted message at info level.
func Infof(format string, args ...any) { emitf(slog.LevelInfo, format, args...) }

// Infoln logs at info level; args are joined with fmt.Sprint.
func Infoln(args ...any) { emit(slog.LevelInfo, args...) }

// Print logs at info level; args are joined with fmt.Sprint.
func Print(args ...any) { emit(slog.LevelInfo, args...) }

// Printf logs a formatted message at info level.
func Printf(format string, args ...any) { emitf(slog.LevelInfo, format, args...) }

// Println logs at info level; args are joined with fmt.Sprint.
func Println(args ...any) { emit(slog.LevelInfo, args...) }

// Warn logs at warn level; args are joined with fmt.Sprint.
func Warn(args ...any) { emit(slog.LevelWarn, args...) }

// Warnf logs a formatted message at warn level.
func Warnf(format string, args ...any) { emitf(slog.LevelWarn, format, args...) }

// Warning logs at warn level; args are joined with fmt.Sprint.
func Warning(args ...any) { emit(slog.LevelWarn, args...) }

// Warningf logs a formatted message at warn level.
func Warningf(format string, args ...any) { emitf(slog.LevelWarn, format, args...) }

// Error logs at error level; args are joined with fmt.Sprint.
func Error(args ...any) { emit(slog.LevelError, args...) }

// Errorf logs a formatted message at error level.
func Errorf(format string, args ...any) { emitf(slog.LevelError, format, args...) }

// Errorln logs at error level; args are joined with fmt.Sprint.
func Errorln(args ...any) { emit(slog.LevelError, args...) }

// Fatal logs at error level and then exits the process, matching logrus.Fatal.
func Fatal(args ...any) {
	emit(slog.LevelError, args...)
	os.Exit(1)
}

// Fatalf logs a formatted message at error level and then exits the process.
func Fatalf(format string, args ...any) {
	emitf(slog.LevelError, format, args...)
	os.Exit(1)
}
