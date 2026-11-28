<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AISettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheAISettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/ai');

        $this->assertResponseIsSuccessful();

        // The AI settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="ai"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[ai][enabled]"]'));
        $this->assertCount(1, $crawler->filter('section[data-dependent-on="aiEnabled"][data-visible-when="checked"] input#aiAgentEnabled[type="checkbox"][name="data[ai][agent][enabled]"][checked]'));
        $this->assertCount(1, $crawler->filter('input[type="hidden"][name="data[ai][agent][enabled]"][value="0"]'));
        $this->assertSame(
            'Enables "Mark", your personal workout assistant. Use caution when enabling this if your app is publicly accessible.',
            $crawler->filter('input#aiAgentEnabled')->closest('section')?->filter('h3 + .description')->text(),
        );
        $this->assertCount(1, $crawler->filter('section[data-dependent-on="aiEnabled"][data-visible-when="checked"] input[type="checkbox"][name="data[ai][mediaLocation][enabled]"]:not([checked])'));
        $this->assertCount(1, $crawler->filter('input[type="hidden"][name="data[ai][mediaLocation][enabled]"][value="0"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[ai][agent][commands][__index__][command]"]'));
        $this->assertCount(1, $crawler->filter('section[data-dependent-on="aiEnabled"][data-visible-when="checked"] [data-dependent-on="aiAgentEnabled"][data-visible-when="checked"] [data-repeater]'));
        $this->assertSame(
            ['', 'anthropic', 'azureOpenAI', 'deepseek', 'gemini', 'grok', 'huggingFace', 'ollama', 'openAI', 'openAILike', 'openAIResponses', 'mistral'],
            $crawler->filter('select#aiProvider option')->each(static fn (Crawler $option): string => (string) $option->attr('value')),
        );
        $this->assertSame(
            'anthropic,azureOpenAI,deepseek,gemini,grok,huggingFace,openAI,openAILike,openAIResponses,mistral',
            $crawler->filter('#aiKey')->ancestors()->first()->attr('data-visible-when'),
        );
        $this->assertSame('ollama', $crawler->filter('#aiUrl')->ancestors()->first()->attr('data-visible-when'));
        $this->assertSame('openAILike', $crawler->filter('#aiBaseUri')->ancestors()->first()->attr('data-visible-when'));
        $this->assertSame('azureOpenAI', $crawler->filter('#aiEndpoint')->ancestors()->first()->attr('data-visible-when'));

        // The settings navigation, with "AI" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertSame('AI', $selectedLink->text());
    }
}
