import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkComponent} from 'ngx-remark';

// The whole policy is one $localize block (rendered as Markdown by <remark>, like about.component)
// rather than dozens of <p i18n> elements: a legal text has to be translated as a coherent whole,
// and a single translation unit can't be left half-updated or orphaned paragraph-by-paragraph when
// the wording changes. The explicit @@privacy-policy-body id keeps the translation unit stable
// across small wording edits (a bumped date, a reworded cookie line) instead of re-keying and
// orphaning all nine translations. Keep the "Last updated" date in the first line in sync with real
// changes.
const policyText = $localize`:@@privacy-policy-body:*Last updated: 29 August 2026*

This Privacy Policy explains how the websites **wheelsage.org** and **autowp.ru** (together, the "Site") collect, use, and share information about you. It is written to meet the requirements of the EU General Data Protection Regulation (GDPR) and, for visitors in Russia, Federal Law No. 152-FZ "On Personal Data".

The Site is a community car catalogue and encyclopaedia operated by the Site's administration ("we", "us"). For any question about this Policy or your personal data, contact us at [autowp@gmail.com](mailto:autowp@gmail.com).

## Our approach

We collect as little as we can. We do not show advertising, we do not use third-party tracking or profiling, and we never sell your data. Analytics stays off until you turn it on. We do not ask for your real name. Running an account-based site still involves some personal data — an email address, an IP address in our logs, and whatever you choose to post — and the rest of this Policy explains exactly what, and why.

## What we collect

Information you provide:

* **Account data** — when you register, your username, email address, and password are handled by our authentication system (Keycloak). Your profile may also hold a display name, avatar, preferred language, and time zone.
* **Content you submit** — comments, forum posts, private messages, catalogue and specification edits, votes, and pictures you upload. A picture may carry metadata added by your camera, such as the date and the GPS coordinates where it was taken; that metadata is stored and may be shown publicly together with the picture. Uploaded images are stored on our own servers (on the \`s3.wheelsage.org\` subdomain), not with a third-party cloud service.
* **Correspondence** — messages you send us through the feedback form, by email, or via the Telegram bot.

Information collected automatically:

* **Technical data** — your IP address, browser type and version, the page that referred you, and the pages you request, recorded in server logs.
* **Cookies and local storage** — see the "Cookies" section below.

We do not ask for, and do not knowingly collect, special categories of data (such as health, political views, or religious beliefs).

## Why we use it, and our legal basis

We use your information to:

* **operate your account and provide the Site to you** — necessary to perform our agreement with you;
* **publish the content you choose to submit** — on the basis of your submission and that agreement;
* **keep the Site secure** — detect and prevent spam, abuse, and unauthorised access, apply rate limits, and automatically block abusive clients — on the basis of our legitimate interest in protecting the Site and its users;
* **send you service messages and the notifications you subscribed to** — to perform our agreement with you, or on the basis of our legitimate interests;
* **measure how the Site is used**, through analytics cookies — only on the basis of your consent;
* **comply with the law** and respond to lawful requests — where we are under a legal obligation.

For visitors in Russia: we process the personal data you enter into forms on the Site on the basis of the consent you give by submitting those forms, and you may withdraw that consent at any time (see the "Your rights" section below). Anonymised usage statistics are collected only if your browser allows cookies and JavaScript.

## Who we share it with

Your content — including your username and anything you post or upload — is **public**: it is visible to every visitor and can be indexed by search engines. Please think carefully before posting anything you would not want to be public.

We also rely on a small number of service providers that process data on our behalf, under contract and only on our instructions:

* **hosting and network providers** whose data centres and connectivity our servers depend on;
* **email delivery** for account and notification messages;
* **Google Analytics** (Google LLC) — only if you accept analytics cookies; Google receives your shortened IP address and usage events;
* **Telegram** — only if you choose to use our Telegram bot.

We do not sell your personal data. We disclose it to public authorities only where we are legally required to do so.

## International transfers

The Site is operated from, and your data is processed in, Russia and the European Union, and by the providers listed above, some of which are located in other countries, including the United States (Google). Where we transfer personal data out of the European Economic Area, we rely on an adequacy decision or on the European Commission's Standard Contractual Clauses. Because your content is public, it is by its nature accessible worldwide.

## How long we keep it

We keep personal data for as long as your account exists and for as long as it is needed for the purposes described above; after that, we keep it only as long as required to meet legal obligations, resolve disputes, and enforce our agreements. Server logs and security-related data are kept for a limited period and then deleted or anonymised. When you delete your account we delete or anonymise your personal data, except where the law requires us to keep it; contributions you made to the catalogue may remain, dissociated from your account.

## Your rights

Under the GDPR you have the right to access your data, have it corrected or erased, restrict or object to its processing, receive it in a portable format, and withdraw consent at any time without affecting processing already carried out. You also have the right to lodge a complaint with the data protection supervisory authority in your country.

Under Federal Law No. 152-FZ you may obtain information about how your data is processed and request that it be corrected, blocked, or destroyed if it is incomplete, outdated, inaccurate, unlawfully obtained, or no longer needed for the purpose of processing.

To exercise any of these rights, email us at [autowp@gmail.com](mailto:autowp@gmail.com). You can edit your profile at any time, and you can delete your account from the [account deletion page](/account/delete). To withdraw consent to the processing of your personal data under 152-FZ, email us with the subject line "Withdrawal of consent to personal data processing".

## Cookies

We use:

* **Strictly necessary cookies** — set by our authentication system when you sign in, to keep you signed in. The Site cannot work without them, so they are not subject to consent.
* **Small values in your browser's local storage** — such as your cookie choice (\`cookie-consent\`) and interface preferences. These stay on your device and are not sent to us.
* **Analytics cookies** — set only after you accept them: Google Analytics (GA4) \`_ga\` and \`_ga_*\` (each expires after about two years), which help us understand how the Site is used. We have enabled IP anonymisation, and Google Signals (cross-device tracking and advertising features) is switched off.

You can change or withdraw your choice at any time through the **"Cookie settings"** link in the Site footer, and you can block or delete cookies in your browser settings.

## Children

The Site is not directed to children under the age of 16, or the lower age of digital consent set by your country, and we do not knowingly collect their personal data. If you believe a child has given us personal data, contact us and we will delete it.

## Changes to this Policy

We may update this Policy from time to time. The current version is always available at wheelsage.org/policy and autowp.ru/policy, with the date of the last change shown at the top. If a change is significant, we will make reasonable efforts to bring it to your attention.

## Contact

Questions about this Policy or your personal data: [autowp@gmail.com](mailto:autowp@gmail.com).`;

@Component({
  selector: 'app-policy',
  imports: [RouterLink, RemarkComponent],
  templateUrl: './policy.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PolicyComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly policyText = policyText;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }
}
