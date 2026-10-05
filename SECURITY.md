# PHP Fast Forward security guidance

This is the organization's default security policy. A repository's own security policy takes precedence. Report a potential vulnerability to the affected repository, including when it involves a specific `fast-forward/*` package.

## Reporting a potential vulnerability

Use GitHub's **Report a vulnerability** option on the affected repository when private vulnerability reporting is enabled. Review the repository's security policy and submit reproduction steps, affected versions or files, and likely impact through the private form. Availability is configured per repository; publishing this file does not enable private reporting. See [GitHub's private reporting instructions](https://docs.github.com/en/code-security/how-tos/report-and-fix-vulnerabilities/report-privately).

When that option is unavailable and the affected repository has no published private reporting route, open a public issue asking maintainers to provide a private security contact. Keep the request brief and omit vulnerability details, exploit code, credentials, and sensitive attachments. Share the technical report only after a private route has been established.

Do not include secrets or personal information in public issues or pull requests. For platform abuse or exposed private information, use the appropriate [GitHub reporting tools](https://docs.github.com/en/communities/maintaining-your-safety-on-github/reporting-abuse-or-spam).

## Scope and expectations

Repository maintainers document supported package versions and any additional reporting terms in their own policies. These defaults do not announce a bounty program, a private email address, or response deadlines. Coordinate disclosure of unresolved vulnerabilities through the private reporting route that is actually available.

The organization profile repository contains public content, design assets, and shared community metadata. Package vulnerabilities still belong with the affected package's maintainers.
