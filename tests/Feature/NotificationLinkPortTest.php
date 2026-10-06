<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AiInsightNotification;
use App\Support\NotificationUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationLinkPortTest extends TestCase
{
    private const LEGACY_URL = 'http://localhost:8000/ai/intelligence?tab=risks#top';

    public function test_relative_strips_scheme_host_and_port(): void
    {
        $this->assertSame('/ai/actions/12', NotificationUrl::relative('http://localhost:8000/ai/actions/12'));
        $this->assertSame('/ai/actions/12', NotificationUrl::relative('https://127.0.0.1:7644/ai/actions/12'));
        $this->assertSame('/ai/actions/12?x=1#y', NotificationUrl::relative('http://127.0.0.1:9823/ai/actions/12?x=1#y'));
        $this->assertSame('/', NotificationUrl::relative('http://localhost:8000'));
        $this->assertSame('/ai/intelligence', NotificationUrl::relative('/ai/intelligence'));
        $this->assertSame('#', NotificationUrl::relative('#'));
        $this->assertNull(NotificationUrl::relative(null));
    }

    public function test_no_stored_notification_holds_an_absolute_url(): void
    {
        $absolute = [];

        foreach (DB::table('notifications')->orderBy('id')->get() as $row) {
            $url = data_get(json_decode($row->data, true), 'url');

            if (is_string($url) && preg_match('#^https?://#i', $url)) {
                $absolute[] = $row->id.' => '.$url;
            }
        }

        $this->assertSame([], $absolute, 'Stored notifications must use relative URLs.');
    }

    public function test_notification_links_render_relative_regardless_of_app_url(): void
    {
        config(['app.url' => 'http://localhost:9999']);

        $user = User::find(4);

        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get('/notifications');

        $response->assertOk();

        $this->assertStringNotContainsString('localhost:8000', $response->getContent());
        $this->assertStringNotContainsString('localhost:9999', $response->getContent());

        preg_match_all('/href="([^"]*)"[^>]*class="btn btn-sm btn-outline-secondary"/', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1], 'No notification "Open" links were rendered.');

        foreach ($matches[1] as $href) {
            $this->assertStringStartsWith('/', $href);
            $this->assertDoesNotMatchRegularExpression('#^https?://#i', $href);
        }
    }

    public function test_legacy_absolute_row_is_rendered_as_relative_link(): void
    {
        $user = User::find(4);

        $notificationId = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => AiInsightNotification::class,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => json_encode([
                'title' => 'Legacy absolute link',
                'summary' => 'Row stored before the port-agnostic fix.',
                'severity' => 'warning',
                'url' => self::LEGACY_URL,
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $response = $this->actingAs($user)->get('/notifications');

            $response->assertOk();

            $this->assertStringContainsString('href="/ai/intelligence?tab=risks#top"', $response->getContent());
            $this->assertStringNotContainsString('href="'.self::LEGACY_URL.'"', $response->getContent());
        } finally {
            DB::table('notifications')->where('id', $notificationId)->delete();
        }
    }
}
