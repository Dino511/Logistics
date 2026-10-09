<?php

namespace Tests\Feature;

use App\Enums\Role;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class LocationDetectTest extends TestCase
{
    use MigratesCoreTables;

    private function detect(string $text): array
    {
        return $this->actingAs($this->user(Role::LogisticsCoordinator))
            ->getJson('/locations/detect?'.http_build_query(['text' => $text]))
            ->assertOk()->json();
    }

    public function test_a_city_named_in_the_address_is_the_one_clear_match(): void
    {
        $places = $this->detect('sta. anna, manila');

        $this->assertSame(['city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000', 'exact' => true], $places[0]);
        $this->assertCount(1, array_filter($places, fn ($p) => $p['exact']));
    }

    public function test_a_full_city_name_beats_a_partial_one_and_accents_are_ignored(): void
    {
        // "Roxas" alone could be Roxas City, but Manila is named in full.
        $places = $this->detect('Roxas Blvd, Manila');
        $this->assertSame('Manila', $places[0]['city']);
        $this->assertSame(['Manila'], array_column(array_filter($places, fn ($p) => $p['exact']), 'city'));

        $this->assertSame('Las Piñas', $this->detect('12 Real St, las pinas')[0]['city']);
    }

    public function test_a_name_shared_by_two_provinces_gives_several_matches_to_choose_from(): void
    {
        $exact = array_filter($this->detect('123 Rizal St, San Fernando'), fn ($p) => $p['exact']);

        $this->assertEqualsCanonicalizing(['Pampanga', 'La Union'], array_column($exact, 'province'));
    }

    public function test_a_province_alone_suggests_its_cities_and_an_unknown_address_gives_nothing(): void
    {
        $this->assertSame('Batangas City', $this->detect('batangas')[0]['city']);
        $this->assertContains('Lipa', array_column($this->detect('batangas'), 'city'));
        $this->assertSame([], $this->detect('dito lang'));
    }

    public function test_guests_cannot_use_it(): void
    {
        $this->getJson('/locations/detect?text=manila')->assertUnauthorized();
    }
}
