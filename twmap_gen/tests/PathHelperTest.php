<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * 路徑轉換 helpers 的行為契約 (map_rel_path / map_fs_path / map_url_path / map_url)
 *
 * DB 現在存「相對路徑」, 過渡期仍有歷史絕對路徑, 這些函式是兩者之間唯一的橋樑。
 */
final class PathHelperTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['out_roots', 'fs_root', 'out_root', 'out_html_root', 'site_url'] as $k) {
            $this->saved[$k] = $GLOBALS[$k] ?? null;
        }
    }

    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->rrmdir($dir);
        }
        $this->tempDirs = [];

        foreach ($this->saved as $k => $v) {
            if ($v === null) {
                unset($GLOBALS[$k]);
            } else {
                $GLOBALS[$k] = $v;
            }
        }
        parent::tearDown();
    }

    private function useRoots(string $newRoot, string ...$legacy): void
    {
        $GLOBALS['fs_root'] = $newRoot;
        $GLOBALS['out_roots'] = array_values(array_merge([$newRoot], $legacy));
    }

    // ---- map_rel_path ----------------------------------------------------

    public function testRelPathStripsEveryKnownRoot(): void
    {
        $rel = '00/03/000003/4/307000x2677000-12x6-v3.tag.png';
        foreach (['/srv/www/htdocs/map/out', '/home/happyman/map/out', '/home/nas/twmapcache/tmp/out'] as $root) {
            $this->assertSame($rel, map_rel_path($root . '/' . $rel), "root=$root");
        }
    }

    public function testRelPathKeepsAlreadyRelativePaths(): void
    {
        $this->assertSame('00/03/000003/4/a.tag.png', map_rel_path('00/03/000003/4/a.tag.png'));
        $this->assertSame('00/65/track/7', map_rel_path('00/65/track/7/'));
    }

    public function testRelPathReturnsNullForUnknownAbsolutePrefix(): void
    {
        $this->assertNull(map_rel_path('/somewhere/else/a.tag.png'));
        $this->assertNull(map_rel_path('/home/happyman/map/outside/a.tag.png'));
    }

    public function testRelPathEdgeCases(): void
    {
        $this->assertNull(map_rel_path(null));
        $this->assertNull(map_rel_path(''));
        $this->assertNull(map_rel_path('/'));
        $this->assertSame('', map_rel_path('/srv/www/htdocs/map/out'));
        $this->assertSame('', map_rel_path('/srv/www/htdocs/map/out/'));
        // 前綴後面必須是 '/' , 不能誤抓 /out-other
        $this->assertNull(map_rel_path('/srv/www/htdocs/map/out-other/a.tag.png'));
    }

    // ---- map_fs_path -----------------------------------------------------

    public function testFsPathPrefixesFsRootForRelativeInput(): void
    {
        $GLOBALS['fs_root'] = '/new/root';
        $this->assertSame('/new/root/00/03/4/a.tag.png', map_fs_path('00/03/4/a.tag.png'));
    }

    public function testFsPathPassesUnknownPrefixThrough(): void
    {
        $this->assertSame('/somewhere/else/a.tag.png', map_fs_path('/somewhere/else/a.tag.png'));
    }

    public function testFsPathPrefersNewRootWhenFileExistsThere(): void
    {
        $legacy = $this->tempDir('legacy');
        $fresh  = $this->tempDir('fresh');
        $rel = '00/03/4/a.tag.png';
        $this->put($legacy . '/' . $rel, 'old');
        $this->put($fresh . '/' . $rel, 'new');
        $this->useRoots($fresh, $legacy);

        $this->assertSame($fresh . '/' . $rel, map_fs_path($rel));
    }

    public function testFsPathFallsBackToLegacyRootDuringMigration(): void
    {
        $legacy = $this->tempDir('legacy');
        $fresh  = $this->tempDir('fresh');
        $rel = '00/03/4/a.tag.png';
        $this->put($legacy . '/' . $rel, 'old');
        $this->useRoots($fresh, $legacy);

        // 新根還沒檔案 -> 必須回歷史根, 否則過渡期全部 404
        $this->assertSame($legacy . '/' . $rel, map_fs_path($rel));
        // 新根找不到時仍回傳預期位置, 讓呼叫端用 file_exists 判定
        $this->assertSame($fresh . '/00/03/9/nope.tag.png', map_fs_path('00/03/9/nope.tag.png'));
    }

    // ---- map_url_path / map_url -----------------------------------------

    public function testUrlPathBuildsHtmlRoot(): void
    {
        $GLOBALS['out_html_root'] = '/out';
        $this->assertSame('/out/00/03/4/a.tag.png', map_url_path('00/03/4/a.tag.png'));
        $this->assertSame('/out/00/03/4/a.tag.png', map_url_path('/srv/www/htdocs/map/out/00/03/4/a.tag.png'));
        $this->assertSame('/out', map_url_path('/srv/www/htdocs/map/out/'));
        $this->assertSame('/somewhere/else/a.tag.png', map_url_path('/somewhere/else/a.tag.png'));
    }

    public function testUrlPrefixesSiteUrlAndLeavesAbsoluteUrlsAlone(): void
    {
        $GLOBALS['site_url'] = 'https://dev.happyman.idv.tw';
        $GLOBALS['out_html_root'] = '/out';
        $this->assertSame('https://dev.happyman.idv.tw/out/00/03/4/a.tag.png', map_url('00/03/4/a.tag.png'));
        $this->assertSame('https://cdn.example.com/x.png', map_url('https://cdn.example.com/x.png'));
    }

    // ---- 檔案清單 ---------------------------------------------------------

    public function testMapFilesGlobsByPrefix(): void
    {
        $root = $this->tempDir('fs');
        $this->useRoots($root);
        $dir = $root . '/00/03/4';
        $prefix = '307000x2677000-12x6-v3';
        foreach (['.tag.png', '.gpx', '_01.png'] as $suffix) {
            $this->put("$dir/$prefix$suffix", 'x');
        }
        $this->put("$dir/readme.txt", 'not mine');

        $files = map_files('00/03/4/' . $prefix . '.tag.png');

        $this->assertCount(3, $files);
        $this->assertSame([
            "$dir/{$prefix}.gpx",
            "$dir/{$prefix}.tag.png",
            "$dir/{$prefix}_01.png",
        ], $files);
    }

    public function testMapFilesReturnsEmptyArrayForUnrecognisedName(): void
    {
        $this->assertSame([], map_files('/somewhere/else/notes.txt'));
    }

    public function testMapFileNameMapsExtensions(): void
    {
        $this->useRoots('/new/root');
        $src = '00/03/4/307000x2677000-12x6-v3.tag.png';
        $base = '/new/root/00/03/4/307000x2677000-12x6-v3';

        $this->assertSame($base . '.pdf', map_file_name($src, 'pdf'));
        // str_replace('.png', ...) 只換副檔名, .tag 會留下來 -> .tag.kmz / .tag.tiff
        $this->assertSame($base . '.tag.kmz', map_file_name($src, 'kmz'));
        $this->assertSame($base . '.txt', map_file_name($src, 'txt'));
        $this->assertSame($base . '.gpx', map_file_name($src, 'gpx'));
        $this->assertSame($base . '.tag.tiff', map_file_name($src, 'tiff'));
        $this->assertSame($base . '.tag.png', map_file_name($src, 'image'));
    }

    // ---- root 設定 ---------------------------------------------------------

    public function testRootHelpersFollowConfig(): void
    {
        $GLOBALS['fs_root'] = '/new/root';
        $GLOBALS['out_roots'] = ['/old/a', '/old/b'];

        $this->assertSame('/new/root', map_fs_root());
        $this->assertSame(['/old/a', '/old/b'], map_roots());
    }

    public function testFsRootFallsBackToOutRoot(): void
    {
        $GLOBALS['fs_root'] = '';
        $GLOBALS['out_root'] = '/srv/www/htdocs/map/out';
        $this->assertSame('/srv/www/htdocs/map/out', map_fs_root());
    }

    public function testHashDirLayout(): void
    {
        $this->assertSame('00/03', gethashdir(3));
        $this->assertSame('00/35', gethashdir(53));
        $this->assertSame('03/af', gethashdir(943));
        $this->assertSame('03/e8', gethashdir(1000));
    }

    // ---- helpers -----------------------------------------------------------

    private function tempDir(string $tag): string
    {
        $dir = sys_get_temp_dir() . '/twmap_test_' . $tag . '_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function put(string $path, string $body): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $body);
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
