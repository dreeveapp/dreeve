# AI assistant

Dreeve ships with an AI-powered workout assistant. To use it, you configure an AI provider under
**Settings → Integrations**.

> [!WARNING]
> **Warning** Use caution when enabling this feature if your app is publicly accessible.

> [!IMPORTANT]
> **Important** Dreeve uses the <a href="https://docs.neuron-ai.dev">Neuron AI</a> library to interface with AI models.
> Only providers supported by Neuron AI are compatible. See the full list of <a href="https://docs.neuron-ai.dev/providers/ai-provider">supported providers</a>

## Cloud providers

To use a cloud-based AI provider you need an API key and the name of the model you want. Configure the key in the
`AI_API_KEY` environment variable, then pick the provider and enter the model name in the settings.

## Locally hosted Ollama

You can also run a local model using Ollama. Start by configuring a Docker container for Ollama:

```yaml
services:
    ollama:
        image: ollama/ollama:latest
        container_name: 'dreeve-ollama'
        tty: true
        restart: unless-stopped
        volumes:
            - .:/code
            - ./ollama:/root/.ollama
        environment:
            - OLLAMA_KEEP_ALIVE=24h
            - OLLAMA_HOST=0.0.0.0
        ports:
            - '11434:11434'
        networks:
            - dreeve-network
```

> [!TIP]
> **Tip**  Looking for more advanced setups?
> Check out this <a href="https://github.com/mythrantic/ollama-docker">Ollama Docker GitHub repository</a>.

Next, download the model you want to use. For example, to run `llama3.2`:

```bash
> docker compose exec ollama ollama pull llama3.2
```

Finally, enable the integration, choose the `ollama` provider, and set the model to the one you pulled.

> [!IMPORTANT]
> **Important** Make sure you're running the latest version of Ollama. Streaming responses with tooling has been <a href="https://ollama.com/blog/streaming-tool">added on May 28, 2025.</a>

## OpenRouter

[OpenRouter](https://openrouter.ai) provides access to hundreds of models (OpenAI, Anthropic, Google and more) through a single API key, including free-tier models. This makes it easy to get started or test different models.

To use OpenRouter, pick the **openAILike** provider under **Settings → Integrations** and set:

* **Base URI**: `https://openrouter.ai/api/v1`
* **Model**: e.g. `anthropic/claude-sonnet-4.5`. See [openrouter.ai/models](https://openrouter.ai/models).

and put your OpenRouter API key in `AI_API_KEY` in your `.env`.

> [!TIP]
> OpenRouter offers [free models](https://openrouter.ai/collections/free) which are great for testing without any cost.

## Your AI workout assistant

### Via the CLI

```bash
> docker compose exec app bin/console app:ai:agent-chat
```

This will prompt you with a message like the following:

![Mark example](../assets/images/mark-example.png) 

### Via the UI

[Virtual AI assistant](https://www.youtube.com/embed/d1r8ISbRL5o ':include :type=iframe width=100% height=400px title="Dreeve" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen')

### Pre-defining chat commands

The app allows you to pre-define chat commands that can be used by the AI assistant. This is useful for questions that might be asked frequently, such as

> Please analyze my most recent ride with regard to aspects such as heart rate, power (if available). Please give me an assessment of my performance level and possible improvements for future training sessions?

Each command has a **name** and the **message** it expands to. For example:

| Command | Message |
|---|---|
| `analyse-last-workout` | *You are my bike trainer. Please analyze my most recent ride with regard to aspects such as heart rate, power (if available). Please give me an assessment of my performance level and possible improvements for future training sessions.* |
| `compare-last-two-weeks` | *You are my bike trainer. Please compare my workouts and performance of the last 7 days with the 7 days before and give a short assessment.* |

You can then use `/analyse-last-workout` and `/compare-last-two-weeks` in your chat with the AI assistant:

![Mark chat commands](../assets/images/mark-chat-commands.png)