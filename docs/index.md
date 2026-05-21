---
layout: home

hero:
  name: cakephp-file-storage
  text: File Storage for CakePHP
  tagline: Upload, store, and serve files in virtually any backend — built on FlySystem with image variants, signed URLs, and a self-contained admin backend.
  image:
    src: /logo.svg
    alt: cakephp-file-storage
  actions:
    - theme: brand
      text: Get Started
      link: /guide/
    - theme: alt
      text: Quick Start
      link: /guide/quick-start
    - theme: alt
      text: Serving Files
      link: /serving/
    - theme: alt
      text: View on GitHub
      link: https://github.com/dereuromark/cakephp-file-storage

features:
  - icon: 🗄️
    title: Any Storage Backend
    details: Local filesystem, S3, Azure, in-memory, and more through the FlySystem-based adapter abstraction. Swap backends without touching your app code.
  - icon: 🧩
    title: Separation of Concerns
    details: A file is always a row in the file_storage table. The table references the real location and keeps metadata — no file paths scattered across your schema.
  - icon: 🖼️
    title: Image Variants
    details: Generate thumbnails, crops, and modern AVIF/WebP formats with a fluent variant API, an Image helper, and a regeneration command.
  - icon: 🔐
    title: Signed URLs
    details: Built-in HMAC-signed serving for temporary, authentication-free access — with HTTP Range support for video and audio on local adapters.
  - icon: 🛠️
    title: Admin Backend
    details: A self-contained, fail-closed Bootstrap 5 admin UI for browsing files, bulk deletes, and storage cleanup — opt in when you want it.
  - icon: ✅
    title: Upload Validation
    details: Server-side MIME sniffing, extension allow-lists, size limits, and image dimension checks via reusable validation traits.
---
