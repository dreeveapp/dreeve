// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightThemeRapide from 'starlight-theme-rapide';
import starlightImageZoom from 'starlight-image-zoom';
import starlightLinksValidator from 'starlight-links-validator';
import starlightOpenAPI, { openAPISidebarGroups } from 'starlight-openapi';
import starlightSidebarTopics from 'starlight-sidebar-topics';
import remarkGemoji from 'remark-gemoji';

export default defineConfig({
	site: 'https://docs.dreeve.app',
	markdown: {
		remarkPlugins: [remarkGemoji],
	},
	integrations: [
		starlight({
			title: 'Dreeve',
			description: 'Dreeve is a self-hosted, open-source dashboard for your sports and fitness data',
			favicon: '/assets/images/logo.svg',
			logo: { src: './src/assets/logo.svg' },
			customCss: ['./src/styles/custom.css'],
			plugins: [
				starlightThemeRapide(),
				starlightImageZoom(),
				starlightLinksValidator({ errorOnLocalLinks: false }),
				starlightOpenAPI([
					{
						base: 'api',
						schema: './src/schemas/dreeve-api.yaml',
						sidebar: { label: 'HTTP API', collapsed: false, operations: { badges: false } },
					},
				]),
				starlightSidebarTopics(
					[
						{
							label: 'Documentation',
							link: '/getting-started/prerequisites/',
							icon: 'open-book',
							items: [
								{
									label: 'Getting Started',
									items: [
										{ slug: 'getting-started/prerequisites', label: 'Prerequisites' },
										{ slug: 'getting-started/installation', label: 'Installation' },
										{ slug: 'getting-started/installation-on-k8s', label: 'Installation on Kubernetes' },
										{ slug: 'getting-started/updates', label: 'Updates' },
										{ slug: 'getting-started/migrating-from-v4', label: 'Migrating from v4 to v5' },
									],
								},
								{
									label: 'Importing activities',
									items: [
										{ slug: 'importing/overview', label: 'Overview' },
										{ slug: 'importing/file-import', label: 'File import' },
										{ slug: 'importing/strava-import', label: 'Strava import' },
										{ slug: 'importing/strava-webhooks', label: 'Strava webhooks' },
										{ slug: 'importing/strava-challenges-and-trophies', label: 'Strava challenges and trophies' },
									],
								},
								{
									label: 'Integrations',
									items: [
										{ slug: 'integrations/ai', label: 'AI assistant' },
										{ slug: 'integrations/garmin-connect', label: 'Garmin Connect' },
										{ slug: 'integrations/polar-flow', label: 'Polar Flow' },
										{ slug: 'integrations/wahoo-connector', label: 'Wahoo Connector' },
										{ slug: 'integrations/hammerhead-connector', label: 'Hammerhead Connector' },
										{ slug: 'integrations/notifications', label: 'Notifications' },
									],
								},
								{
									label: 'Troubleshooting',
									items: [
										{ slug: 'troubleshooting/faq', label: 'FAQ' },
										{ slug: 'troubleshooting/logs', label: 'Logs' },
										{ slug: 'troubleshooting/clearing-the-cache', label: 'Clearing the cache' },
										{ slug: 'troubleshooting/import-build-fails', label: 'Import fails with syntax error' },
										{ slug: 'troubleshooting/strava-api-errors', label: 'Strava API errors' },
										{ slug: 'troubleshooting/shoutrrr-notifications', label: 'Notification issues' },
									],
								},
								{
									label: 'Development',
									items: [
										{ slug: 'development/local-development', label: 'Local development' },
										{ slug: 'development/locales-and-translations', label: 'Locales and translations' },
									],
								},
								{
									label: 'Community & Contributions',
									items: [{ slug: 'community/feature-requests', label: 'Feature requests' }],
								},
								{ label: 'Changelog', link: 'https://github.com/dreeveapp/dreeve/releases' },
							],
						},
						{
							label: 'API specifications',
							id: 'api',
							link: '/api/',
							icon: 'puzzle',
							items: [...openAPISidebarGroups],
						},
					],
					{ topics: { api: ['/api/**'] }, exclude: ['/'] },
				),
			],
			social: [
				{ icon: 'rocket', label: 'Live demo', href: 'https://demo.dreeve.app/' },
				{ icon: 'discord', label: 'Discord', href: 'https://discord.gg/p4zpZyCHNc' },
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/dreeveapp/dreeve' },
			],
			head: [
				{
					tag: 'script',
					attrs: {
						defer: true,
						src: 'https://analytics.robiningelbrecht.be/script.js',
						'data-website-id': '38bb383d-32cf-4d59-aaa2-b6eceaa245e2',
						'data-domains': 'docs.dreeve.app',
					},
				},
			],
		}),
	],
});
