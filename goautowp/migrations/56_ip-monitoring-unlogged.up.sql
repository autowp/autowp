-- Request counters are transient; avoid WAL for their frequent updates.
ALTER TABLE ip_monitoring SET UNLOGGED;
