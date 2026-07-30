# Security policy

## Supported versions

Only the latest tagged release of LJNDI HRMS Workflow receives security updates during the public preview.

## Reporting a vulnerability

Do not publish security vulnerabilities in a public GitHub issue.

Report suspected vulnerabilities through:

- https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow

Include the affected plugin version, WordPress version, PHP version, reproduction steps, and the security impact. Do not include real OAuth client secrets, access tokens, refresh tokens, employee passwords, or client website credentials.

## Security design

The plugin uses OAuth 2.1 Authorization Code with PKCE S256, high-entropy state values, exact callback URLs, encrypted local token storage, rotating refresh tokens, and periodic HRMS session validation.
