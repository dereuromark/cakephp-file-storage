import { defineConfig } from 'vitepress'

function guideSidebar() {
  return [
    {
      text: 'Guide',
      items: [
        { text: 'Overview', link: '/guide/' },
        { text: 'Installation', link: '/guide/installation' },
        { text: 'Quick Start', link: '/guide/quick-start' },
        { text: 'Usage', link: '/guide/usage' },
        { text: 'Validation', link: '/guide/validation' },
        { text: 'Paths and URLs', link: '/guide/paths-and-urls' },
      ],
    },
    {
      text: 'Images',
      items: [
        { text: 'Variants and Versioning', link: '/images/' },
        { text: 'Image Helper', link: '/images/helper' },
        { text: 'Variant Command', link: '/images/command' },
      ],
    },
    {
      text: 'Serving Files',
      items: [
        { text: 'Overview', link: '/serving/' },
        { text: 'Authorization', link: '/serving/authorization' },
        { text: 'Signed URLs', link: '/serving/signed-urls' },
        { text: 'Security and Performance', link: '/serving/security' },
      ],
    },
    {
      text: 'Admin',
      items: [
        { text: 'Admin Backend', link: '/admin/' },
      ],
    },
  ]
}

export default defineConfig({
  title: 'cakephp-file-storage',
  description: 'Store and serve files in any backend for CakePHP — FlySystem adapters, image variants, signed URLs, and a self-contained admin backend.',
  base: '/cakephp-file-storage/',
  lastUpdated: true,
  sitemap: {
    hostname: 'https://dereuromark.github.io/cakephp-file-storage/',
  },
  head: [
    ['link', { rel: 'icon', href: '/cakephp-file-storage/favicon.svg', type: 'image/svg+xml' }],
  ],
  themeConfig: {
    logo: '/logo.svg',
    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/(guide|admin)/' },
      { text: 'Images', link: '/images/', activeMatch: '/images/' },
      { text: 'Serving', link: '/serving/', activeMatch: '/serving/' },
      { text: 'Reference', link: '/reference/', activeMatch: '/reference/' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/dereuromark/cakephp-file-storage' },
          { text: 'Packagist', link: 'https://packagist.org/packages/dereuromark/cakephp-file-storage' },
          { text: 'Issues', link: 'https://github.com/dereuromark/cakephp-file-storage/issues' },
        ],
      },
    ],
    sidebar: {
      '/guide/': guideSidebar(),
      '/images/': guideSidebar(),
      '/serving/': guideSidebar(),
      '/admin/': guideSidebar(),
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Configuration', link: '/reference/' },
            { text: 'Behavior Options', link: '/reference/behavior' },
            { text: 'Console Commands', link: '/reference/commands' },
            { text: 'Troubleshooting', link: '/reference/troubleshooting' },
          ],
        },
      ],
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/dereuromark/cakephp-file-storage' },
    ],
    search: {
      provider: 'local',
    },
    editLink: {
      pattern: 'https://github.com/dereuromark/cakephp-file-storage/edit/master/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright Mark Scherer',
    },
  },
})
