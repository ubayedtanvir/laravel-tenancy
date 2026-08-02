# Security Policy

## Reporting a Vulnerability

If you find a security issue in Laravel Tenancy, please **do not open a public issue or pull request**. Security bugs disclosed publicly give attackers a head start before a fix is available.

Instead, email **ubayedtanvir@yahoo.com** with:

- A description of the vulnerability and how to reproduce it
- The impact as you understand it (data leak, privilege escalation, denial of service, etc.)
- Your name or handle if you'd like credit in the advisory

You'll get an acknowledgment within 48 hours. From there we'll work on a fix, coordinate a release, and credit you in the changelog (unless you prefer not to be named).

## What Counts

Anything that breaks tenant isolation, leaks data across tenants, bypasses write guards, or allows a request to act in a tenant context it shouldn't have access to — those are the things we care most about.

General Laravel or PHP issues that aren't specific to this package should be reported to the relevant project instead.

## Supported Versions

Security fixes are applied to the latest release only. If you're on an older version, update first and check whether the issue still exists.
