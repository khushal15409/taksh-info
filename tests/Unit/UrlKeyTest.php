<?php

namespace Tests\Unit;

use App\Support\UrlKey;
use Tests\TestCase;

class UrlKeyTest extends TestCase
{
    public function test_a_key_round_trips_to_the_same_id(): void
    {
        foreach ([0, 1, 2, 12, 99999, 4294967295] as $id) {
            $this->assertSame($id, UrlKey::decode('shop', UrlKey::encode('shop', $id)));
        }
    }

    public function test_neighbouring_ids_do_not_look_alike_and_the_key_hides_the_number(): void
    {
        $a = UrlKey::encode('order', 1001);
        $b = UrlKey::encode('order', 1002);

        $this->assertNotSame($a, $b);
        $this->assertSame(UrlKey::LENGTH, strlen($a));
        $this->assertStringNotContainsString('1001', $a);
    }

    public function test_a_key_only_works_for_its_own_scope(): void
    {
        $this->assertNull(UrlKey::decode('order', UrlKey::encode('product', 5)));
    }

    public function test_an_edited_or_made_up_key_is_rejected(): void
    {
        $key = UrlKey::encode('shop', 5);
        $edited = substr($key, 0, -1).($key[-1] === 'a' ? 'b' : 'a');

        $this->assertNull(UrlKey::decode('shop', $edited));
        $this->assertNull(UrlKey::decode('shop', 'aaaaaaaaaaa'));
        $this->assertNull(UrlKey::decode('shop', '12'));
        $this->assertNull(UrlKey::decode('shop', ''));
    }

    public function test_a_slug_in_front_is_decoration_only(): void
    {
        $slug = UrlKey::slug('shop', 7, 'RELISH Store & Co!');

        $this->assertStringStartsWith('relish-store-co-', $slug);
        $this->assertSame(7, UrlKey::decode('shop', $slug));
        $this->assertSame(7, UrlKey::decode('shop', 'something-else-'.UrlKey::encode('shop', 7)));
        $this->assertSame(UrlKey::encode('shop', 7), UrlKey::slug('shop', 7, null));
    }
}
