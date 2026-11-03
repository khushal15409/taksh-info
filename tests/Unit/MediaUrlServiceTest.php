<?php

namespace Tests\Unit;

use App\Services\MediaUrlService;
use Tests\TestCase;

class MediaUrlServiceTest extends TestCase
{
    public function test_full_urls_are_kept_and_paths_are_prefixed(): void
    {
        config(['taksh.media_url' => 'https://media.test/storage/']);
        $media = new MediaUrlService;

        $this->assertNull($media->url(null, 'banner'));
        $this->assertNull($media->url('', 'banner'));
        $this->assertSame('https://cdn.test/a.jpg', $media->url('https://cdn.test/a.jpg', 'banner'));
        $this->assertSame('https://media.test/storage/banner/a.jpg', $media->url('a.jpg', 'banner'));
        $this->assertSame('https://media.test/storage/banner/x/a.jpg', $media->url('/x/a.jpg', '/banner/'));
    }
}
