# LJNDI HRMS Workflow

**Secure WordPress access for approved LJNDI team members.**

LJNDI HRMS Workflow is a free WordPress plugin that adds **Continue with LJNDI HRMS** to WordPress. Team members sign in through the central LJNDI identity service, while each WordPress website controls the local access that approved HRMS roles and departments receive.

## What it does

- OAuth 2.1 Authorization Code login with PKCE S256.
- Separate credentials and revocation controls for every WordPress website.
- Links approved HRMS employees to local WordPress users by verified work email.
- Optionally creates a local account after approved sign-in.
- Maps exact HRMS roles or departments to WordPress roles.
- Revalidates connected sessions when an employee becomes inactive or access is revoked.
- Encrypts OAuth client secrets and employee tokens before local storage.
- Never sends an HRMS password to the connected WordPress website.

## How connection works

1. Install LJNDI HRMS Workflow from WordPress.
2. Copy the callback URL displayed in the plugin settings.
3. Register the WordPress website in **HRMS Admin → Settings → Identity & API**.
4. Paste the generated Client ID and Client Secret into WordPress.
5. Configure the HRMS-to-WordPress role mappings.
6. Test Continue with LJNDI HRMS in a private browser window.

Every website receives separate OAuth credentials. A credential issued to one WordPress website must never be reused on another website.

## Access is explicit

The plugin does not automatically make job titles WordPress administrators. Subscriber is the safest fallback. Administrator, Editor, Author, Contributor, Shop Manager, and custom roles must be mapped intentionally by an authorised WordPress administrator.

Example:

```text
role:Founder & Visionary Leader=administrator
role:Content Manager=editor
department:Client and Customer Success Department=editor
```

## External identity service

The plugin connects to:

```text
https://auth.lerionjakenwauda.com
```

The employee signs in on the LJNDI identity service. The WordPress website receives only the approved identity information and OAuth tokens needed to establish and revalidate local access.

## Privacy

The plugin may process an approved employee ID, name, work email, employment status, roles, departments, OAuth scopes, access token, and refresh token. Secrets and tokens are encrypted before being saved by WordPress. HRMS passwords are never transmitted to the WordPress website.

Read the Privacy Policy and Terms before connecting a website.

## Support

For installation, OAuth application registration, callback configuration, role mapping, security reporting, and support, contact Lerion Jake Nwauda Digital Innovations Ltd through this page.

## Free and open source

LJNDI HRMS Workflow is free software licensed under GPL-2.0-or-later. The development source is publicly available on GitHub.
