<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/expirelib.inc.php';

/**
 * lib/expirelib.inc.php 的行為契約
 *
 * 規則判定 (與 api/migrate.php check 同一套) / 檔案家族掃描 /
 * expire 候選排序與預算計算 / flock 與狀態檔。
 * 這些都不該碰 DB, 所以可以直接在 temp dir 上驗。
 */
final class ExpireTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $saved = [];

    /** @var string[] */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['out_roots', 'fs_root', 'out_root', 'out_html_root', 'site_url'] as $k) {
            $this->saved[$k] = $GLOBALS[$k] ?? null;
        }
    }

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

    // ---- 規則判定 ----------------------------------------------------------

    public function testActiveRowWithoutFilesHitsR1(): void
    {
        $row = ['flag' => 0, 'gpx' => 0, 'ddate' => null, 'edate' => null, 'size' => 0];
        $fam = ['any' => false, 'bytes' => 0, 'gpx_ext' => false];

        $this->assertSame(['R1.1', 'R1.2'], expire_eval_rules($row, false, $fam));
        // 檔案在, 只是 src 檔名本身不見 -> 只有 R1.1
        $this->assertSame(['R1.1'], expire_eval_rules($row, false, ['any' => true, 'bytes' => 1, 'gpx_ext' => false]));
        // src 在但家族空 = R1.2
        $this->assertSame(['R1.2'], expire_eval_rules($row, true, $fam));
        $this->assertSame([], expire_eval_rules($row, true, ['any' => true, 'bytes' => 1, 'gpx_ext' => false]));
    }

    public function testActiveRowWithTimestampsHitsR13R14(): void
    {
        $base = ['flag' => 0, 'gpx' => 0, 'size' => 0];
        $fam = ['any' => true, 'bytes' => 1, 'gpx_ext' => false];

        $this->assertSame(['R1.3'], expire_eval_rules($base + ['ddate' => '2026-01-01', 'edate' => null], true, $fam));
        $this->assertSame(['R1.4'], expire_eval_rules($base + ['ddate' => null, 'edate' => '2026-01-01'], true, $fam));
        $this->assertSame(
            ['R1.3', 'R1.4'],
            expire_eval_rules($base + ['ddate' => '2026-01-01', 'edate' => '2026-01-02'], true, $fam)
        );
    }

    public function testExpiredRowRules(): void
    {
        $withFile = ['any' => true, 'bytes' => 10, 'gpx_ext' => false];
        $nothing = ['any' => false, 'bytes' => 0, 'gpx_ext' => false];

        // flag=1 一定要有 edate
        $this->assertSame(['R2.1'], expire_eval_rules(['flag' => 1, 'gpx' => 0, 'edate' => null], true, $nothing));
        // gpx=0 卻還留檔 = 殘檔
        $this->assertSame(['R2.2'], expire_eval_rules(['flag' => 1, 'gpx' => 0, 'edate' => 'e'], true, $withFile));
        // gpx=1 但家族沒有 gpx 檔 = INFO (map_expire 刻意只留 gpx)
        $this->assertSame(['R2.3'], expire_eval_rules(['flag' => 1, 'gpx' => 1, 'edate' => 'e'], true, $withFile));
        $this->assertSame(
            [],
            expire_eval_rules(['flag' => 1, 'gpx' => 1, 'edate' => 'e'], true, ['any' => true, 'bytes' => 5, 'gpx_ext' => true])
        );
    }

    public function testDeletedRowRules(): void
    {
        $this->assertSame(
            ['R3.1', 'R3.2'],
            expire_eval_rules(['flag' => 2, 'gpx' => 0, 'ddate' => null], true, ['any' => true, 'bytes' => 3, 'gpx_ext' => false])
        );
        $this->assertSame(
            [],
            expire_eval_rules(['flag' => 2, 'gpx' => 0, 'ddate' => 'd'], true, ['any' => false, 'bytes' => 0, 'gpx_ext' => false])
        );
    }

    public function testSizeRuleOnlyWhenAskedAndOverThreshold(): void
    {
        $row = ['flag' => 0, 'gpx' => 0, 'ddate' => null, 'edate' => null, 'size' => 100];
        $big = ['any' => true, 'bytes' => 100 + 67108865, 'gpx_ext' => false];
        $ok = ['any' => true, 'bytes' => 100 + 67108864, 'gpx_ext' => false];

        // 預設關閉 (migrate check 沒撈 size 欄位)
        $this->assertNotContains('R4.1', expire_eval_rules($row, true, $big));
        $this->assertContains('R4.1', expire_eval_rules($row, true, $big, true));
        // 剛好 64MB 不算
        $this->assertNotContains('R4.1', expire_eval_rules($row, true, $ok, true));
        // DB size 高估同樣要報 (+ 不會覆寫既有 key, 得自己換列)
        $under = ['any' => true, 'bytes' => 1, 'gpx_ext' => false];
        $stale = $row;
        $stale['size'] = 67108866;
        $this->assertContains('R4.1', expire_eval_rules($stale, true, $under, true));
    }

    public function testRuleCatalogue(): void
    {
        $base = expire_rule_desc();
        $full = expire_rule_desc(true);

        $this->assertArrayHasKey('R1.1', $base);
        $this->assertArrayHasKey('R3.2', $base);
        $this->assertArrayNotHasKey('R4.1', $base);
        $this->assertArrayHasKey('R4.1', $full);
        $this->assertTrue(expire_rule_is_info('R2.3'));
        $this->assertTrue(expire_rule_is_info('R4.1'));
        $this->assertFalse(expire_rule_is_info('R1.1'));
        $this->assertFalse(expire_rule_is_info('R3.2'));
    }

    // ---- 檔案家族 ----------------------------------------------------------

    public function testRowFamilySumsExistingFiles(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $dir = $root . '/00/03/4';
        $prefix = '307000x2677000-12x6-v3';
        $this->put("$dir/$prefix.tag.png", str_repeat('a', 10));
        $this->put("$dir/$prefix.pdf", str_repeat('b', 20));
        $this->put("$dir/readme.txt", 'not mine');

        $fam = expire_row_family('00/03/4/' . $prefix . '.tag.png');

        $this->assertTrue($fam['any']);
        $this->assertCount(2, $fam['exists']);
        $this->assertSame(30, $fam['bytes']);
        $this->assertSame(30, $fam['bytes_nongpx']);
        $this->assertFalse($fam['gpx_ext']);
    }

    public function testRowFamilyExcludesKeptGpxFromFreedBytes(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $dir = $root . '/00/03/4';
        $prefix = '307000x2677000-12x6-v3';
        $this->put("$dir/$prefix.tag.png", str_repeat('a', 10));   // map_expire 會刪
        $this->put("$dir/$prefix.gpx", str_repeat('b', 50));       // 小寫 .gpx 保留
        $this->put("$dir/$prefix.GPX", str_repeat('c', 70));       // strstr('.gpx') 不中 -> 會刪

        $fam = expire_row_family('00/03/4/' . $prefix . '.tag.png');

        $this->assertSame(130, $fam['bytes']);
        $this->assertSame(80, $fam['bytes_nongpx']);
        $this->assertTrue($fam['gpx_ext']);
    }

    public function testRowFamilyFallsBackToTagPngWhenNameUnrecognised(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $this->put($root . '/somewhere/else/notes.txt', str_repeat('x', 4));

        // 副檔名不合 \d+x\d+-\d+x\d+ 的檔, map_files 不認 -> 回落 tag.png 路徑
        $fam = expire_row_family('somewhere/else/notes.txt');

        $this->assertTrue($fam['any']);
        $this->assertSame(4, $fam['bytes']);
        $this->assertSame(4, $fam['bytes_nongpx']);
    }

    public function testRowFamilyOfMissingFileIsEmpty(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $fam = expire_row_family('00/03/4/307000x2677000-12x6-v3.tag.png');

        $this->assertFalse($fam['any']);
        $this->assertSame(0, $fam['bytes']);
        $this->assertSame([], $fam['exists']);
    }

    // ---- 候選排序 ----------------------------------------------------------

    private function activeRow(int $mid, array $over = []): array
    {
        return $over + [
            'mid' => $mid,
            'uid' => 1,
            'count' => 0,
            'cdate_ts' => 1_000_000,
            'family' => ['bytes' => 100, 'bytes_nongpx' => 100, 'any' => true],
        ];
    }

    public function testCandidatesSortOldestFirst(): void
    {
        $rows = [
            3 => $this->activeRow(3, ['cdate_ts' => 300]),
            1 => $this->activeRow(1, ['cdate_ts' => 100]),
            2 => $this->activeRow(2, ['cdate_ts' => 200]),
        ];

        $cands = expire_candidates($rows, 10_000_000, ['min_days' => 0, 'protect_count' => 0, 'protect_uids' => []]);

        $this->assertSame([1, 2, 3], array_keys($cands));
        $this->assertSame(100, $cands[1]);
    }

    public function testCandidatesTieBreakByMid(): void
    {
        $rows = [
            9 => $this->activeRow(9, ['cdate_ts' => 100]),
            4 => $this->activeRow(4, ['cdate_ts' => 100]),
            7 => $this->activeRow(7, ['cdate_ts' => 100]),
        ];

        $cands = expire_candidates($rows, 10_000_000, ['min_days' => 0, 'protect_count' => 0, 'protect_uids' => []]);

        $this->assertSame([4, 7, 9], array_keys($cands));
    }

    public function testCandidatesApplyMinDaysUidAndCountProtections(): void
    {
        $now = 1_000_000_000;
        $rows = [
            1 => $this->activeRow(1, ['cdate_ts' => $now - 40 * 86400]),                 // 舊圖, 可刪
            2 => $this->activeRow(2, ['cdate_ts' => $now - 5 * 86400]),                  // 新圖
            3 => $this->activeRow(3, ['cdate_ts' => $now - 400 * 86400, 'count' => 500]), // 熱門
            4 => $this->activeRow(4, ['cdate_ts' => $now - 400 * 86400, 'uid' => 3]),     // 管理員
            5 => $this->activeRow(5, ['cdate_ts' => $now - 400 * 86400]),
        ];

        $cands = expire_candidates($rows, $now, [
            'min_days' => 30,
            'protect_count' => 100,
            'protect_uids' => expire_parse_uids('3'),
        ]);

        // 5 最舊, 1 次之 (其餘被 min-days / count / uid 保護)
        $this->assertSame([5, 1], array_keys($cands));
    }

    public function testCandidatesSkipRowsWithoutFiles(): void
    {
        $rows = [
            1 => $this->activeRow(1, ['family' => ['bytes' => 0, 'bytes_nongpx' => 0, 'any' => false]]),
            2 => $this->activeRow(2),
        ];

        $cands = expire_candidates($rows, 10_000_000, ['min_days' => 0, 'protect_count' => 0, 'protect_uids' => []]);

        $this->assertSame([2], array_keys($cands));
    }

    public function testParseUids(): void
    {
        $this->assertSame([3 => true], expire_parse_uids('3'));
        $this->assertSame([3 => true, 67441 => true], expire_parse_uids('3,67441'));
        $this->assertSame([], expire_parse_uids(''));
        $this->assertSame([], expire_parse_uids(null));
        $this->assertSame([0 => true], expire_parse_uids(' 0 '));
    }

    // ---- 預算計算 ----------------------------------------------------------

    public function testPlanExpiresOldestUntilUnderCap(): void
    {
        // 5 列各 10GB (bytes_nongpx), 總量 50GB, 預算 25GB
        $cand = [1 => 10, 2 => 10, 3 => 10, 4 => 10, 5 => 10];

        $plan = expire_plan(50, 25, $cand);

        $this->assertSame([1, 2, 3], $plan['mids']);
        $this->assertSame(30, $plan['freed']);
        $this->assertSame(20, $plan['remaining']);
        $this->assertTrue($plan['ok']);
    }

    public function testPlanDoesNothingWhenUnderCap(): void
    {
        $plan = expire_plan(10, 25, [1 => 10]);

        $this->assertSame([], $plan['mids']);
        $this->assertSame(0, $plan['freed'], '預算內不該挑任何列');
        $this->assertSame(10, $plan['remaining']);
        $this->assertTrue($plan['ok']);
    }

    public function testPlanHonorsMaxExpire(): void
    {
        $cand = [1 => 10, 2 => 10, 3 => 10, 4 => 10];

        $plan = expire_plan(40, 5, $cand, 2);

        $this->assertSame([1, 2], $plan['mids']);
        $this->assertSame(20, $plan['remaining']);
        $this->assertFalse($plan['ok']);   // 受 --max-expire 限制, 沒降到預算內
    }

    public function testPlanReportsWhenProtectedRowsBlockCap(): void
    {
        // 只有第 2 列可刪, 刪完 40 - 10 = 30 還是超過 25
        $plan = expire_plan(40, 25, [2 => 10]);

        $this->assertSame([2], $plan['mids']);
        $this->assertSame(30, $plan['remaining']);
        $this->assertFalse($plan['ok']);
    }

    public function testPlanObeysCandidateOrder(): void
    {
        // 候選順序決定誰先被犧牲, plan 不再自行排序
        $plan = expire_plan(30, 20, [7 => 5, 3 => 5, 9 => 5]);

        $this->assertSame([7, 3], $plan['mids']);
    }

    // ---- lock / state ------------------------------------------------------

    public function testLockBlocksSecondAcquireUntilReleased(): void
    {
        $lockfile = $this->tempDir('lock') . '/expire.lock';

        $first = expire_lock_acquire($lockfile);
        $this->assertTrue($first['ok'], '第一次取鎖要成功');

        $second = expire_lock_acquire($lockfile);
        $this->assertFalse($second['ok'], '前一次沒跑完時不能取到鎖');
        $this->assertTrue(is_array($second['holder']));
        $this->assertSame(getmypid(), $second['holder']['pid']);
        $this->assertArrayHasKey('started', $second['holder']);

        $this->assertTrue(expire_lock_release($first));

        $third = expire_lock_acquire($lockfile);
        $this->assertTrue($third['ok'], '釋放後要能再取到');
        expire_lock_release($third);
    }

    public function testLockAcquireCreatesLockFile(): void
    {
        $lockfile = $this->tempDir('lock') . '/nested/expire.lock';
        @rmdir(dirname($lockfile));   // 確保不存在

        // fopen "c" 不會自動建目錄, 沒有目錄就取不到 -> 回報原因而不是致命錯誤
        $res = expire_lock_acquire($lockfile);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('open fail', $res['why']);

        mkdir(dirname($lockfile), 0777, true);
        $res = expire_lock_acquire($lockfile);
        $this->assertTrue($res['ok']);
        expire_lock_release($res);
    }

    public function testStateRoundTrip(): void
    {
        $f = $this->tempDir('state') . '/state.json';

        $this->assertSame([], expire_state_load($f), '不存在的狀態檔回空陣列');
        $this->assertTrue(expire_state_save($f, ['status' => 'running', 'pid' => 123]));
        $back = expire_state_load($f);

        $this->assertSame('running', $back['status']);
        $this->assertSame(123, $back['pid']);

        // 壞掉的 JSON 不該炸掉
        file_put_contents($f, 'not json');
        $this->assertSame([], expire_state_load($f));
    }

    public function testPidAlive(): void
    {
        $this->assertTrue(expire_pid_alive(getmypid()));
        $this->assertFalse(expire_pid_alive(0));
        $this->assertFalse(expire_pid_alive(-1));
    }

    // ---- 目錄清理 / 路徑安全 ----------------------------------------------

    public function testCleanupRemovesOnlyEmptyChain(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $prefix = '307000x2677000-12x6-v3';
        $this->put("$root/aa/bb/000001/100/$prefix.tag.png", 'x');
        $this->put("$root/aa/bb/000001/200/$prefix.tag.png", 'y');

        unlink("$root/aa/bb/000001/100/$prefix.tag.png");
        $removed = expire_cleanup_dirs("aa/bb/000001/100/$prefix.tag.png");

        $this->assertSame(1, $removed, '只清掉空的 mid 目錄');
        $this->assertDirectoryDoesNotExist("$root/aa/bb/000001/100");
        $this->assertDirectoryExists("$root/aa/bb/000001/200", '有別的圖就不能動祖先');
        $this->assertDirectoryExists($root . '/aa');
    }

    public function testCleanupKeepsDirectorysWithFiles(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $prefix = '307000x2677000-12x6-v3';
        $this->put("$root/aa/bb/000001/100/$prefix.tag.png", 'x');

        $this->assertSame(0, expire_cleanup_dirs("aa/bb/000001/100/$prefix.tag.png"));
        $this->assertDirectoryExists("$root/aa/bb/000001/100");
    }

    public function testCleanupIgnoresUnknownAbsolutePrefix(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $this->assertSame(0, expire_cleanup_dirs('/somewhere/else/a.tag.png'));
        $this->assertSame(0, expire_cleanup_dirs('a.tag.png'));
    }

    public function testPathOkOnlyInsideKnownRoots(): void
    {
        $root = $this->tempDir('fs');
        $GLOBALS['fs_root'] = $root;
        $GLOBALS['out_roots'] = [$root];

        $this->put($root . '/aa/bb/1/in.png', 'x');
        $this->put('/tmp/twmap_expire_outside_test.png', 'x');
        $this->tempDirs[] = '/tmp/twmap_expire_outside_test.png';

        $this->assertTrue(expire_path_ok($root . '/aa/bb/1/in.png'));
        $this->assertFalse(expire_path_ok('/tmp/twmap_expire_outside_test.png'));
        $this->assertFalse(expire_path_ok($root . '/aa/bb/1/does-not-exist.png'), 'realpath 失敗要拒絕');
    }

    // ---- 雜項 --------------------------------------------------------------

    public function testExpireTsHandlesFractionalSeconds(): void
    {
        $expected = strtotime('2026-10-17 20:31:23');

        $this->assertSame($expected, expire_ts('2026-10-17 20:31:23.517747'));
        $this->assertSame($expected, expire_ts('2026-10-17 20:31:23'));
        $this->assertSame(1700000000, expire_ts(1700000000));
        $this->assertSame(0, expire_ts(null));
        $this->assertSame(0, expire_ts(''));
    }

    public function testBytesFmt(): void
    {
        $this->assertSame('512 B', expire_bytes_fmt(512));
        $this->assertSame('1.50 KB', expire_bytes_fmt(1536));
        $this->assertSame('150.00 GB', expire_bytes_fmt(150 * 1073741824));
    }

    // ---- helpers -----------------------------------------------------------

    private function tempDir(string $tag): string
    {
        $dir = sys_get_temp_dir() . '/twmap_expire_test_' . $tag . '_' . uniqid('', true);
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
        if (is_file($dir)) {
            @unlink($dir);
            return;
        }
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
