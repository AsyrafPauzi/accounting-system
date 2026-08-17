<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\IlmuClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IlmuClientTest extends TestCase
{
    public function test_chat_posts_openai_compatible_payload_with_bearer_auth(): void
    {
        Http::fake([
            IlmuClient::CHAT_URL => Http::response([
                'choices' => [[
                    'message' => ['content' => 'ok', 'tool_calls' => []],
                ]],
            ], 200),
        ]);

        $client = new IlmuClient('sk-test-key', 'ilmu-v3.1');
        $tools = [[
            'type' => 'function',
            'function' => ['name' => 'overdue_invoices', 'description' => 'x', 'parameters' => ['type' => 'object', 'properties' => new \stdClass]],
        ]];

        $payload = $client->chat([
            ['role' => 'user', 'content' => 'siapa overdue?'],
        ], $tools);

        $this->assertSame('ok', IlmuClient::messageText($payload));

        Http::assertSent(function ($request) {
            return $request->url() === IlmuClient::CHAT_URL
                && $request->hasHeader('Authorization', 'Bearer sk-test-key')
                && $request['model'] === 'ilmu-v3.1'
                && isset($request['tools'])
                && $request['messages'][0]['content'] === 'siapa overdue?';
        });
    }

    public function test_vision_sends_data_uri_image_url(): void
    {
        Http::fake([
            IlmuClient::CHAT_URL => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"vendor_name":"Kedai"}'],
                ]],
            ], 200),
        ]);

        $client = new IlmuClient('sk-test-key');
        $client->vision('parse', 'PNGDATA', 'image/png', jsonMode: true);

        Http::assertSent(function ($request) {
            $content = $request['messages'][0]['content'];

            return $request['response_format']['type'] === 'json_object'
                && $content[1]['type'] === 'image_url'
                && str_starts_with($content[1]['image_url']['url'], 'data:image/png;base64,');
        });
    }
}
