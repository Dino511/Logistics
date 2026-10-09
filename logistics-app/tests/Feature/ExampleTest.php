<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** The home page just forwards to the dashboard (which then asks guests to sign in). */
    public function test_home_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/shipments')->assertRedirect('/login');
        $this->get('/reports/activity-logs')->assertRedirect('/login');
    }
}
