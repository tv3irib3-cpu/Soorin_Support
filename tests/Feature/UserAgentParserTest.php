<?php

namespace Tests\Feature;

use App\Support\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentParserTest extends TestCase
{
    public function test_chrome_on_windows_desktop(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
        $p  = UserAgent::parse($ua);

        $this->assertSame('Chrome 128', $p['browser']);
        $this->assertSame('Windows 10/11', $p['platform']);
        $this->assertSame('desktop', $p['device']);
        $this->assertFalse($p['is_robot']);
    }

    public function test_safari_on_iphone_is_mobile(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $p  = UserAgent::parse($ua);

        $this->assertStringStartsWith('Safari', $p['browser']);
        $this->assertSame('iOS', $p['platform']);
        $this->assertSame('mobile', $p['device']);
    }

    public function test_edge_is_detected_before_chrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.0.0';
        $p  = UserAgent::parse($ua);

        $this->assertSame('Edge 128', $p['browser']);
    }

    public function test_android_tablet_vs_mobile(): void
    {
        $tablet = UserAgent::parse('Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 Chrome/120.0 Safari/537.36');
        $this->assertSame('tablet', $tablet['device']);   // اندروید بدون Mobile → تبلت
        $this->assertSame('Android', $tablet['platform']);

        $phone = UserAgent::parse('Mozilla/5.0 (Linux; Android 13; Pixel 8 Mobile) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36');
        $this->assertSame('mobile', $phone['device']);
    }

    public function test_googlebot_is_robot(): void
    {
        $p = UserAgent::parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $this->assertTrue($p['is_robot']);
        $this->assertSame('bot', $p['device']);
    }

    public function test_empty_user_agent(): void
    {
        $p = UserAgent::parse('');

        $this->assertNull($p['browser']);
        $this->assertNull($p['platform']);
        $this->assertSame('unknown', $p['device']);
    }
}
