<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** ریشه سایت به ورود پرتال مشتری می‌رود — نه صفحه پیش‌فرض لاراول. */
    public function test_root_redirects_to_portal_login(): void
    {
        $this->get('/')->assertRedirect(route('portal.login'));
    }
}
