<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class IntegrationsSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheIntegrationsSettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/integrations');

        $this->assertResponseIsSuccessful();

        // The integrations settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="integrations"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[ai][enabled]"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[ai][agent][commands][__index__][command]"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[notifications][services][]"]'));
        $this->assertCount(1, $crawler->filter('input[type="checkbox"][name="data[notifications][notifyOnSuccessfulBuild]"][checked]'));
        $this->assertCount(1, $crawler->filter('input[type="hidden"][name="data[notifications][notifyOnSuccessfulBuild]"][value="0"]'));
        $this->assertCount(1, $crawler->filter('a[href="https://shoutrrr.nickfedor.com/dev/services/overview/"]'));
        $this->assertCount(1, $crawler->filter('section[data-dependent-on="aiEnabled"][data-visible-when="checked"] [data-repeater]'));
        $this->assertSame(
            ['', 'anthropic', 'azureOpenAI', 'deepseek', 'gemini', 'grok', 'huggingFace', 'ollama', 'openAI', 'openAILike', 'openAIResponses', 'mistral'],
            $crawler->filter('select#aiProvider option')->each(static fn ($option): string => (string) $option->attr('value')),
        );
        $this->assertSame(
            'anthropic,azureOpenAI,deepseek,gemini,grok,huggingFace,openAI,openAILike,openAIResponses,mistral',
            $crawler->filter('#aiKey')->ancestors()->first()->attr('data-visible-when'),
        );
        $this->assertSame('ollama', $crawler->filter('#aiUrl')->ancestors()->first()->attr('data-visible-when'));
        $this->assertSame('openAILike', $crawler->filter('#aiBaseUri')->ancestors()->first()->attr('data-visible-when'));
        $this->assertSame('azureOpenAI', $crawler->filter('#aiEndpoint')->ancestors()->first()->attr('data-visible-when'));

        // The settings navigation, with "Integrations" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Integrations', $selectedLink->text());
    }
}
