---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory.

## What does {package.name} require?

The latest release requires {package.requiresText}.

## Does it change responses I already return?

No. Only responses you build with this package's `Response` class are shaped. Exception rendering is off until you register `ExceptionRenderer` with your exception handler, and even then it only handles API requests.

## Does it need pharaonic/laravel-seo?

No. The SEO integration is optional, and a response carries SEO tags only when it calls `withSEO()`.

## Can I change the shape of the JSON?

Yes. Rename any envelope field in the configuration, or register a profile that reshapes pagination and notifications, adds headers, or sends the data without an envelope.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, configuration, and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), ask in [GitHub Discussions](https://github.com/orgs/Pharaonic/discussions), or chat with us on [Discord](https://discord.gg/XQG9RhvEvf).
