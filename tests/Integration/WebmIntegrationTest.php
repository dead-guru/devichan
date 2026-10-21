<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class WebmIntegrationTest extends TestCase
{
    public function testNativeParserReadsVideoAndBuildsAThumbnailFrame(): void
    {
        require_once 'inc/lib/webm/videodata.php';

        $source = 'tests/_output/integration-video.webm';
        exec(
            'ffmpeg -hide_banner -loglevel error -f lavfi -i color=c=blue:s=32x24:d=0.2'
            . ' -c:v libvpx -colorspace bt709 -color_primaries bt709 -color_trc bt709 -color_range tv'
            . ' -an -y ' . escapeshellarg($source) . ' 2>&1',
            $output,
            $status,
        );
        self::assertSame(0, $status, implode("\n", $output));

        $video = \videoData($source);
        self::assertSame('webm', $video['container']);
        self::assertSame(32, $video['width']);
        self::assertSame(24, $video['height']);
        self::assertEqualsWithDelta(0.2, $video['duration'], 0.001);
        self::assertNotEmpty($video['frame']);

        $thumbnail = 'tests/_output/integration-video-thumb.webm';
        file_put_contents($thumbnail, $video['frame']);
        $frame = \videoData($thumbnail);
        self::assertSame('webm', $frame['container']);
        self::assertSame(32, $frame['width']);
        self::assertSame(24, $frame['height']);
    }
}
