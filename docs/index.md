---
name: API Response

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: api
  icon: code-brackets
  tags: api response json envelope pagination resources exceptions error-codes notifications request-id profiles content-negotiation
  description: One consistent JSON envelope for Laravel API responses. Data, errors, API resources and every paginator, with profiles, structured notifications, request IDs and opt-in exception rendering.

seo:
  title: "{package.fullName} - Consistent JSON API Responses"
  description: "{package.name} is a Laravel package for consistent JSON API responses: one envelope for data, errors, resources and paginators, with error codes, profiles, notifications, request IDs and opt-in exception rendering. {package.downloadsShort}+ downloads, {package.license} licensed."
  keywords: laravel api response, laravel json response, api envelope, laravel pagination json, api resources, api error codes, laravel exception json, request id, content negotiation
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a Laravel package for consistent JSON API responses: one envelope for data, errors, resources and paginators."
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
