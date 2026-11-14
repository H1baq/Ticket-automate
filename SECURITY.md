# Security Policy

## Supported versions

Security fixes are applied to the latest release and to `develop`.

| Version        | Supported |
| -------------- | --------- |
| Latest release | Yes       |
| Older releases | No        |

## Reporting a vulnerability

Please do not open a public issue for a security problem.

Report it privately through GitHub: open the repository's **Security** tab and
choose **Report a vulnerability**. Include:

- what the problem is and which part of the system it affects,
- steps to reproduce it, or a proof of concept,
- the impact you expect, and any suggested fix.

You can expect an acknowledgement within 5 working days. We will keep you
informed while the problem is confirmed and fixed, and credit you in the
release notes if you wish.

## Scope

In scope: authentication and sessions, role and ticket access control,
file uploads, injection and cross-site scripting, and exposure of personal
data in tickets, notifications and reports.

Out of scope: findings that need physical access to a server, denial of
service through sheer traffic volume, and problems in third-party services
that the project does not control.

## Handling secrets

Never commit `.env` files, passwords, tokens or database dumps. If a secret is
committed by mistake, treat it as exposed: rotate it first, then remove it from
the repository history.
